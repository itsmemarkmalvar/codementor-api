<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiService
{
    private $apiKey;
    private $apiUrl;
    private $model;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta';
        $this->model = 'gemini-1.5-pro'; // Default model
    }

    /**
     * Get a response from the Gemini AI model
     */
    public function getResponse($question, $conversationHistory, $preferences, $topic = null)
    {
        try {
            // Make sure API key is loaded before each request
            if (empty($this->apiKey)) {
                $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
                Log::info('GeminiService::getResponse - Refreshing API key from config', [
                    'apiKey_exists' => !empty($this->apiKey)
                ]);
            }

            Log::info('GeminiService::getResponse - Starting request processing', [
                'apiKey_exists' => !empty($this->apiKey),
                'model' => $this->model,
                'topic' => $topic
            ]);

            // Check if API key is missing
            if (empty($this->apiKey)) {
                Log::error('Gemini API key is missing');
                throw new \Exception('API key for Gemini AI is not configured');
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

            // Prepare the history in the format Gemini expects
            $formattedHistory = $this->formatConversationHistory($conversationHistory);
            
            // Prepare the system prompt
            $systemPrompt = $this->generateSystemPrompt($preferences, $topic);

            // Build the request body
            $requestBody = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topP' => 0.8,
                    'topK' => 40,
                    'maxOutputTokens' => $this->getMaxTokensBasedOnPreferences($preferences),
                ]
            ];
            
            // Add conversation history
            if (!empty($formattedHistory)) {
                // Merge the formatted history into the contents array
                $requestBody['contents'] = array_merge($requestBody['contents'], $formattedHistory);
            }
            
            // Add the current question as the latest user message
            $requestBody['contents'][] = [
                'role' => 'user',
                'parts' => [
                    ['text' => $question]
                ]
            ];
            
            Log::info('GeminiService::getResponse - Sending request to Gemini AI', [
                'url' => $this->apiUrl . '/models/' . $this->model . ':generateContent',
                'request_structure' => [
                    'contents_count' => count($requestBody['contents']),
                    'max_tokens' => $requestBody['generationConfig']['maxOutputTokens']
                ]
            ]);
            
            // Add retries for temporary service issues
            $maxRetries = 2;
            $retryDelay = 1000; // 1 second in milliseconds
            $currentTry = 0;
            
            while ($currentTry <= $maxRetries) {
                $currentTry++;
                
                try {
                    $response = Http::timeout(30)->withHeaders([
                        'Content-Type' => 'application/json'
                    ])->post(
                        $this->apiUrl . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey, 
                        $requestBody
                    );
                    
                    Log::info('GeminiService::getResponse - Received response from Gemini AI', [
                        'status' => $response->status(),
                        'response_structure' => array_keys($response->json() ?: [])
                    ]);
                    
                    if ($response->successful()) {
                        $responseData = $response->json() ?: [];
                        
                        // Extract the text from Gemini's response
                        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                            $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                            Log::info('GeminiService::getResponse - Successfully extracted response text', [
                                'text_length' => strlen($text)
                            ]);
                            return $text;
                        } else {
                            Log::error('Unexpected Gemini AI response structure', [
                                'response' => $responseData
                            ]);
                            throw new \Exception('Unexpected API response structure');
                        }
                    } else {
                        $errorResponse = $response->json() ?: ['error' => 'Unknown error'];
                        $statusCode = $response->status();
                        
                        Log::error('Gemini AI error', [
                            'status' => $statusCode,
                            'response' => $errorResponse,
                            'try' => $currentTry,
                            'max_tries' => $maxRetries
                        ]);
                        
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
                            Log::error('Gemini AI service is unavailable', [
                                'status' => $statusCode,
                                'response' => $errorResponse
                            ]);
                            
                            // Return a meaningful response about the service being down
                            return $this->generateFallbackResponse($question, $topic);
                        }
                        
                        throw new \Exception('Failed to get response from Gemini AI: ' . json_encode($errorResponse));
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::error('Connection error to Gemini AI: ' . $e->getMessage());
                    
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
            Log::error('Error in GeminiService::getResponse', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide a meaningful response instead of failing
            return $this->generateFallbackResponse($question, $topic);
        }
    }

    /**
     * Format conversation history for Gemini
     */
    private function formatConversationHistory($history)
    {
        if (empty($history) || !is_array($history)) {
            return [];
        }
        
        $formattedHistory = [];
        
        foreach ($history as $message) {
            $role = isset($message['role']) ? $message['role'] : '';
            $content = isset($message['content']) ? $message['content'] : '';
            
            // Skip empty messages
            if (empty($content) || !is_string($content)) {
                continue;
            }
            
            // Convert roles to Gemini format
            $geminiRole = 'user';
            if ($role === 'assistant' || $role === 'bot' || $role === 'ai') {
                $geminiRole = 'model';
            }
            
            // Add to formatted history
            $formattedHistory[] = [
                'role' => $geminiRole,
                'parts' => [
                    ['text' => $content]
                ]
            ];
        }
        
        return $formattedHistory;
    }

    /**
     * Generate a system prompt based on preferences and topic
     */
    private function generateSystemPrompt($preferences, $topic = null)
    {
        $detailLevel = isset($preferences['explanationDetail']) ? $preferences['explanationDetail'] : 'detailed';
        $includeExamples = isset($preferences['codeExamples']) ? $preferences['codeExamples'] : true;
        
        $systemPrompt = "You are CodeMentor, an AI programming tutor specializing in Java programming. ";
        
        if ($topic) {
            $systemPrompt .= "The current topic is: $topic. ";
        }
        
        $systemPrompt .= "Your goal is to help students learn Java concepts and improve their programming skills. ";
        
        if ($detailLevel === 'brief') {
            $systemPrompt .= "Provide brief, concise explanations. ";
        } elseif ($detailLevel === 'comprehensive') {
            $systemPrompt .= "Provide comprehensive, in-depth explanations with detailed breakdowns of concepts. ";
        } else {
            $systemPrompt .= "Provide clear, detailed explanations that are easy to understand. ";
        }
        
        if ($includeExamples) {
            $systemPrompt .= "Include practical code examples to illustrate concepts. ";
        }
        
        $systemPrompt .= "Be encouraging and supportive. When students make mistakes, explain the error and suggest improvements without directly solving the entire problem for them. ";
        $systemPrompt .= "Format your responses clearly, using markdown where appropriate. Use code blocks with syntax highlighting for code examples.";
        
        return $systemPrompt;
    }

    /**
     * Get max tokens based on preferences
     */
    private function getMaxTokensBasedOnPreferences($preferences)
    {
        $responseLength = isset($preferences['responseLength']) ? $preferences['responseLength'] : 'medium';
        
        switch ($responseLength) {
            case 'short':
                return 500;
            case 'detailed':
                return 1500;
            case 'medium':
            default:
                return 1000;
        }
    }

    /**
     * Generate a fallback response when the API is unavailable
     */
    private function generateFallbackResponse($question, $topic = null)
    {
        $fallback = "I apologize, but I'm having trouble connecting to my knowledge services right now. ";
        
        if ($topic) {
            $fallback .= "I understand you're asking about $topic. ";
        }
        
        $fallback .= "Please try again in a moment, or you can try breaking your question into smaller parts. If you're asking about a code issue, could you share what you've tried so far?";
        
        return $fallback;
    }

    /**
     * Evaluate code with Gemini AI
     */
    public function evaluateCode($code, $stdout = '', $stderr = '', $topic = null)
    {
        try {
            // Check if code is empty
            if (empty($code) || !is_string($code)) {
                return "Please provide some Java code to evaluate.";
            }
            
            // Create a prompt for code evaluation
            $prompt = "As a Java programming tutor, please evaluate the following code:\n\n";
            $prompt .= "```java\n$code\n```\n\n";
            
            if (!empty($stdout)) {
                $prompt .= "Output:\n```\n$stdout\n```\n\n";
            }
            
            if (!empty($stderr)) {
                $prompt .= "Errors:\n```\n$stderr\n```\n\n";
            }
            
            if ($topic) {
                $prompt .= "Topic: $topic\n\n";
            }
            
            $prompt .= "Please provide feedback on this code. Consider: 
1. Correctness - Does it work as intended? If there are errors, explain them.
2. Style - Does it follow Java conventions and best practices?
3. Efficiency - Could the algorithm be improved?
4. Structure - Is the code well-organized?
5. Suggestions - What specific improvements would you recommend?

Be encouraging but honest, and include code examples for suggested improvements.";

            // Get response from Gemini
            return $this->getResponse($prompt, [], [
                'responseLength' => 'detailed',
                'explanationDetail' => 'comprehensive'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in GeminiService::evaluateCode', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return "I'm unable to evaluate your code at the moment due to a technical issue. If you're seeing errors in your code, check for syntax issues like missing semicolons, unmatched brackets, or incorrect method calls.";
        }
    }
} 