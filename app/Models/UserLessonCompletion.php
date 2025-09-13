<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLessonCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_plan_id',
        'completed_at',
        'source',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}


