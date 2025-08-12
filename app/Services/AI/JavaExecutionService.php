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
            
            // Extract the class name from the code
            $className = $this->extractClassName($code);
            
            if (!$className) {
                return [
                    'error' => 'Could not determine class name from code. Make sure you have a public class declaration.',
                    'execution_time' => 0,
                ];
            }
            
            // Create a unique directory for this execution
            $executionId = Str::uuid()->toString();
            $executionDir = $this->tempDir . '/' . $executionId;
            File::makeDirectory($executionDir, 0755, true);
            
            // Write the code to a file
            $filePath = $executionDir . '/' . $className . '.java';
            File::put($filePath, $code);
            
            // Compile the code
            $compileResult = $this->compileJavaCode($filePath, $executionDir);
            
            if (!$compileResult['success']) {
                $this->cleanUp($executionDir);
                return [
                    'error' => $compileResult['stderr'],
                    'execution_time' => 0,
                ];
            }
            
            // Run test cases
            $testResults = [];
            $allPassed = true;
            
            foreach ($testCases as $index => $testCase) {
                $testInput = $testCase['input'] ?? '';
                $expectedOutput = trim($testCase['expected_output'] ?? '');
                
                // Write test input to file
                $inputFile = null;
                if (!empty($testInput)) {
                    $inputFile = $executionDir . '/test_input_' . $index . '.txt';
                    File::put($inputFile, $testInput);
                }
                
                // Execute with this test case
                $result = $this->runJavaCode($className, $executionDir, $inputFile);
                
                $actualOutput = trim($result['stdout'] ?? '');
                $passed = ($actualOutput === $expectedOutput) && $result['success'];
                
                $testResults[] = [
                    'passed' => $passed,
                    'expected' => $expectedOutput,
                    'actual' => $actualOutput,
                    'error' => $result['stderr'] ?? null
                ];
                
                if (!$passed) {
                    $allPassed = false;
                }
                
                // Clean up test input file
                if ($inputFile && File::exists($inputFile)) {
                    File::delete($inputFile);
                }
            }
            
            // Clean up temporary files
            $this->cleanUp($executionDir);
            
            return [
                'success' => $allPassed,
                'test_results' => $testResults,
                'execution_time' => 0, // We can add timing if needed
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

            $payload = [
                'language' => 'java',
                'version' => $javaVersion,
                'files' => [
                    [
                        'name' => $className . '.java',
                        'content' => $code,
                    ],
                ],
                'stdin' => $input ?? '',
                'args' => [],
                'compile_timeout' => $compileTimeoutMs,
                'run_timeout' => $runTimeoutMs,
                'compile_memory_limit' => -1,
                'run_memory_limit' => $memoryLimit,
            ];

            $response = Http::timeout(max(10, (int) ceil(($compileTimeoutMs + $runTimeoutMs) / 1000) + 5))
                ->acceptJson()
                ->post($baseUrl . '/execute', $payload);

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
            $runErr = is_array($run) ? ($run['stderr'] ?? '') : '';

            $stderr = trim($compileErr . (strlen($compileErr) && strlen($runErr) ? "\n" : '') . $runErr);

            return [
                'success' => empty($stderr) && (($run['code'] ?? 0) === 0),
                'stdout' => (string) ($run['stdout'] ?? ''),
                'stderr' => (string) $stderr,
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
                'args' => [],
                'compile_timeout' => $compileTimeoutMs,
                'run_timeout' => $runTimeoutMs,
                'compile_memory_limit' => -1,
                'run_memory_limit' => $memoryLimit,
            ];

            $response = Http::timeout(max(12, (int) ceil(($compileTimeoutMs + $runTimeoutMs) / 1000) + 5))
                ->acceptJson()
                ->post($baseUrl . '/execute', $payload);

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
            $runErr = is_array($run) ? ($run['stderr'] ?? '') : '';
            $stderr = trim($compileErr . (strlen($compileErr) && strlen($runErr) ? "\n" : '') . $runErr);

            return [
                'success' => empty($stderr) && (($run['code'] ?? 0) === 0),
                'stdout' => (string) ($run['stdout'] ?? ''),
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