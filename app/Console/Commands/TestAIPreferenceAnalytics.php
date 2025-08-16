<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SplitScreenSession;
use App\Models\PracticeAttempt;
use App\Models\QuizAttempt;
use App\Models\LearningTopic;
use App\Models\PracticeProblem;
use App\Models\LessonQuiz;
use Carbon\Carbon;

class TestAIPreferenceAnalytics extends Command
{
    protected $signature = 'app:test-ai-preference-analytics {--user-id=1} {--create-sample-data}';
    protected $description = 'Test AI preference analytics endpoint with sample data';

    public function handle()
    {
        $this->info('Testing AI Preference Analytics...');
        $this->info('==================================');

        $userId = $this->option('user-id');
        $createSampleData = $this->option('create-sample-data');

        // Check if user exists
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Testing with user: {$user->name} (ID: {$userId})");

        if ($createSampleData) {
            $this->info('Creating sample data...');
            $this->createSampleData($userId);
        }

        // Test the analytics endpoint
        $this->info('Testing analytics endpoint...');
        
        try {
            // Simulate the analytics request
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'window' => '30d',
                'topic_id' => null,
                'difficulty' => null,
            ]);

            // Set the authenticated user
            auth()->login($user);

            $analyticsController = app()->make(\App\Http\Controllers\API\AnalyticsController::class);
            $response = $analyticsController->getAIPreferenceAnalysis($request);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                
                $this->info('✅ Analytics endpoint working correctly!');
                $this->info('');
                
                // Display summary
                $this->info('Summary:');
                $this->info("  Total Interactions: {$data['data']['overall_summary']['total_interactions']}");
                $this->info("  Code Executions: {$data['data']['code_execution_analysis']['total_code_executions']}");
                $this->info("  Practice Attempts: {$data['data']['practice_analysis']['total_practice_attempts']}");
                $this->info("  Quiz Attempts: {$data['data']['quiz_analysis']['total_quiz_attempts']}");
                
                $this->info('');
                $this->info('Overall Preferences:');
                foreach ($data['data']['overall_summary']['overall_preferences'] as $preference => $rate) {
                    $this->info("  {$preference}: {$rate}%");
                }
                
