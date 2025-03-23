<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
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
     * Execute Java code and return the result
     *
     * @param string $code The Java code to execute
     * @param string|null $input Optional input to provide to the program
     * @return array The execution result containing stdout, stderr, and execution info
     */
    public function executeJavaCode(string $code, ?string $input = null): array
    {
        try {
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