<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    protected $fillable = [
        'course_id',
        'lesson_id',
        'question_id',
        'user_id',
        'front',
        'back',
        'domain',
        'tags',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function spacedRepetitionItems()
    {
        return $this->morphMany(SpacedRepetitionItem::class, 'reviewable');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeSystemGenerated($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeUserCreated($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFromWrongAnswers($query)
    {
        return $query->where('source', 'wrong_answer');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a flashcard from a wrong quiz answer.
     */
    public static function createFromWrongAnswer(Question $question, int $userId): self
    {
        return static::firstOrCreate(
            [
                'question_id' => $question->id,
                'user_id' => $userId,
                'source' => 'wrong_answer',
            ],
            [
                'course_id' => $question->course_id,
                'front' => $question->stem,
                'back' => $question->explanation_correct ?? 'Review the correct answer.',
                'domain' => $question->domain,
                'tags' => $question->tags,
            ]
        );
    }

    /**
     * Create flashcards from lesson key terms.
     */
    public static function createFromKeyTerms(Lesson $lesson, array $keyTerms, ?int $userId = null): array
    {
        $cards = [];

        foreach ($keyTerms as $term) {
            $termText = is_array($term) ? ($term['term'] ?? '') : $term;
            $definition = is_array($term) ? ($term['definition'] ?? '') : '';

            if (! $termText) {
                continue;
            }

            $cards[] = static::firstOrCreate(
                [
                    'lesson_id' => $lesson->id,
                    'front' => $termText,
                    'source' => 'lesson_term',
                    'user_id' => $userId,
                ],
                [
                    'course_id' => $lesson->module->course_id,
                    'back' => $definition,
                    'domain' => null,
                ]
            );
        }

        return $cards;
    }
}
