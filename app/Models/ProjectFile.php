<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'path',
        'content',
        'is_directory',
        'language',
        'parent_path',
    ];

    protected $casts = [
        'is_directory' => 'boolean',
    ];

    /**
     * Get the project that owns the file.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
