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
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/chat/completions', $requestBody);
            
            Log::info('TutorService::getResponse - Received response from Together AI', [
                'status' => $response->status(),
                'response_structure' => array_keys($response->json())
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
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
                Log::error('Together AI error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to get response from Together AI: ' . json_encode($response->json()));
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
            
            Log::info('TutorService::getResponseWithContext - Sending request to Together AI', [
                'url' => $this->apiUrl . '/chat/completions',
                'request_structure' => [
                    'message_count' => count($requestBody['messages']),
                    'max_tokens' => $requestBody['max_tokens']
                ]
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/chat/completions', $requestBody);
            
            Log::info('TutorService::getResponseWithContext - Received response from Together AI', [
                'status' => $response->status(),
                'response_structure' => array_keys($response->json())
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
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
                Log::error('Together AI error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to get response from Together AI: ' . json_encode($response->json()));
            }
        } catch (\Exception $e) {
            Log::error('Error in TutorService::getResponseWithContext', [
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
                    // Map 'assistant' role to 'model' for Together AI
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
     * Generate system prompt with additional context from lesson modules
     */
    private function generateSystemPromptWithContext($preferences, $topic = null, $context = [])
    {
        // Start with the base prompt
        $prompt = "You are an AI tutor specializing in teaching Java programming. ";
        
        // Add topic information if provided
        if ($topic) {
            $prompt .= "The current topic we're discussing is: $topic. ";
        }
        
        // Add module context if provided
        if (!empty($context)) {
            $prompt .= "\n\n### LESSON CONTEXT ###\n";
            
            if (isset($context['module_title'])) {
                $prompt .= "Current module: " . $context['module_title'] . "\n";
            }
            
            if (isset($context['module_content'])) {
                $prompt .= "Module content: " . $context['module_content'] . "\n";
            }
            
            if (isset($context['examples'])) {
                $prompt .= "Examples for teaching:\n" . $context['examples'] . "\n";
            }
            
            if (isset($context['key_points'])) {
                $prompt .= "Key points to emphasize:\n" . $context['key_points'] . "\n";
            }
            
            if (isset($context['guidance_notes'])) {
                $prompt .= "Teaching guidance notes:\n" . $context['guidance_notes'] . "\n";
            }
            
            if (isset($context['teaching_strategy'])) {
                $prompt .= "Teaching strategy for this module: " . json_encode($context['teaching_strategy']) . "\n";
            }
            
            if (isset($context['common_misconceptions'])) {
                $prompt .= "Watch for these common misconceptions: " . json_encode($context['common_misconceptions']) . "\n";
            }
            
            if (isset($context['struggle_points'])) {
                $prompt .= "The student has previously struggled with:\n";
                foreach ($context['struggle_points'] as $point) {
                    $prompt .= "- " . ($point['concept'] ?? 'Unspecified concept') . ": " . ($point['details'] ?? 'No details') . "\n";
                }
            }
            
            $prompt .= "\n### END LESSON CONTEXT ###\n\n";
        }
        
        // Add instruction about response style
        $prompt .= "Provide clear, concise explanations with practical examples that align with the current lesson module. ";
        
        // Process preferences
        if (is_array($preferences)) {
            // Adjust explanation depth based on expertise level
            if (isset($preferences['expertise_level'])) {
                $level = strtolower($preferences['expertise_level']);
                if ($level === 'beginner') {
                    $prompt .= "The student is a beginner, so explain concepts in simple terms with basic examples. Avoid complex terminology without explanation. ";
                } elseif ($level === 'intermediate') {
                    $prompt .= "The student has intermediate knowledge, so you can use standard Java terminology and provide more nuanced explanations. Still provide examples for new concepts. ";
                } elseif ($level === 'advanced') {
                    $prompt .= "The student has advanced knowledge, so you can use complex terminology and discuss advanced Java concepts. Focus on optimization, best practices, and edge cases. ";
                }
            }
            
            // Adjust response format based on preference
            if (isset($preferences['include_code_examples']) && $preferences['include_code_examples']) {
                $prompt .= "Always include relevant code examples to illustrate your explanations. Make sure your code examples are correct, complete, and follow Java best practices. ";
            }
            
            // Adjust explanation style
            if (isset($preferences['explanation_style'])) {
                $style = strtolower($preferences['explanation_style']);
                if ($style === 'analogy') {
                    $prompt .= "Use analogies to real-world situations to explain programming concepts. ";
                } elseif ($style === 'step_by_step') {
                    $prompt .= "Explain concepts in step-by-step detail, breaking down complex ideas into simpler components. ";
                } elseif ($style === 'visual') {
                    $prompt .= "Describe concepts using visual language and spatial metaphors when possible. ";
                }
            }
        }
        
        // Add final instruction about keeping answers focused and using the provided context
        $prompt .= "Your responses should specifically address the student's question while incorporating the lesson context provided above. If the student is struggling with a particular concept, provide additional explanation on that point. Ensure your response is directly relevant to the current module they're working on.";
        
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
                    Log::error('Unexpected Together AI response structure in code evaluation', [
                        'response' => $responseData
                    ]);
                    throw new \Exception('Unexpected API response structure');
                }
            } else {
                Log::error('Together AI error in code evaluation', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to get response from Together AI');
            }
        } catch (\Exception $e) {
            Log::error('Error in TutorService::evaluateCode', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Evaluate Java code with additional context and provide feedback
     */
    public function evaluateCodeWithContext($code, $context = [])
    {
        try {
            Log::info('TutorService::evaluateCodeWithContext - Starting code evaluation', [
                'code_length' => strlen($code),
                'context_keys' => array_keys($context)
            ]);
            
            // Construct a prompt asking for code feedback
            $prompt = "Please evaluate the following Java code and provide feedback. ";
            
            if (!empty($context['module'])) {
                $prompt .= "The code is related to the module: {$context['module']['title']}. ";
            }
            
            if (!empty($context['lesson_plan'])) {
                $prompt .= "It's part of the lesson plan: {$context['lesson_plan']['title']}. ";
            }
            
            if (!empty($context['exercise'])) {
                $prompt .= "The specific exercise is: {$context['exercise']['title']}. ";
                $prompt .= "\nExercise description: {$context['exercise']['description']}. ";
                
                if (!empty($context['exercise']['instructions'])) {
                    $prompt .= "\nInstructions: {$context['exercise']['instructions']}. ";
                }
                
                if (!empty($context['exercise']['expected_output'])) {
                    $prompt .= "\nExpected output: {$context['exercise']['expected_output']}. ";
                }
            }
            
            $prompt .= "\n\nHere's the code to evaluate:\n\n```java\n" . $code . "\n```\n\n";
            
            $prompt .= "Provide specific feedback on:
1. Correctness - Does it meet the requirements?
2. Code style and best practices
3. Efficiency and performance
4. Potential bugs or edge cases
5. Suggestions for improvement

Format your response as:
- Summary: A brief assessment of the code quality and correctness
- Strengths: What the code does well
- Areas for improvement: Specific issues or concerns
- Code suggestions: Examples of how to fix or improve the code
- Overall assessment: A final evaluation of the code quality (Excellent, Good, Needs improvement, or Poor)";
            
            $requestBody = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'top_p' => 0.8,
                'top_k' => 40,
                'max_tokens' => 1500,
            ];
            
            Log::info('TutorService::evaluateCodeWithContext - Sending request to Together AI', [
                'url' => $this->apiUrl . '/chat/completions',
                'request_structure' => [
                    'message_count' => count($requestBody['messages']),
                    'prompt_length' => strlen($prompt)
                ]
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/chat/completions', $requestBody);
            
            Log::info('TutorService::evaluateCodeWithContext - Received response', [
                'status' => $response->status(),
                'response_structure' => array_keys($response->json())
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $text = $responseData['choices'][0]['message']['content'];
                    Log::info('TutorService::evaluateCodeWithContext - Successfully extracted feedback', [
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
                Log::error('Together AI error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to get response from Together AI: ' . json_encode($response->json()));
            }
        } catch (\Exception $e) {
            Log::error('Error in TutorService::evaluateCodeWithContext', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 