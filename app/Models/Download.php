<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'video_title', 'youtube_link', 'file_path', 'file_format',
    ];

    // Define the relationship to the User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
