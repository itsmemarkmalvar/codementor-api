<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'topic_id',
        'title',
        'description',
        'learning_objectives',
        'prerequisites',
        'estimated_minutes',
        'resources',
        'instructor_notes',
        'difficulty_level',
        'modules_count',
        'is_published',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'resources' => 'array',
        'is_published' => 'boolean',
    ];

    /**
     * Get the topic that this lesson plan belongs to.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'topic_id');
    }

    /**
     * Get the modules for this lesson plan.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(LessonModule::class)->orderBy('order_index');
    }

    /**
     * Update the modules count for this lesson plan.
     */
    public function updateModulesCount(): void
    {
        $this->modules_count = $this->modules()->count();
        $this->save();
    }

    /**
     * Check if this lesson plan is ready for student use.
     */
    public function isReady(): bool
    {
        return $this->is_published && $this->modules_count > 0;
    }

    /**
     * Get all exercises associated with this lesson plan through modules.
     */
    public function getAllExercises()
    {
        $moduleIds = $this->modules()->pluck('id');
        return LessonExercise::whereIn('module_id', $moduleIds)->orderBy('module_id')->orderBy('order_index')->get();
    }
}
