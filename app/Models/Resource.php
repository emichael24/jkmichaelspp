<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'type',
        'description',
        'file_url',
        'file_type',
        'file_size_bytes',
        'download_count',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'download_count'  => 'integer',
            'sort_order'      => 'integer',
            'is_published'    => 'boolean',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size_bytes;
        if (!$bytes) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
