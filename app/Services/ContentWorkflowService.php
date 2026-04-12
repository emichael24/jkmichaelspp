<?php

namespace App\Services;

use App\Models\ContentApproval;
use App\Models\ContentVersion;
use App\Models\User;
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
        // Mark any previous pending approvals as superseded
        ContentApproval::forContent(get_class($content), $content->id)
            ->pending()
            ->update(['status' => 'changes_requested', 'review_notes' => 'Superseded by new submission']);

        return ContentApproval::create([
            'approvable_type' => get_class($content),
            'approvable_id'   => $content->id,
            'status'          => 'pending',
            'submitted_by'    => $submitter->id,
            'submitted_at'    => now(),
        ]);
    }

    /**
     * Approve content and optionally publish.
     */
    public function approve(ContentApproval $approval, User $reviewer, ?string $notes = null, bool $publish = false): void
    {
        DB::transaction(function () use ($approval, $reviewer, $notes, $publish) {
            $approval->approve($reviewer, $notes);

            if ($publish) {
                $content = $approval->approvable;
                $this->publish($content, $reviewer);
            }
        });
    }

    /**
     * Reject content with notes.
     */
    public function reject(ContentApproval $approval, User $reviewer, string $notes): void
    {
        $approval->reject($reviewer, $notes);
    }

    /**
     * Request changes on content.
     */
    public function requestChanges(ContentApproval $approval, User $reviewer, string $notes): void
    {
        $approval->requestChanges($reviewer, $notes);
    }

    /**
     * Publish content: mark it as published and create a version snapshot.
     */
    public function publish(Model $content, User $publisher, ?string $changeSummary = null): ContentVersion
    {
        return DB::transaction(function () use ($content, $publisher, $changeSummary) {
            // Mark as published if the model has the attribute
            if (method_exists($content, 'getTable')) {
                $content->update([
                    'is_published' => true,
                    'published_at' => now(),
                ]);
            }

            // Create version snapshot
            return $this->createVersion($content, $publisher, $changeSummary);
        });
    }

    /**
     * Create a version snapshot of the content.
     */
    public function createVersion(Model $content, User $creator, ?string $changeSummary = null): ContentVersion
    {
        $latestVersion = ContentVersion::forContent(get_class($content), $content->id)
            ->max('version_number') ?? 0;

        return ContentVersion::create([
            'versionable_type' => get_class($content),
            'versionable_id'   => $content->id,
            'version_number'   => $latestVersion + 1,
            'data'             => $content->toArray(),
            'change_summary'   => $changeSummary,
            'created_by'       => $creator->id,
            'created_at'       => now(),
        ]);
    }

    /**
     * Get version history for a piece of content.
     */
    public function getVersionHistory(Model $content): \Illuminate\Database\Eloquent\Collection
    {
        return ContentVersion::forContent(get_class($content), $content->id)
            ->latestFirst()
            ->with('creator')
            ->get();
    }

    /**
     * Revert content to a previous version.
     */
    public function revertToVersion(Model $content, int $versionId, User $reverter): ContentVersion
    {
        return DB::transaction(function () use ($content, $versionId, $reverter) {
            $version = ContentVersion::findOrFail($versionId);

            // Make sure this version belongs to this content
            if ($version->versionable_type !== get_class($content) || $version->versionable_id !== $content->id) {
                throw new \InvalidArgumentException('Version does not belong to this content.');
            }

            // Restore the data from the version
            $data = collect($version->data)->except(['id', 'created_at', 'updated_at', 'deleted_at'])->toArray();
            $content->update($data);

            // Create a new version marking the revert
            return $this->createVersion(
                $content->fresh(),
                $reverter,
                "Reverted to version {$version->version_number}"
            );
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
        $question = \App\Models\Question::findOrFail($questionId);

        return [
            'difficulty'            => $this->calculateDifficulty($question),
            'discrimination_index'  => $question->discrimination_index,
            'correct_rate'          => $question->correct_rate,
            'exposure_count'        => $question->exposure_count,
            'avg_time_seconds'      => $question->avg_time_seconds,
            'distractor_analysis'   => $this->analyzeDistractors($question),
        ];
    }

    /**
     * Calculate difficulty category.
     */
    private function calculateDifficulty(\App\Models\Question $question): string
    {
        $rate = (float) $question->correct_rate;
        if ($rate >= 0.8) return 'easy';
        if ($rate >= 0.4) return 'medium';
        return 'hard';
    }

    /**
     * Analyze distractor effectiveness.
     */
    private function analyzeDistractors(\App\Models\Question $question): array
    {
        $responses = \App\Models\QuestionResponse::where('question_id', $question->id)
            ->get();

        if ($responses->isEmpty()) {
            return [];
        }

        $options = $question->options ?? [];
        $correctIds = $question->correct_answer_ids ?? [];
        $analysis = [];

        foreach ($options as $option) {
            $optionId = $option['id'] ?? null;
            if (! $optionId) continue;

            $selectedCount = $responses->filter(function ($r) use ($optionId) {
                $selected = $r->selected_answer_ids ?? [];
                return in_array($optionId, $selected);
            })->count();

            $analysis[] = [
                'option_id'       => $optionId,
                'label'           => $option['text'] ?? '',
                'is_correct'      => in_array($optionId, $correctIds),
                'selection_count' => $selectedCount,
                'selection_pct'   => $responses->count() > 0
                    ? round(($selectedCount / $responses->count()) * 100, 1)
                    : 0,
                'is_effective'    => ! in_array($optionId, $correctIds) && $selectedCount > 0,
            ];
        }

        return $analysis;
    }

    /**
     * Get blueprint coverage report for a course.
     */
    public function getBlueprintCoverage(int $courseId): array
    {
        $course = \App\Models\Course::findOrFail($courseId);
        $blueprint = $course->exam_blueprint ?? [];

        $questions = \App\Models\Question::where('course_id', $courseId)->get();

        $coverage = [];
        foreach ($blueprint as $domain => $targetPct) {
            $domainQuestions = $questions->where('domain', $domain);
            $coverage[] = [
                'domain'          => $domain,
                'target_pct'      => $targetPct,
                'question_count'  => $domainQuestions->count(),
                'total_questions' => $questions->count(),
                'actual_pct'      => $questions->count() > 0
                    ? round(($domainQuestions->count() / $questions->count()) * 100, 1)
                    : 0,
                'difficulty_dist' => [
                    'easy'   => $domainQuestions->where('difficulty', 'easy')->count(),
                    'medium' => $domainQuestions->where('difficulty', 'medium')->count(),
                    'hard'   => $domainQuestions->where('difficulty', 'hard')->count(),
                ],
            ];
        }

        return $coverage;
    }
}
