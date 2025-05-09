<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TutorService
{
    private $apiKey;
    private $apiUrl;
    private $model;

    public function __construct()
    {
        $this->apiKey = env('TOGETHER_API_KEY', '');
        $this->apiUrl = env('TOGETHER_API_URL', 'https://api.together.xyz/v1');
        $this->model = 'mistralai/Mixtral-8x7B-Instruct-v0.1'; // Default model, can be changed
    }

    /**
     * Get a response from the AI tutor
     */
    public function getResponse($question, $conversationHistory, $preferences, $topic = null)
    {
        try {
            Log::info('TutorService::getResponse - Starting request processing', [
                'apiKey_exists' => !empty($this->apiKey),
                'model' => $this->model,
                'topic' => $topic
            ]);

            // Check if API key is missing
            if (empty($this->apiKey)) {
                Log::error('Together API key is missing');
                throw new \Exception('API key for Together AI is not configured');
            }

            // Validate required parameters
            if (!is_string($question) || trim($question) === '') {
                Log::error('Invalid question: Question must be a non-empty string');
                throw new \Exception('Invalid question format');
            }

            // Ensure preferences is an array, default to empty array if null
            if (!is_array($preferences)) {
                $preferences = [];
                Log::warning('Preferences parameter is not an array, defaulting to empty array');
            }

            $systemPrompt = $this->generateSystemPrompt($preferences, $topic);
            $formattedHistory = $this->formatConversationHistory($conversationHistory);
            
            Log::info('TutorService::getResponse - Created system prompt and formatted history', [
                'systemPrompt_length' => strlen($systemPrompt),
                'formattedHistory_count' => count($formattedHistory)
            ]);
            
            $requestBody = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ]
                ],
                'temperature' => 0.7,
                'top_p' => 0.8,
                'top_k' => 40,
                'max_tokens' => $this->getMaxTokensBasedOnPreferences($preferences),
            ];
            
            // Add conversation history
            foreach ($formattedHistory as $message) {
                $requestBody['messages'][] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
            
            // Add the current question
            $requestBody['messages'][] = [
                'role' => 'user',
                'content' => $question
            ];
            
            Log::info('TutorService::getResponse - Sending request to Together AI', [
                'url' => $this->apiUrl . '/chat/completions',
                'request_structure' => [
                    'message_count' => count($requestBody['messages']),
                    'max_tokens' => $requestBody['max_tokens']
                ],
                'request_body' => json_encode($requestBody, JSON_PRETTY_PRINT)
            ]);
            
            // Add retries for temporary service issues
            $maxRetries = 2;
            $retryDelay = 1000; // 1 second in milliseconds
            $currentTry = 0;
            
            while ($currentTry <= $maxRetries) {
                $currentTry++;
                
                try {
                    $response = Http::timeout(30)->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json'
                    ])->post($this->apiUrl . '/chat/completions', $requestBody);
                    
                    Log::info('TutorService::getResponse - Received response from Together AI', [
                        'status' => $response->status(),
                        'response_structure' => array_keys($response->json() ?: [])
                    ]);
                    
                    if ($response->successful()) {
                        $responseData = $response->json() ?: [];
                        
                        if (isset($responseData['choices'][0]['message']['content'])) {
                            $text = $responseData['choices'][0]['message']['content'];
                            Log::info('TutorService::getResponse - Successfully extracted response text', [
                                'text_length' => strlen($text)
                            ]);
                            return $text;
                        } else {
                            Log::error('Unexpected Together AI response structure', [
                                'response' => $responseData
                            ]);
                            throw new \Exception('Unexpected API response structure');
                        }
                    } else {
                        $errorResponse = $response->json() ?: ['error' => 'Unknown error'];
                        $statusCode = $response->status();
                        
                        Log::error('Together AI error', [
                            'status' => $statusCode,
                            'response' => $errorResponse,
                            'try' => $currentTry,
                            'max_tries' => $maxRetries
                        ]);
                        
                        // Additional detailed logging for 400 errors (input validation errors)
                        if ($statusCode == 400) {
                            Log::error('Together AI input validation error - DETAILED', [
                                'error_details' => $errorResponse,
                                'message_count' => count($requestBody['messages']),
                                'model' => $this->model,
                                'message_roles' => array_map(function($msg) { return $msg['role']; }, $requestBody['messages']),
                                'message_content_types' => array_map(function($msg) { 
                                    $type = gettype($msg['content']);
                                    if ($type === 'string') {
                                        return 'string(' . strlen($msg['content']) . ')';
                                    }
                                    return $type;
                                }, $requestBody['messages'])
                            ]);
                        }
                        
                        // If it's a 503 (Service Unavailable) or 429 (Too Many Requests) and we have retries left
                        if (($statusCode == 503 || $statusCode == 429) && $currentTry < $maxRetries) {
                            Log::info("Temporary service issue detected, retrying in {$retryDelay}ms. Attempt {$currentTry} of {$maxRetries}");
                            // Sleep for a moment before retrying
                            usleep($retryDelay * 1000); // Convert milliseconds to microseconds
                            // Increase delay for next attempt
                            $retryDelay *= 2;
                            continue;
                        }
                        
                        // Check if this is a 503 Service Unavailable error
                        if ($statusCode == 503) {
                            Log::error('Together AI service is unavailable', [
                                'status' => $statusCode,
                                'response' => $errorResponse
                            ]);
                            
                            // Return a meaningful response about the service being down
                            return $this->generateFallbackResponse($question, $topic);
                        }
                        
                        throw new \Exception('Failed to get response from Together AI: ' . json_encode($errorResponse));
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::error('Connection error to Together AI: ' . $e->getMessage());
                    
                    // If we have retries left, try again
                    if ($currentTry < $maxRetries) {
                        Log::info("Connection issue detected, retrying in {$retryDelay}ms. Attempt {$currentTry} of {$maxRetries}");
                        usleep($retryDelay * 1000);
                        $retryDelay *= 2;
                        continue;
                    }
                    
                    // If we've exhausted retries, return a fallback response
                    return $this->generateFallbackResponse($question, $topic);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in TutorService::getResponse', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide a meaningful response instead of failing
            return $this->generateFallbackResponse($question, $topic);
        }
    }

    /**
     * Get a response from the AI tutor with additional context from lesson modules
     */
    public function getResponseWithContext($question, $conversationHistory, $preferences, $topic = null, $context = [])
    {
        try {
            Log::info('TutorService::getResponseWithContext - Starting request processing', [
                'apiKey_exists' => !empty($this->apiKey),
                'model' => $this->model,
                'topic' => $topic,
                'context_keys' => !empty($context) ? array_keys($context) : []
            ]);

            // Check if API key is missing
            if (empty($this->apiKey)) {
                Log::error('Together API key is missing');
                throw new \Exception('API key for Together AI is not configured');
            }

            // Validate required parameters
            if (!is_string($question) || trim($question) === '') {
                Log::error('Invalid question: Question must be a non-empty string');
                throw new \Exception('Invalid question format');
            }

            // Ensure preferences is an array, default to empty array if null
            if (!is_array($preferences)) {
                $preferences = [];
                Log::warning('Preferences parameter is not an array, defaulting to empty array');
            }

            $systemPrompt = $this->generateSystemPromptWithContext($preferences, $topic, $context);
            $formattedHistory = $this->formatConversationHistory($conversationHistory);
            
            Log::info('TutorService::getResponseWithContext - Created system prompt and formatted history', [
                'systemPrompt_length' => strlen($systemPrompt),
                'formattedHistory_count' => count($formattedHistory)
            ]);
            
            $requestBody = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ]
                ],
                'temperature' => 0.7,
                'top_p' => 0.8,
                'top_k' => 40,
                'max_tokens' => $this->getMaxTokensBasedOnPreferences($preferences),
            ];
            
            // Add conversation history
            foreach ($formattedHistory as $message) {
                $requestBody['messages'][] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
            
            // Add the current question
            $requestBody['messages'][] = [
                'role' => 'user',
                'content' => $question
            ];
            
            // Improved detailed logging for debugging
            Log::debug('TutorService::getResponseWithContext - DETAILED REQUEST', [
                'url' => $this->apiUrl . '/chat/completions',
                'all_messages' => $requestBody['messages'],
                'message_roles' => array_map(function($msg) { return $msg['role']; }, $requestBody['messages']),
                'message_lengths' => array_map(function($msg) { return strlen($msg['content']); }, $requestBody['messages']),
                'exact_request' => json_encode($requestBody)
            ]);
            
            Log::info('TutorService::getResponseWithContext - Sending request to Together AI', [
                'url' => $this->apiUrl . '/chat/completions',
                'request_structure' => [
                    'message_count' => count($requestBody['messages']),
                    'max_tokens' => $requestBody['max_tokens']
                ],
                'request_body' => json_encode($requestBody, JSON_PRETTY_PRINT)
            ]);
            
            // Add retries for temporary service issues
            $maxRetries = 2;
            $retryDelay = 1000; // 1 second in milliseconds
            $currentTry = 0;
            
            while ($currentTry <= $maxRetries) {
                $currentTry++;
                
                try {
                    $response = Http::timeout(30)->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json'
                    ])->post($this->apiUrl . '/chat/completions', $requestBody);
                    
                    Log::info('TutorService::getResponseWithContext - Received response from Together AI', [
                        'status' => $response->status(),
                        'response_structure' => array_keys($response->json() ?: [])
                    ]);
                    
                    if ($response->successful()) {
                        $responseData = $response->json() ?: [];
                        
                        if (isset($responseData['choices'][0]['message']['content'])) {
                            $text = $responseData['choices'][0]['message']['content'];
                            Log::info('TutorService::getResponseWithContext - Successfully extracted response text', [
                                'text_length' => strlen($text)
                            ]);
                            return $text;
                        } else {
                            Log::error('Unexpected Together AI response structure', [
                                'response' => $responseData
                            ]);
                            throw new \Exception('Unexpected API response structure');
                        }
                    } else {
                        $errorResponse = $response->json() ?: ['error' => 'Unknown error'];
                        $statusCode = $response->status();
                        
                        Log::error('Together AI error', [
                            'status' => $statusCode,
                            'response' => $errorResponse,
                            'try' => $currentTry,
                            'max_tries' => $maxRetries
                        ]);
                        
                        // Additional detailed logging for 400 errors (input validation errors)
                        if ($statusCode == 400) {
                            Log::error('Together AI input validation error - DETAILED', [
                                'error_details' => $errorResponse,
                                'message_count' => count($requestBody['messages']),
                                'model' => $this->model,
                                'message_roles' => array_map(function($msg) { return $msg['role']; }, $requestBody['messages']),
                                'message_content_types' => array_map(function($msg) { 
