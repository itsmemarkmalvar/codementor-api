<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class JavaExecutionService
{
    protected $tempDir;
    protected $maxExecutionTime;
    protected $memoryLimit;
    
    public function __construct()
    {
        $this->tempDir = env('JAVA_TEMP_DIR', storage_path('app/java-execution'));
        $this->maxExecutionTime = env('JAVA_MAX_EXECUTION_TIME', 5); // seconds
        $this->memoryLimit = env('JAVA_MEMORY_LIMIT', 128); // MB
        
        // Ensure the temp directory exists
        if (!File::exists($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }
    }
    
    /**
     * Execute Java code with test cases for practice problems
     *
     * @param string $code The Java code to execute
     * @param string|null $input Optional input to provide to the program
     * @param array|null $testCases Array of test cases to run
     * @return array The execution result with test case results
     */
    public function execute(string $code, ?string $input = null, ?array $testCases = null): array
    {
        try {
            // If no test cases provided, just execute the code normally
            if (!$testCases || empty($testCases)) {
                return $this->executeJavaCode($code, $input);
            }

            // Choose execution provider
            $provider = strtolower((string) env('CODE_EXECUTION_PROVIDER', 'local'));
            
            // Judge0 path: compile+run remotely per test case via Judge0 API
            if ($provider === 'judge0') {
                $testResults = [];
                $allPassed = true;
                foreach ($testCases as $index => $testCase) {
                    $testInput = $testCase['input'] ?? '';
                    $expectedOutput = (string) ($testCase['expected_output'] ?? '');
                    $result = $this->executeViaJudge0Single($code, $testInput);
                    $actualOutput = (string) ($result['stdout'] ?? '');

                    // Normalize line endings and trim each line for comparison
                    $norm = function (string $s) {
                        $s = str_replace(["\r\n", "\r"], "\n", $s);
                        $lines = array_map(fn($l) => rtrim($l, " \t"), explode("\n", $s));
                        return trim(implode("\n", $lines));
                    };
                    $passed = ($norm($actualOutput) === $norm($expectedOutput)) && ($result['success'] ?? false);
                    $testResults[] = [
                        'passed' => $passed,
                        'expected' => $expectedOutput,
                        'actual' => $actualOutput,
                        'error' => ($result['stderr'] ?? null)
                    ];
                    if (!$passed) { $allPassed = false; }
                }
                return [
                    'success' => $allPassed,
                    'test_results' => $testResults,
                    'execution_time' => 0,
                ];
            }

            // Piston path: compile+run remotely per test case (simple, robust)
            if ($provider === 'piston') {
                $testResults = [];
                $allPassed = true;
                foreach ($testCases as $index => $testCase) {
                    $testInput = $testCase['input'] ?? '';
                    $expectedOutput = (string) ($testCase['expected_output'] ?? '');
                    $result = $this->executeViaPistonSingle($code, $testInput);
                    $actualOutput = (string) ($result['stdout'] ?? '');

                    // Normalize line endings and trim each line for comparison
                    $norm = function (string $s) {
                        $s = str_replace(["\r\n", "\r"], "\n", $s);
                        $lines = array_map(fn($l) => rtrim($l, " \t"), explode("\n", $s));
                        return trim(implode("\n", $lines));
                    };
                    $passed = ($norm($actualOutput) === $norm($expectedOutput)) && ($result['success'] ?? false);
                    $testResults[] = [
                        'passed' => $passed,
                        'expected' => $expectedOutput,
                        'actual' => $actualOutput,
                        'error' => ($result['stderr'] ?? null)
                    ];
                    if (!$passed) { $allPassed = false; }
                }
                return [
                    'success' => $allPassed,
                    'test_results' => $testResults,
                    'execution_time' => 0,
                ];
            }

            // Local path: compile once and run tests
            // Extract the class name from the code
            $className = $this->extractClassName($code);
            if (!$className) {
                return [
                    'error' => 'Could not determine class name from code. Make sure you have a public class declaration.',
                    'execution_time' => 0,
                ];
            }
            $executionId = Str::uuid()->toString();
            $executionDir = $this->tempDir . '/' . $executionId;
            File::makeDirectory($executionDir, 0755, true);
            $filePath = $executionDir . '/' . $className . '.java';
            File::put($filePath, $code);
            $compileResult = $this->compileJavaCode($filePath, $executionDir);
            if (!$compileResult['success']) {
                $this->cleanUp($executionDir);
                return [
                    'error' => $compileResult['stderr'],
                    'execution_time' => 0,
                ];
            }
            $testResults = [];
            $allPassed = true;
            foreach ($testCases as $index => $testCase) {
                $testInput = $testCase['input'] ?? '';
                $expectedOutput = (string) ($testCase['expected_output'] ?? '');
                $inputFile = null;
                if (!empty($testInput)) {
                    $inputFile = $executionDir . '/test_input_' . $index . '.txt';
                    File::put($inputFile, $testInput);
                }
                $result = $this->runJavaCode($className, $executionDir, $inputFile);
                $actualOutput = (string) ($result['stdout'] ?? '');
                $norm = function (string $s) {
                    $s = str_replace(["\r\n", "\r"], "\n", $s);
                    $lines = array_map(fn($l) => rtrim($l, " \t"), explode("\n", $s));
                    return trim(implode("\n", $lines));
                };
                $passed = ($norm($actualOutput) === $norm($expectedOutput)) && $result['success'];
                $testResults[] = [
                    'passed' => $passed,
                    'expected' => $expectedOutput,
                    'actual' => $actualOutput,
                    'error' => $result['stderr'] ?? null
                ];
                if (!$passed) { $allPassed = false; }
                if ($inputFile && File::exists($inputFile)) { File::delete($inputFile); }
            }
            $this->cleanUp($executionDir);
            return [
                'success' => $allPassed,
                'test_results' => $testResults,
                'execution_time' => 0,
            ];
            
        } catch (Exception $e) {
            Log::error('Java execution error: ' . $e->getMessage());
            return [
                'error' => 'An error occurred during code execution: ' . $e->getMessage(),
                'execution_time' => 0,
            ];
        }
    }

    /**
     * Execute Java code and return the result
     *
     * @param string $code The Java code to execute
     * @param string|null $input Optional input to provide to the program
     * @return array The execution result containing stdout, stderr, and execution info
     */
    public function executeJavaCode(string $code, ?string $input = null): array
    {
        try {
            $provider = strtolower((string) env('CODE_EXECUTION_PROVIDER', 'local'));

            if ($provider === 'judge0') {
                return $this->executeViaJudge0Single($code, $input);
            }

            if ($provider === 'piston') {
                return $this->executeViaPistonSingle($code, $input);
            }
            // Extract the class name from the code
            $className = $this->extractClassName($code);
            
            if (!$className) {
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => 'Could not determine class name from code. Make sure you have a public class declaration.',
                    'executionTime' => 0,
                ];
            }
            
            // Create a unique directory for this execution
            $executionId = Str::uuid()->toString();
            $executionDir = $this->tempDir . '/' . $executionId;
            File::makeDirectory($executionDir, 0755, true);
            
            // Write the code to a file
            $filePath = $executionDir . '/' . $className . '.java';
            File::put($filePath, $code);
            
            // Write input to a file if provided
            $inputFile = null;
            if ($input) {
                $inputFile = $executionDir . '/input.txt';
                File::put($inputFile, $input);
            }
            
            // Compile the code
            $compileResult = $this->compileJavaCode($filePath, $executionDir);
            
            if (!$compileResult['success']) {
                $this->cleanUp($executionDir);
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => $compileResult['stderr'],
                    'executionTime' => 0,
                ];
            }
            
            // Execute the compiled code
            $executeResult = $this->runJavaCode($className, $executionDir, $inputFile);
            
            // Clean up temporary files
            $this->cleanUp($executionDir);
            
            return $executeResult;
            
        } catch (Exception $e) {
            Log::error('Java execution error: ' . $e->getMessage());
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'An error occurred during code execution: ' . $e->getMessage(),
                'executionTime' => 0,
            ];
        }
    }

    /**
     * Execute a Java project with multiple files and return the result
     *
     * @param array $files Array of file objects with 'path' and 'content' keys
     * @param string $mainClass The main class to execute (e.g. 'com.example.Main')
     * @param string|null $input Optional input to provide to the program
     * @return array The execution result containing stdout, stderr, and execution info
     */
    public function executeJavaProject(array $files, string $mainClass, ?string $input = null): array
    {
        try {
            $provider = strtolower((string) env('CODE_EXECUTION_PROVIDER', 'local'));

            if ($provider === 'judge0') {
                return $this->executeViaJudge0Project($files, $mainClass, $input);
            }

            if ($provider === 'piston') {
                return $this->executeViaPistonProject($files, $mainClass, $input);
            }
            // Extract the simple class name from the fully qualified name
            $mainClassSimpleName = $this->extractSimpleClassName($mainClass);
            
            // Create a unique directory for this execution
            $executionId = Str::uuid()->toString();
            $executionDir = $this->tempDir . '/' . $executionId;
            File::makeDirectory($executionDir, 0755, true);

            // Map to store file paths => compiled class paths (for validation)
            $javaFiles = [];
            
            // Process and save all files
            foreach ($files as $file) {
                if (!isset($file['path']) || !isset($file['content'])) {
                    continue; // Skip invalid files
                }
                
                // Skip non-Java files
                if (!Str::endsWith($file['path'], '.java')) {
                    continue;
                }
                
                // Normalize path and create necessary directories
                $relativePath = ltrim($file['path'], '/');
                $fullPath = $executionDir . '/' . $relativePath;
                $directory = dirname($fullPath);
                
                if (!File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                // Write the file content
                File::put($fullPath, $file['content']);
                $javaFiles[] = $fullPath;
            }
            
            if (empty($javaFiles)) {
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => 'No Java files found in the project.',
                    'executionTime' => 0,
                ];
            }
            
            // Write input to a file if provided
            $inputFile = null;
            if ($input) {
                $inputFile = $executionDir . '/input.txt';
                File::put($inputFile, $input);
            }
            
            // Compile all Java files together
            $compileResult = $this->compileJavaProject($javaFiles, $executionDir);
            
            if (!$compileResult['success']) {
                $this->cleanUp($executionDir);
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => $compileResult['stderr'],
                    'executionTime' => 0,
                ];
            }
            
            // Execute the compiled project
            $executeResult = $this->runJavaProject($mainClass, $executionDir, $inputFile);
            
            // Clean up temporary files
            $this->cleanUp($executionDir);
            
            return $executeResult;
            
        } catch (Exception $e) {
            Log::error('Java project execution error: ' . $e->getMessage());
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'An error occurred during project execution: ' . $e->getMessage(),
                'executionTime' => 0,
            ];
        }
    }
    
    /**
     * Extract the class name from Java code
     *
     * @param string $code
     * @return string|null
     */
    protected function extractClassName(string $code): ?string
    {
        // Simple regex to extract the class name
        preg_match('/public\s+class\s+([a-zA-Z0-9_]+)/', $code, $matches);
        
        return $matches[1] ?? null;
    }

    /**
     * Extract the simple class name from a fully qualified class name
     * 
     * @param string $fullyQualifiedName
     * @return string
     */
    protected function extractSimpleClassName(string $fullyQualifiedName): string
    {
        $parts = explode('.', $fullyQualifiedName);
        return end($parts);
    }
    
    /**
     * Compile Java code
     *
     * @param string $filePath Path to the Java file
     * @param string $outputDir Directory for compiled output
     * @return array Compilation result
     */
    protected function compileJavaCode(string $filePath, string $outputDir): array
    {
        $command = sprintf('cd %s && javac %s 2>&1', escapeshellarg($outputDir), escapeshellarg(basename($filePath)));
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        $stderr = implode("\n", $output);
        
        return [
            'success' => $returnCode === 0,
            'stderr' => $stderr,
        ];
    }

    /**
     * Compile multiple Java files as a project
     *
     * @param array $filePaths Array of paths to Java files
     * @param string $outputDir Directory for compiled output
     * @return array Compilation result
     */
    protected function compileJavaProject(array $filePaths, string $outputDir): array
    {
        // Convert absolute paths to relative paths from the output directory
        $relativeFilePaths = [];
        foreach ($filePaths as $path) {
            $relativePath = str_replace($outputDir . '/', '', $path);
            $relativeFilePaths[] = $relativePath;
        }
        
        // Join all file paths with space for the javac command
        $filesArg = implode(' ', array_map('escapeshellarg', $relativeFilePaths));
        
        // Include classpath as current directory
        $command = sprintf('cd %s && javac -cp . %s 2>&1', escapeshellarg($outputDir), $filesArg);
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        $stderr = implode("\n", $output);
        
        return [
            'success' => $returnCode === 0,
            'stderr' => $stderr,
        ];
    }
    
    /**
     * Run compiled Java code
     *
     * @param string $className Name of the Java class to run
     * @param string $executionDir Directory containing the compiled class
     * @param string|null $inputFile Path to input file if any
     * @return array Execution result
     */
    protected function runJavaCode(string $className, string $executionDir, ?string $inputFile): array
    {
        // Determine operating system
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Build the command with resource limits
        if ($isWindows) {
            // Windows doesn't have timeout command like Linux/Unix
            // Use a different approach for Windows
            $command = sprintf(
                'cd %s && java -Xmx%dM %s',
                escapeshellarg($executionDir),
                $this->memoryLimit,
                escapeshellarg($className)
            );
        } else {
            // Unix/Linux command
            $command = sprintf(
                'cd %s && timeout %d java -Xmx%dM %s',
                escapeshellarg($executionDir),
                $this->maxExecutionTime,
                $this->memoryLimit,
                escapeshellarg($className)
            );
        }
        
        // Add input redirection if input file exists
        if ($inputFile && File::exists($inputFile)) {
            $command .= ' < ' . escapeshellarg($inputFile);
        }
        
        // Redirect stderr to a separate file
        $stderrFile = $executionDir . '/stderr.txt';
        $command .= ' 2> ' . escapeshellarg($stderrFile);
        
        // Measure execution time
        $startTime = microtime(true);
        
        // Execute the command
        $stdout = shell_exec($command);
        
        $executionTime = microtime(true) - $startTime;
        
        // Read stderr if exists
        $stderr = '';
        if (File::exists($stderrFile)) {
            $stderr = File::get($stderrFile);
        }
        
        // Check for timeout manually on Windows
        if ($isWindows && $executionTime >= $this->maxExecutionTime) {
            // Force terminate if still running (Windows specific)
            // This is a simplified approach - a more robust solution would use process management
            shell_exec("taskkill /F /IM java.exe 2>NUL");
            $stderr = 'Execution timed out. Your code took too long to run.';
        }
        
        return [
            'success' => empty($stderr),
            'stdout' => $stdout ?: '',
            'stderr' => $stderr,
            'executionTime' => round($executionTime, 3),
        ];
    }

    /**
     * Execute single-file Java via Piston API
     *
     * @param string $code
     * @param string|null $input
     * @return array
     */
    protected function executeViaPistonSingle(string $code, ?string $input = null): array
    {
        try {
            $className = $this->extractClassName($code) ?? 'Main';
            $javaVersion = env('PISTON_JAVA_VERSION', '21.0.0');
            $baseUrl = rtrim((string) env('PISTON_URL', 'https://emkc.org/api/v2/piston'), '/');
            $compileTimeoutMs = (int) (env('PISTON_COMPILE_TIMEOUT_MS', 10000));
            $runTimeoutMs = (int) (env('PISTON_RUN_TIMEOUT_MS', $this->maxExecutionTime * 1000));
            $memoryLimit = (int) (env('PISTON_RUN_MEMORY_LIMIT', 256)); // MB

            // Piston runs Java by executing class Main by default. If the user's
            // public class has a different name, we rewrite the declaration so
            // the compiled file contains a public class Main with the same body.
            $pistonCode = $code;
            if (strcasecmp($className, 'Main') !== 0) {
                $replaced = preg_replace('/public\s+class\s+' . preg_quote($className, '/') . '\b/', 'public class Main', $pistonCode, 1);
                if (is_string($replaced) && $replaced !== $pistonCode) {
                    $pistonCode = $replaced;
                } else {
                    // If no public class declaration matched, wrap code into Main
                    $pistonCode = "public class Main {\n" . $code . "\n}";
                }
            }

            $payload = [
                'language' => 'java',
                'version' => $javaVersion,
                'files' => [
                    [
                        'name' => 'Main.java',
                        'content' => $pistonCode,
                    ],
                ],
                'stdin' => $input ?? '',
                // For Java on Piston, pass the main class to the run step
                'args' => ['Main'],
                'compile_timeout' => $compileTimeoutMs,
                'run_timeout' => $runTimeoutMs,
                'compile_memory_limit' => -1,
                'run_memory_limit' => $memoryLimit,
            ];
            // Build a version negotiation list: preferred env version first, then other Java versions from runtimes
            $versionsToTry = [$javaVersion];
            try {
                $runtimes = Http::timeout(6)->acceptJson()->get($baseUrl . '/runtimes')->json();
                $available = [];
                if (is_array($runtimes)) {
                    foreach ($runtimes as $rt) {
                        if (is_array($rt) && (strtolower($rt['language'] ?? '') === 'java') && !empty($rt['version'])) {
                            $available[] = (string) $rt['version'];
                        }
                    }
                }
                $available = array_values(array_unique($available));
                // Prefer majors 21, 17, 19, 11, 8 in that order
                $pref = ['21','17','19','11','8'];
                usort($available, function ($a, $b) use ($pref) {
                    $ma = strtok($a, '.');
                    $mb = strtok($b, '.');
                    $ia = array_search($ma, $pref);
                    $ib = array_search($mb, $pref);
                    $ia = $ia === false ? 999 : $ia;
                    $ib = $ib === false ? 999 : $ib;
                    if ($ia === $ib) { return version_compare($b, $a); } // newer first
                    return $ia <=> $ib;
                });
                foreach ($available as $v) {
                    if (!in_array($v, $versionsToTry, true)) { $versionsToTry[] = $v; }
                }
            } catch (\Throwable $e) {
                // ignore; we'll try env version only
            }
            // Final fallback: try without explicit version
            $versionsToTry[] = null;

            $client = Http::timeout(max(10, (int) ceil(($compileTimeoutMs + $runTimeoutMs) / 1000) + 5))
                ->acceptJson()->asJson();

            foreach ($versionsToTry as $ver) {
                if ($ver === null) { unset($payload['version']); } else { $payload['version'] = $ver; }
                $response = $client->post($baseUrl . '/execute', $payload);

                if (filter_var(env('DEBUG_EXEC_LOG', false), FILTER_VALIDATE_BOOLEAN)) {
                    \Log::debug('[EXEC] Piston single raw response', [
                        'try_version' => $ver,
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);
                }

                if (!$response->ok()) { continue; }

                $data = $response->json();
                $compile = $data['compile'] ?? null;
                $run     = $data['run'] ?? null;
                $compileErr = is_array($compile) ? ($compile['stderr'] ?? ($compile['output'] ?? '')) : '';
                $runErr     = is_array($run) ? ($run['stderr'] ?? ($run['output'] ?? '')) : '';
                $runOut     = is_array($run) ? ($run['stdout'] ?? ($run['output'] ?? '')) : '';
                $stderr     = trim($compileErr . (strlen($compileErr) && strlen($runErr) ? "\n" : '') . $runErr);
                $signal     = is_array($run) ? ($run['signal'] ?? null) : null;

                // If segfaulted, try next version
                if ($signal === 'SIGSEGV') { continue; }

                return [
                    'success' => empty($stderr) && (!isset($run['code']) || (($run['code'] ?? 0) === 0)),
                    'stdout'  => (string) $runOut,
                    'stderr'  => (string) $stderr,
                    'executionTime' => 0,
                ];
            }

            return [
                'success' => false,
                'stdout'  => '',
                'stderr'  => 'Remote executor error: No valid Java runtime available (segfaults)',
                'executionTime' => 0,
            ];
        } catch (Exception $e) {
            Log::error('Piston single-file execution error: ' . $e->getMessage());
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'Remote executor error: ' . $e->getMessage(),
                'executionTime' => 0,
            ];
        }
    }

    /**
     * Execute multi-file Java project via Piston API
     *
     * @param array $files
     * @param string $mainClass Fully qualified main class (e.g., com.example.Main)
     * @param string|null $input
     * @return array
     */
    protected function executeViaPistonProject(array $files, string $mainClass, ?string $input = null): array
    {
        try {
            $debug = filter_var(env('DEBUG_EXEC_LOG', false), FILTER_VALIDATE_BOOLEAN);
            $javaVersion = env('PISTON_JAVA_VERSION', '21.0.0');
            $baseUrl = rtrim((string) env('PISTON_URL', 'https://emkc.org/api/v2/piston'), '/');
            $compileTimeoutMs = (int) (env('PISTON_COMPILE_TIMEOUT_MS', 15000));
            $runTimeoutMs = (int) (env('PISTON_RUN_TIMEOUT_MS', $this->maxExecutionTime * 1000));
            $memoryLimit = (int) (env('PISTON_RUN_MEMORY_LIMIT', 256));

            $pistonFiles = [];
            foreach ($files as $file) {
                if (!isset($file['path']) || !isset($file['content'])) {
                    continue;
                }
                // Ensure .java filenames
                $name = ltrim($file['path'], '/');
                if (!Str::endsWith($name, '.java')) {
                    $name .= '.java';
                }
                $pistonFiles[] = [
                    'name' => $name,
                    'content' => (string) $file['content'],
                ];
            }

            // Add a small launcher if mainClass is provided and Piston needs args
            $args = ['-cp', '.', $mainClass];

            $payload = [
                'language' => 'java',
                'version' => $javaVersion,
                'files' => $pistonFiles,
                'stdin' => $input ?? '',
                // Run compiled project using the specified main class
                'args' => $args,
                'compile_timeout' => $compileTimeoutMs,
                'run_timeout' => $runTimeoutMs,
                'compile_memory_limit' => -1,
                'run_memory_limit' => $memoryLimit,
            ];

            $client = Http::timeout(max(12, (int) ceil(($compileTimeoutMs + $runTimeoutMs) / 1000) + 5))
                ->acceptJson()
                ->asJson();
            $response = $client->post($baseUrl . '/execute', $payload);

            if ($response->status() === 400) {
                try {
                    $runtimes = Http::timeout(6)->acceptJson()->get($baseUrl . '/runtimes')->json();
                    if (is_array($runtimes)) {
                        $java = collect($runtimes)->first(function ($r) {
                            return is_array($r) && isset($r['language']) && strtolower($r['language']) === 'java';
                        });
                        if (is_array($java) && !empty($java['version'])) {
                            $payload['version'] = (string) $java['version'];
                            $response = $client->post($baseUrl . '/execute', $payload);
                        } else {
                            unset($payload['version']);
                            $response = $client->post($baseUrl . '/execute', $payload);
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            if (!$response->ok()) {
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => 'Remote executor error: HTTP ' . $response->status(),
                    'executionTime' => 0,
                ];
            }

            $data = $response->json();
            $compile = $data['compile'] ?? null;
            $run = $data['run'] ?? null;

            $compileErr = is_array($compile) ? ($compile['stderr'] ?? ($compile['output'] ?? '')) : '';
            $runErr = is_array($run) ? ($run['stderr'] ?? ($run['output'] ?? '')) : '';
            $runOut = is_array($run) ? ($run['stdout'] ?? ($run['output'] ?? '')) : '';
            $stderr = trim($compileErr . (strlen($compileErr) && strlen($runErr) ? "\n" : '') . $runErr);

            return [
                'success' => empty($stderr) && (($run['code'] ?? 0) === 0),
                'stdout' => (string) $runOut,
                'stderr' => (string) $stderr,
                'executionTime' => 0,
            ];
        } catch (Exception $e) {
            Log::error('Piston project execution error: ' . $e->getMessage());
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'Remote executor error: ' . $e->getMessage(),
                'executionTime' => 0,
            ];
        }
    }

    /**
     * Execute single-file Java via Judge0 API (RapidAPI Judge0 CE).
     *
     * @param string $code
     * @param string|null $input
     * @return array
     */
    protected function executeViaJudge0Single(string $code, ?string $input = null): array
    {
        try {
            $apiUrl = rtrim((string) env('JUDGE0_API_URL', ''), '/');
            $apiHost = (string) env('JUDGE0_API_HOST', '');
            $apiKey  = (string) env('JUDGE0_API_KEY', '');
            $langId  = (int) env('JUDGE0_LANGUAGE_ID_JAVA', 62);

            if (empty($apiUrl) || empty($apiHost) || empty($apiKey)) {
                throw new Exception('Judge0 API configuration missing');
            }

            // Ensure public class is Main for Judge0 default run
            $className = $this->extractClassName($code) ?? 'Main';
            $pistonStyle = $code;
            if (strcasecmp($className, 'Main') !== 0) {
                $replaced = preg_replace('/public\s+class\s+' . preg_quote($className, '/') . '\b/', 'public class Main', $pistonStyle, 1);
                if (is_string($replaced) && $replaced !== $pistonStyle) {
                    $pistonStyle = $replaced;
                } else {
                    $pistonStyle = "public class Main {\n" . $code . "\n}";
                }
            }

            $payload = [
                'language_id' => $langId,
                'source_code' => base64_encode($pistonStyle),
                'stdin' => base64_encode($input ?? ''),
            ];

            $endpoint = $apiUrl . '/submissions?base64_encoded=true&wait=true';
            $t0 = microtime(true);
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-RapidAPI-Host' => $apiHost,
                    'X-RapidAPI-Key'  => $apiKey,
                    'Content-Type'    => 'application/json',
                ])->post($endpoint, $payload);

            $ms = (int) round((microtime(true) - $t0) * 1000);
            if (!$response->ok()) {
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => 'Judge0 error: HTTP ' . $response->status(),
                    'executionTime' => $ms,
                ];
            }
            $data = $response->json() ?: [];
            $statusId = (int) ($data['status']['id'] ?? 0); // 3 = Accepted
            $stdout = isset($data['stdout']) ? base64_decode($data['stdout']) : '';
            $stderr = '';
            if (!empty($data['stderr'])) { $stderr = base64_decode($data['stderr']); }
            elseif (!empty($data['compile_output'])) { $stderr = base64_decode($data['compile_output']); }
            elseif (!empty($data['message'])) { $stderr = $data['message']; }

            return [
                'success' => ($statusId === 3) && empty($stderr),
                'stdout' => (string) $stdout,
                'stderr' => (string) $stderr,
                'executionTime' => $ms,
            ];
        } catch (Exception $e) {
            Log::error('Judge0 single-file execution error: ' . $e->getMessage());
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'Remote executor error (Judge0): ' . $e->getMessage(),
                'executionTime' => 0,
            ];
        }
    }

    /**
     * Execute multi-file Java project via Judge0 (best-effort using additional_files).
     * Falls back with a clear error if unsupported by the configured endpoint.
     */
    protected function executeViaJudge0Project(array $files, string $mainClass, ?string $input = null): array
    {
        try {
            $apiUrl = rtrim((string) env('JUDGE0_API_URL', ''), '/');
            $apiHost = (string) env('JUDGE0_API_HOST', '');
            $apiKey  = (string) env('JUDGE0_API_KEY', '');
            $langId  = (int) env('JUDGE0_LANGUAGE_ID_JAVA', 62);

            if (empty($apiUrl) || empty($apiHost) || empty($apiKey)) {
                throw new Exception('Judge0 API configuration missing');
            }

            // Build a wrapper Main launcher that calls the provided main class
            $launcher = "public class Main {\n  public static void main(String[] args) {\n    try {\n      " . $mainClass . ".main(args);\n    } catch (Throwable t) { t.printStackTrace(); }\n  }\n}";

            // Build a ZIP archive for additional_files
            $tmpZip = tempnam(sys_get_temp_dir(), 'j0zip_');
            if ($tmpZip === false) { throw new Exception('Failed to create temp file for zip'); }
            $zip = new \ZipArchive();
            if ($zip->open($tmpZip, \ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to open zip archive');
            }
            foreach ($files as $file) {
                if (!isset($file['path']) || !isset($file['content'])) { continue; }
                $path = ltrim((string) $file['path'], '/');
                // Ensure .java extension
                if (!Str::endsWith($path, '.java')) { $path .= '.java'; }
                $zip->addFromString($path, (string) $file['content']);
            }
            $zip->close();
            $zipData = file_get_contents($tmpZip) ?: '';
            @unlink($tmpZip);

            if ($zipData === '') {
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => 'No Java files found for project execution.',
                    'executionTime' => 0,
                ];
            }

            $payload = [
                'language_id' => $langId,
                'source_code' => base64_encode($launcher),
                'additional_files' => base64_encode($zipData),
                'stdin' => base64_encode($input ?? ''),
            ];

            $endpoint = $apiUrl . '/submissions?base64_encoded=true&wait=true';
            $t0 = microtime(true);
            $response = Http::timeout(20)
                ->withHeaders([
                    'X-RapidAPI-Host' => $apiHost,
                    'X-RapidAPI-Key'  => $apiKey,
                    'Content-Type'    => 'application/json',
                ])->post($endpoint, $payload);

            $ms = (int) round((microtime(true) - $t0) * 1000);
            if (!$response->ok()) {
                // Fallback message, multi-file may not be supported on CE endpoint
                return [
                    'success' => false,
                    'stdout' => '',
                    'stderr' => 'Judge0 project execution not available (HTTP ' . $response->status() . ').',
                    'executionTime' => $ms,
                ];
            }

            $data = $response->json() ?: [];
            $statusId = (int) ($data['status']['id'] ?? 0);
            $stdout = isset($data['stdout']) ? base64_decode($data['stdout']) : '';
            $stderr = '';
            if (!empty($data['stderr'])) { $stderr = base64_decode($data['stderr']); }
            elseif (!empty($data['compile_output'])) { $stderr = base64_decode($data['compile_output']); }
            elseif (!empty($data['message'])) { $stderr = $data['message']; }

            return [
                'success' => ($statusId === 3) && empty($stderr),
                'stdout' => (string) $stdout,
                'stderr' => (string) $stderr,
                'executionTime' => $ms,
            ];
        } catch (Exception $e) {
            Log::error('Judge0 project execution error: ' . $e->getMessage());
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'Remote executor error (Judge0 project): ' . $e->getMessage(),
                'executionTime' => 0,
            ];
        }
    }

    /**
     * Run compiled Java project
     *
     * @param string $mainClass Fully qualified name of the main class to run
     * @param string $executionDir Directory containing the compiled classes
     * @param string|null $inputFile Path to input file if any
     * @return array Execution result
     */
    protected function runJavaProject(string $mainClass, string $executionDir, ?string $inputFile): array
    {
        // Determine operating system
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Build the command with resource limits
        if ($isWindows) {
            $command = sprintf(
                'cd %s && java -Xmx%dM -cp . %s',
                escapeshellarg($executionDir),
                $this->memoryLimit,
                escapeshellarg($mainClass)
            );
        } else {
            $command = sprintf(
                'cd %s && timeout %d java -Xmx%dM -cp . %s',
                escapeshellarg($executionDir),
                $this->maxExecutionTime,
                $this->memoryLimit,
                escapeshellarg($mainClass)
            );
        }
        
        // Add input redirection if input file exists
        if ($inputFile && File::exists($inputFile)) {
            $command .= ' < ' . escapeshellarg($inputFile);
        }
        
        // Redirect stderr to a separate file
        $stderrFile = $executionDir . '/stderr.txt';
        $command .= ' 2> ' . escapeshellarg($stderrFile);
        
        // Measure execution time
        $startTime = microtime(true);
        
        // Execute the command
        $stdout = shell_exec($command);
        
        $executionTime = microtime(true) - $startTime;
        
        // Read stderr if exists
        $stderr = '';
        if (File::exists($stderrFile)) {
            $stderr = File::get($stderrFile);
        }
        
        // Check for timeout manually on Windows
        if ($isWindows && $executionTime >= $this->maxExecutionTime) {
            shell_exec("taskkill /F /IM java.exe 2>NUL");
            $stderr = 'Execution timed out. Your code took too long to run.';
        }
        
        return [
            'success' => empty($stderr),
            'stdout' => $stdout ?: '',
            'stderr' => $stderr,
            'executionTime' => round($executionTime, 3),
        ];
    }
    
    /**
     * Clean up temporary files after execution
     *
     * @param string $executionDir
     * @return void
     */
    protected function cleanUp(string $executionDir): void
    {
        if (File::exists($executionDir)) {
            File::deleteDirectory($executionDir);
        }
    }
} 