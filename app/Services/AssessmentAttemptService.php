<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentAttemptService
{
    public function start(Model $assessment, User $user, callable $questions): QuizAttempt
    {
        return DB::transaction(function () use ($assessment, $user, $questions) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $assessment->attempts()->where('user_id', $user->id)->where('status', 'in_progress')->lockForUpdate()->first();
            if ($existing && empty($existing->question_snapshot)) {
                $existing->update(['status' => 'abandoned']);
                $existing = null;
            }
            if ($existing) {
                if (! $existing->paused_at && $existing->expires_at?->isPast()) {
                    return $this->complete($existing);
                }

                return $this->resume($existing);
            }
            if ($assessment instanceof Quiz) {
                abort_unless($assessment->canAttempt($user), 409, 'Maximum attempts reached.');
            }
            $selected = $questions();
            abort_if($selected->isEmpty(), 422, 'No published questions are available.');
            $attempt = $assessment->attempts()->create(['user_id' => $user->id, 'status' => 'in_progress', 'started_at' => now(), 'total_count' => $selected->count()]);
            $this->capture($attempt, $selected, $assessment->time_limit_minutes);
            foreach ($selected as $question) {
                Question::whereKey($question->id)->increment('exposure_count');
            }

            return $attempt;
        }, 3);
    }

    public function answer(QuizAttempt $attempt, array $values): void
    {
        DB::transaction(function () use ($attempt, $values) {
            $attempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->assertWritable($attempt);
            $question = $this->question($attempt, $values['question_id']);
            $score = app(QuestionGrader::class)->score($question, $values['selected_answer_ids']);
            $attempt->responses()->updateOrCreate(['question_id' => $question->id], [
                'user_id' => $attempt->user_id, 'selected_answer_ids' => $values['selected_answer_ids'],
                'is_correct' => $score === 1.0, 'score_fraction' => $score,
                'time_spent_seconds' => min(86400, $values['time_spent_seconds']),
                'confidence_level' => $values['confidence_level'] ?? null, 'is_flagged' => $values['is_flagged'] ?? false,
            ]);
        }, 3);
    }

    public function complete(QuizAttempt $attempt, ?array $submitted = null): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $submitted) {
            User::whereKey($attempt->user_id)->lockForUpdate()->firstOrFail();
            $attempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status === 'in_progress') {
                abort_if($attempt->paused_at, 409, 'Resume the assessment before submitting.');
                // After the deadline, only answers already saved on the server count.
                $expired = $attempt->expires_at?->isPast() ?? false;
                $responses = $expired || $submitted === null
                    ? collect($attempt->draft_responses ?? [])->map(fn ($answers, $id) => ['question_id' => $id, 'selected_ids' => $answers])->values()->all()
                    : $submitted;
                abort_if(count(array_unique(array_column($responses, 'question_id'))) !== count($responses), 422, 'Duplicate question responses.');
                foreach ($responses as $response) {
                    $question = $this->question($attempt, (int) $response['question_id']);
                    $answers = $response['selected_ids'];
                    $score = app(QuestionGrader::class)->score($question, $answers);
                    $attempt->responses()->updateOrCreate(['question_id' => $question->id], [
                        'user_id' => $attempt->user_id, 'selected_answer_ids' => $answers, 'is_correct' => $score === 1.0,
                        'score_fraction' => $score, 'time_spent_seconds' => min(86400, $response['time_spent'] ?? 0),
                    ]);
                }
                $attempt->calculateScore();
            }
            abort_unless($attempt->status === 'completed', 409, 'This attempt cannot be completed.');
            app(SpacedRepetitionService::class)->processCompletedAttempt($attempt);
            if ($attempt->quizzable instanceof Quiz) {
                $quiz = $attempt->quizzable;
                // A quiz lesson is complete once the learner reaches its pass mark.
                if ($quiz->lesson_id && $quiz->lesson?->content_type === 'quiz' && (float) $attempt->score_pct >= (float) ($quiz->passing_score_pct ?? 70)) {
                    app(CourseCompletionService::class)->progress($attempt->user, $quiz->lesson, [], true);
                }
                app(CourseCompletionService::class)->reconcile($attempt->user, $quiz->course);
            }

            return $attempt->fresh();
        }, 3);
    }

    public function saveDraft(QuizAttempt $attempt, array $answers): void
    {
        DB::transaction(function () use ($attempt, $answers) {
            $attempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->assertWritable($attempt);
            foreach ($answers as $id => $values) {
                $this->question($attempt, (int) $id);
            }
            $attempt->update(['draft_responses' => $answers]);
        }, 3);
    }

    public function pause(QuizAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $attempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($attempt->quizzable instanceof MockExam && $attempt->quizzable->allow_pause, 403, 'Pausing is disabled for this assessment.');
            if ($attempt->paused_at) {
                return;
            }
            $this->assertWritable($attempt);
            $attempt->update(['paused_at' => now(), 'remaining_seconds' => $attempt->expires_at ? max(0, (int) now()->diffInSeconds($attempt->expires_at)) : null]);
        }, 3);
    }

    public function resume(QuizAttempt $attempt): QuizAttempt
    {
        if ($attempt->paused_at) {
            $attempt->update(['expires_at' => $attempt->remaining_seconds !== null ? now()->addSeconds($attempt->remaining_seconds) : null, 'paused_at' => null, 'remaining_seconds' => null]);
        }

        return $attempt;
    }

    public function capture(QuizAttempt $attempt, Collection $questions, ?int $minutes): void
    {
        abort_if($questions->isEmpty(), 422, 'This assessment has no published questions.');
        $attempt->update([
            'question_snapshot' => $questions->map(fn ($question) => $question->attributesToArray())->values()->all(),
            'expires_at' => $minutes ? $attempt->started_at->copy()->addMinutes($minutes) : null,
            'total_count' => $questions->count(),
        ]);
    }

    public function questions(QuizAttempt $attempt): Collection
    {
        abort_if(empty($attempt->question_snapshot), 409, 'This older attempt has no saved question set. Please start a new assessment.');

        return collect($attempt->question_snapshot)->map(fn ($data) => (new Question)->forceFill($data));
    }

    public function question(QuizAttempt $attempt, int $id): Question
    {
        $question = $this->questions($attempt)->firstWhere('id', $id);
        abort_unless($question, 422, 'This question is not part of this attempt.');

        return $question;
    }

    public function assertWritable(QuizAttempt $attempt): void
    {
        abort_if($attempt->paused_at, 409, 'Resume this assessment before answering.');
        abort_unless($attempt->status === 'in_progress', 409, 'This attempt is already complete.');
        abort_if($attempt->expires_at && now()->greaterThan($attempt->expires_at), 409, 'The assessment time limit has elapsed.');
    }

    public function learnerQuestions(QuizAttempt $attempt): Collection
    {
        return $this->questions($attempt)->map(fn ($question) => [
            'id' => $question->id, 'uuid' => $question->uuid, 'type' => $question->type,
            'stem' => $question->stem, 'stem_media' => $question->stem_media, 'domain' => $question->domain,
            'time_estimate_seconds' => $question->time_estimate_seconds,
            'options' => collect($question->options)->map(fn ($option) => collect($option)->only(['id', 'text', 'media_url', 'match_text'])->all())->values()->all(),
        ])->values();
    }
}
