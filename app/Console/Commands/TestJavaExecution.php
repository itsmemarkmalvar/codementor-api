<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\JavaExecutionService;

class TestJavaExecution extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:java-execution';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Java code execution functionality';

    protected $javaExecutionService;

    public function __construct(JavaExecutionService $javaExecutionService)
    {
        parent::__construct();
        $this->javaExecutionService = $javaExecutionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Testing Java Execution Service...');
        $this->newLine();

        // Test 1: Simple Hello World
        $this->info('Test 1: Simple Hello World');
        $simpleCode = 'public class HelloWorld {
    public static void main(String[] args) {
        System.out.println("Hello, World!");
        System.out.println("Java execution is working!");
    }
}';

        $result = $this->javaExecutionService->executeJavaCode($simpleCode);
        
        if ($result['success']) {
            $this->info('✅ SUCCESS');
            $this->line('Output: ' . $result['stdout']);
            $this->line('Execution Time: ' . $result['executionTime'] . 's');
        } else {
            $this->error('❌ FAILED');
            $this->line('Error: ' . $result['stderr']);
        }

        $this->newLine();

        // Test 2: Bubble Sort with Test Cases
        $this->info('Test 2: Bubble Sort with Test Cases');
        $bubbleSortCode = 'public class BubbleSort {
    public static void bubbleSort(int[] arr) {
        int n = arr.length;
        for (int i = 0; i < n-1; i++) {
            for (int j = 0; j < n-i-1; j++) {
                if (arr[j] > arr[j+1]) {
                    int temp = arr[j];
                    arr[j] = arr[j+1];
                    arr[j+1] = temp;
                }
            }
        }
    }
    
    public static void printArray(int[] arr) {
        for (int i = 0; i < arr.length; i++) {
            System.out.print(arr[i] + " ");
        }
        System.out.println();
    }
    
    public static void main(String[] args) {
        int[] arr = {64, 34, 25, 12, 22, 11, 90};
        System.out.println("Original array:");
        printArray(arr);
        bubbleSort(arr);
        System.out.println("Sorted array:");
        printArray(arr);
    }
}';

        $testCases = [
            [
                'input' => '',
                'expected_output' => 'Original array:\n64 34 25 12 22 11 90 \nSorted array:\n11 12 22 25 34 64 90'
            ]
        ];

        $result = $this->javaExecutionService->execute($bubbleSortCode, '', $testCases);
        
        if (isset($result['error'])) {
            $this->error('❌ EXECUTION FAILED');
            $this->line('Error: ' . $result['error']);
        } elseif (isset($result['test_results'])) {
            $allPassed = $result['success'] ?? false;
            
            if ($allPassed) {
                $this->info('✅ ALL TESTS PASSED');
            } else {
                $this->warn('⚠️ SOME TESTS FAILED');
            }
            
            foreach ($result['test_results'] as $index => $testResult) {
                $status = $testResult['passed'] ? '✅' : '❌';
                $this->line("  Test " . ($index + 1) . ": $status");
                
                if (!$testResult['passed']) {
                    $this->line("    Expected: " . $testResult['expected']);
                    $this->line("    Actual: " . ($testResult['actual'] ?? 'No output'));
                    if ($testResult['error']) {
                        $this->line("    Error: " . $testResult['error']);
                    }
                }
            }
        } else {
            $this->error('❌ UNEXPECTED RESULT FORMAT');
            $this->line('Result: ' . json_encode($result, JSON_PRETTY_PRINT));
        }

        $this->newLine();

        // Test 3: Error Handling
        $this->info('Test 3: Error Handling (Compilation Error)');
        $errorCode = 'public class ErrorTest {
    public static void main(String[] args) {
        System.out.println("This will compile fine");
        undefinedVariable = 5; // This will cause a compilation error
    }
}';

        $result = $this->javaExecutionService->executeJavaCode($errorCode);
        
        if ($result['success']) {
            $this->error('❌ UNEXPECTED SUCCESS (should have failed)');
        } else {
            $this->info('✅ CORRECTLY DETECTED ERROR');
            $this->line('Error: ' . substr($result['stderr'], 0, 200) . '...');
        }

        $this->newLine();
        $this->info('🎉 Java execution testing completed!');
        
        return 0;
    }
}
