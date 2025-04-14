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
        $existingPlans = LessonPlan::where('topic_id', $javaTopic->id)->count();
        if ($existingPlans > 0) {
            $this->command->info('Lesson plans for Java Basics already exist. Skipping lesson plan creation to prevent duplicates.');
            return;
        }
        
        // Create Java Fundamentals Lesson Plan
        $javaFundamentals = LessonPlan::create([
            'title' => 'Java Fundamentals',
            'description' => 'A comprehensive introduction to Java programming language fundamentals.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 1,
            'estimated_minutes' => 120,
            'learning_objectives' => 'Understand Java syntax, variables, data types, and basic operators.',
            'prerequisites' => 'Basic computer knowledge',
            'resources' => json_encode(['Oracle Java Documentation', 'Java Tutorials']),
            'is_published' => true,
        ]);
        
        // Create modules for Java Fundamentals
        $module1 = LessonModule::create([
            'lesson_plan_id' => $javaFundamentals->id,
            'title' => 'Getting Started with Java',
            'order_index' => 1,
            'description' => 'An introduction to Java programming and its core concepts.',
            'content' => "# Getting Started with Java\n\nJava is a high-level, class-based, object-oriented programming language. It's designed to have minimal implementation dependencies.\n\nIn this module, you'll learn about:\n- The JDK (Java Development Kit)\n- Java Virtual Machine (JVM)\n- Your first Java program",
            'examples' => "```java\n// This is your first Java program\npublic class HelloWorld {\n    public static void main(String[] args) {\n        System.out.println(\"Hello, World!\");\n    }\n}\n```",
            'key_points' => json_encode([
                'Java programs are compiled into bytecode',
                'Bytecode runs on the JVM, making Java platform-independent',
                'Java is strongly typed and object-oriented'
            ]),
            'teaching_strategy' => json_encode(['Start with basic concepts and progressively introduce more complex topics.']),
            'estimated_minutes' => 30,
            'is_published' => true,
        ]);
        
        // Create exercises for Module 1
        LessonExercise::create([
            'module_id' => $module1->id,
            'title' => 'Your First Hello World',
            'type' => 'coding',
            'description' => 'Practice writing a simple Java program to print text.',
            'instructions' => "Write a Java program that prints 'Hello, Java World!' to the console.",
            'starter_code' => "public class HelloJava {\n    public static void main(String[] args) {\n        // Write your code here\n        \n    }\n}",
            'solution' => json_encode([
                "public class HelloJava {\n    public static void main(String[] args) {\n        System.out.println(\"Hello, Java World!\");\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => 'Hello, Java World!']
            ]),
            'hints' => json_encode([
                'Use System.out.println() to print to the console',
                'Make sure your text is exactly "Hello, Java World!" including capitals and punctuation'
            ]),
            'difficulty' => 1,
            'points' => 10,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        // Module 2: Variables and Data Types
        $module2 = LessonModule::create([
            'lesson_plan_id' => $javaFundamentals->id,
            'title' => 'Variables and Data Types',
            'order_index' => 2,
            'description' => 'Learn how to work with different data types and variables in Java.',
            'content' => "# Variables and Data Types\n\nIn Java, variables are containers for storing data values. Java has different types of variables, such as:\n\n- Primitive Data Types: byte, short, int, long, float, double, boolean, char\n- Reference Data Types: String, Arrays, Classes\n\nIn this module, you'll learn how to declare variables, initialize them, and understand when to use each data type.",
            'examples' => "```java\n// Declaring variables\nint number = 10;\ndouble price = 9.99;\nboolean isJavaFun = true;\nchar letter = 'A';\nString message = \"Hello, Java!\";\n\n// Working with variables\nint sum = number + 5;\nSystem.out.println(sum); // Outputs 15\n```",
            'key_points' => json_encode([
                'Variables must be declared with a type',
                'Primitive types are built into Java and are not objects',
                'String is a class in Java but can be used like a primitive type',
                'Always initialize variables before using them'
            ]),
            'teaching_strategy' => json_encode(['Begin with primitive types, then move to reference types. Provide plenty of examples showing how types work.']),
            'estimated_minutes' => 45,
            'is_published' => true,
        ]);
        
        // Create exercises for Module 2
        LessonExercise::create([
            'module_id' => $module2->id,
            'title' => 'Variable Declaration Practice',
            'type' => 'coding',
            'description' => 'Practice declaring and initializing variables of different types.',
            'instructions' => "Declare and initialize variables for the following situations:\n1. An integer to store a person's age (42)\n2. A double to store a product price ($15.99)\n3. A string to store a person's name (\"John Doe\")\n4. A boolean to indicate if it's raining (false)\n\nThen print each variable on a new line.",
            'starter_code' => "public class VariablePractice {\n    public static void main(String[] args) {\n        // Declare your variables here\n        \n        \n        // Print each variable\n        \n        \n    }\n}",
            'solution' => json_encode([
                "public class VariablePractice {\n    public static void main(String[] args) {\n        // Declare your variables here\n        int age = 42;\n        double price = 15.99;\n        String name = \"John Doe\";\n        boolean isRaining = false;\n        \n        // Print each variable\n        System.out.println(age);\n        System.out.println(price);\n        System.out.println(name);\n        System.out.println(isRaining);\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => "42\n15.99\nJohn Doe\nfalse"]
            ]),
            'hints' => json_encode([
                'Use int for whole numbers',
                'Use double for decimal numbers',
                'Use String for text values',
                'Use boolean for true/false values',
                'Use System.out.println() for each variable on a new line'
            ]),
            'difficulty' => 1,
            'points' => 15,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        $this->command->info('Successfully created Java Fundamentals lesson plan with modules and exercises.');
        
        // Create Java OOP Lesson Plan
        $javaOOP = LessonPlan::create([
            'title' => 'Java Object-Oriented Programming',
            'description' => 'Learn object-oriented programming concepts using Java.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 2,
            'estimated_minutes' => 150,
            'learning_objectives' => 'Understand classes, objects, inheritance, polymorphism, encapsulation, and abstraction in Java.',
            'prerequisites' => 'Java Fundamentals',
            'resources' => json_encode(['Oracle Java OOP Documentation', 'Head First Java']),
            'is_published' => true,
        ]);
        
        // Create module for Java OOP
        $oopModule1 = LessonModule::create([
            'lesson_plan_id' => $javaOOP->id,
            'title' => 'Classes and Objects',
            'order_index' => 1,
            'description' => 'Learn how to create and use classes and objects in Java.',
            'content' => "# Classes and Objects in Java\n\nClasses are blueprints for creating objects. Objects are instances of classes.\n\nIn this module, you'll learn about:\n- Class definition and structure\n- Object creation using constructors\n- Instance variables and methods\n- The 'this' keyword",
            'examples' => "```java\n// Defining a class\npublic class Car {\n    // Instance variables\n    private String make;\n    private String model;\n    private int year;\n    \n    // Constructor\n    public Car(String make, String model, int year) {\n        this.make = make;\n        this.model = model;\n        this.year = year;\n    }\n    \n    // Method\n    public void displayInfo() {\n        System.out.println(year + \" \" + make + \" \" + model);\n    }\n}\n\n// Creating objects\nCar myCar = new Car(\"Toyota\", \"Camry\", 2022);\nmyCar.displayInfo(); // Outputs: 2022 Toyota Camry\n```",
            'key_points' => json_encode([
                'A class is a blueprint for objects',
                'Objects have state (fields) and behavior (methods)',
                'Constructors initialize objects',
                'The "this" keyword refers to the current object'
            ]),
            'teaching_strategy' => json_encode(['Use real-world analogies to explain classes and objects.']),
            'estimated_minutes' => 40,
            'is_published' => true,
        ]);
        
        // Create exercise for OOP Module 1
        LessonExercise::create([
            'module_id' => $oopModule1->id,
            'title' => 'Create a Student Class',
            'type' => 'coding',
            'description' => 'Practice creating classes and objects by implementing a Student class.',
            'instructions' => "Create a Student class with the following:\n1. Private fields for name, age, and GPA\n2. A constructor that initializes all fields\n3. Getter methods for all fields\n4. A displayInfo() method that prints all student information\n\nThen create a student object and display its information.",
            'starter_code' => "// Create your Student class here\n\n\npublic class Main {\n    public static void main(String[] args) {\n        // Create a student with name=\"John Doe\", age=20, gpa=3.8\n        \n        // Display the student's information\n        \n    }\n}",
            'solution' => json_encode([
                "class Student {\n    private String name;\n    private int age;\n    private double gpa;\n    \n    public Student(String name, int age, double gpa) {\n        this.name = name;\n        this.age = age;\n        this.gpa = gpa;\n    }\n    \n    public String getName() {\n        return name;\n    }\n    \n    public int getAge() {\n        return age;\n    }\n    \n    public double getGpa() {\n        return gpa;\n    }\n    \n    public void displayInfo() {\n        System.out.println(\"Name: \" + name);\n        System.out.println(\"Age: \" + age);\n        System.out.println(\"GPA: \" + gpa);\n    }\n}\n\npublic class Main {\n    public static void main(String[] args) {\n        // Create a student with name=\"John Doe\", age=20, gpa=3.8\n        Student student = new Student(\"John Doe\", 20, 3.8);\n        \n        // Display the student's information\n        student.displayInfo();\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => "Name: John Doe\nAge: 20\nGPA: 3.8"]
            ]),
            'hints' => json_encode([
                'Use private access modifier for the fields',
                'Create getter methods that return the respective field values',
                'In the constructor, use "this" to refer to instance variables'
            ]),
            'difficulty' => 2,
            'points' => 20,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        // Create Java Data Structures Lesson Plan
        $javaDataStructures = LessonPlan::create([
            'title' => 'Java Data Structures',
            'description' => 'Learn about essential data structures in Java and how to use the Java Collections Framework.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 3,
            'estimated_minutes' => 180,
            'learning_objectives' => 'Understand and use Java Collections Framework including ArrayList, LinkedList, HashSet, HashMap, and more.',
            'prerequisites' => 'Java Fundamentals, Java Object-Oriented Programming',
            'resources' => json_encode(['Oracle Collections Framework Documentation', 'Java Collections Tutorial']),
            'is_published' => true,
        ]);
        
        // Create module for Java Data Structures
        $dsModule = LessonModule::create([
            'lesson_plan_id' => $javaDataStructures->id,
            'title' => 'ArrayLists and Lists',
            'order_index' => 1,
            'description' => 'Learn how to use dynamic arrays in Java with ArrayList.',
            'content' => "# Lists and ArrayList in Java\n\nArrayList is a resizable array implementation of the List interface.\n\nIn this module, you'll learn about:\n- Creating and initializing ArrayLists\n- Adding, updating, and removing elements\n- Iterating through ArrayLists\n- Common methods like size(), contains(), and indexOf()",
            'examples' => "```java\nimport java.util.ArrayList;\n\npublic class ArrayListExample {\n    public static void main(String[] args) {\n        // Create ArrayList\n        ArrayList<String> fruits = new ArrayList<>();\n        \n        // Add elements\n        fruits.add(\"Apple\");\n        fruits.add(\"Banana\");\n        fruits.add(\"Orange\");\n        \n        // Print size\n        System.out.println(\"Size: \" + fruits.size());\n        \n        // Access elements\n        System.out.println(\"First fruit: \" + fruits.get(0));\n        \n        // Update elements\n        fruits.set(1, \"Mango\");\n        \n        // Remove elements\n        fruits.remove(2);\n        \n        // Iterate through the ArrayList\n        for (String fruit : fruits) {\n            System.out.println(fruit);\n        }\n    }\n}\n```",
            'key_points' => json_encode([
                'ArrayList provides dynamic arrays in Java',
                'Elements can be added and removed at runtime',
                'ArrayList can only store objects, not primitive types',
                'ArrayList implements the List interface'
            ]),
            'teaching_strategy' => json_encode(['Demonstrate practical use cases for ArrayLists and when to use them over arrays.']),
            'estimated_minutes' => 45,
            'is_published' => true,
        ]);
        
        // Create exercise for Data Structures Module
        LessonExercise::create([
            'module_id' => $dsModule->id,
            'title' => 'Student Management System',
            'type' => 'coding',
            'description' => 'Practice using ArrayList by implementing a simple student management system.',
            'instructions' => "Create a program that manages a list of students using ArrayList:\n1. Create a Student class with name and grade fields\n2. Create an ArrayList to store Student objects\n3. Add at least three students\n4. Print all students\n5. Find and print the student with the highest grade",
            'starter_code' => "import java.util.ArrayList;\n\nclass Student {\n    // Add fields, constructor, and methods here\n    \n}\n\npublic class StudentManagement {\n    public static void main(String[] args) {\n        // Create ArrayList of students\n        \n        // Add students\n        \n        // Print all students\n        \n        // Find and print student with highest grade\n        \n    }\n}",
            'solution' => json_encode([
                "import java.util.ArrayList;\n\nclass Student {\n    private String name;\n    private double grade;\n    \n    public Student(String name, double grade) {\n        this.name = name;\n        this.grade = grade;\n    }\n    \n    public String getName() {\n        return name;\n    }\n    \n    public double getGrade() {\n        return grade;\n    }\n    \n    @Override\n    public String toString() {\n        return name + \": \" + grade;\n    }\n}\n\npublic class StudentManagement {\n    public static void main(String[] args) {\n        // Create ArrayList of students\n        ArrayList<Student> students = new ArrayList<>();\n        \n        // Add students\n        students.add(new Student(\"Alice\", 92.5));\n        students.add(new Student(\"Bob\", 88.0));\n        students.add(new Student(\"Carol\", 95.0));\n        \n        // Print all students\n        System.out.println(\"All students:\");\n        for (Student student : students) {\n            System.out.println(student);\n        }\n        \n        // Find and print student with highest grade\n        Student topStudent = students.get(0);\n        for (Student student : students) {\n            if (student.getGrade() > topStudent.getGrade()) {\n                topStudent = student;\n            }\n        }\n        System.out.println(\"\\nStudent with highest grade:\");\n        System.out.println(topStudent);\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => "All students:\nAlice: 92.5\nBob: 88.0\nCarol: 95.0\n\nStudent with highest grade:\nCarol: 95.0"]
            ]),
            'hints' => json_encode([
                'Create a Student class with name and grade fields',
                'Use ArrayList<Student> to store Student objects',
                'Override toString() method in Student class for easy printing',
                'Keep track of the student with the highest grade using a variable'
            ]),
            'difficulty' => 3,
            'points' => 25,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        // Create Java Control Flow Lesson Plan
        $javaControlFlow = LessonPlan::create([
            'title' => 'Java Control Flow',
            'description' => 'Master control flow statements in Java programming.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 2,
            'estimated_minutes' => 120,
            'learning_objectives' => 'Understand and implement conditionals, loops, and control statements in Java programs.',
            'prerequisites' => 'Java Fundamentals',
            'resources' => json_encode(['Oracle Java Flow Control Documentation', 'Java Programming Tutorials']),
            'is_published' => true,
        ]);
        
        // Create modules for Java Control Flow
        $cfModule1 = LessonModule::create([
            'lesson_plan_id' => $javaControlFlow->id,
            'title' => 'Conditional Statements',
            'order_index' => 1,
            'description' => 'Learn how to make decisions in Java using if, else-if, else, and switch statements.',
            'content' => "# Conditional Statements in Java\n\nConditional statements allow your program to make decisions based on conditions.\n\nIn this module, you'll learn about:\n- if statements\n- if-else statements\n- if-else-if-else chains\n- switch statements\n- Ternary operators",
            'examples' => "```java\n// Simple if statement\nint age = 18;\nif (age >= 18) {\n    System.out.println(\"You are an adult.\");\n}\n\n// if-else statement\nint score = 75;\nif (score >= 60) {\n    System.out.println(\"You passed!\");\n} else {\n    System.out.println(\"You failed.\");\n}\n\n// if-else-if-else chain\nint grade = 85;\nif (grade >= 90) {\n    System.out.println(\"A\");\n} else if (grade >= 80) {\n    System.out.println(\"B\");\n} else if (grade >= 70) {\n    System.out.println(\"C\");\n} else if (grade >= 60) {\n    System.out.println(\"D\");\n} else {\n    System.out.println(\"F\");\n}\n\n// switch statement\nchar letterGrade = 'B';\nswitch (letterGrade) {\n    case 'A':\n        System.out.println(\"Excellent\");\n        break;\n    case 'B':\n        System.out.println(\"Good\");\n        break;\n    case 'C':\n        System.out.println(\"Average\");\n        break;\n    default:\n        System.out.println(\"Needs improvement\");\n}\n\n// Ternary operator\nint x = 5;\nString result = (x > 10) ? \"Greater than 10\" : \"Less than or equal to 10\";\nSystem.out.println(result); // Outputs: Less than or equal to 10\n```",
            'key_points' => json_encode([
                'Conditional statements evaluate boolean expressions',
                'Switch statements work with exact matching values',
                'Always use blocks {} with if statements for clarity',
                'The ternary operator is a shorthand for simple if-else statements'
            ]),
            'teaching_strategy' => json_encode(['Use real-world examples to demonstrate decision making in programming.']),
            'estimated_minutes' => 40,
            'is_published' => true,
        ]);
        
        // Create exercise for Control Flow Module 1
        LessonExercise::create([
            'module_id' => $cfModule1->id,
            'title' => 'Grade Calculator',
            'type' => 'coding',
            'description' => 'Practice using conditional statements by implementing a grade calculator.',
            'instructions' => "Create a program that determines a letter grade based on a numeric score:\n- 90-100: A\n- 80-89: B\n- 70-79: C\n- 60-69: D\n- Below 60: F\n\nImplement this logic using both if-else-if chain and switch statements.",
            'starter_code' => "public class GradeCalculator {\n    public static void main(String[] args) {\n        int score = 85;\n        \n        // Calculate letter grade using if-else-if\n        \n        \n        // Calculate letter grade using switch\n        // Hint: Convert the score to a range first\n        \n    }\n}",
            'solution' => json_encode([
                "public class GradeCalculator {\n    public static void main(String[] args) {\n        int score = 85;\n        \n        // Calculate letter grade using if-else-if\n        System.out.println(\"Using if-else statements:\");\n        if (score >= 90) {\n            System.out.println(\"Grade: A\");\n        } else if (score >= 80) {\n            System.out.println(\"Grade: B\");\n        } else if (score >= 70) {\n            System.out.println(\"Grade: C\");\n        } else if (score >= 60) {\n            System.out.println(\"Grade: D\");\n        } else {\n            System.out.println(\"Grade: F\");\n        }\n        \n        // Calculate letter grade using switch\n        System.out.println(\"\\nUsing switch statement:\");\n        int range = score / 10;\n        switch (range) {\n            case 10:\n            case 9:\n                System.out.println(\"Grade: A\");\n                break;\n            case 8:\n                System.out.println(\"Grade: B\");\n                break;\n            case 7:\n                System.out.println(\"Grade: C\");\n                break;\n            case 6:\n                System.out.println(\"Grade: D\");\n                break;\n            default:\n                System.out.println(\"Grade: F\");\n        }\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => "Using if-else statements:\nGrade: B\n\nUsing switch statement:\nGrade: B"]
            ]),
            'hints' => json_encode([
                'For the if-else chain, check from highest to lowest grade',
                'For the switch statement, divide the score by 10 to get a range from 0-10',
                'Remember that switch cases fall through without a break statement'
            ]),
            'difficulty' => 2,
            'points' => 20,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        $cfModule2 = LessonModule::create([
            'lesson_plan_id' => $javaControlFlow->id,
            'title' => 'Loops in Java',
            'order_index' => 2,
            'description' => 'Learn how to use loops to repeat code execution in Java.',
            'content' => "# Loops in Java\n\nLoops are used to execute a block of code multiple times.\n\nIn this module, you'll learn about:\n- for loops\n- while loops\n- do-while loops\n- enhanced for loops (for-each)\n- Loop control statements: break and continue",
            'examples' => "```java\n// Basic for loop\nfor (int i = 0; i < 5; i++) {\n    System.out.println(\"Iteration \" + i);\n}\n\n// while loop\nint count = 0;\nwhile (count < 5) {\n    System.out.println(\"Count: \" + count);\n    count++;\n}\n\n// do-while loop\nint num = 1;\ndo {\n    System.out.println(\"Number: \" + num);\n    num *= 2;\n} while (num <= 16);\n\n// enhanced for loop (for-each)\nint[] numbers = {1, 2, 3, 4, 5};\nfor (int number : numbers) {\n    System.out.println(\"Number: \" + number);\n}\n\n// break statement\nfor (int i = 0; i < 10; i++) {\n    if (i == 5) {\n        break; // exit the loop when i equals 5\n    }\n    System.out.println(i);\n}\n\n// continue statement\nfor (int i = 0; i < 10; i++) {\n    if (i % 2 == 0) {\n        continue; // skip even numbers\n    }\n    System.out.println(i); // prints only odd numbers\n}\n```",
            'key_points' => json_encode([
                'for loops are ideal when you know the number of iterations',
                'while loops check the condition before executing the block',
                'do-while loops execute the block at least once',
                'enhanced for loops simplify iterating through collections',
                'break exits the loop completely',
                'continue skips to the next iteration'
            ]),
            'teaching_strategy' => json_encode(['Provide concrete examples showing when to use each type of loop.']),
            'estimated_minutes' => 50,
            'is_published' => true,
        ]);
        
        // Create exercise for Control Flow Module 2
        LessonExercise::create([
            'module_id' => $cfModule2->id,
            'title' => 'Pattern Printing',
            'type' => 'coding',
            'description' => 'Practice using loops by printing various patterns.',
            'instructions' => "Create a program that prints the following patterns using nested loops:\n\n1. A right triangle of asterisks (*):\n*\n**\n***\n****\n*****\n\n2. A numbered pyramid:\n    1\n   222\n  33333\n 4444444\n555555555",
            'starter_code' => "public class PatternPrinting {\n    public static void main(String[] args) {\n        // Print right triangle pattern\n        System.out.println(\"Right Triangle Pattern:\");\n        \n        \n        // Print numbered pyramid pattern\n        System.out.println(\"\\nNumbered Pyramid Pattern:\");\n        \n        \n    }\n}",
            'solution' => json_encode([
                "public class PatternPrinting {\n    public static void main(String[] args) {\n        // Print right triangle pattern\n        System.out.println(\"Right Triangle Pattern:\");\n        for (int i = 1; i <= 5; i++) {\n            for (int j = 1; j <= i; j++) {\n                System.out.print(\"*\");\n            }\n            System.out.println();\n        }\n        \n        // Print numbered pyramid pattern\n        System.out.println(\"\\nNumbered Pyramid Pattern:\");\n        for (int i = 1; i <= 5; i++) {\n            // Print spaces\n            for (int j = 5 - i; j >= 1; j--) {\n                System.out.print(\" \");\n            }\n            \n            // Print numbers\n            for (int k = 1; k <= 2 * i - 1; k++) {\n                System.out.print(i);\n            }\n            \n            System.out.println();\n        }\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => "Right Triangle Pattern:\n*\n**\n***\n****\n*****\n\nNumbered Pyramid Pattern:\n    1\n   222\n  33333\n 4444444\n555555555"]
            ]),
            'hints' => json_encode([
                'Use nested loops - outer loop for rows, inner loop(s) for columns',
                'For the right triangle, print "*" j times where j ranges from 1 to i',
                'For the pyramid, you need to print spaces first, then numbers',
                'To print on the same line, use System.out.print() instead of System.out.println()'
            ]),
            'difficulty' => 2,
            'points' => 25,
            'order_index' => 2,
            'is_required' => true,
        ]);
        
        // Create Java Exception Handling Lesson Plan
        $javaExceptions = LessonPlan::create([
            'title' => 'Java Exception Handling',
            'description' => 'Learn how to handle errors and exceptions in Java applications.',
            'topic_id' => $javaTopic->id,
            'difficulty_level' => 3,
            'estimated_minutes' => 150,
            'learning_objectives' => 'Understand exception hierarchy, try-catch blocks, throws declaration, and custom exceptions.',
            'prerequisites' => 'Java Fundamentals, Java Control Flow',
            'resources' => json_encode(['Oracle Exception Handling Documentation', 'Java Error Handling Best Practices']),
            'is_published' => true,
        ]);
        
        // Create module for Java Exception Handling
        $exModule = LessonModule::create([
            'lesson_plan_id' => $javaExceptions->id,
            'title' => 'Try-Catch Blocks',
            'order_index' => 1,
            'description' => 'Learn how to handle exceptions using try-catch blocks in Java.',
            'content' => "# Try-Catch Blocks in Java\n\nException handling is a mechanism to handle runtime errors.\n\nIn this module, you'll learn about:\n- Exception hierarchy in Java\n- try-catch blocks\n- Multiple catch blocks\n- finally block\n- try-with-resources statement",
            'examples' => "```java\n// Basic try-catch\ntry {\n    int result = 10 / 0;  // ArithmeticException\n    System.out.println(result);\n} catch (ArithmeticException e) {\n    System.out.println(\"Cannot divide by zero: \" + e.getMessage());\n}\n\n// Multiple catch blocks\ntry {\n    int[] numbers = {1, 2, 3};\n    System.out.println(numbers[5]);  // ArrayIndexOutOfBoundsException\n} catch (ArithmeticException e) {\n    System.out.println(\"Arithmetic error: \" + e.getMessage());\n} catch (ArrayIndexOutOfBoundsException e) {\n    System.out.println(\"Array index error: \" + e.getMessage());\n} catch (Exception e) {\n    System.out.println(\"General error: \" + e.getMessage());\n}\n\n// try-catch-finally\ntry {\n    String str = null;\n    System.out.println(str.length());  // NullPointerException\n} catch (NullPointerException e) {\n    System.out.println(\"Null reference: \" + e.getMessage());\n} finally {\n    System.out.println(\"This will always execute\");\n}\n\n// try-with-resources (Java 7+)\nimport java.io.FileReader;\nimport java.io.IOException;\n\ntry (FileReader reader = new FileReader(\"file.txt\")) {\n    // File operations\n    char[] buffer = new char[100];\n    reader.read(buffer);\n} catch (IOException e) {\n    System.out.println(\"IO error: \" + e.getMessage());\n}\n```",
            'key_points' => json_encode([
                'Exceptions are objects that extend the Exception class',
                'try-catch blocks allow you to handle exceptions gracefully',
                'Catch blocks should be ordered from most specific to most general',
                'The finally block always executes, regardless of whether an exception occurs',
                'try-with-resources automatically closes resources like files and database connections'
            ]),
            'teaching_strategy' => json_encode(['Use real-world scenarios to illustrate when and how exceptions can occur.']),
            'estimated_minutes' => 45,
            'is_published' => true,
        ]);
        
        // Create exercise for Exception Handling Module
        LessonExercise::create([
            'module_id' => $exModule->id,
            'title' => 'SafeDivision Calculator',
            'type' => 'coding',
            'description' => 'Practice exception handling by implementing a division calculator that handles errors gracefully.',
            'instructions' => "Create a division calculator that:\n1. Takes two integers as input\n2. Handles division by zero using try-catch\n3. Handles invalid input (e.g., non-numeric input) using try-catch\n4. Uses a finally block to display a completion message",
            'starter_code' => "import java.util.Scanner;\n\npublic class SafeDivisionCalculator {\n    public static void main(String[] args) {\n        Scanner scanner = new Scanner(System.in);\n        \n        System.out.print(\"Enter numerator: \");\n        String numeratorInput = \"10\"; // In a real scenario, this would be scanner.nextLine();\n        \n        System.out.print(\"Enter denominator: \");\n        String denominatorInput = \"0\"; // In a real scenario, this would be scanner.nextLine();\n        \n        // Implement division with exception handling\n        \n        \n        // scanner.close(); // In a real scenario, close the scanner\n    }\n}",
            'solution' => json_encode([
                "import java.util.Scanner;\n\npublic class SafeDivisionCalculator {\n    public static void main(String[] args) {\n        Scanner scanner = new Scanner(System.in);\n        \n        System.out.print(\"Enter numerator: \");\n        String numeratorInput = \"10\"; // In a real scenario, this would be scanner.nextLine();\n        \n        System.out.print(\"Enter denominator: \");\n        String denominatorInput = \"0\"; // In a real scenario, this would be scanner.nextLine();\n        \n        // Implement division with exception handling\n        try {\n            int numerator = Integer.parseInt(numeratorInput);\n            int denominator = Integer.parseInt(denominatorInput);\n            \n            int result = divide(numerator, denominator);\n            System.out.println(\"Result: \" + result);\n            \n        } catch (NumberFormatException e) {\n            System.out.println(\"Error: Invalid input. Please enter integers only.\");\n        } catch (ArithmeticException e) {\n            System.out.println(\"Error: \" + e.getMessage());\n        } finally {\n            System.out.println(\"Division operation completed.\");\n            // scanner.close(); // In a real scenario, close the scanner\n        }\n    }\n    \n    public static int divide(int numerator, int denominator) {\n        if (denominator == 0) {\n            throw new ArithmeticException(\"Cannot divide by zero\");\n        }\n        return numerator / denominator;\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => "Enter numerator: Enter denominator: Error: Cannot divide by zero\nDivision operation completed."]
            ]),
            'hints' => json_encode([
                'Use Integer.parseInt() to convert strings to integers',
                'Handle NumberFormatException for invalid input',
                'Handle ArithmeticException for division by zero',
                'Create a separate method for division logic',
                'Use a finally block for cleanup code'
            ]),
            'difficulty' => 3,
            'points' => 30,
            'order_index' => 1,
            'is_required' => true,
        ]);
    }
} 