<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\LearningTopic;

class LessonPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the Java Basics topic
        $javaTopic = LearningTopic::where('title', 'Java Basics')->first();
        
        if (!$javaTopic) {
            $this->command->info('Java Basics topic not found. Skipping lesson plan creation.');
            return;
        }
        
        // Check if lesson plans already exist for this topic to prevent duplicates
        $existingPlans = LessonPlan::where('topic_id', $javaTopic->id)
            ->where(function($query) {
                $query->where('title', 'Java Fundamentals')
                    ->orWhere('title', 'Java Object-Oriented Programming')
                    ->orWhere('title', 'Java Data Structures');
            })
            ->count();
        
        if ($existingPlans > 0) {
            $this->command->info('Core Java Basics lesson plans already exist. Proceeding to check for additional plans...');
            
            // Check if Methods lesson plan exists
            $methodsPlan = LessonPlan::where('topic_id', $javaTopic->id)
                ->where('title', 'Java Methods in Depth')
                ->first();
                
            // Check if File I/O lesson plan exists
            $fileIOPlan = LessonPlan::where('topic_id', $javaTopic->id)
                ->where('title', 'Java File I/O')
                ->first();
                
            if ($methodsPlan) {
                $this->command->info('Java Methods lesson plan already exists.');
            }
            
            if ($fileIOPlan) {
                $this->command->info('Java File I/O lesson plan already exists.');
            }
            
            if ($methodsPlan && $fileIOPlan) {
                $this->command->info('All Java lesson plans already exist. Skipping creation.');
            return;
            }
        } else {
            $this->command->info('Creating all Java lesson plans from scratch...');
        }
        
        // If we don't already have the Java Methods plan, create it
        if (!isset($methodsPlan) || !$methodsPlan) {
            // Create Java Methods Lesson Plan
            $javaMethods = LessonPlan::create([
                'title' => 'Java Methods in Depth',
                'description' => 'Master method creation and usage in Java programming.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 2,
                'estimated_minutes' => 140,
                'learning_objectives' => 'Understand method declaration, parameters, return types, overloading, and recursion.',
                'prerequisites' => 'Java Fundamentals, Java Control Flow',
                'resources' => json_encode(['Oracle Java Methods Documentation', 'Java Methods Tutorial']),
            'is_published' => true,
        ]);
        
            // Create module for Java Methods
            $methodsModule = LessonModule::create([
                'lesson_plan_id' => $javaMethods->id,
                'title' => 'Method Fundamentals',
            'order_index' => 1,
                'description' => 'Learn the basics of creating and using methods in Java.',
                'content' => "# Method Fundamentals in Java\n\nMethods are blocks of code designed to perform specific tasks and promote code reuse.\n\nIn this module, you'll learn about:\n- Method declaration and calling\n- Parameters and return types\n- Method overloading\n- Scope and lifetime of variables\n- Recursion basics",
                'examples' => "```java\npublic class MethodExamples {\n    // Method with no parameters and no return value\n    public static void sayHello() {\n        System.out.println(\"Hello!\");\n    }\n    \n    // Method with parameters and return value\n    public static int add(int a, int b) {\n        return a + b;\n    }\n    \n    // Method overloading - same name, different parameters\n    public static double add(double a, double b) {\n        return a + b;\n    }\n    \n    // Method with variable arguments\n    public static int sum(int... numbers) {\n        int total = 0;\n        for (int num : numbers) {\n            total += num;\n        }\n        return total;\n    }\n    \n    // Recursive method\n    public static int factorial(int n) {\n        if (n <= 1) return 1;\n        return n * factorial(n-1);\n    }\n    \n    public static void main(String[] args) {\n        sayHello();\n        System.out.println(add(5, 3));\n        System.out.println(add(4.5, 3.2));\n        System.out.println(sum(1, 2, 3, 4, 5));\n        System.out.println(factorial(5));\n    }\n}\n```",
            'key_points' => json_encode([
                    'Methods must have a return type (void if nothing is returned)',
                    'Parameters allow passing data to methods',
                    'Method overloading allows multiple methods with the same name but different parameters',
                    'Variable scope determines where variables can be accessed',
                    'Recursion occurs when a method calls itself'
                ]),
                'teaching_strategy' => json_encode(['Use practical examples that demonstrate how methods improve code organization and reusability.']),
                'estimated_minutes' => 55,
            'is_published' => true,
        ]);
        
            // Create exercise for Methods Module
        LessonExercise::create([
                'module_id' => $methodsModule->id,
                'title' => 'Calculator Methods',
            'type' => 'coding',
                'description' => 'Build a calculator using methods to handle different operations.',
                'instructions' => "Create a Calculator class with the following methods:\n1. add(int a, int b) - returns sum\n2. subtract(int a, int b) - returns difference\n3. multiply(int a, int b) - returns product\n4. divide(int a, int b) - returns quotient (handle division by zero)\n5. power(int base, int exponent) - returns base raised to exponent\n\nThen demonstrate all methods in the main method.",
                'starter_code' => "public class Calculator {\n    // Implement calculator methods here\n    \n    public static void main(String[] args) {\n        // Demonstrate all calculator operations\n        \n    }\n}",
            'solution' => json_encode([
                    "public class Calculator {\n    // Add method\n    public static int add(int a, int b) {\n        return a + b;\n    }\n    \n    // Subtract method\n    public static int subtract(int a, int b) {\n        return a - b;\n    }\n    \n    // Multiply method\n    public static int multiply(int a, int b) {\n        return a * b;\n    }\n    \n    // Divide method with exception handling\n    public static double divide(int a, int b) {\n        if (b == 0) {\n            System.out.println(\"Error: Division by zero\");\n            return 0; // Return 0 or throw an exception\n        }\n        return (double) a / b;\n    }\n    \n    // Power method\n    public static int power(int base, int exponent) {\n        if (exponent < 0) {\n            System.out.println(\"Error: Negative exponent not supported\");\n            return 0;\n        }\n        \n        int result = 1;\n        for (int i = 0; i < exponent; i++) {\n            result *= base;\n        }\n        return result;\n    }\n    \n    public static void main(String[] args) {\n        // Demonstrate all calculator operations\n        System.out.println(\"Addition: 5 + 3 = \" + add(5, 3));\n        System.out.println(\"Subtraction: 10 - 4 = \" + subtract(10, 4));\n        System.out.println(\"Multiplication: 6 * 7 = \" + multiply(6, 7));\n        System.out.println(\"Division: 20 / 4 = \" + divide(20, 4));\n        System.out.println(\"Division by zero: 5 / 0 = \" + divide(5, 0));\n        System.out.println(\"Power: 2^5 = \" + power(2, 5));\n    }\n}"
            ]),
            'test_cases' => json_encode([
                    ['input' => '', 'output' => "Addition: 5 + 3 = 8\nSubtraction: 10 - 4 = 6\nMultiplication: 6 * 7 = 42\nDivision: 20 / 4 = 5.0\nError: Division by zero\nDivision by zero: 5 / 0 = 0.0\nPower: 2^5 = 32"]
            ]),
            'hints' => json_encode([
                    'Define each method with the appropriate return type and parameters',
                    'Handle edge cases like division by zero and negative exponents',
                    'For power calculation, use a loop to multiply the base by itself exponent times',
                    'For division, consider returning a double to handle decimal results'
            ]),
            'difficulty' => 2,
            'points' => 25,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
            $this->command->info('Created Java Methods lesson plan with module and exercise.');
        }
        
        // If we don't already have the Java File I/O plan, create it
        if (!isset($fileIOPlan) || !$fileIOPlan) {
            // Create Java File I/O Lesson Plan
            $javaFileIO = LessonPlan::create([
                'title' => 'Java File I/O',
                'description' => 'Learn how to work with files and directories in Java.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 3,
            'estimated_minutes' => 150,
                'learning_objectives' => 'Master file reading and writing, understand file paths, and implement error handling for I/O operations.',
                'prerequisites' => 'Java Fundamentals, Java Control Flow, Java Exception Handling',
                'resources' => json_encode(['Oracle Java I/O Documentation', 'Java NIO Tutorial']),
            'is_published' => true,
        ]);
        
            // Create module for Java File I/O
            $fileIOModule = LessonModule::create([
                'lesson_plan_id' => $javaFileIO->id,
                'title' => 'Working with Text Files',
            'order_index' => 1,
                'description' => 'Learn how to read from and write to text files in Java.',
                'content' => "# Working with Text Files in Java\n\nFile I/O operations allow your programs to read from and write to files.\n\nIn this module, you'll learn about:\n- Reading text files\n- Writing to text files\n- Working with file paths\n- Handling I/O exceptions\n- Using BufferedReader and BufferedWriter\n- Try-with-resources pattern",
                'examples' => "```java\nimport java.io.*;\nimport java.nio.file.*;\n\npublic class FileIOExamples {\n    public static void main(String[] args) {\n        // Writing to a file using PrintWriter\n        try (PrintWriter writer = new PrintWriter(new FileWriter(\"output.txt\"))) {\n            writer.println(\"Hello, File I/O!\");\n            writer.println(\"This is the second line.\");\n            System.out.println(\"Successfully wrote to the file.\");\n        } catch (IOException e) {\n            System.out.println(\"An error occurred writing the file: \" + e.getMessage());\n        }\n        \n        // Reading from a file using BufferedReader\n        try (BufferedReader reader = new BufferedReader(new FileReader(\"output.txt\"))) {\n            String line;\n            System.out.println(\"File contents:\");\n            while ((line = reader.readLine()) != null) {\n                System.out.println(line);\n            }\n        } catch (IOException e) {\n            System.out.println(\"An error occurred reading the file: \" + e.getMessage());\n        }\n        \n        // Using Path and Files (Java NIO)\n        try {\n            Path path = Paths.get(\"nio-example.txt\");\n            Files.writeString(path, \"Writing with Java NIO\\nThis is easier!\");\n            \n            String content = Files.readString(path);\n            System.out.println(\"\\nNIO file contents:\\n\" + content);\n        } catch (IOException e) {\n            System.out.println(\"NIO error: \" + e.getMessage());\n        }\n    }\n}\n```",
            'key_points' => json_encode([
                    'File I/O requires exception handling due to potential runtime errors',
                    'BufferedReader and BufferedWriter improve performance for text file operations',
                    'Try-with-resources automatically closes resources to prevent memory leaks',
                    'The java.nio package provides more modern file I/O operations',
                    'Always close file resources when done with them'
                ]),
                'teaching_strategy' => json_encode(['Use practical file I/O examples that students will encounter in real-world applications.']),
                'estimated_minutes' => 60,
            'is_published' => true,
        ]);
        
            // Create exercise for File I/O Module
        LessonExercise::create([
                'module_id' => $fileIOModule->id,
                'title' => 'Student Record Manager',
            'type' => 'coding',
                'description' => 'Practice file I/O by implementing a student record management system.',
                'instructions' => "Create a program that manages student records in a text file:\n1. Implement a Student class with id, name, and grade fields\n2. Implement a method to add a new student to \"students.txt\" (each student on a new line, fields separated by commas)\n3. Implement a method to display all students from the file\n4. Implement a method to find a student by ID\n5. Implement a method to update a student's grade",
                'starter_code' => "import java.io.*;\n\nclass Student {\n    // Implement Student class\n}\n\npublic class StudentRecordManager {\n    private static final String FILE_NAME = \"students.txt\";\n    \n    // Implement record management methods\n    \n    public static void main(String[] args) {\n        // Demonstrate all functionality\n        \n    }\n}",
            'solution' => json_encode([
                    "import java.io.*;\nimport java.util.*;\n\nclass Student {\n    private int id;\n    private String name;\n    private double grade;\n    \n    public Student(int id, String name, double grade) {\n        this.id = id;\n        this.name = name;\n        this.grade = grade;\n    }\n    \n    public int getId() { return id; }\n    public String getName() { return name; }\n    public double getGrade() { return grade; }\n    public void setGrade(double grade) { this.grade = grade; }\n    \n    @Override\n    public String toString() {\n        return id + \",\" + name + \",\" + grade;\n    }\n    \n    public static Student fromString(String line) {\n        String[] parts = line.split(\",\");\n        int id = Integer.parseInt(parts[0]);\n        String name = parts[1];\n        double grade = Double.parseDouble(parts[2]);\n        return new Student(id, name, grade);\n    }\n}\n\npublic class StudentRecordManager {\n    private static final String FILE_NAME = \"students.txt\";\n    \n    // Add a student to the file\n    public static void addStudent(Student student) throws IOException {\n        try (PrintWriter writer = new PrintWriter(new FileWriter(FILE_NAME, true))) {\n            writer.println(student.toString());\n        }\n    }\n    \n    // Display all students\n    public static void displayAllStudents() throws IOException {\n        try (BufferedReader reader = new BufferedReader(new FileReader(FILE_NAME))) {\n            String line;\n            System.out.println(\"\\nAll Students:\");\n            System.out.println(\"ID\\tName\\tGrade\");\n            while ((line = reader.readLine()) != null) {\n                Student student = Student.fromString(line);\n                System.out.println(student.getId() + \"\\t\" + student.getName() + \"\\t\" + student.getGrade());\n            }\n        } catch (FileNotFoundException e) {\n            System.out.println(\"No student records found.\");\n        }\n    }\n    \n    // Find student by ID\n    public static Student findStudentById(int id) throws IOException {\n        try (BufferedReader reader = new BufferedReader(new FileReader(FILE_NAME))) {\n            String line;\n            while ((line = reader.readLine()) != null) {\n                Student student = Student.fromString(line);\n                if (student.getId() == id) {\n                    return student;\n                }\n            }\n        } catch (FileNotFoundException e) {\n            System.out.println(\"No student records found.\");\n        }\n        return null; // Student not found\n    }\n    \n    // Update student's grade\n    public static void updateStudentGrade(int id, double newGrade) throws IOException {\n        List<Student> students = new ArrayList<>();\n        \n        // Read all students\n        try (BufferedReader reader = new BufferedReader(new FileReader(FILE_NAME))) {\n            String line;\n            while ((line = reader.readLine()) != null) {\n                Student student = Student.fromString(line);\n                if (student.getId() == id) {\n                    student.setGrade(newGrade);\n                }\n                students.add(student);\n            }\n        } catch (FileNotFoundException e) {\n            System.out.println(\"No student records found.\");\n            return;\n        }\n        \n        // Write all students back\n        try (PrintWriter writer = new PrintWriter(new FileWriter(FILE_NAME))) {\n            for (Student student : students) {\n                writer.println(student.toString());\n            }\n        }\n    }\n    \n    public static void main(String[] args) {\n        try {\n            // Add students\n            addStudent(new Student(1, \"John Doe\", 85.5));\n            addStudent(new Student(2, \"Jane Smith\", 92.0));\n            addStudent(new Student(3, \"Bob Johnson\", 78.5));\n            \n            // Display all students\n            displayAllStudents();\n            \n            // Find and display a student\n            Student found = findStudentById(2);\n            if (found != null) {\n                System.out.println(\"\\nFound student: \" + found.getName() + \" with grade \" + found.getGrade());\n            } else {\n                System.out.println(\"\\nStudent not found.\");\n            }\n            \n            // Update a student's grade\n            System.out.println(\"\\nUpdating Bob's grade to 82.0\");\n            updateStudentGrade(3, 82.0);\n            \n            // Display all students after update\n            displayAllStudents();\n            \n        } catch (IOException e) {\n            System.out.println(\"An error occurred: \" + e.getMessage());\n        }\n    }\n}"
            ]),
            'test_cases' => json_encode([
                    ['input' => '', 'output' => "All Students:\nID\tName\tGrade\n1\tJohn Doe\t85.5\n2\tJane Smith\t92.0\n3\tBob Johnson\t78.5\n\nFound student: Jane Smith with grade 92.0\n\nUpdating Bob's grade to 82.0\n\nAll Students:\nID\tName\tGrade\n1\tJohn Doe\t85.5\n2\tJane Smith\t92.0\n3\tBob Johnson\t82.0"]
            ]),
            'hints' => json_encode([
                    'Create a Student class with toString() method for file storage',
                    'Use a static method to convert a line of text to a Student object',
                    'Use try-with-resources to handle file resources',
                    'To update a record, you need to read all records, modify the one you want, and write them all back',
                    'Store student records in CSV format (comma-separated values)'
            ]),
            'difficulty' => 3,
            'points' => 30,
            'order_index' => 1,
            'is_required' => true,
        ]);
            
            $this->command->info('Created Java File I/O lesson plan with module and exercise.');
        }
    }
} 