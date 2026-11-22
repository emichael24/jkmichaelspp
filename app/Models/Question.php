<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use App\Services\QuestionGrader;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Question extends Model
{
    use HasFactory, SoftDeletes;
    use RecordsDomainAudit;

    protected $fillable = [
        'uuid',
        'course_id',
        'domain',
        'subtopic',
        'type',
        'difficulty',
        'bloom_level',
        'stem',
        'stem_media',
        'options',
        'correct_answer_ids', 'grading_config',
        'explanation_correct',
        'explanation_incorrect',
        'exam_tip',
        'linked_lesson_id',
        'time_estimate_seconds',
        'exposure_count',
        'correct_rate',
        'avg_time_seconds',
        'discrimination_index',
        'status',
        'reviewed_by',
        'reviewed_at',
        'version',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'stem_media' => 'array',
            'options' => 'array',
            'correct_answer_ids' => 'array', 'grading_config' => 'array',
            'explanation_incorrect' => 'array',
            'tags' => 'array',
            'time_estimate_seconds' => 'integer',
            'exposure_count' => 'integer',
            'correct_rate' => 'decimal:4',
            'avg_time_seconds' => 'decimal:2',
            'discrimination_index' => 'decimal:4',
            'version' => 'integer',
            'reviewed_at' => 'datetime',
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

    public function linkedLesson()
    {
        return $this->belongsTo(Lesson::class, 'linked_lesson_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function quizzes()
    {
        return $this->belongsToMany(Quiz::class, 'quiz_questions')
            ->withPivot('sort_order');
    }

    public function responses()
    {
        return $this->hasMany(QuestionResponse::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isCorrect(array $selectedIds): bool
    {
        return app(QuestionGrader::class)->score($this, $selectedIds) === 1.0;
    }

    public function incrementExposure(): void
    {
        $this->increment('exposure_count');
    }

    public function updateStats(bool $wasCorrect, int $timeTaken): void
    {
        $total = $this->exposure_count;

        if ($total > 0) {
            $this->correct_rate = (($this->correct_rate * ($total - 1)) + ($wasCorrect ? 1 : 0)) / $total;
            $this->avg_time_seconds = (($this->avg_time_seconds * ($total - 1)) + $timeTaken) / $total;
        }

        $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (Question $question) {
            if (empty($question->uuid)) {
                $question->uuid = (string) Str::uuid();
            }
        });
    }
}
