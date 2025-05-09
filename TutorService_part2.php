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
            Log::error('Error in TutorService::getResponseWithContext', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide a meaningful response instead of failing
            return $this->generateFallbackResponse($question, $topic);
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
        
        // Check if history is null or not an array
        if (!is_array($history)) {
            Log::warning('TutorService::formatConversationHistory - Non-array history received, returning empty array', [
                'history_type' => gettype($history)
            ]);
            return [];
        }
        
        Log::info('TutorService::formatConversationHistory - Processing conversation history', [
            'history_count' => count($history),
            'first_item_type' => count($history) > 0 ? gettype($history[0]) : 'none'
        ]);
        
        $formatted = [];
        
        foreach ($history as $index => $message) {
            try {
                // Skip null messages
                if ($message === null) {
                    Log::warning('TutorService::formatConversationHistory - Skipping null message at index ' . $index);
                    continue;
                }
                
                // Convert to array if it's an object
                if (is_object($message)) {
                    $message = (array)$message;
                }
                
                // Skip if not an array at this point
                if (!is_array($message)) {
                    Log::warning('TutorService::formatConversationHistory - Skipping non-array/object message', [
                        'message_type' => gettype($message),
                        'index' => $index
                    ]);
                    continue;
                }
                
                // Initialize role and content variables
                $role = null;
                $content = null;
                
                // Process various message formats
                if (isset($message['role']) && is_string($message['role'])) {
                    // Standard format with 'role' field
                    // Map to Together AI expected roles: 'user' or 'assistant'
                    $role = strtolower($message['role']) === 'user' ? 'user' : 'assistant';
                    
                    // Get content from 'content' field
                    if (isset($message['content'])) {
                        $content = $message['content'];
                    }
                } elseif (isset($message['sender']) && is_string($message['sender'])) {
                    // Format with 'sender' field - map consistently
                    $senderLower = strtolower($message['sender']);
                    $role = ($senderLower === 'user') ? 'user' : 'assistant';
                    
                    // Try various content field names in order of preference
                    if (isset($message['message']) && !empty($message['message'])) {
                        $content = $message['message'];
                    } elseif (isset($message['content']) && !empty($message['content'])) {
                        $content = $message['content'];
                    } elseif (isset($message['text']) && !empty($message['text'])) {
                        $content = $message['text'];
                    }
                } else {
                    // Check for other formats or infer from message structure
                    if (isset($message['user']) && is_string($message['user'])) {
                        $role = 'user';
                        $content = $message['user'];
                    } elseif (isset($message['assistant']) || isset($message['ai']) || isset($message['bot'])) {
                        $role = 'assistant';
                        $content = $message['assistant'] ?? $message['ai'] ?? $message['bot'] ?? '';
                    } elseif (isset($message['question'])) {
                        $role = 'user';
                        $content = $message['question'];
                    } elseif (isset($message['answer']) || isset($message['response'])) {
                        $role = 'assistant';
                        $content = $message['answer'] ?? $message['response'];
                    } else {
                        // If we still can't determine the format, try to infer from keys
                        $keys = array_keys($message);
                        $possibleUserKeys = ['question', 'query', 'input', 'user_message'];
                        $possibleAssistantKeys = ['answer', 'response', 'reply', 'assistant_message', 'completion'];
                        
                        $foundUserKey = null;
                        foreach ($possibleUserKeys as $key) {
                            if (in_array($key, $keys) && !empty($message[$key])) {
                                $foundUserKey = $key;
                                break;
                            }
                        }
                        
                        $foundAssistantKey = null;
                        foreach ($possibleAssistantKeys as $key) {
                            if (in_array($key, $keys) && !empty($message[$key])) {
                                $foundAssistantKey = $key;
                                break;
                            }
                        }
                        
                        if ($foundUserKey) {
                            $role = 'user';
                            $content = $message[$foundUserKey];
                        } elseif ($foundAssistantKey) {
                            $role = 'assistant';
                            $content = $message[$foundAssistantKey];
                        } else {
                            // If we still can't determine, log and skip
                            Log::warning('TutorService::formatConversationHistory - Unknown message format', [
                                'message_keys' => $keys,
                                'index' => $index
                            ]);
                            continue;
                        }
                    }
                }
                
                // Validate content and convert to string if needed
                if ($content === null) {
                    Log::warning('TutorService::formatConversationHistory - Null content in message, skipping', [
                        'index' => $index,
                        'role' => $role
                    ]);
                    continue;
                }
                
                if (!is_string($content)) {
                    if (is_array($content) || is_object($content)) {
                        // Try to JSON encode complex content
                        $content = json_encode($content);
                        if ($content === false) {
                            Log::warning('TutorService::formatConversationHistory - Failed to JSON encode content, skipping message', [
                                'index' => $index
                            ]);
                            continue;
                        }
                    } else {
                        // Convert to string
                        $content = (string)$content;
                    }
                }
                
                // Ensure content is not empty
                if (trim($content) === '') {
                    Log::warning('TutorService::formatConversationHistory - Empty content in message, skipping', [
                        'index' => $index,
                        'role' => $role
                    ]);
                    continue;
                }
                
                // Add to formatted messages
                $formatted[] = [
                    'role' => $role,
                    'content' => $content
                ];
                
                Log::debug('TutorService::formatConversationHistory - Successfully processed message', [
                    'index' => $index,
                    'role' => $role,
                    'content_length' => strlen($content)
                ]);
            } catch (\Exception $e) {
                Log::warning('TutorService::formatConversationHistory - Error processing message', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
                // Continue to next message
                continue;
            }
        }
        
        Log::info('TutorService::formatConversationHistory - Completed formatting', [
            'input_count' => count($history),
            'output_count' => count($formatted)
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
        $prompt = "You are an AI Java programming tutor specializing in teaching specific lesson content. ";
        
        // Add topic information if provided
        if ($topic) {
            $prompt .= "The current topic we're discussing is: $topic. ";
        }
        
        // Add lesson context if provided
        if (!empty($context)) {
            $prompt .= "\n\n### LESSON CONTEXT ###\n";
            
            // Process lesson_context from frontend if available
            if (isset($context['lesson_context']) && !empty($context['lesson_context'])) {
                $prompt .= $context['lesson_context'] . "\n";
            } else {
                // Use individual context fields if lesson_context not available
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
            }
            
            $prompt .= "\n### END LESSON CONTEXT ###\n\n";
        }
        
        // Add instruction about response style with focus on current lesson
        $prompt .= "IMPORTANT GUIDELINES:
1. Focus your response STRICTLY on the content and concepts from the current lesson module
2. If the user asks about topics outside the current lesson, briefly acknowledge their question but gently redirect them to the lesson material
3. Provide code examples that directly demonstrate concepts from this specific module
4. Ensure your explanations use terminology consistent with the lesson content
5. Keep responses focused on the specific learning objectives of this module
6. All code examples should be relevant to the current lesson content
7. Do not introduce advanced concepts that are not part of the current lesson
";
        
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
        $prompt .= "Your responses must focus specifically on the current lesson content and module. If the student asks about topics outside the current lesson, briefly acknowledge their interest but redirect them to focus on mastering the current module first.";
        
        return $prompt;
    }

    /**
     * Determine max tokens based on user preferences
     */
    private function getMaxTokensBasedOnPreferences($preferences)
    {
        // Default to medium length if preferences is not an array
        if (!is_array($preferences)) {
            return 800;
        }
        
        $maxTokens = 800; // Default to medium length
        
        if (isset($preferences['responseLength'])) {
            if (is_string($preferences['responseLength'])) {
                switch (strtolower($preferences['responseLength'])) {
                    case 'brief':
                    case 'short':
                        $maxTokens = 300;
                        break;
                    case 'medium':
                    case 'moderate':
                        $maxTokens = 800;
                        break;
                    case 'detailed':
                    case 'long':
                    case 'comprehensive':
                        $maxTokens = 1500;
                        break;
                    default:
                        $maxTokens = 800; // Default to medium for unrecognized strings
                }
            } elseif (is_numeric($preferences['responseLength'])) {
                // If a numeric value is provided, use it directly (with bounds)
                $maxTokens = intval($preferences['responseLength']);
                
                // Enforce min/max bounds
                $maxTokens = max(100, min(2000, $maxTokens));
            }
        }
        
        // Ensure maxTokens is always an integer
        return intval($maxTokens);
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
            
            // Check if we're dealing with a multi-file project
            if (!empty($context['project_files'])) {
                $prompt .= "\n\nThis is a multi-file Java project. Here are all the files:\n\n";
                
                foreach ($context['project_files'] as $file) {
                    $prompt .= "File: {$file['path']}\n```java\n{$file['content']}\n```\n\n";
                }
                
                if (!empty($context['main_class'])) {
                    $prompt .= "The main class is: {$context['main_class']}\n\n";
                }
            } else {
                // Single file code evaluation
                $prompt .= "\n\nHere's the code to evaluate:\n\n```java\n" . $code . "\n```\n\n";
            }
            
            // Execution results if available
            if (!empty($context['stdout']) || !empty($context['stderr'])) {
                $prompt .= "Execution results:\n";
                if (!empty($context['stdout'])) {
                    $prompt .= "Standard output:\n```\n{$context['stdout']}\n```\n\n";
                }
                if (!empty($context['stderr'])) {
                    $prompt .= "Standard error:\n```\n{$context['stderr']}\n```\n\n";
                }
            }
            
            $prompt .= "Provide specific feedback on:
1. Correctness - Does it meet the requirements?
2. Code style and best practices
3. Efficiency and performance
4. Potential bugs or edge cases
5. Suggestions for improvement";

            if (!empty($context['project_files'])) {
                $prompt .= "\n6. Project organization and structure
7. Interactions between classes and files";
            }
            
            $prompt .= "\n\nFormat your response as:
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

    /**
     * Generate a fallback response when the AI service is unavailable
     */
    private function generateFallbackResponse($question, $topic = null)
    {
        Log::info('Generating fallback response for user question', [
            'question' => $question,
            'topic' => $topic
        ]);
        
        $topicPhrase = $topic ? " about " . $topic : "";
        
        // Basic response patterns based on question type
        if (preg_match('/\b(?:hi|hello|hey|greetings)\b/i', $question)) {
            return "Hello! I'm your programming tutor. I'm currently experiencing connectivity issues with my knowledge services. Please try again in a few minutes.";
        }
        
        if (preg_match('/\bexplain\b/i', $question)) {
            return "I'd be happy to explain that{$topicPhrase}. However, my knowledge services are temporarily unavailable. This is a temporary issue. Please try again in a few minutes.";
        }
        
        if (preg_match('/\b(?:how|what|why|when|where)\b/i', $question)) {
            return "That's a good question{$topicPhrase}. I'm having trouble connecting to my knowledge services at the moment. Please try again in a few minutes.";
        }
        
        if (preg_match('/\bcode\b/i', $question) || preg_match('/\bexample\b/i', $question)) {
            return "I'd love to provide a code example{$topicPhrase}, but I'm currently experiencing technical difficulties connecting to my knowledge base. Please try again in a few minutes.";
        }
        
        if (preg_match('/\b(?:error|bug|fix|problem|issue)\b/i', $question)) {
            return "I'd like to help troubleshoot that{$topicPhrase}, but I'm currently experiencing connectivity issues. Please try again shortly, or you might try describing the error in different terms when I'm back online.";
        }
        
        if (preg_match('/\b(?:compare|versus|vs|difference)\b/i', $question)) {
            return "I'd be happy to compare those concepts{$topicPhrase} once my knowledge services are back online. Please try again in a few minutes.";
        }
        
        if (preg_match('/\b(?:create|make|build|implement)\b/i', $question)) {
            return "I'd love to help you build that{$topicPhrase}, but I'm temporarily disconnected from my knowledge base. Please try again in a few minutes.";
        }
        
        if (preg_match('/\b(?:best practice|should I|recommend)\b/i', $question)) {
            return "I'd be happy to recommend best practices{$topicPhrase} once my knowledge services are restored. Please try again shortly.";
        }
        
        // More specific responses based on topic if provided
        if ($topic) {
            if (stripos($topic, 'java') !== false) {
                return "I'd love to help with your Java question, but I'm experiencing connectivity issues with my knowledge services. Please try again in a few minutes.";
            }
            
            if (stripos($topic, 'algorithm') !== false || stripos($topic, 'data structure') !== false) {
                return "I'd be happy to discuss that algorithm or data structure once my knowledge services are back online. Please try again shortly.";
            }
            
            if (stripos($topic, 'object') !== false && stripos($topic, 'oriented') !== false) {
                return "I'd love to explore that object-oriented programming concept with you once my knowledge services are restored. Please try again in a few minutes.";
            }
        }
        
        // Default fallback response with more helpful context
        return "I'm currently experiencing temporary connectivity issues with my knowledge services and can't provide a complete answer at this moment. This is typically resolved within a few minutes. Please try asking your question again shortly. In the meantime, you might try refreshing the page or checking out the available lesson plans and code practice sections.";
    }
} 
