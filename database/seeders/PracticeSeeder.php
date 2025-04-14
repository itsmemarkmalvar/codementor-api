<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PracticeCategory;
use App\Models\PracticeProblem;

class PracticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create practice categories
        $this->createPracticeCategories();
        
        // Create practice problems
        $this->createAlgorithmProblems();
        $this->createDataStructureProblems();
        $this->createProblemSolvingProblems();
        $this->createDailyChallenges();
    }
    
    /**
     * Create practice categories
     */
    private function createPracticeCategories(): void
    {
        $categories = [
            [
                'name' => 'Algorithms',
                'description' => 'Enhance your problem-solving skills with algorithmic challenges that focus on efficiency and optimization.',
                'icon' => 'Brain',
                'color' => 'from-blue-500/20 to-purple-500/20',
                'display_order' => 1,
                'is_active' => true,
                'required_level' => 0
            ],
            [
                'name' => 'Data Structures',
                'description' => 'Learn to organize and store data efficiently with challenges focused on various data structures.',
                'icon' => 'Code',
                'color' => 'from-emerald-500/20 to-teal-500/20',
                'display_order' => 2,
                'is_active' => true,
                'required_level' => 0
            ],
            [
                'name' => 'Problem Solving',
                'description' => 'Apply Java concepts to solve real-world problems and practical coding challenges.',
                'icon' => 'Target',
                'color' => 'from-orange-500/20 to-yellow-500/20',
                'display_order' => 3,
                'is_active' => true,
                'required_level' => 0
            ],
            [
                'name' => 'Daily Challenges',
                'description' => 'Test your skills with new challenges that refresh daily, perfect for consistent practice.',
                'icon' => 'Flame',
                'color' => 'from-pink-500/20 to-rose-500/20',
                'display_order' => 4,
                'is_active' => true,
                'required_level' => 0
            ]
        ];
        
        foreach ($categories as $category) {
            PracticeCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
    
    /**
     * Create algorithm problems
     */
    private function createAlgorithmProblems(): void
    {
        $category = PracticeCategory::where('name', 'Algorithms')->first();
        
        if (!$category) {
            return;
        }
        
        $problems = [
            [
                'title' => 'Binary Search Implementation',
                'description' => 'Implement the binary search algorithm to find a target element in a sorted array.',
                'instructions' => 'Create a method that performs binary search on a sorted array of integers.',
                'requirements' => [
                    'Implement a binary search method that takes a sorted array and a target value',
                    'Return the index of the target if found, or -1 if not found',
                    'The algorithm should have O(log n) time complexity'
                ],
                'difficulty_level' => 'medium',
                'points' => 100,
                'estimated_time_minutes' => 45,
                'complexity_tags' => ['time-complexity', 'binary-search', 'divide-and-conquer'],
                'topic_tags' => ['algorithms', 'searching', 'arrays'],
                'starter_code' => "public class BinarySearch {\n    public static int binarySearch(int[] arr, int target) {\n        // TODO: Implement binary search algorithm\n        // Return index of target if found, or -1 if not found\n        return -1;\n    }\n    \n    public static void main(String[] args) {\n        int[] arr = {1, 3, 5, 7, 9, 11, 13, 15, 17};\n        int target = 7;\n        int result = binarySearch(arr, target);\n        System.out.println(\"Target found at index: \" + result);\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'int[] arr = {1, 3, 5, 7, 9, 11, 13, 15, 17}; int target = 7;',
                        'expected' => '3'
                    ],
                    [
                        'input' => 'int[] arr = {1, 3, 5, 7, 9, 11, 13, 15, 17}; int target = 10;',
                        'expected' => '-1'
                    ],
                    [
                        'input' => 'int[] arr = {1, 3, 5, 7, 9, 11, 13, 15, 17}; int target = 1;',
                        'expected' => '0'
                    ]
                ],
                'solution_code' => "public class BinarySearch {\n    public static int binarySearch(int[] arr, int target) {\n        int left = 0;\n        int right = arr.length - 1;\n        \n        while (left <= right) {\n            int mid = left + (right - left) / 2;\n            \n            // Check if target is present at mid\n            if (arr[mid] == target)\n                return mid;\n            \n            // If target is greater, ignore left half\n            if (arr[mid] < target)\n                left = mid + 1;\n            // If target is smaller, ignore right half\n            else\n                right = mid - 1;\n        }\n        \n        // Target not found in array\n        return -1;\n    }\n    \n    public static void main(String[] args) {\n        int[] arr = {1, 3, 5, 7, 9, 11, 13, 15, 17};\n        int target = 7;\n        int result = binarySearch(arr, target);\n        System.out.println(\"Target found at index: \" + result);\n    }\n}",
                'hints' => [
                    'Remember that binary search works by repeatedly dividing the search interval in half.',
                    'You\'ll need to keep track of the left and right boundaries of your search space.',
                    'Think about how to calculate the middle index without integer overflow.',
                    'Consider the base case: when should the search terminate?'
                ],
                'learning_concepts' => ['binary search', 'algorithms', 'time complexity', 'array traversal'],
                'is_featured' => true
            ],
            [
                'title' => 'Bubble Sort Implementation',
                'description' => 'Implement the bubble sort algorithm to sort an array of integers in ascending order.',
                'instructions' => 'Create a method that sorts an array using the bubble sort algorithm.',
                'requirements' => [
                    'Implement a bubble sort method that sorts an array of integers in ascending order',
                    'The method should modify the input array in-place',
                    'Print the array after each pass of the algorithm'
                ],
                'difficulty_level' => 'easy',
                'points' => 75,
                'estimated_time_minutes' => 30,
                'complexity_tags' => ['time-complexity', 'space-complexity', 'sorting'],
                'topic_tags' => ['algorithms', 'sorting', 'arrays'],
                'starter_code' => "public class BubbleSort {\n    public static void bubbleSort(int[] arr) {\n        // TODO: Implement bubble sort algorithm\n    }\n    \n    // Helper method to print the array\n    public static void printArray(int[] arr) {\n        for (int i = 0; i < arr.length; i++) {\n            System.out.print(arr[i] + \" \");\n        }\n        System.out.println();\n    }\n    \n    public static void main(String[] args) {\n        int[] arr = {64, 34, 25, 12, 22, 11, 90};\n        System.out.println(\"Original array:\");\n        printArray(arr);\n        bubbleSort(arr);\n        System.out.println(\"Sorted array:\");\n        printArray(arr);\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'int[] arr = {64, 34, 25, 12, 22, 11, 90};',
                        'expected' => '11 12 22 25 34 64 90'
                    ],
                    [
                        'input' => 'int[] arr = {5, 1, 4, 2, 8};',
                        'expected' => '1 2 4 5 8'
                    ],
                    [
                        'input' => 'int[] arr = {1, 2, 3, 4, 5};',
                        'expected' => '1 2 3 4 5'
                    ]
                ],
                'hints' => [
                    'Bubble sort works by repeatedly stepping through the list and swapping adjacent elements if they are in the wrong order.',
                    'Think about how many passes through the array you need to make.',
                    'Consider how to optimize the algorithm to avoid unnecessary iterations when the array is already sorted.',
                    'Remember to use a temporary variable when swapping elements.'
                ],
                'learning_concepts' => ['bubble sort', 'algorithms', 'time complexity', 'array traversal'],
                'is_featured' => false
            ],
            [
                'title' => 'Find the Missing Number',
                'description' => 'Given an array containing n distinct numbers taken from 0, 1, 2, ..., n, find the one number that is missing from the array.',
                'instructions' => 'Create a method that finds the missing number in an array.',
                'requirements' => [
                    'Implement a method that finds the missing number in a sequence from 0 to n',
                    'The array contains n distinct numbers taken from 0, 1, 2, ..., n (inclusive)',
                    'Only one number is missing from the sequence',
                    'The method should have O(n) time complexity'
                ],
                'difficulty_level' => 'beginner',
                'points' => 50,
                'estimated_time_minutes' => 20,
                'complexity_tags' => ['time-complexity', 'math', 'array-manipulation'],
                'topic_tags' => ['algorithms', 'arrays', 'math'],
                'starter_code' => "public class MissingNumber {\n    public static int findMissingNumber(int[] nums) {\n        // TODO: Implement the algorithm to find the missing number\n        return -1;\n    }\n    \n    public static void main(String[] args) {\n        int[] nums = {3, 0, 1, 4, 6, 5, 8, 7};\n        int missing = findMissingNumber(nums);\n        System.out.println(\"The missing number is: \" + missing);\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'int[] nums = {3, 0, 1, 4, 6, 5, 8, 7};',
                        'expected' => '2'
                    ],
                    [
                        'input' => 'int[] nums = {9, 6, 4, 2, 3, 5, 7, 0, 1};',
                        'expected' => '8'
                    ],
                    [
                        'input' => 'int[] nums = {0};',
                        'expected' => '1'
                    ]
                ],
                'hints' => [
                    'Consider using the mathematical formula for the sum of numbers from 0 to n: n(n+1)/2.',
                    'Calculate the expected sum of numbers from 0 to n, and compare it with the actual sum of the array.',
                    'The difference between the expected sum and the actual sum is the missing number.',
                    'You can also solve this using bit manipulation techniques.'
                ],
                'learning_concepts' => ['array manipulation', 'algorithms', 'math', 'bit manipulation'],
                'is_featured' => false
            ]
        ];
        
        foreach ($problems as $problem) {
            PracticeProblem::firstOrCreate(
                [
                    'title' => $problem['title'],
                    'category_id' => $category->id
                ],
                array_merge($problem, ['category_id' => $category->id])
            );
        }
    }
    
    /**
     * Create data structure problems
     */
    private function createDataStructureProblems(): void
    {
        $category = PracticeCategory::where('name', 'Data Structures')->first();
        
        if (!$category) {
            return;
        }
        
        $problems = [
            [
                'title' => 'Linked List Reversal',
                'description' => 'Implement a method to reverse a singly linked list.',
                'instructions' => 'Create a method that reverses a singly linked list and returns the new head.',
                'requirements' => [
                    'Implement a method to reverse a singly linked list',
                    'Return the new head of the reversed list',
                    'The method should have O(n) time complexity and O(1) space complexity'
                ],
                'difficulty_level' => 'medium',
                'points' => 100,
                'estimated_time_minutes' => 30,
                'complexity_tags' => ['time-complexity', 'space-complexity', 'linked-list'],
                'topic_tags' => ['data-structures', 'linked-list', 'pointers'],
                'starter_code' => "class ListNode {\n    int val;\n    ListNode next;\n    \n    ListNode(int val) {\n        this.val = val;\n        this.next = null;\n    }\n}\n\npublic class LinkedListReversal {\n    public static ListNode reverseList(ListNode head) {\n        // TODO: Implement the linked list reversal algorithm\n        return null;\n    }\n    \n    // Helper method to print the linked list\n    public static void printList(ListNode head) {\n        ListNode current = head;\n        while (current != null) {\n            System.out.print(current.val + \" -> \");\n            current = current.next;\n        }\n        System.out.println(\"null\");\n    }\n    \n    public static void main(String[] args) {\n        // Create a sample linked list: 1 -> 2 -> 3 -> 4 -> 5\n        ListNode head = new ListNode(1);\n        head.next = new ListNode(2);\n        head.next.next = new ListNode(3);\n        head.next.next.next = new ListNode(4);\n        head.next.next.next.next = new ListNode(5);\n        \n        System.out.println(\"Original linked list:\");\n        printList(head);\n        \n        head = reverseList(head);\n        \n        System.out.println(\"Reversed linked list:\");\n        printList(head);\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'ListNode: 1 -> 2 -> 3 -> 4 -> 5',
                        'expected' => '5 -> 4 -> 3 -> 2 -> 1 -> null'
                    ],
                    [
                        'input' => 'ListNode: 1 -> 2',
                        'expected' => '2 -> 1 -> null'
                    ],
                    [
                        'input' => 'ListNode: 1',
                        'expected' => '1 -> null'
                    ]
                ],
                'hints' => [
                    'Consider using three pointers to keep track of the current node and its neighbors.',
                    'You\'ll need to reverse the direction of each link in the list.',
                    'Be careful not to lose track of the next node before reversing the current pointer.',
                    'Draw out the algorithm on paper to visualize the reversal process.'
                ],
                'learning_concepts' => ['linked list', 'data structures', 'pointers', 'in-place algorithm'],
                'is_featured' => true
            ],
            [
                'title' => 'Implement a Stack',
                'description' => 'Implement a stack data structure with push, pop, and peek operations using an array.',
                'instructions' => 'Create a stack class with standard stack operations.',
                'requirements' => [
                    'Implement a stack class using an array',
                    'Include push, pop, peek, and isEmpty methods',
                    'Handle stack overflow and underflow conditions'
                ],
                'difficulty_level' => 'beginner',
                'points' => 75,
                'estimated_time_minutes' => 25,
                'complexity_tags' => ['data-structure-implementation', 'stack'],
                'topic_tags' => ['data-structures', 'stack', 'arrays'],
                'starter_code' => "public class Stack {\n    private int maxSize;\n    private int[] stackArray;\n    private int top;\n    \n    // Constructor\n    public Stack(int size) {\n        maxSize = size;\n        stackArray = new int[maxSize];\n        top = -1; // Initialize top to -1 (empty stack)\n    }\n    \n    // TODO: Implement push operation\n    public void push(int value) {\n        // Add code here\n    }\n    \n    // TODO: Implement pop operation\n    public int pop() {\n        // Add code here\n        return -1;\n    }\n    \n    // TODO: Implement peek operation\n    public int peek() {\n        // Add code here\n        return -1;\n    }\n    \n    // TODO: Implement isEmpty operation\n    public boolean isEmpty() {\n        // Add code here\n        return false;\n    }\n    \n    // TODO: Implement isFull operation\n    public boolean isFull() {\n        // Add code here\n        return false;\n    }\n    \n    public static void main(String[] args) {\n        Stack stack = new Stack(5);\n        \n        stack.push(10);\n        stack.push(20);\n        stack.push(30);\n        \n        System.out.println(\"Top element: \" + stack.peek());\n        \n        System.out.println(\"Popped: \" + stack.pop());\n        System.out.println(\"Popped: \" + stack.pop());\n        \n        System.out.println(\"Top element after pops: \" + stack.peek());\n        \n        System.out.println(\"Is stack empty? \" + stack.isEmpty());\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'Stack stack = new Stack(3); stack.push(10); stack.push(20); stack.push(30); stack.isFull();',
                        'expected' => 'true'
                    ],
                    [
                        'input' => 'Stack stack = new Stack(3); stack.push(10); stack.push(20); stack.pop(); stack.peek();',
                        'expected' => '10'
                    ],
                    [
                        'input' => 'Stack stack = new Stack(3); stack.push(10); stack.pop(); stack.isEmpty();',
                        'expected' => 'true'
                    ]
                ],
                'hints' => [
                    'For push operation, increment top and then insert the element.',
                    'For pop operation, return the element at top and then decrement top.',
                    'For peek, just return the element at top without changing the stack.',
                    'The stack is empty when top is -1, and full when top is maxSize-1.'
                ],
                'learning_concepts' => ['stack', 'data structures', 'array implementation'],
                'is_featured' => false
            ]
        ];
        
        foreach ($problems as $problem) {
            PracticeProblem::firstOrCreate(
                [
                    'title' => $problem['title'],
                    'category_id' => $category->id
                ],
                array_merge($problem, ['category_id' => $category->id])
            );
        }
    }
    
    /**
     * Create problem solving problems
     */
    private function createProblemSolvingProblems(): void
    {
        $category = PracticeCategory::where('name', 'Problem Solving')->first();
        
        if (!$category) {
            return;
        }
        
        $problems = [
            [
                'title' => 'FizzBuzz Challenge',
                'description' => 'Implement the classic FizzBuzz problem to practice conditional logic.',
                'instructions' => 'Write a program that prints numbers from 1 to n, but for multiples of 3 print "Fizz", for multiples of 5 print "Buzz", and for multiples of both 3 and 5 print "FizzBuzz".',
                'requirements' => [
                    'Implement a method that accepts an integer n',
                    'Print numbers from 1 to n with the FizzBuzz rules',
                    'Use proper conditional logic to handle all cases'
                ],
                'difficulty_level' => 'beginner',
                'points' => 50,
                'estimated_time_minutes' => 15,
                'complexity_tags' => ['conditional-logic', 'loops'],
                'topic_tags' => ['problem-solving', 'loops', 'conditionals'],
                'starter_code' => "public class FizzBuzz {\n    public static void fizzBuzz(int n) {\n        // TODO: Implement FizzBuzz algorithm\n    }\n    \n    public static void main(String[] args) {\n        int n = 15;\n        fizzBuzz(n);\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'fizzBuzz(15)',
                        'expected' => '1\n2\nFizz\n4\nBuzz\nFizz\n7\n8\nFizz\nBuzz\n11\nFizz\n13\n14\nFizzBuzz'
                    ],
                    [
                        'input' => 'fizzBuzz(5)',
                        'expected' => '1\n2\nFizz\n4\nBuzz'
                    ]
                ],
                'hints' => [
                    'Use a loop to iterate from 1 to n.',
                    'Check if the number is divisible by both 3 and 5 first, then check individual cases.',
                    'Use the modulo operator (%) to check divisibility.',
                    'Remember to handle the case when a number is neither divisible by 3 nor 5.'
                ],
                'learning_concepts' => ['conditionals', 'loops', 'modulo operation'],
                'is_featured' => true
            ],
            [
                'title' => 'Calculate Factorial',
                'description' => 'Implement a method to calculate the factorial of a given number.',
                'instructions' => 'Write a program that calculates the factorial of a non-negative integer n.',
                'requirements' => [
                    'Implement a method that calculates n!',
                    'Handle edge cases properly (n=0, n=1)',
                    'Use proper error handling for negative inputs'
                ],
                'difficulty_level' => 'beginner',
                'points' => 50,
                'estimated_time_minutes' => 15,
                'complexity_tags' => ['math', 'recursion'],
                'topic_tags' => ['problem-solving', 'math', 'recursion'],
                'starter_code' => "public class Factorial {\n    public static long factorial(int n) {\n        // TODO: Implement factorial calculation\n        return 0;\n    }\n    \n    public static void main(String[] args) {\n        int n = 5;\n        System.out.println(n + \"! = \" + factorial(n));\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'factorial(5)',
                        'expected' => '120'
                    ],
                    [
                        'input' => 'factorial(0)',
                        'expected' => '1'
                    ],
                    [
                        'input' => 'factorial(10)',
                        'expected' => '3628800'
                    ]
                ],
                'hints' => [
                    'Remember that 0! is defined as 1.',
                    'You can use either an iterative or recursive approach.',
                    'Be careful with large numbers - factorial grows very quickly.',
                    'Consider throwing an IllegalArgumentException for negative inputs.'
                ],
                'learning_concepts' => ['factorial', 'math', 'recursion', 'iteration'],
                'is_featured' => false
            ]
        ];
        
        foreach ($problems as $problem) {
            PracticeProblem::firstOrCreate(
                [
                    'title' => $problem['title'],
                    'category_id' => $category->id
                ],
                array_merge($problem, ['category_id' => $category->id])
            );
        }
    }
    
    /**
     * Create daily challenges
     */
    private function createDailyChallenges(): void
    {
        $category = PracticeCategory::where('name', 'Daily Challenges')->first();
        
        if (!$category) {
            return;
        }
        
        $problems = [
            [
                'title' => 'String Palindrome Check',
                'description' => 'Write a method to check if a string is a palindrome.',
                'instructions' => 'Create a method that checks if a given string is a palindrome (reads the same forwards and backwards).',
                'requirements' => [
                    'Implement a method that returns true if the string is a palindrome, false otherwise',
                    'Ignore case sensitivity (treat uppercase and lowercase letters as the same)',
                    'Ignore non-alphanumeric characters (spaces, punctuation, etc.)'
                ],
                'difficulty_level' => 'beginner',
                'points' => 75,
                'estimated_time_minutes' => 20,
                'complexity_tags' => ['string-manipulation', 'two-pointers'],
                'topic_tags' => ['daily-challenge', 'strings', 'algorithms'],
                'starter_code' => "public class PalindromeCheck {\n    public static boolean isPalindrome(String str) {\n        // TODO: Implement palindrome check\n        return false;\n    }\n    \n    public static void main(String[] args) {\n        String[] testStrings = {\n            \"racecar\",\n            \"A man, a plan, a canal: Panama\",\n            \"hello\",\n            \"Madam, I'm Adam\"\n        };\n        \n        for (String str : testStrings) {\n            System.out.println(\"\\\"\" + str + \"\\\" is a palindrome: \" + isPalindrome(str));\n        }\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'isPalindrome("racecar")',
                        'expected' => 'true'
                    ],
                    [
                        'input' => 'isPalindrome("A man, a plan, a canal: Panama")',
                        'expected' => 'true'
                    ],
                    [
                        'input' => 'isPalindrome("hello")',
                        'expected' => 'false'
                    ],
                    [
                        'input' => 'isPalindrome("Madam, I\'m Adam")',
                        'expected' => 'true'
                    ]
                ],
                'hints' => [
                    'First, clean the string by removing non-alphanumeric characters and converting to lowercase.',
                    'You can use two pointers - one starting from the beginning and one from the end.',
                    'Java\'s Character.isLetterOrDigit() and Character.toLowerCase() methods can be helpful.',
                    'An alternative approach is to create a reversed string and compare it with the original.'
                ],
                'learning_concepts' => ['strings', 'two-pointer technique', 'character manipulation'],
                'is_featured' => true
            ],
            [
                'title' => 'Compute Sum of Digits',
                'description' => 'Write a method to compute the sum of all digits in an integer.',
                'instructions' => 'Create a method that calculates the sum of all digits in a given integer.',
                'requirements' => [
                    'Implement a method that accepts an integer and returns the sum of its digits',
                    'Handle both positive and negative integers',
                    'Handle large numbers efficiently'
                ],
                'difficulty_level' => 'beginner',
                'points' => 50,
                'estimated_time_minutes' => 15,
                'complexity_tags' => ['math', 'loops'],
                'topic_tags' => ['daily-challenge', 'math', 'loops'],
                'starter_code' => "public class DigitSum {\n    public static int sumOfDigits(int number) {\n        // TODO: Implement sum of digits calculation\n        return 0;\n    }\n    \n    public static void main(String[] args) {\n        int[] testNumbers = {123, 9045, -78, 0, 10000};\n        \n        for (int num : testNumbers) {\n            System.out.println(\"Sum of digits in \" + num + \" is: \" + sumOfDigits(num));\n        }\n    }\n}",
                'test_cases' => [
                    [
                        'input' => 'sumOfDigits(123)',
                        'expected' => '6'
                    ],
                    [
                        'input' => 'sumOfDigits(9045)',
                        'expected' => '18'
                    ],
                    [
                        'input' => 'sumOfDigits(-78)',
                        'expected' => '15'
                    ],
                    [
                        'input' => 'sumOfDigits(0)',
                        'expected' => '0'
                    ]
                ],
                'hints' => [
                    'Use the modulo operator (%) to extract the last digit of a number.',
                    'Divide the number by 10 to remove the last digit.',
                    'Remember to handle negative numbers by taking the absolute value first.',
                    'You can use a loop to process each digit or convert the number to a string.'
                ],
                'learning_concepts' => ['math operations', 'loops', 'modulo operator'],
                'is_featured' => false
            ]
        ];
        
        foreach ($problems as $problem) {
            PracticeProblem::firstOrCreate(
                [
                    'title' => $problem['title'],
                    'category_id' => $category->id
                ],
                array_merge($problem, ['category_id' => $category->id])
            );
        }
    }
} 