                $this->info('');
                $this->info('Performance Correlation:');
                foreach ($data['data']['performance_correlation'] as $preference => $metrics) {
                    $this->info("  {$preference}:");
                    $this->info("    Practice Success: {$metrics['practice_success_rate']}%");
                    $this->info("    Quiz Pass Rate: {$metrics['quiz_pass_rate']}%");
                    $this->info("    Total Attempts: {$metrics['total_attempts']}");
                }
                
            } else {
                $this->error('❌ Analytics endpoint returned error status: ' . $response->getStatusCode());
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error testing analytics endpoint: ' . $e->getMessage());
            return 1;
        }

        $this->info('');
        $this->info('✅ AI Preference Analytics test completed successfully!');
        return 0;
    }

    private function createSampleData($userId)
    {
        // Get or create a topic
        $topic = LearningTopic::first();
        if (!$topic) {
            $topic = LearningTopic::create([
                'title' => 'Java Basics',
                'description' => 'Fundamental Java concepts',
                'order' => 1,
            ]);
        }

        // Create sample split-screen sessions with user choices
        $sessions = [
            [
                'user_choice' => 'gemini',
                'quiz_triggered' => true,
                'practice_triggered' => false,
                'started_at' => Carbon::now()->subDays(5),
                'ended_at' => Carbon::now()->subDays(5)->addMinutes(30),
            ],
            [
                'user_choice' => 'together',
                'quiz_triggered' => false,
                'practice_triggered' => true,
                'started_at' => Carbon::now()->subDays(3),
                'ended_at' => Carbon::now()->subDays(3)->addMinutes(45),
            ],
            [
                'user_choice' => 'both',
                'quiz_triggered' => true,
                'practice_triggered' => true,
                'started_at' => Carbon::now()->subDays(1),
                'ended_at' => Carbon::now()->subDays(1)->addMinutes(60),
            ],
        ];

        foreach ($sessions as $sessionData) {
            SplitScreenSession::create([
                'user_id' => $userId,
                'topic_id' => $topic->id,
                'session_type' => 'comparison',
                'ai_models_used' => ['gemini', 'together'],
                'started_at' => $sessionData['started_at'],
                'ended_at' => $sessionData['ended_at'],
                'total_messages' => 10,
                'engagement_score' => 15,
                'quiz_triggered' => $sessionData['quiz_triggered'],
                'practice_triggered' => $sessionData['practice_triggered'],
                'user_choice' => $sessionData['user_choice'],
                'choice_reason' => 'Sample reason for testing',
            ]);
        }

        // Create sample practice problems
        $practiceProblem = PracticeProblem::first();
        if (!$practiceProblem) {
            $practiceProblem = PracticeProblem::create([
                'title' => 'Hello World',
                'description' => 'Write a simple Hello World program',
                'difficulty_level' => 'beginner',
                'category_id' => 1,
                'solution_code' => 'public class Main { public static void main(String[] args) { System.out.println("Hello World"); } }',
                'test_cases' => json_encode([['input' => '', 'output' => 'Hello World']]),
            ]);
        }

        // Create sample practice attempts
        $practiceAttempts = [
            ['attribution_model' => 'gemini', 'is_correct' => true, 'execution_time_ms' => 1500],
            ['attribution_model' => 'together', 'is_correct' => false, 'execution_time_ms' => 2000],
            ['attribution_model' => 'gemini', 'is_correct' => true, 'execution_time_ms' => 1200],
            ['attribution_model' => 'together', 'is_correct' => true, 'execution_time_ms' => 1800],
        ];

        foreach ($practiceAttempts as $attemptData) {
            PracticeAttempt::create([
                'user_id' => $userId,
                'problem_id' => $practiceProblem->id,
                'submitted_code' => 'public class Main { public static void main(String[] args) { System.out.println("Hello World"); } }',
                'is_correct' => $attemptData['is_correct'],
                'execution_time_ms' => $attemptData['execution_time_ms'],
                'attribution_model' => $attemptData['attribution_model'],
                'compiler_errors' => $attemptData['is_correct'] ? null : 'Compilation error',
                'runtime_errors' => null,
            ]);
        }

        // Create sample quiz
        $quiz = LessonQuiz::first();
        if (!$quiz) {
            $quiz = LessonQuiz::create([
                'title' => 'Java Basics Quiz',
                'description' => 'Test your Java knowledge',
                'module_id' => 1,
                'difficulty' => 'beginner',
                'pass_percentage' => 70,
                'time_limit_minutes' => 30,
            ]);
        }

        // Create sample quiz attempts
        $quizAttempts = [
            ['attribution_model' => 'gemini', 'percentage' => 85, 'passed' => true, 'time_spent_seconds' => 1200],
            ['attribution_model' => 'together', 'percentage' => 65, 'passed' => false, 'time_spent_seconds' => 1800],
            ['attribution_model' => 'gemini', 'percentage' => 90, 'passed' => true, 'time_spent_seconds' => 900],
            ['attribution_model' => 'together', 'percentage' => 75, 'passed' => true, 'time_spent_seconds' => 1500],
        ];

        foreach ($quizAttempts as $attemptData) {
            QuizAttempt::create([
                'user_id' => $userId,
                'quiz_id' => $quiz->id,
                'percentage' => $attemptData['percentage'],
                'passed' => $attemptData['passed'],
                'time_spent_seconds' => $attemptData['time_spent_seconds'],
                'attribution_model' => $attemptData['attribution_model'],
                'max_possible_score' => 100,
                'score' => $attemptData['percentage'],
                'question_responses' => [],
                'correct_questions' => [],
                'attempt_number' => 1,
            ]);
        }

        $this->info('✅ Sample data created successfully!');
    }
}
