<?php

namespace App\Services;

use App\Models\ContentApproval;
use App\Models\ContentVersion;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContentWorkflowService
{
    /**
     * Content status flow:
     * Draft → Review → Approved → Published → Version → Archive
     */

    /**
     * Submit content for review. Creates a ContentApproval record.
     */
    public function submitForReview(Model $content, User $submitter): ContentApproval
    {
        return DB::transaction(function () use ($content, $submitter) {
            $content = $this->lockEditableContent($content);
            if ($content instanceof Course) {
                app(CurriculumRevisionService::class)->assertEditable($content);
                app(CourseReadinessService::class)->assertReady($content);
            }
            if ($content instanceof Question) {
                app(QuestionDefinitionValidator::class)->validate($content->attributesToArray());
            }
            abort_if(ContentApproval::forContent(get_class($content), $content->id)->pending()->exists(), 409, 'A review is already pending.');
            if ($content instanceof Course) {
                $content->update(['approval_status' => 'pending_review', 'submitted_for_review_at' => now(), 'rejection_reason' => null]);
            } elseif ($content instanceof Question) {
                $content->update(['status' => 'review']);
            }

            return ContentApproval::create([
                'approvable_type' => get_class($content), 'approvable_id' => $content->id,
                'status' => 'pending', 'submitted_by' => $submitter->id, 'submitted_at' => now(),
                'snapshot_hash' => app(CurriculumRevisionService::class)->hash($content),
            ]);
        });
    }

    public function approve(ContentApproval $approval, User $reviewer, ?string $notes = null, bool $publish = false): void
    {
        $this->resolve($approval, $reviewer, 'approved', $notes, $publish);
    }

    public function reject(ContentApproval $approval, User $reviewer, string $notes): void
    {
        $this->resolve($approval, $reviewer, 'rejected', $notes);
    }

    public function requestChanges(ContentApproval $approval, User $reviewer, string $notes): void
    {
        $this->resolve($approval, $reviewer, 'changes_requested', $notes);
    }

    private function resolve(ContentApproval $approval, User $reviewer, string $status, ?string $notes, bool $publish = false): void
    {
        DB::transaction(function () use ($approval, $reviewer, $status, $notes, $publish) {
            $content = $approval->approvable;
            abort_unless($content, 404);
            $content = $this->lockEditableContent($content);
            $approval = ContentApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            abort_unless($approval->status === 'pending', 409, 'This review has already been resolved.');
            abort_if($approval->submitted_by === $reviewer->id || ($content instanceof Course && $content->author_id === $reviewer->id), 403, 'A different reviewer must review this content.');
            if ($status === 'approved' && $approval->snapshot_hash) {
                abort_unless(hash_equals($approval->snapshot_hash, app(CurriculumRevisionService::class)->hash($content)), 409, 'The content changed after submission. Recall and submit it again.');
            }
            $approval->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'review_notes' => $notes, 'reviewed_at' => now()]);
            if ($content instanceof Course) {
                $content->update(['approval_status' => $status === 'approved' ? 'approved' : 'rejected',
                    'approved_by' => $reviewer->id, 'approved_at' => now(), 'rejection_reason' => $status === 'approved' ? null : $notes]);
            } elseif ($content instanceof Question) {
                $content->update(['status' => $status === 'approved' ? 'approved' : 'draft', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            }
            if ($status === 'approved') {
                $approval->update(['snapshot_hash' => app(CurriculumRevisionService::class)->hash($content)]);
                if ($publish) {
                    $this->publish($content, $reviewer, $notes);
                }
            }
        });
    }

    public function publish(Model $content, User $publisher, ?string $changeSummary = null): ContentVersion
    {
        return DB::transaction(function () use ($content, $publisher, $changeSummary) {
            $content = $this->lockEditableContent($content);
            if ($content instanceof Course) {
                app(CourseReadinessService::class)->assertReady($content);
                if ($content->created_by_role === 'facilitator') {
                    abort_unless($content->approval_status === 'approved', 422, 'Independent approval is required.');
                }
            }
            $approval = ContentApproval::forContent(get_class($content), $content->id)->approved()->latest('id')->first();
            if ($approval?->snapshot_hash && ! ($content instanceof Course && $content->published_at)) {
                abort_unless(hash_equals($approval->snapshot_hash, app(CurriculumRevisionService::class)->hash($content)), 409, 'Approved content changed. Submit it for review again.');
            }
            $alreadyPublished = $content instanceof Question ? $content->status === 'published' : $content->is_published;
            if ($alreadyPublished && ($version = ContentVersion::forContent(get_class($content), $content->id)->latestFirst()->first())) {
                return $version;
            }
            if ($content instanceof Question) {
                app(QuestionDefinitionValidator::class)->validate($content->attributesToArray());
                $content->update(['status' => 'published']);
            } elseif ($content instanceof Course) {
                $content->update(['is_published' => true, 'published_at' => $content->published_at ?? now()]);
                if ($content->revision_of_id) {
                    Course::findOrFail($content->revision_of_id)->update(['superseded_at' => now()]);
                }
            } elseif ($content instanceof Lesson) {
                app(CurriculumRevisionService::class)->assertEditable($content->module->course);
                $content->update(['is_published' => true]);
            } else {
                abort(422, 'This content type cannot be published.');
            }

            return $this->createVersion($content, $publisher, $changeSummary);
        });
    }

    public function createVersion(Model $content, User $creator, ?string $changeSummary = null): ContentVersion
    {
        return DB::transaction(function () use ($content, $creator, $changeSummary) {
            $content = $content->newQuery()->whereKey($content->id)->lockForUpdate()->firstOrFail();

            return ContentVersion::create([
                'versionable_type' => get_class($content), 'versionable_id' => $content->id,
                'version_number' => 1 + (ContentVersion::forContent(get_class($content), $content->id)->max('version_number') ?? 0),
                'data' => app(CurriculumRevisionService::class)->snapshot($content), 'change_summary' => $changeSummary,
                'created_by' => $creator->id, 'created_at' => now(),
            ]);
        });
    }

    private function lockEditableContent(Model $content): Model
    {
        if ($content instanceof Question || $content instanceof Lesson) {
            $courseId = $content instanceof Question ? $content->course_id : $content->module->course_id;
            $course = Course::whereKey($courseId)->lockForUpdate()->firstOrFail();
            app(CurriculumRevisionService::class)->assertEditable($course);
        }

        return $content->newQuery()->whereKey($content->id)->lockForUpdate()->firstOrFail();
    }

    public function getVersionHistory(Model $content): Collection
    {
        return ContentVersion::forContent(get_class($content), $content->id)->latestFirst()->with('creator')->get();
    }

    public function revertToVersion(Model $content, int $versionId, User $reverter): ContentVersion
    {
        return DB::transaction(function () use ($content, $versionId, $reverter) {
            $version = ContentVersion::findOrFail($versionId);
            abort_unless($version->versionable_type === get_class($content) && $version->versionable_id === $content->id, 404);
            if ($content instanceof Course) {
                $draft = app(CurriculumRevisionService::class)->draft($content, $reverter, $version->data);

                return $this->createVersion($draft, $reverter, "Draft restored from version {$version->version_number}");
            }
            $course = $content instanceof Question ? $content->course : $content->module->course;
            app(CurriculumRevisionService::class)->assertEditable($course);
            $data = collect($version->data)->only($content->getFillable())->except(['id', 'uuid', 'course_id', 'module_id', 'published_at'])->all();
            $data[$content instanceof Question ? 'status' : 'is_published'] = $content instanceof Question ? 'draft' : false;
            $content->update($data);

            return $this->createVersion($content, $reverter, "Restored draft from version {$version->version_number}");
        });
    }

    /**
     * Get pending approvals, optionally filtered by content type.
     */
    public function getPendingApprovals(?string $contentType = null)
    {
        $query = ContentApproval::pending()
            ->with(['approvable', 'submitter'])
            ->orderBy('submitted_at');

        if ($contentType) {
            $query->where('approvable_type', $contentType);
        }

        return $query->paginate(20);
    }

    /**
     * Get the approval history for a piece of content.
     */
    public function getApprovalHistory(Model $content)
    {
        return ContentApproval::forContent(get_class($content), $content->id)
            ->with(['submitter', 'reviewer'])
            ->orderByDesc('submitted_at')
            ->get();
    }

    /**
     * Get item quality analytics for a question.
     */
    public function getQuestionAnalytics(int $questionId): array
    {
        $question = Question::findOrFail($questionId);

        return [
            'difficulty' => $this->calculateDifficulty($question),
            'discrimination_index' => $question->discrimination_index,
            'correct_rate' => $question->correct_rate,
            'exposure_count' => $question->exposure_count,
            'avg_time_seconds' => $question->avg_time_seconds,
            'distractor_analysis' => $this->analyzeDistractors($question),
        ];
    }

    /**
     * Calculate difficulty category.
     */
    private function calculateDifficulty(Question $question): string
    {
        $rate = (float) $question->correct_rate;
        if ($rate >= 0.8) {
            return 'easy';
        }
        if ($rate >= 0.4) {
            return 'medium';
        }

        return 'hard';
    }

    /**
     * Analyze distractor effectiveness.
     */
    private function analyzeDistractors(Question $question): array
    {
        $responses = QuestionResponse::where('question_id', $question->id)
            ->get();

        if ($responses->isEmpty()) {
            return [];
        }

        $options = $question->options ?? [];
        $correctIds = $question->correct_answer_ids ?? [];
        $analysis = [];

        foreach ($options as $option) {
            $optionId = $option['id'] ?? null;
            if (! $optionId) {
                continue;
            }

            $selectedCount = $responses->filter(function ($r) use ($optionId) {
                $selected = $r->selected_answer_ids ?? [];

                return in_array($optionId, $selected);
            })->count();

            $analysis[] = [
                'option_id' => $optionId,
                'label' => $option['text'] ?? '',
                'is_correct' => in_array($optionId, $correctIds),
                'selection_count' => $selectedCount,
                'selection_pct' => $responses->count() > 0
                    ? round(($selectedCount / $responses->count()) * 100, 1)
                    : 0,
                'is_effective' => ! in_array($optionId, $correctIds) && $selectedCount > 0,
            ];
        }

        return $analysis;
    }

    /**
     * Get blueprint coverage report for a course.
     */
    public function getBlueprintCoverage(int $courseId): array
    {
        $course = Course::findOrFail($courseId);
        $blueprint = $course->exam_blueprint ?? [];

        $questions = Question::where('course_id', $courseId)->get();

        $coverage = [];
        foreach ($blueprint as $domain => $targetPct) {
            $domainQuestions = $questions->where('domain', $domain);
            $coverage[] = [
                'domain' => $domain,
                'target_pct' => $targetPct,
                'question_count' => $domainQuestions->count(),
                'total_questions' => $questions->count(),
                'actual_pct' => $questions->count() > 0
                    ? round(($domainQuestions->count() / $questions->count()) * 100, 1)
                    : 0,
                'difficulty_dist' => [
                    'easy' => $domainQuestions->where('difficulty', 'easy')->count(),
                    'medium' => $domainQuestions->where('difficulty', 'medium')->count(),
                    'hard' => $domainQuestions->where('difficulty', 'hard')->count(),
                ],
            ];
        }

        return $coverage;
    }
}
