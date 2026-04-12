<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceClone extends Model
{
    protected $fillable = ['user_id', 'name', 'voice_id', 'sample_audio_url', 'is_active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
