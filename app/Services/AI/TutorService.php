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
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
        $this->model = 'gemini-1.5-pro';
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

            $systemPrompt = $this->generateSystemPrompt($preferences, $topic);
            $formattedHistory = $this->formatConversationHistory($conversationHistory);
            
            Log::info('TutorService::getResponse - Created system prompt and formatted history', [
                'systemPrompt_length' => strlen($systemPrompt),
                'formattedHistory_count' => count($formattedHistory)
            ]);
            
            $url = $this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey;
            
            $requestBody = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => $systemPrompt
                            ]
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
            foreach ($formattedHistory as $message) {
                $requestBody['contents'][] = [
                    'role' => $message['role'],
                    'parts' => [
                        [
                            'text' => $message['content']
                        ]
                    ]
                ];
            }
            
            // Add the current question
            $requestBody['contents'][] = [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => $question
                    ]
                ]
            ];
            
            Log::info('TutorService::getResponse - Sending request to Gemini API', [
                'url' => $url,
                'request_structure' => [
                    'content_count' => count($requestBody['contents']),
                    'maxOutputTokens' => $requestBody['generationConfig']['maxOutputTokens']
                ],
                'request_body' => json_encode($requestBody, JSON_PRETTY_PRINT)
            ]);
            
            $response = Http::post($url, $requestBody);
            
            Log::info('TutorService::getResponse - Received response from Gemini API', [
                'status' => $response->status(),
                'response_structure' => array_keys($response->json())
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                    Log::info('TutorService::getResponse - Successfully extracted response text', [
                        'text_length' => strlen($text)
                    ]);
                    return $text;
                } else {
                    Log::error('Unexpected Gemini API response structure', [
                        'response' => $responseData
                    ]);
                    throw new \Exception('Unexpected API response structure');
                }
            } else {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to get response from Gemini API: ' . json_encode($response->json()));
            }
        } catch (\Exception $e) {
            Log::error('Error in TutorService::getResponse', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Format conversation history for the API
     */
    private function formatConversationHistory($history)
    {
        if (empty($history)) {
            Log::info('TutorService::formatConversationHistory - Empty history received, returning empty array');
            return [];
        }
        
        Log::info('TutorService::formatConversationHistory - Input history:', [
            'history_type' => gettype($history),
            'history_count' => is_array($history) ? count($history) : 0,
            'first_item_keys' => (is_array($history) && count($history) > 0) ? array_keys((array)$history[0]) : []
        ]);
        
        $formatted = [];
        
        if (!is_array($history)) {
            Log::warning('TutorService::formatConversationHistory - Non-array history received', [
                'history_type' => gettype($history)
            ]);
            return $formatted;
        }
        
        foreach ($history as $message) {
            // Handle different potential formats of incoming messages
            if (is_array($message)) {
                // Check if the message uses 'role' or 'sender' key
                if (isset($message['role'])) {
                    // Map 'assistant' role to 'model' for Gemini API
                    $role = $message['role'] === 'user' ? 'user' : 'model';
                    $content = $message['content'] ?? '';
                } elseif (isset($message['sender'])) {
                    // Handle the case where message has 'sender' instead of 'role'
                    $role = $message['sender'] === 'user' ? 'user' : 'model';
                    $content = $message['text'] ?? '';
                } else {
                    // Default case if neither expected format is found
                    Log::warning('TutorService::formatConversationHistory - Unexpected message format', [
                        'message' => json_encode($message)
                    ]);
                    continue; // Skip this message
                }
            } else {
                // Handle unexpected non-array message
                Log::warning('TutorService::formatConversationHistory - Non-array message received', [
                    'message_type' => gettype($message)
                ]);
                continue; // Skip this message
            }
            
            $formatted[] = [
                'role' => $role,
                'content' => $content
            ];
        }
        
        Log::info('TutorService::formatConversationHistory - Formatted history:', [
            'formatted_count' => count($formatted)
        ]);
        
        return $formatted;
    }

    /**
     * Generate system prompt based on preferences and topic
     */
    private function generateSystemPrompt($preferences, $topic = null)
    {
        $prompt = "You are an AI Java programming tutor that specializes in teaching programming concepts clearly and effectively. ";
        
        // Add topic context if provided
        if ($topic) {
            $prompt .= "The current topic is {$topic}. ";
        }
        
        // Add preferences for response style
        if (isset($preferences['responseLength'])) {
            $prompt .= "Keep your responses " . $preferences['responseLength'] . ". ";
        }
        
        if (isset($preferences['codeExamples']) && $preferences['codeExamples']) {
            $prompt .= "Include code examples in your explanations. ";
        } else {
            $prompt .= "Only include code examples when specifically requested. ";
        }
        
        if (isset($preferences['explanationDetail'])) {
            $detailLevel = $preferences['explanationDetail'];
            $prompt .= "Provide " . $detailLevel . " explanations. ";
        }
        
        $prompt .= "Your goal is to help the student understand Java programming concepts and improve their coding skills.";
        
        return $prompt;
    }

    /**
     * Determine max tokens based on user preferences
     */
    private function getMaxTokensBasedOnPreferences($preferences)
    {
        if (isset($preferences['responseLength'])) {
            switch ($preferences['responseLength']) {
                case 'brief':
                    return 300;
                case 'medium':
                    return 800;
                case 'detailed':
                    return 1500;
                default:
                    return 800;
            }
        }
        
        return 800; // Default to medium length
    }

    /**
     * Evaluate Java code and provide feedback
     */
    public function evaluateCode($code, $stdout = '', $stderr = '', $topic = null)
    {
        try {
            $url = $this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey;
            
            $prompt = "You are a Java programming expert tasked with evaluating code. Analyze the following Java code";
            
            if ($topic) {
                $prompt .= " related to the topic of {$topic}";
            }
            
            $prompt .= " and provide constructive feedback:\n\n```java\n{$code}\n```\n\n";
            
            // Include execution results if available
            if (!empty($stdout)) {
                $prompt .= "Code output:\n```\n{$stdout}\n```\n\n";
            }
            
            if (!empty($stderr)) {
                $prompt .= "Errors/warnings:\n```\n{$stderr}\n```\n\n";
            }
            
            $prompt .= "Please provide feedback on:
1. Code correctness
2. Style and best practices
3. Potential improvements
4. Efficiency considerations
5. Any errors or bugs you spot";
            
            $requestBody = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topP' => 0.8,
                    'topK' => 40,
                    'maxOutputTokens' => 1000,
                ]
            ];
            
            $response = Http::post($url, $requestBody);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    return $responseData['candidates'][0]['content']['parts'][0]['text'];
                } else {
                    Log::error('Unexpected Gemini API response structure in code evaluation', [
                        'response' => $responseData
                    ]);
                    throw new \Exception('Unexpected API response structure');
                }
            } else {
                Log::error('Gemini API error in code evaluation', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to get response from Gemini API');
            }
        } catch (\Exception $e) {
            Log::error('Error in TutorService::evaluateCode', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 