<?php

namespace App\Services\Progress;

class ProgressService
{
    /**
     * Calculate Java code complexity per the exact formula:
     * Complexity = min(LineCount/10, 2) + ClassPresence + min(MethodCount, 2)
     * - LineCount: number of lines in code (non-empty lines considered)
     * - ClassPresence: 1 if the word "class" exists, else 0
     * - MethodCount: number of methods
     */
    public static function calculateCodeComplexity(string $code): float
    {
        $lines = preg_split('/\r\n|\r|\n/', $code);
        $nonEmptyLines = array_filter($lines, function ($l) {
            return trim($l) !== '';
        });
        $lineCount = count($nonEmptyLines);

        $classPresence = (stripos($code, 'class') !== false) ? 1 : 0;

        // Basic Java method signature regex (visibility? return type? name(...){ )
        $methodPattern = '/\b(public|private|protected)?\s*(static\s+)?[A-Za-z_][A-Za-z0-9_<>\[\]]*\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s*\{/m';
        preg_match_all($methodPattern, $code, $matches);
        $methodCount = is_array($matches) && isset($matches[0]) ? count($matches[0]) : 0;

        $termLines = min($lineCount / 10.0, 2.0);
        $termMethods = min($methodCount, 2.0);

        $complexity = $termLines + $classPresence + $termMethods;
        return round($complexity, 2);
    }

    /**
     * RewardPoints = success ? min(4 + Complexity, 8) : 1
     */
    public static function computeExecutionReward(bool $success, float $complexity): int
    {
        if ($success) {
            $val = min(4.0 + $complexity, 8.0);
            return (int) floor($val); // integer points
        }
        return 1;
    }

    /**
     * TimePoints = floor(TotalMinutes/10)
     */
    public static function computeTimePoints(int $totalMinutes): int
    {
        if ($totalMinutes <= 0) {
            return 0;
        }
        return (int) floor($totalMinutes / 10);
    }

    /**
     * TotalProgress = min(Interaction,30) + min(Code,40) + min(Time,5) + min(Quiz,30)
     * OverallProgress = min(TotalProgress, 100)
     * Returns array with detailed breakdown and overall.
     */
    public static function computeWeightedProgress(
        int $interactionPoints,
        int $codePoints,
        int $timePoints,
        int $quizPoints
    ): array {
        $interaction = min($interactionPoints, 30);
        $code = min($codePoints, 40);
        $time = min($timePoints, 5);
        $quiz = min($quizPoints, 30);
        $total = $interaction + $code + $time + $quiz;
        $overall = min($total, 100);

        return [
            'interaction_capped' => $interaction,
            'code_capped' => $code,
            'time_capped' => $time,
            'quiz_capped' => $quiz,
            'total_progress' => $total,
            'overall_progress' => $overall,
        ];
    }

    /**
     * PerformanceScore = α(QuizScore) + β(CodeSuccessRate) − γ(ErrorRate)
     * All inputs are expected as numeric (0-100 for percentages). α, β, γ default to 1.0
     */
    public static function computePerformanceScore(
        float $quizScore,
        float $codeSuccessRate,
        float $errorRate,
        float $alpha = 1.0,
        float $beta = 1.0,
        float $gamma = 1.0
    ): float {
        $score = ($alpha * $quizScore) + ($beta * $codeSuccessRate) - ($gamma * $errorRate);
        return round($score, 2);
    }

    /**
     * Difficulty_next = { Increase if score > ThresholdHigh; Decrease if score < ThresholdLow; Same otherwise }
     */
    public static function decideNextDifficulty(float $performanceScore, float $thresholdHigh = 70.0, float $thresholdLow = 40.0): string
    {
        if ($performanceScore > $thresholdHigh) {
            return 'increase';
        }
        if ($performanceScore < $thresholdLow) {
            return 'decrease';
        }
        return 'same';
    }
}


