<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LearningTopic;

class LearningTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define Java learning topics
        
        // Root level topics
        $javaBasics = LearningTopic::create([
            'title' => 'Java Basics',
            'description' => 'Learn the fundamentals of Java programming including syntax, variables, and control flow.',
            'difficulty_level' => 'beginner',
            'order' => 1,
            'parent_id' => null,
            'learning_objectives' => 'Understand Java syntax, Create simple applications, Work with variables and data types',
            'prerequisites' => 'None',
            'estimated_hours' => '10-15',
            'icon' => 'book-open',
            'is_active' => true,
        ]);
        
        $oop = LearningTopic::create([
            'title' => 'Object-Oriented Programming',
            'description' => 'Master the principles of OOP in Java, including classes, objects, inheritance, and polymorphism.',
            'difficulty_level' => 'intermediate',
            'order' => 2,
            'parent_id' => null,
            'learning_objectives' => 'Create classes and objects, Implement inheritance, Apply polymorphism and encapsulation',
            'prerequisites' => 'Java Basics',
            'estimated_hours' => '15-20',
            'icon' => 'code',
            'is_active' => true,
        ]);
        
        $dataStructures = LearningTopic::create([
            'title' => 'Data Structures',
            'description' => 'Learn essential data structures in Java including arrays, lists, stacks, queues, and more.',
            'difficulty_level' => 'intermediate',
            'order' => 3,
            'parent_id' => null,
            'learning_objectives' => 'Implement basic data structures, Choose appropriate data structures for problems, Analyze performance of data structures',
            'prerequisites' => 'Java Basics, Object-Oriented Programming',
            'estimated_hours' => '20-25',
            'icon' => 'bar-chart',
            'is_active' => true,
        ]);
        
        $algorithms = LearningTopic::create([
            'title' => 'Algorithms',
            'description' => 'Explore common algorithms in Java for searching, sorting, and problem-solving.',
            'difficulty_level' => 'advanced',
            'order' => 4,
            'parent_id' => null,
            'learning_objectives' => 'Implement searching and sorting algorithms, Analyze algorithm efficiency, Apply algorithms to solve complex problems',
            'prerequisites' => 'Java Basics, Object-Oriented Programming, Data Structures',
            'estimated_hours' => '25-30',
            'icon' => 'zap',
            'is_active' => true,
        ]);
        
        // Java Basics subtopics
        LearningTopic::create([
            'title' => 'Java Syntax',
            'description' => 'Learn the basic syntax and structure of Java programs.',
            'difficulty_level' => 'beginner',
            'order' => 1,
            'parent_id' => $javaBasics->id,
            'learning_objectives' => 'Understand Java program structure, Write and run a simple Java program',
            'prerequisites' => 'None',
            'estimated_hours' => '2-3',
            'icon' => 'type',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Variables and Data Types',
            'description' => 'Learn about primitive and reference data types in Java.',
            'difficulty_level' => 'beginner',
            'order' => 2,
            'parent_id' => $javaBasics->id,
            'learning_objectives' => 'Declare and initialize variables, Understand data types and their limits, Convert between data types',
            'prerequisites' => 'Java Syntax',
            'estimated_hours' => '3-4',
            'icon' => 'box',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Control Flow',
            'description' => 'Learn about conditional statements and loops in Java.',
            'difficulty_level' => 'beginner',
            'order' => 3,
            'parent_id' => $javaBasics->id,
            'learning_objectives' => 'Use if-else statements, Implement loops, Handle switch statements',
            'prerequisites' => 'Variables and Data Types',
            'estimated_hours' => '3-4',
            'icon' => 'git-branch',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Methods',
            'description' => 'Learn how to create and use methods in Java.',
            'difficulty_level' => 'beginner',
            'order' => 4,
            'parent_id' => $javaBasics->id,
            'learning_objectives' => 'Define methods, Pass parameters, Return values, Understand method overloading',
            'prerequisites' => 'Control Flow',
            'estimated_hours' => '2-3',
            'icon' => 'function-square',
            'is_active' => true,
        ]);
        
        // OOP subtopics
        LearningTopic::create([
            'title' => 'Classes and Objects',
            'description' => 'Learn how to create and use classes and objects in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 1,
            'parent_id' => $oop->id,
            'learning_objectives' => 'Define classes, Create objects, Use fields and methods',
            'prerequisites' => 'Java Basics',
            'estimated_hours' => '4-5',
            'icon' => 'boxes',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Inheritance',
            'description' => 'Learn about inheritance and how to create a hierarchy of classes.',
            'difficulty_level' => 'intermediate',
            'order' => 2,
            'parent_id' => $oop->id,
            'learning_objectives' => 'Extend classes, Override methods, Use super keyword',
            'prerequisites' => 'Classes and Objects',
            'estimated_hours' => '3-4',
            'icon' => 'git-merge',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Polymorphism',
            'description' => 'Learn about polymorphism and how to implement it in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 3,
            'parent_id' => $oop->id,
            'learning_objectives' => 'Understand polymorphic methods, Use method overriding, Apply dynamic binding',
            'prerequisites' => 'Inheritance',
            'estimated_hours' => '3-4',
            'icon' => 'layers',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Encapsulation',
            'description' => 'Learn about encapsulation and access modifiers in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 4,
            'parent_id' => $oop->id,
            'learning_objectives' => 'Apply access modifiers, Create getters and setters, Protect data',
            'prerequisites' => 'Classes and Objects',
            'estimated_hours' => '2-3',
            'icon' => 'shield',
            'is_active' => true,
        ]);
        
        // Data Structures subtopics
        LearningTopic::create([
            'title' => 'Arrays',
            'description' => 'Learn about arrays and how to use them in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 1,
            'parent_id' => $dataStructures->id,
            'learning_objectives' => 'Create and initialize arrays, Access and modify array elements, Iterate through arrays',
            'prerequisites' => 'Java Basics',
            'estimated_hours' => '3-4',
            'icon' => 'table',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'ArrayLists',
            'description' => 'Learn about ArrayLists and the Collections framework in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 2,
            'parent_id' => $dataStructures->id,
            'learning_objectives' => 'Create and use ArrayLists, Manipulate list elements, Use Collections methods',
            'prerequisites' => 'Arrays',
            'estimated_hours' => '3-4',
            'icon' => 'list',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Linked Lists',
            'description' => 'Learn about linked lists and how to implement them in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 3,
            'parent_id' => $dataStructures->id,
            'learning_objectives' => 'Understand linked list structure, Implement singly and doubly linked lists, Compare with arrays',
            'prerequisites' => 'ArrayLists',
            'estimated_hours' => '4-5',
            'icon' => 'link',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Stacks and Queues',
            'description' => 'Learn about stacks and queues and how to use them in Java.',
            'difficulty_level' => 'intermediate',
            'order' => 4,
            'parent_id' => $dataStructures->id,
            'learning_objectives' => 'Implement stacks, Implement queues, Solve problems using stacks and queues',
            'prerequisites' => 'Linked Lists',
            'estimated_hours' => '4-5',
            'icon' => 'layers',
            'is_active' => true,
        ]);
        
        // Algorithms subtopics
        LearningTopic::create([
            'title' => 'Searching Algorithms',
            'description' => 'Learn about common searching algorithms in Java.',
            'difficulty_level' => 'advanced',
            'order' => 1,
            'parent_id' => $algorithms->id,
            'learning_objectives' => 'Implement linear search, Implement binary search, Analyze search efficiency',
            'prerequisites' => 'Data Structures',
            'estimated_hours' => '5-6',
            'icon' => 'search',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Sorting Algorithms',
            'description' => 'Learn about common sorting algorithms in Java.',
            'difficulty_level' => 'advanced',
            'order' => 2,
            'parent_id' => $algorithms->id,
            'learning_objectives' => 'Implement bubble sort, Implement quick sort, Implement merge sort, Compare algorithm efficiency',
            'prerequisites' => 'Searching Algorithms',
            'estimated_hours' => '6-7',
            'icon' => 'sort-asc',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Recursive Algorithms',
            'description' => 'Learn about recursion and how to implement recursive algorithms in Java.',
            'difficulty_level' => 'advanced',
            'order' => 3,
            'parent_id' => $algorithms->id,
            'learning_objectives' => 'Understand recursion, Implement recursive solutions, Analyze recursive efficiency',
            'prerequisites' => 'Sorting Algorithms',
            'estimated_hours' => '5-6',
            'icon' => 'repeat',
            'is_active' => true,
        ]);
        
        LearningTopic::create([
            'title' => 'Dynamic Programming',
            'description' => 'Learn about dynamic programming and how to implement DP solutions in Java.',
            'difficulty_level' => 'advanced',
            'order' => 4,
            'parent_id' => $algorithms->id,
            'learning_objectives' => 'Understand memoization, Solve optimization problems, Implement DP solutions',
            'prerequisites' => 'Recursive Algorithms',
            'estimated_hours' => '7-8',
            'icon' => 'table',
            'is_active' => true,
        ]);
    }
}
