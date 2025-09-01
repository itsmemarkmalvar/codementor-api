<?php
/**
 * Test Local Java Execution
 * This script tests the Java execution service to verify it's working properly
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\AI\JavaExecutionService;

echo "=== Java Execution Test ===\n";

// Test 1: Basic Java execution
echo "\n1. Testing basic Java execution...\n";
$code = 'public class Test {
    public static void main(String[] args) {
        System.out.println("Hello from local Java!");
        System.out.println("Java version: " + System.getProperty("java.version"));
    }
}';

$service = new JavaExecutionService();
$result = $service->executeJavaCode($code);

echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "STDOUT: " . ($result['stdout'] ?? 'N/A') . "\n";
echo "STDERR: " . ($result['stderr'] ?? 'N/A') . "\n";
echo "Execution Time: " . ($result['executionTime'] ?? 'N/A') . "ms\n";

// Test 2: Java with input
echo "\n2. Testing Java with input...\n";
$codeWithInput = 'import java.util.Scanner;

public class TestInput {
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        String input = scanner.nextLine();
        System.out.println("You entered: " + input);
        scanner.close();
    }
}';

$resultWithInput = $service->executeJavaCode($codeWithInput, "Hello World");

echo "Success: " . ($resultWithInput['success'] ? 'YES' : 'NO') . "\n";
echo "STDOUT: " . ($resultWithInput['stdout'] ?? 'N/A') . "\n";
echo "STDERR: " . ($resultWithInput['stderr'] ?? 'N/A') . "\n";

// Test 3: Java with test cases
echo "\n3. Testing Java with test cases...\n";
$testCode = 'import java.util.Scanner;

public class TestCases {
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        int a = scanner.nextInt();
        int b = scanner.nextInt();
        System.out.println(a + b);
        scanner.close();
    }
}';

$testCases = [
    ['input' => "5\n3", 'expected_output' => "8"],
    ['input' => "10\n20", 'expected_output' => "30"]
];

$resultWithTests = $service->execute($testCode, null, $testCases);

echo "Success: " . ($resultWithTests['success'] ? 'YES' : 'NO') . "\n";
echo "Test Results:\n";
foreach ($resultWithTests['test_results'] ?? [] as $index => $test) {
    echo "  Test " . ($index + 1) . ": " . ($test['passed'] ? 'PASS' : 'FAIL') . "\n";
    if (!$test['passed']) {
        echo "    Expected: " . $test['expected'] . "\n";
        echo "    Actual: " . $test['actual'] . "\n";
        echo "    Error: " . $test['error'] . "\n";
    }
}

echo "\n=== Test Complete ===\n";
