<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AITutorController;
use App\Http\Controllers\API\LearningTopicController;
use App\Http\Controllers\API\LearningSessionController;
use App\Http\Controllers\API\ChatMessageController;
use App\Http\Controllers\API\UserProgressController;
use App\Http\Controllers\API\CodeSnippetController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\QuizController;
use App\Http\Controllers\API\PracticeController;
use App\Http\Controllers\API\ProjectController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Handle CORS preflight OPTIONS requests
Route::options('/{any}', function() {
    return response()->json('OK', 200)
        ->withHeaders([
            'Access-Control-Allow-Origin' => 'http://localhost:3000',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
})->where('any', '.*');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Testing route - remove in production
Route::get('/test-user-create', function() {
    try {
        $user = \App\Models\User::create([
            'name' => 'Test User ' . rand(1000, 9999),
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Test user created successfully',
            'user' => $user,
            'users_count' => \App\Models\User::count(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create test user',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// Testing route - remove in production
Route::get('/test-ai', function() {
    try {
        $apiKey = env('GEMINI_API_KEY', '');
        $hasKey = !empty($apiKey);
        $maskedKey = $hasKey ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : 'Not Set';
        $aiService = app()->make(\App\Services\AI\TutorService::class);
        
        return response()->json([
            'success' => true,
            'message' => 'AI API configuration check',
            'gemini_key_exists' => $hasKey,
            'gemini_key_masked' => $maskedKey,
            'service_loaded' => $aiService !== null,
            'env' => [
                'app_env' => env('APP_ENV'),
                'api_url_set' => !empty(env('API_URL')),
                'frontend_url_set' => !empty(env('FRONTEND_URL'))
            ],
            'cors' => [
                'allowed_origins' => config('cors.allowed_origins'),
                'supports_credentials' => config('cors.supports_credentials'),
                'max_age' => config('cors.max_age')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking AI configuration',
            'error' => $e->getMessage()
        ], 500);
    }
});

// AI Tutor routes - temporarily public for testing
Route::post('/tutor/chat', [AITutorController::class, 'chat']);
Route::post('/tutor/execute-code', [AITutorController::class, 'executeCode']);
Route::post('/tutor/execute-project', [AITutorController::class, 'executeProject']);
Route::post('/tutor/update-progress', [AITutorController::class, 'updateProgress']);

// Learning topics public routes
Route::get('/topics', [LearningTopicController::class, 'index']);
Route::get('/topics/hierarchy', [LearningTopicController::class, 'hierarchy']);
Route::get('/topics/{id}', [LearningTopicController::class, 'show']);

// Lesson plans public routes
Route::get('/topics/lesson-plans', [LessonController::class, 'getAllLessonPlans']);
Route::get('/topics/{topicId}/lesson-plans', [LessonController::class, 'getLessonPlans']);
Route::get('/lesson-plans/{id}', [LessonController::class, 'getLessonPlan']);
Route::get('/lesson-plans/{lessonPlanId}/modules', [LessonController::class, 'getLessonPlanModules']);
Route::get('/modules/{id}', [LessonController::class, 'getModule']);
Route::get('/lesson-modules/{moduleId}/exercises', [LessonController::class, 'getModuleExercises']);
Route::get('/exercises/{id}', [LessonController::class, 'getExercise']);
// Debug route for all lesson plans
Route::get('/all-lesson-plans', [LessonController::class, 'getAllLessonPlans']);

// Public practice routes (for demo and guest users)
Route::prefix('practice')->group(function () {
    Route::get('/categories', [PracticeController::class, 'getCategories']);
    Route::get('/categories/{categoryId}/problems', [PracticeController::class, 'getProblemsByCategory']);
    Route::get('/problems/{id}', [PracticeController::class, 'getProblem']);
    Route::post('/problems/{id}/solution', [PracticeController::class, 'submitSolution']);
    Route::get('/problems/{id}/hint', [PracticeController::class, 'getHint']);
    Route::get('/problems/{id}/resources', [PracticeController::class, 'getProblemResources']);
    Route::get('/problems/{id}/resources/suggestions', [PracticeController::class, 'getSuggestedResources']);
    Route::get('/all-data', [PracticeController::class, 'getAllPracticeData']);
    
    // User session and statistics
    Route::post('/session/init', [PracticeController::class, 'initializeUserSession']);
    Route::get('/session/stats', [PracticeController::class, 'getUserSessionStats']);
    Route::post('/session/signup-prompt', [PracticeController::class, 'sendSignupPrompt']);
});

// Test routes for development only (REMOVE IN PRODUCTION)
Route::post('/test-projects', [ProjectController::class, 'testStore']);
Route::put('/test-projects/{id}', [ProjectController::class, 'update'])->withoutMiddleware(['auth:sanctum']);

// Testing route - remove in production
Route::get('/test-auth', function(Request $request) {
    $headers = $request->headers->all();
    $authHeader = $request->header('Authorization');
    $isAuthenticated = Auth::check();
    $user = Auth::user();
    
    return response()->json([
        'success' => true,
        'message' => 'Auth test route',
        'is_authenticated' => $isAuthenticated,
        'auth_header' => $authHeader ? substr($authHeader, 0, 15) . '...' : 'Not present',
        'user' => $isAuthenticated ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ] : null,
        'request_details' => [
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept')
        ]
    ]);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Learning topics management (admin only)
    Route::post('/topics', [LearningTopicController::class, 'store']);
    Route::put('/topics/{id}', [LearningTopicController::class, 'update']);
    Route::delete('/topics/{id}', [LearningTopicController::class, 'destroy']);
    
    // Learning sessions
    Route::apiResource('sessions', LearningSessionController::class);
    
    // Chat messages
    Route::apiResource('messages', ChatMessageController::class);
    
    // User progress
    Route::get('/progress', [UserProgressController::class, 'index']);
    Route::get('/progress/{topicId}', [UserProgressController::class, 'show']);
    Route::put('/progress/{topicId}', [UserProgressController::class, 'update']);
    Route::get('/topics/lock-status', [UserProgressController::class, 'getTopicsWithLockStatus']);
    
    // Code snippets
    Route::apiResource('snippets', CodeSnippetController::class);
    
    // Lesson plan management
    Route::post('/lesson-plans', [LessonController::class, 'createLessonPlan']);
    Route::put('/lesson-plans/{id}', [LessonController::class, 'updateLessonPlan']);
    Route::post('/modules', [LessonController::class, 'createModule']);
    Route::put('/modules/{id}', [LessonController::class, 'updateModule']);
    Route::post('/exercises', [LessonController::class, 'createExercise']);
    Route::put('/exercises/{id}', [LessonController::class, 'updateExercise']);
    
    // User lesson progress
    Route::get('/lesson-plans/{id}/progress', [LessonController::class, 'getUserLessonProgress']);
    Route::post('/modules/struggle-point', [LessonController::class, 'recordStrugglePoint']);
    Route::get('/exercises/{id}/hint', [LessonController::class, 'getNextHint']);
    
    // Quiz routes
    Route::get('/quizzes/{id}', [QuizController::class, 'getQuiz']);
    Route::get('/modules/{moduleId}/quizzes', [QuizController::class, 'getModuleQuizzes']);
    Route::post('/quizzes/{id}/attempt', [QuizController::class, 'startQuizAttempt']);
    Route::post('/quiz-attempts/{id}/submit', [QuizController::class, 'submitQuizAttempt']);
    Route::get('/quiz-attempts/{id}', [QuizController::class, 'getQuizAttempt']);
    Route::get('/users/quizzes', [QuizController::class, 'getUserQuizzes']);
    Route::post('/tutor/analyze-quiz-results', [AITutorController::class, 'analyzeQuizResults']);
    
    // Practice routes (admin only)
    Route::get('/practice/problems/{id}/resources', [PracticeController::class, 'getSuggestedResources']);
    Route::get('/practice/problems/{id}/linked-resources', [PracticeController::class, 'getProblemResources']);
    Route::post('/practice/problems/{id}/resources', [PracticeController::class, 'associateResources']);
    
    // Project management routes
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    
    // Project files management
    Route::post('/projects/{projectId}/files', [ProjectController::class, 'addFile']);
    Route::put('/projects/{projectId}/files/{fileId}', [ProjectController::class, 'updateFile']);
    Route::delete('/projects/{projectId}/files/{fileId}', [ProjectController::class, 'deleteFile']);
    
    // Project import/export
    Route::get('/projects/{id}/export', [ProjectController::class, 'export']);
    Route::post('/projects/import', [ProjectController::class, 'import']);
}); 