<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeAttempt extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'problem_id',
        'submitted_code',
        'execution_result',
        'is_correct',
        'points_earned',
        'time_spent_seconds',
        'attempt_number',
        'last_hint_index',
        'hints_used',
        'compiler_errors',
        'runtime_errors',
        'test_case_results',
        'execution_time_ms',
        'memory_usage_kb',
        'feedback',
        'status',  // 'started', 'in_progress', 'submitted', 'evaluated'
        'struggle_points', // Areas where user struggled
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'execution_result' => 'array',
        'is_correct' => 'boolean',
        'hints_used' => 'array',
        'compiler_errors' => 'array',
        'runtime_errors' => 'array',
        'test_case_results' => 'array',
        'struggle_points' => 'array',
    ];

    /**
     * Get the user that owns this attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the practice problem for this attempt.
     */
    public function problem(): BelongsTo
    {
        return $this->belongsTo(PracticeProblem::class, 'problem_id');
    }

    /**
     * Check if this attempt used a specific hint.
     */
    public function usedHint($hintIndex): bool
    {
        return is_array($this->hints_used) && in_array($hintIndex, $this->hints_used);
    }

    /**
     * Add a hint usage record.
     */
    public function addHintUsage($hintIndex)
    {
        $hints = $this->hints_used ?: [];
        
        if (!in_array($hintIndex, $hints)) {
            $hints[] = $hintIndex;
            $this->update(['hints_used' => $hints]);
        }
        
        return $this;
    }

    /**
     * Calculate points reduction based on hint usage.
     */
    public function calculatePointsReduction()
    {
        $hintsCount = is_array($this->hints_used) ? count($this->hints_used) : 0;
        $basePoints = $this->problem->points ?? 100;
        
        // Each hint reduces points by 10%
        $reduction = $hintsCount * 0.1;
        
        // Cap reduction at 50%
        $reduction = min($reduction, 0.5);
        
        return $basePoints * $reduction;
    }

    /**
     * Calculate final score considering time, hints, and correctness.
     */
    public function calculateFinalScore()
    {
        if (!$this->is_correct) {
            return 0;
        }
        
        $basePoints = $this->problem->points ?? 100;
        $hintReduction = $this->calculatePointsReduction();
        $estimatedTime = $this->problem->estimated_time_minutes * 60;
        $timeFactor = 1.0;
        
        // Time bonus/penalty (up to 20%)
        if ($this->time_spent_seconds > 0 && $estimatedTime > 0) {
            $timeRatio = $this->time_spent_seconds / $estimatedTime;
            if ($timeRatio < 0.8) {
                // Time bonus for fast completion
                $timeFactor = 1.2;
            } elseif ($timeRatio > 1.5) {
                // Time penalty for slow completion
                $timeFactor = 0.9;
            }
        }
        
        return ($basePoints - $hintReduction) * $timeFactor;
    }

    /**
     * Identify and record struggle points based on runtime errors and test case failures
     */
    public function identifyStrugglePoints()
    {
        $strugglePoints = [];
        
        // Check compiler errors
        if (!empty($this->compiler_errors)) {
            foreach ($this->compiler_errors as $error) {
                // Common error categories in Java
                if (strpos($error, 'cannot find symbol') !== false) {
                    $strugglePoints[] = 'variable_declaration';
                } elseif (strpos($error, 'incompatible types') !== false) {
                    $strugglePoints[] = 'type_conversion';
                } elseif (strpos($error, 'missing return statement') !== false) {
                    $strugglePoints[] = 'method_return';
                }
            }
        }
        
        // Check runtime errors
        if (!empty($this->runtime_errors)) {
            foreach ($this->runtime_errors as $error) {
                if (strpos($error, 'NullPointerException') !== false) {
                    $strugglePoints[] = 'null_handling';
                } elseif (strpos($error, 'IndexOutOfBoundsException') !== false) {
                    $strugglePoints[] = 'array_index';
                } elseif (strpos($error, 'ClassCastException') !== false) {
                    $strugglePoints[] = 'type_casting';
                }
            }
        }
        
        // Analyze failed test cases
        if (!empty($this->test_case_results)) {
            $failedTests = array_filter($this->test_case_results, function($result) {
                return $result['passed'] === false;
            });
            
            if (count($failedTests) > 0) {
                $strugglePoints[] = 'test_case_understanding';
            }
        }
        
        // Update struggle points
        $this->update(['struggle_points' => array_unique($strugglePoints)]);
        
        return $strugglePoints;
    }
} 