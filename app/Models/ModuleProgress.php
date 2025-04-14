<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleProgress extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'module_id',
        'status',
        'progress_percentage',
        'time_spent_seconds',
        'started_at',
        'completed_at',
        'last_activity_at',
        'notes',
        'struggle_points',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notes' => 'array',
        'struggle_points' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Get the user that this progress belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the module that this progress is for.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LessonModule::class, 'module_id');
    }

    /**
     * Mark this module as started.
     */
    public function markAsStarted(): void
    {
        if ($this->status === 'not_started') {
            $this->status = 'in_progress';
            $this->started_at = now();
        }
        
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Mark this module as completed.
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->progress_percentage = 100;
        $this->completed_at = now();
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Update the progress percentage based on exercises completed.
     */
    public function updateProgressPercentage(): void
    {
        $module = $this->module;
        $exercises = $module->exercises;
        
        if ($exercises->isEmpty()) {
            $this->progress_percentage = $this->status === 'completed' ? 100 : 0;
            $this->save();
            return;
        }

        $totalExercises = $exercises->count();
        $completedExercises = 0;

        foreach ($exercises as $exercise) {
            if ($exercise->isCompletedByUser($this->user_id)) {
                $completedExercises++;
            }
        }

        $percentage = ($completedExercises / $totalExercises) * 100;
        $this->progress_percentage = round($percentage);
        
        if ($this->progress_percentage >= 100) {
            $this->markAsCompleted();
        } else {
            $this->save();
        }
    }

    /**
     * Add a note about the student's understanding.
     */
    public function addNote(string $note): void
    {
        $notes = $this->notes ?? [];
        $notes[] = [
            'content' => $note,
            'timestamp' => now()->toIso8601String()
        ];
        
        $this->notes = $notes;
        $this->save();
    }

    /**
     * Record a struggle point.
     */
    public function addStrugglePoint(string $concept, string $details): void
    {
        $strugglePoints = $this->struggle_points ?? [];
        $strugglePoints[] = [
            'concept' => $concept,
            'details' => $details,
            'timestamp' => now()->toIso8601String()
        ];
        
        $this->struggle_points = $strugglePoints;
        $this->save();
    }
}
