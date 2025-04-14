<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonModule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lesson_plan_id',
        'title',
        'order_index',
        'description',
        'content',
        'examples',
        'key_points',
        'guidance_notes',
        'estimated_minutes',
        'teaching_strategy',
        'common_misconceptions',
        'is_published',
        'completion_points',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'teaching_strategy' => 'array',
        'common_misconceptions' => 'array',
        'is_published' => 'boolean',
        'completion_points' => 'integer',
        'order_index' => 'integer',
    ];

    /**
     * Get the lesson plan that this module belongs to.
     */
    public function lessonPlan(): BelongsTo
    {
        return $this->belongsTo(LessonPlan::class, 'lesson_plan_id');
    }

    /**
     * Get the exercises for this module.
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(LessonExercise::class, 'module_id')->orderBy('order_index');
    }

    /**
     * Get the progress records for this module.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ModuleProgress::class, 'module_id');
    }

    /**
     * Get the quizzes for this module.
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(LessonQuiz::class, 'module_id')->orderBy('order_index');
    }

    /**
     * Get the next module in the lesson plan.
     */
    public function getNextModule()
    {
        return static::where('lesson_plan_id', $this->lesson_plan_id)
                    ->where('order_index', '>', $this->order_index)
                    ->orderBy('order_index')
                    ->first();
    }

    /**
     * Get the previous module in the lesson plan.
     */
    public function getPreviousModule()
    {
        return static::where('lesson_plan_id', $this->lesson_plan_id)
                    ->where('order_index', '<', $this->order_index)
                    ->orderBy('order_index', 'desc')
                    ->first();
    }

    /**
     * Check if a user has completed this module.
     * 
     * @param int $userId
     * @return bool
     */
    public function isCompletedByUser(int $userId): bool
    {
        return $this->progress()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->exists();
    }

    /**
     * Get user progress for this module.
     * 
     * @param int $userId
     * @return ModuleProgress|null
     */
    public function getUserProgress(int $userId)
    {
        return $this->progress()->where('user_id', $userId)->first();
    }
}
