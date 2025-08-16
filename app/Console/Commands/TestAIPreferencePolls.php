<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AIPreferenceLog;
use App\Models\SplitScreenSession;
use App\Models\User;
use App\Models\LearningTopic;
use App\Models\QuizAttempt;
use App\Models\PracticeAttempt;
use App\Models\ChatMessage;
use Carbon\Carbon;

class TestAIPreferencePolls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-ai-preference-polls 
                            {--user-id= : User ID to create test data for}
                            {--count=10 : Number of test poll records to create}
                            {--days=30 : Number of days back to create data for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test AI preference poll data for testing the analytics system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $count = (int) $this->option('count');
        $days = (int) $this->option('days');

        // Find or use specified user
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found!");
                return 1;
            }
        } else {
            $user = User::first();
            if (!$user) {
                $this->error("No users found in database!");
                return 1;
            }
        }

        $this->info("Creating test AI preference poll data for user: {$user->name} (ID: {$user->id})");
        $this->info("Creating {$count} poll records over the last {$days} days...");

        // Get available topics
        $topics = LearningTopic::take(5)->get();
        if ($topics->isEmpty()) {
            $this->error("No learning topics found in database!");
            return 1;
        }

        // Get or create a split screen session
        $session = SplitScreenSession::where('user_id', $user->id)->first();
        if (!$session) {
            $session = SplitScreenSession::create([
                'user_id' => $user->id,
                'topic_id' => $topics->first()->id,
                'session_type' => 'split_screen',
                'ai_models_used' => ['gemini', 'together'],
                'started_at' => now()->subDays($days),
                'total_messages' => 50,
                'engagement_score' => 25,
            ]);
        }

        $createdCount = 0;
        $interactionTypes = ['quiz', 'practice', 'code_execution'];
        $aiChoices = ['gemini', 'together', 'both', 'neither'];
        $difficulties = ['beginner', 'easy', 'medium', 'hard', 'expert'];

        for ($i = 0; $i < $count; $i++) {
            $interactionType = $interactionTypes[array_rand($interactionTypes)];
            $chosenAI = $aiChoices[array_rand($aiChoices)];
            $topic = $topics->random();
            $difficulty = $difficulties[array_rand($difficulties)];
            
            // Create random date within the specified range
            $createdAt = Carbon::now()->subDays(rand(0, $days))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            // Generate performance metrics based on interaction type
            $performanceMetrics = $this->generatePerformanceMetrics($interactionType, $chosenAI);

            // Create preference log
            $preferenceLog = AIPreferenceLog::create([
                'user_id' => $user->id,
                'session_id' => $session->id,
                'topic_id' => $topic->id,
                'interaction_type' => $interactionType,
                'chosen_ai' => $chosenAI,
                'choice_reason' => $this->generateChoiceReason($chosenAI, $interactionType),
                'performance_score' => $performanceMetrics['performance_score'],
                'success_rate' => $performanceMetrics['success_rate'],
                'time_spent_seconds' => $performanceMetrics['time_spent_seconds'],
                'attempt_count' => $performanceMetrics['attempt_count'],
                'difficulty_level' => $difficulty,
                'context_data' => $performanceMetrics['context_data'],
                'attribution_model' => $performanceMetrics['attribution_model'],
                'attribution_confidence' => $performanceMetrics['attribution_confidence'],
                'attribution_delay_sec' => $performanceMetrics['attribution_delay_sec'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $createdCount++;
            $this->line("Created poll #{$createdCount}: {$interactionType} - {$chosenAI} - {$topic->title}");
        }

        $this->info("✅ Successfully created {$createdCount} AI preference poll records!");
        
        // Show summary
        $this->showSummary($user->id);

        return 0;
    }

    /**
     * Generate performance metrics based on interaction type and chosen AI
     */
    private function generatePerformanceMetrics($interactionType, $chosenAI): array
    {
        $baseScore = match($chosenAI) {
            'gemini' => rand(75, 95),
            'together' => rand(70, 90),
            'both' => rand(80, 95),
            'neither' => rand(50, 75),
        };

        $metrics = [
            'performance_score' => $baseScore,
            'success_rate' => $baseScore,
            'time_spent_seconds' => rand(30, 300),
            'attempt_count' => rand(1, 3),
            'context_data' => [],
            'attribution_model' => $chosenAI === 'both' ? ['gemini', 'together'][array_rand([0, 1])] : $chosenAI,
            'attribution_confidence' => ['explicit', 'session', 'temporal'][array_rand([0, 1, 2])],
            'attribution_delay_sec' => rand(0, 60),
        ];

        // Add interaction-specific context
        switch ($interactionType) {
            case 'quiz':
                $metrics['context_data'] = [
                    'quiz_id' => rand(1, 10),
                    'score' => rand(70, 100),
                    'max_score' => 100,
                    'passed' => $baseScore >= 70,
                    'questions_answered' => rand(5, 10),
                ];
                break;

            case 'practice':
                $metrics['context_data'] = [
                    'problem_id' => rand(1, 20),
                    'is_correct' => $baseScore >= 70,
                    'points_earned' => $baseScore,
                    'complexity_score' => rand(1, 5),
                    'test_cases_passed' => rand(3, 5),
                ];
                break;

            case 'code_execution':
                $metrics['context_data'] = [
                    'message_id' => rand(1, 100),
                    'message_type' => 'user_question',
                    'code_snippet_length' => rand(50, 200),
                    'execution_success' => $baseScore >= 70,
                ];
                break;
        }

        return $metrics;
    }

    /**
     * Generate a realistic choice reason
     */
    private function generateChoiceReason($chosenAI, $interactionType): string
    {
        $reasons = [
            'gemini' => [
                'quiz' => 'Gemini provided clearer explanations for the concepts',
                'practice' => 'Gemini helped me understand the coding approach better',
                'code_execution' => 'Gemini gave more detailed code explanations',
            ],
            'together' => [
                'quiz' => 'Together AI had better examples that helped me understand',
                'practice' => 'Together AI provided more practical coding tips',
                'code_execution' => 'Together AI was more helpful with debugging',
            ],
            'both' => [
                'quiz' => 'Both AIs were equally helpful in different ways',
                'practice' => 'I used insights from both AIs to solve the problem',
                'code_execution' => 'Both provided valuable but different perspectives',
            ],
            'neither' => [
                'quiz' => 'I figured it out mostly on my own',
                'practice' => 'The AIs didn\'t help much with this particular problem',
                'code_execution' => 'I solved the coding issue independently',
            ],
        ];

        return $reasons[$chosenAI][$interactionType] ?? 'No specific reason provided';
    }

    /**
     * Show summary of created data
     */
    private function showSummary($userId): void
    {
        $this->info("\n📊 Test Data Summary:");
        
        $totalPolls = AIPreferenceLog::where('user_id', $userId)->count();
        $this->line("Total poll records: {$totalPolls}");

        $byType = AIPreferenceLog::where('user_id', $userId)
            ->selectRaw('interaction_type, COUNT(*) as count')
            ->groupBy('interaction_type')
            ->get();

        $this->line("\nBy interaction type:");
        foreach ($byType as $type) {
            $this->line("  - {$type->interaction_type}: {$type->count}");
        }

        $byAI = AIPreferenceLog::where('user_id', $userId)
            ->selectRaw('chosen_ai, COUNT(*) as count')
            ->groupBy('chosen_ai')
            ->get();

        $this->line("\nBy chosen AI:");
        foreach ($byAI as $ai) {
            $this->line("  - {$ai->chosen_ai}: {$ai->count}");
        }

        $avgPerformance = AIPreferenceLog::where('user_id', $userId)
            ->whereNotNull('performance_score')
            ->avg('performance_score');

        $this->line("\nAverage performance score: " . round($avgPerformance, 1) . "%");
    }
}
