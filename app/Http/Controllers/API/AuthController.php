<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Handle all possible request formats
        $data = $this->parseAllRequestFormats($request);

        // Validate the data
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'content_type' => $request->header('Content-Type'),
                    'raw_content' => $request->getContent(),
                    'all_data' => $request->all(),
                    'parsed_data' => $data
                ]
            ], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Authenticate a user and generate token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        // Handle all possible request formats
        $data = $this->parseAllRequestFormats($request);

        // Validate the data
        $validator = Validator::make($data, [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'content_type' => $request->header('Content-Type'),
                    'raw_content' => $request->getContent(),
                    'all_data' => $request->all(),
                    'parsed_data' => $data
                ]
            ], 422);
        }

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401)
            ->withHeaders([
                'Access-Control-Allow-Origin' => env('CORS_ALLOWED_ORIGINS', 'https://codementor-java.com'),
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token, Authorization, X-Requested-With, Accept',
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $user,
            'token' => $token
        ])
        ->withHeaders([
            'Access-Control-Allow-Origin' => env('CORS_ALLOWED_ORIGINS', 'https://codementor-java.com'),
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token, Authorization, X-Requested-With, Accept',
        ]);
    }

    /**
     * Log out a user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * Request a password reset link
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json([
                'message' => 'We will send you a reset link if your email is in our system.'
            ]);
        }
        
        // Generate a password reset token
        $token = Str::random(60);
        
        // Store the token in the password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );
        
        // In a real application, you would send an email with the reset link here
        // For now, we'll just return the token
        
        return response()->json([
            'message' => 'Password reset link has been generated.',
            'token' => $token, // In production, you would not return this
            'email' => $request->email
        ]);
    }

    /**
     * Reset the user's password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);
        
        // In a real application, you would verify the token here
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unable to reset password.'
            ], 400);
        }
        
        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();
        
        // Delete the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        
        // Revoke all tokens for this user
        $user->tokens()->delete();
        
        // Create a new token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'message' => 'Password has been reset successfully',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Parse all possible request formats (JSON, Form Data, URLSearchParams, etc.)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    private function parseAllRequestFormats(Request $request)
    {
        $data = [];
        
        // Method 1: Try JSON parsing
        if ($request->header('Content-Type') === 'application/json' && $request->getContent()) {
            $jsonData = json_decode($request->getContent(), true);
            if ($jsonData && is_array($jsonData)) {
                $data = $jsonData;
            }
        }
        
        // Method 2: Try form data (application/x-www-form-urlencoded)
        if (empty($data) && $request->header('Content-Type') === 'application/x-www-form-urlencoded') {
            $data = $request->all();
        }
        
        // Method 3: Try multipart/form-data (FormData)
        if (empty($data) && strpos($request->header('Content-Type'), 'multipart/form-data') !== false) {
            $data = $request->all();
        }
        
        // Method 4: Try Laravel's default parsing
        if (empty($data)) {
            $data = $request->all();
        }
        
        // Method 5: Try raw content parsing as form data
        if (empty($data) && $request->getContent()) {
            parse_str($request->getContent(), $parsedData);
            if (!empty($parsedData)) {
                $data = $parsedData;
            }
        }
        
        return $data;
    }

} 