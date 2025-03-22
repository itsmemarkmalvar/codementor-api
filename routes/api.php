<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

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

// Tutor routes - temporarily public for testing
Route::post('/tutor/response', [App\Http\Controllers\API\TutorController::class, 'getResponse']);
Route::post('/tutor/execute-java', [App\Http\Controllers\API\TutorController::class, 'executeJavaCode']);
Route::post('/tutor/evaluate-code', [App\Http\Controllers\API\TutorController::class, 'evaluateCode']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Other protected routes will go here
}); 