<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeSnippet extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'title',
        'code',
        'language',
        'description',
        'status',
        'execution_result',
        'ai_feedback',
        'is_favorite',
    ];

    protected $casts = [
        'ai_feedback' => 'json',
        'is_favorite' => 'boolean',
    ];

    /**
     * Get the user that owns the code snippet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the session that contains the code snippet.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class);
    }
}
