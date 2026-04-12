<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLessonSummary extends Model
{
    protected $fillable = ['lesson_id', 'summary_type', 'content', 'language', 'tokens_used'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
