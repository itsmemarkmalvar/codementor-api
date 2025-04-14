<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\LearningTopic;

class AdvancedJavaLessonPlanSeeder extends Seeder
{
    /**
     * Run the database seeds for advanced Java topics.
     */
    public function run(): void
    {
        // Find the Java Advanced Concepts topic
        $advancedJavaTopic = LearningTopic::where('title', 'Java Advanced Concepts')->first();
        
        if (!$advancedJavaTopic) {
            $this->command->info('Java Advanced Concepts topic not found. Skipping lesson plan creation.');
            return;
        }
        
        // Find the Java Enterprise Development topic
        $enterpriseJavaTopic = LearningTopic::where('title', 'Java Enterprise Development')->first();
        
        if (!$enterpriseJavaTopic) {
            $this->command->info('Java Enterprise Development topic not found. Skipping lesson plan creation.');
            return;
        }
        
        // Create lesson plans for Java Advanced Concepts
        $this->createAdvancedJavaLessonPlans($advancedJavaTopic);
        
        // Create lesson plans for Java Enterprise Development
        $this->createEnterpriseJavaLessonPlans($enterpriseJavaTopic);
        
        $this->command->info('Successfully created lesson plans for advanced Java topics.');
    }
    
    /**
     * Create lesson plans for Java Advanced Concepts
     */
    private function createAdvancedJavaLessonPlans($topic)
    {
        // Check if lesson plans already exist for this topic to prevent duplicates
        $existingPlans = LessonPlan::where('topic_id', $topic->id)->count();
        if ($existingPlans > 0) {
            $this->command->info('Lesson plans for Java Advanced Concepts already exist. Skipping to prevent duplicates.');
            return;
        }
        
        // Create Java Concurrency Lesson Plan
        $javaConcurrency = LessonPlan::create([
            'title' => 'Java Concurrency',
            'description' => 'Learn advanced concurrency and multithreading in Java.',
            'topic_id' => $topic->id,
            'difficulty_level' => 2,
            'estimated_minutes' => 180,
            'learning_objectives' => 'Master threads, synchronization, locks, and concurrent collections in Java.',
            'prerequisites' => 'Java Fundamentals, Java Object-Oriented Programming',
            'resources' => json_encode(['Java Concurrency in Practice', 'Oracle Multithreading Documentation']),
            'is_published' => true,
        ]);
        
        // Create module for Java Concurrency
        $concurrencyModule1 = LessonModule::create([
            'lesson_plan_id' => $javaConcurrency->id,
            'title' => 'Thread Fundamentals',
            'order_index' => 1,
            'description' => 'Learn the basics of multithreading in Java.',
            'content' => "# Thread Fundamentals in Java\n\nThreads allow Java applications to perform multiple tasks simultaneously.\n\nIn this module, you'll learn about:\n- Creating and starting threads\n- Thread lifecycle\n- Thread priorities\n- Thread synchronization\n- The Thread and Runnable interfaces",
            'examples' => "```java\n// Creating a thread by extending Thread class\nclass MyThread extends Thread {\n    public void run() {\n        for (int i = 1; i <= 5; i++) {\n            System.out.println(\"Thread using Thread class: \" + i);\n            try {\n                Thread.sleep(500);\n            } catch (InterruptedException e) {\n                System.out.println(e);\n            }\n        }\n    }\n}\n\n// Creating a thread by implementing Runnable interface\nclass MyRunnable implements Runnable {\n    public void run() {\n        for (int i = 1; i <= 5; i++) {\n            System.out.println(\"Thread using Runnable: \" + i);\n            try {\n                Thread.sleep(500);\n            } catch (InterruptedException e) {\n                System.out.println(e);\n            }\n        }\n    }\n}\n\npublic class ThreadExample {\n    public static void main(String[] args) {\n        MyThread thread1 = new MyThread();\n        thread1.start();\n        \n        Thread thread2 = new Thread(new MyRunnable());\n        thread2.start();\n    }\n}\n```",
            'key_points' => json_encode([
                'Threads can be created by extending Thread class or implementing Runnable interface',
                'Thread lifecycle includes states: New, Runnable, Blocked, Waiting, Timed Waiting, and Terminated',
                'Thread priorities range from 1 (lowest) to 10 (highest)',
                'Synchronization prevents thread interference and memory consistency errors'
            ]),
            'teaching_strategy' => json_encode(['Start with basic thread creation before moving to synchronization and more complex concepts.']),
            'estimated_minutes' => 60,
            'is_published' => true,
        ]);
        
        // Create exercise for Thread Fundamentals
        LessonExercise::create([
            'module_id' => $concurrencyModule1->id,
            'title' => 'Bank Account Thread Safety',
            'type' => 'coding',
            'description' => 'Practice thread synchronization by implementing a thread-safe bank account.',
            'instructions' => "Create a thread-safe BankAccount class that allows concurrent deposits and withdrawals:\n\n1. Create a BankAccount class with a balance field and synchronized deposit() and withdraw() methods\n2. Create a WithdrawTask class implementing Runnable that attempts to withdraw a fixed amount\n3. Create a DepositTask class implementing Runnable that deposits a fixed amount\n4. In the main method, create multiple deposit and withdraw threads operating on the same account\n5. Ensure the final balance is correct after all threads complete execution",
            'starter_code' => "public class BankAccount {\n    private double balance;\n    \n    public BankAccount(double initialBalance) {\n        balance = initialBalance;\n    }\n    \n    // Add synchronized methods here\n    \n    public double getBalance() {\n        return balance;\n    }\n}\n\nclass WithdrawTask implements Runnable {\n    // Implement this class\n}\n\nclass DepositTask implements Runnable {\n    // Implement this class\n}\n\npublic class ThreadSafetyExample {\n    public static void main(String[] args) {\n        BankAccount account = new BankAccount(1000);\n        \n        // Create and start threads here\n        \n        // Wait for all threads to complete\n        \n        System.out.println(\"Final Balance: \" + account.getBalance());\n    }\n}",
            'solution' => json_encode([
                "public class BankAccount {\n    private double balance;\n    \n    public BankAccount(double initialBalance) {\n        balance = initialBalance;\n    }\n    \n    public synchronized void deposit(double amount) {\n        if (amount > 0) {\n            balance += amount;\n            System.out.println(\"Deposit: \" + amount + \", New Balance: \" + balance);\n        }\n    }\n    \n    public synchronized boolean withdraw(double amount) {\n        if (amount > 0 && balance >= amount) {\n            balance -= amount;\n            System.out.println(\"Withdraw: \" + amount + \", New Balance: \" + balance);\n            return true;\n        }\n        return false;\n    }\n    \n    public double getBalance() {\n        return balance;\n    }\n}\n\nclass WithdrawTask implements Runnable {\n    private BankAccount account;\n    private double amount;\n    \n    public WithdrawTask(BankAccount account, double amount) {\n        this.account = account;\n        this.amount = amount;\n    }\n    \n    public void run() {\n        for (int i = 0; i < 5; i++) {\n            account.withdraw(amount);\n            try {\n                Thread.sleep(100);\n            } catch (InterruptedException e) {\n                e.printStackTrace();\n            }\n        }\n    }\n}\n\nclass DepositTask implements Runnable {\n    private BankAccount account;\n    private double amount;\n    \n    public DepositTask(BankAccount account, double amount) {\n        this.account = account;\n        this.amount = amount;\n    }\n    \n    public void run() {\n        for (int i = 0; i < 5; i++) {\n            account.deposit(amount);\n            try {\n                Thread.sleep(100);\n            } catch (InterruptedException e) {\n                e.printStackTrace();\n            }\n        }\n    }\n}\n\npublic class ThreadSafetyExample {\n    public static void main(String[] args) {\n        BankAccount account = new BankAccount(1000);\n        \n        Thread withdrawThread = new Thread(new WithdrawTask(account, 100));\n        Thread depositThread = new Thread(new DepositTask(account, 200));\n        \n        withdrawThread.start();\n        depositThread.start();\n        \n        try {\n            withdrawThread.join();\n            depositThread.join();\n        } catch (InterruptedException e) {\n            e.printStackTrace();\n        }\n        \n        System.out.println(\"Final Balance: \" + account.getBalance());\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => 'Final Balance: 1500']
            ]),
            'hints' => json_encode([
                'Use the synchronized keyword on methods that modify the account balance',
                'Remember to handle the case where a withdrawal amount exceeds the available balance',
                'Use Thread.sleep() to simulate real-world timing issues',
                'Use thread.join() to wait for threads to complete before checking the final balance'
            ]),
            'difficulty' => 3,
            'points' => 30,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        // Create Java Collections Lesson Plan
        $javaCollections = LessonPlan::create([
            'title' => 'Advanced Java Collections',
            'description' => 'Master the Java Collections Framework and advanced data structures.',
            'topic_id' => $topic->id,
            'difficulty_level' => 2,
            'estimated_minutes' => 150,
            'learning_objectives' => 'Understand and implement advanced collection operations, concurrency collections, and custom collections.',
            'prerequisites' => 'Java Fundamentals, Java Data Structures',
            'resources' => json_encode(['Java Collections Framework Documentation', 'Effective Java']),
            'is_published' => true,
        ]);
        
        // Create module for Advanced Collections
        $collectionsModule = LessonModule::create([
            'lesson_plan_id' => $javaCollections->id,
            'title' => 'Concurrent Collections',
            'order_index' => 1,
            'description' => 'Learn about thread-safe collections in Java.',
            'content' => "# Concurrent Collections in Java\n\nThe java.util.concurrent package provides thread-safe collection implementations.\n\nIn this module, you'll learn about:\n- ConcurrentHashMap\n- CopyOnWriteArrayList\n- ConcurrentLinkedQueue\n- BlockingQueue implementations\n- When to use concurrent collections",
            'examples' => "```java\nimport java.util.concurrent.ConcurrentHashMap;\nimport java.util.concurrent.CopyOnWriteArrayList;\nimport java.util.concurrent.ConcurrentLinkedQueue;\nimport java.util.concurrent.BlockingQueue;\nimport java.util.concurrent.LinkedBlockingQueue;\n\npublic class ConcurrentCollectionsExample {\n    public static void main(String[] args) throws InterruptedException {\n        // ConcurrentHashMap example\n        ConcurrentHashMap<String, Integer> map = new ConcurrentHashMap<>();\n        map.put(\"One\", 1);\n        map.put(\"Two\", 2);\n        map.put(\"Three\", 3);\n        \n        // Safe to modify while iterating\n        for (String key : map.keySet()) {\n            System.out.println(key + \": \" + map.get(key));\n            map.put(\"Four\", 4);  // Won't cause ConcurrentModificationException\n        }\n        \n        // CopyOnWriteArrayList example\n        CopyOnWriteArrayList<String> list = new CopyOnWriteArrayList<>();\n        list.add(\"A\");\n        list.add(\"B\");\n        \n        for (String s : list) {\n            System.out.println(s);\n            list.add(\"C\");  // Won't cause ConcurrentModificationException\n        }\n        \n        // BlockingQueue example\n        BlockingQueue<String> queue = new LinkedBlockingQueue<>();\n        \n        // Producer thread\n        new Thread(() -> {\n            try {\n                queue.put(\"Task 1\");\n                System.out.println(\"Added Task 1\");\n                queue.put(\"Task 2\");\n                System.out.println(\"Added Task 2\");\n            } catch (InterruptedException e) {\n                e.printStackTrace();\n            }\n        }).start();\n        \n        // Consumer thread\n        new Thread(() -> {\n            try {\n                Thread.sleep(1000);  // Wait a bit\n                System.out.println(\"Processed: \" + queue.take());\n                System.out.println(\"Processed: \" + queue.take());\n            } catch (InterruptedException e) {\n                e.printStackTrace();\n            }\n        }).start();\n        \n        Thread.sleep(2000);  // Wait for both threads to finish\n    }\n}\n```",
            'key_points' => json_encode([
                'Concurrent collections provide thread-safe access without external synchronization',
                'ConcurrentHashMap allows concurrent reads and a limited number of concurrent writes',
                'CopyOnWriteArrayList creates a fresh copy of the underlying array when modified',
                'BlockingQueue implementations support operations that wait for the queue to become non-empty or have space available',
                'Concurrent collections generally offer better scalability than synchronized collections'
            ]),
            'teaching_strategy' => json_encode(['Compare concurrent collections with their non-concurrent counterparts to understand their advantages and trade-offs.']),
            'estimated_minutes' => 50,
            'is_published' => true,
        ]);
        
        // Create exercise for Advanced Collections
        LessonExercise::create([
            'module_id' => $collectionsModule->id,
            'title' => 'Producer-Consumer Pattern',
            'type' => 'coding',
            'description' => 'Implement the producer-consumer pattern using BlockingQueue.',
            'instructions' => "Implement a producer-consumer pattern using BlockingQueue:\n\n1. Create a Producer class that generates items and puts them in a BlockingQueue\n2. Create a Consumer class that takes items from the BlockingQueue and processes them\n3. Run multiple producers and consumers in separate threads\n4. Ensure thread safety and proper coordination between producers and consumers",
            'starter_code' => "import java.util.concurrent.BlockingQueue;\nimport java.util.concurrent.LinkedBlockingQueue;\n\nclass Producer implements Runnable {\n    // Implement this class\n}\n\nclass Consumer implements Runnable {\n    // Implement this class\n}\n\npublic class ProducerConsumerExample {\n    public static void main(String[] args) {\n        // Create a BlockingQueue\n        \n        // Create and start producer and consumer threads\n        \n        // Wait for all threads to complete\n    }\n}",
            'solution' => json_encode([
                "import java.util.concurrent.BlockingQueue;\nimport java.util.concurrent.LinkedBlockingQueue;\nimport java.util.concurrent.ThreadLocalRandom;\n\nclass Producer implements Runnable {\n    private final BlockingQueue<Integer> queue;\n    private final int id;\n    \n    public Producer(BlockingQueue<Integer> queue, int id) {\n        this.queue = queue;\n        this.id = id;\n    }\n    \n    @Override\n    public void run() {\n        try {\n            for (int i = 0; i < 5; i++) {\n                int number = ThreadLocalRandom.current().nextInt(100);\n                queue.put(number);\n                System.out.println(\"Producer \" + id + \" produced: \" + number);\n                Thread.sleep(ThreadLocalRandom.current().nextInt(100, 300));\n            }\n        } catch (InterruptedException e) {\n            Thread.currentThread().interrupt();\n        }\n    }\n}\n\nclass Consumer implements Runnable {\n    private final BlockingQueue<Integer> queue;\n    private final int id;\n    \n    public Consumer(BlockingQueue<Integer> queue, int id) {\n        this.queue = queue;\n        this.id = id;\n    }\n    \n    @Override\n    public void run() {\n        try {\n            while (true) {\n                Integer value = queue.take();\n                System.out.println(\"Consumer \" + id + \" consumed: \" + value);\n                Thread.sleep(ThreadLocalRandom.current().nextInt(200, 500));\n            }\n        } catch (InterruptedException e) {\n            Thread.currentThread().interrupt();\n        }\n    }\n}\n\npublic class ProducerConsumerExample {\n    public static void main(String[] args) {\n        // Create a BlockingQueue with capacity 5\n        BlockingQueue<Integer> queue = new LinkedBlockingQueue<>(5);\n        \n        // Create and start producer threads\n        Thread producer1 = new Thread(new Producer(queue, 1));\n        Thread producer2 = new Thread(new Producer(queue, 2));\n        \n        // Create and start consumer threads\n        Thread consumer1 = new Thread(new Consumer(queue, 1));\n        Thread consumer2 = new Thread(new Consumer(queue, 2));\n        \n        producer1.start();\n        producer2.start();\n        consumer1.start();\n        consumer2.start();\n        \n        // Wait for producers to finish\n        try {\n            producer1.join();\n            producer2.join();\n            \n            // Let consumers run for a bit then interrupt them\n            Thread.sleep(3000);\n            consumer1.interrupt();\n            consumer2.interrupt();\n        } catch (InterruptedException e) {\n            e.printStackTrace();\n        }\n        \n        System.out.println(\"Simulation completed.\");\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => 'Simulation completed.']
            ]),
            'hints' => json_encode([
                'Use LinkedBlockingQueue as the BlockingQueue implementation',
                'Producers should use the put() method to add items to the queue',
                'Consumers should use the take() method to remove items from the queue',
                'Use Thread.sleep() to simulate processing time',
                'Handle InterruptedException properly'
            ]),
            'difficulty' => 3,
            'points' => 35,
            'order_index' => 1,
            'is_required' => true,
        ]);
    }
    
    /**
     * Create lesson plans for Java Enterprise Development
     */
    private function createEnterpriseJavaLessonPlans($topic)
    {
        // Check if lesson plans already exist for this topic to prevent duplicates
        $existingPlans = LessonPlan::where('topic_id', $topic->id)->count();
        if ($existingPlans > 0) {
            $this->command->info('Lesson plans for Java Enterprise Development already exist. Skipping to prevent duplicates.');
            return;
        }
        
        // Create Spring Framework Lesson Plan
        $springFramework = LessonPlan::create([
            'title' => 'Spring Framework Essentials',
            'description' => 'Learn the fundamentals of the Spring Framework for enterprise Java applications.',
            'topic_id' => $topic->id,
            'difficulty_level' => 3,
            'estimated_minutes' => 240,
            'learning_objectives' => 'Understand Spring Core, Dependency Injection, Spring MVC, and Spring Boot.',
            'prerequisites' => 'Java Fundamentals, Java Object-Oriented Programming, Java Advanced Concepts',
            'resources' => json_encode(['Spring Documentation', 'Spring in Action Book']),
            'is_published' => true,
        ]);
        
        // Create module for Spring Framework
        $springModule = LessonModule::create([
            'lesson_plan_id' => $springFramework->id,
            'title' => 'Spring Boot Introduction',
            'order_index' => 1,
            'description' => 'Learn how to build enterprise applications with Spring Boot.',
            'content' => "# Spring Boot Introduction\n\nSpring Boot is an extension of the Spring Framework that simplifies the initial setup and development of new Spring applications.\n\nIn this module, you'll learn about:\n- Spring Boot starters\n- Auto-configuration\n- Creating RESTful services with Spring Boot\n- Spring Boot application properties\n- Building and deploying Spring Boot applications",
            'examples' => "```java\n// A simple Spring Boot application\nimport org.springframework.boot.SpringApplication;\nimport org.springframework.boot.autoconfigure.SpringBootApplication;\nimport org.springframework.web.bind.annotation.GetMapping;\nimport org.springframework.web.bind.annotation.RestController;\n\n@SpringBootApplication\npublic class MySpringBootApp {\n    public static void main(String[] args) {\n        SpringApplication.run(MySpringBootApp.class, args);\n    }\n}\n\n@RestController\nclass HelloController {\n    @GetMapping(\"/hello\")\n    public String hello() {\n        return \"Hello, Spring Boot!\";\n    }\n}\n```\n\n```xml\n<!-- Maven POM file for Spring Boot -->\n<dependencies>\n    <dependency>\n        <groupId>org.springframework.boot</groupId>\n        <artifactId>spring-boot-starter-web</artifactId>\n    </dependency>\n    <dependency>\n        <groupId>org.springframework.boot</groupId>\n        <artifactId>spring-boot-starter-test</artifactId>\n        <scope>test</scope>\n    </dependency>\n</dependencies>\n```",
            'key_points' => json_encode([
                'Spring Boot reduces boilerplate configuration',
                'Spring Boot starters provide dependency descriptors for common use cases',
                'Auto-configuration automatically configures beans based on the classpath contents',
                'Spring Boot applications can be packaged as stand-alone JAR files with embedded servers',
                'Spring Boot has sensible defaults but allows overriding through properties files'
            ]),
            'teaching_strategy' => json_encode(['Begin with a working Spring Boot application and gradually explore its components and features.']),
            'estimated_minutes' => 80,
            'is_published' => true,
        ]);
        
        // Create exercise for Spring Framework
        LessonExercise::create([
            'module_id' => $springModule->id,
            'title' => 'RESTful API with Spring Boot',
            'type' => 'coding',
            'description' => 'Build a RESTful API using Spring Boot.',
            'instructions' => "Create a RESTful API for a simple book management system using Spring Boot:\n\n1. Create a Book model class with id, title, author, and year fields\n2. Implement a BookRepository to manage book data (using an in-memory List for simplicity)\n3. Create a BookController with endpoints for:\n   - GET /api/books - list all books\n   - GET /api/books/{id} - get a book by id\n   - POST /api/books - add a new book\n   - PUT /api/books/{id} - update a book\n   - DELETE /api/books/{id} - delete a book\n4. Implement proper error handling for book not found and bad requests",
            'starter_code' => "import org.springframework.boot.SpringApplication;\nimport org.springframework.boot.autoconfigure.SpringBootApplication;\n\n@SpringBootApplication\npublic class BookManagementApplication {\n    public static void main(String[] args) {\n        SpringApplication.run(BookManagementApplication.class, args);\n    }\n}\n\n// Create Book model class here\n\n// Create BookRepository here\n\n// Create BookController here",
            'solution' => json_encode([
                "import java.util.ArrayList;\nimport java.util.List;\nimport java.util.Optional;\nimport java.util.concurrent.atomic.AtomicLong;\n\nimport org.springframework.boot.SpringApplication;\nimport org.springframework.boot.autoconfigure.SpringBootApplication;\nimport org.springframework.http.HttpStatus;\nimport org.springframework.http.ResponseEntity;\nimport org.springframework.stereotype.Repository;\nimport org.springframework.web.bind.annotation.*;\n\n@SpringBootApplication\npublic class BookManagementApplication {\n    public static void main(String[] args) {\n        SpringApplication.run(BookManagementApplication.class, args);\n    }\n}\n\n// Book model class\nclass Book {\n    private Long id;\n    private String title;\n    private String author;\n    private int year;\n    \n    public Book() { }\n    \n    public Book(Long id, String title, String author, int year) {\n        this.id = id;\n        this.title = title;\n        this.author = author;\n        this.year = year;\n    }\n    \n    // Getters and setters\n    public Long getId() { return id; }\n    public void setId(Long id) { this.id = id; }\n    \n    public String getTitle() { return title; }\n    public void setTitle(String title) { this.title = title; }\n    \n    public String getAuthor() { return author; }\n    public void setAuthor(String author) { this.author = author; }\n    \n    public int getYear() { return year; }\n    public void setYear(int year) { this.year = year; }\n}\n\n// Book repository\n@Repository\nclass BookRepository {\n    private final List<Book> books = new ArrayList<>();\n    private final AtomicLong idGenerator = new AtomicLong(1);\n    \n    public BookRepository() {\n        // Add some initial data\n        books.add(new Book(idGenerator.getAndIncrement(), \"Effective Java\", \"Joshua Bloch\", 2018));\n        books.add(new Book(idGenerator.getAndIncrement(), \"Clean Code\", \"Robert C. Martin\", 2008));\n    }\n    \n    public List<Book> findAll() {\n        return books;\n    }\n    \n    public Optional<Book> findById(Long id) {\n        return books.stream()\n                .filter(book -> book.getId().equals(id))\n                .findFirst();\n    }\n    \n    public Book save(Book book) {\n        if (book.getId() == null) {\n            book.setId(idGenerator.getAndIncrement());\n            books.add(book);\n        } else {\n            deleteById(book.getId());\n            books.add(book);\n        }\n        return book;\n    }\n    \n    public boolean deleteById(Long id) {\n        return books.removeIf(book -> book.getId().equals(id));\n    }\n}\n\n// Book controller\n@RestController\n@RequestMapping(\"/api/books\")\nclass BookController {\n    private final BookRepository bookRepository;\n    \n    public BookController(BookRepository bookRepository) {\n        this.bookRepository = bookRepository;\n    }\n    \n    @GetMapping\n    public List<Book> getAllBooks() {\n        return bookRepository.findAll();\n    }\n    \n    @GetMapping(\"/{id}\")\n    public ResponseEntity<Book> getBookById(@PathVariable Long id) {\n        return bookRepository.findById(id)\n                .map(ResponseEntity::ok)\n                .orElse(ResponseEntity.notFound().build());\n    }\n    \n    @PostMapping\n    @ResponseStatus(HttpStatus.CREATED)\n    public Book createBook(@RequestBody Book book) {\n        book.setId(null);  // Ensure ID is generated by the repository\n        return bookRepository.save(book);\n    }\n    \n    @PutMapping(\"/{id}\")\n    public ResponseEntity<Book> updateBook(@PathVariable Long id, @RequestBody Book book) {\n        if (!bookRepository.findById(id).isPresent()) {\n            return ResponseEntity.notFound().build();\n        }\n        book.setId(id); // Ensure ID matches path variable\n        return ResponseEntity.ok(bookRepository.save(book));\n    }\n    \n    @DeleteMapping(\"/{id}\")\n    public ResponseEntity<Void> deleteBook(@PathVariable Long id) {\n        if (!bookRepository.findById(id).isPresent()) {\n            return ResponseEntity.notFound().build();\n        }\n        bookRepository.deleteById(id);\n        return ResponseEntity.noContent().build();\n    }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => 'HTTP 200 OK']
            ]),
            'hints' => json_encode([
                'Use the @SpringBootApplication annotation on the main class',
                'Create a Book class with appropriate fields and getters/setters',
                'Implement a simple BookRepository using an ArrayList to store books',
                'Use @RestController, @RequestMapping, and other appropriate annotations for the controller',
                'Handle potential errors like book not found with appropriate HTTP status codes'
            ]),
            'difficulty' => 4,
            'points' => 50,
            'order_index' => 1,
            'is_required' => true,
        ]);
        
        // Create JPA and Hibernate Lesson Plan
        $jpaHibernate = LessonPlan::create([
            'title' => 'JPA and Hibernate',
            'description' => 'Learn how to use Java Persistence API (JPA) and Hibernate for database interactions.',
            'topic_id' => $topic->id,
            'difficulty_level' => 3,
            'estimated_minutes' => 210,
            'learning_objectives' => 'Master JPA annotations, entity relationships, transactions, and Hibernate features.',
            'prerequisites' => 'Java Fundamentals, SQL Basics, Spring Framework',
            'resources' => json_encode(['Hibernate Documentation', 'JPA Specification']),
            'is_published' => true,
        ]);
        
        // Create module for JPA and Hibernate
        $jpaModule = LessonModule::create([
            'lesson_plan_id' => $jpaHibernate->id,
            'title' => 'Entity Relationships',
            'order_index' => 1,
            'description' => 'Learn how to define relationships between JPA entities.',
            'content' => "# JPA Entity Relationships\n\nEntity relationships define how database tables are related to each other in the object model.\n\nIn this module, you'll learn about:\n- One-to-One relationships\n- One-to-Many relationships\n- Many-to-Many relationships\n- Cascade operations\n- Fetch strategies (EAGER vs. LAZY)",
            'examples' => "```java\nimport jakarta.persistence.*;\nimport java.util.ArrayList;\nimport java.util.List;\n\n// One-to-Many relationship example\n@Entity\nclass Department {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    private String name;\n    \n    @OneToMany(mappedBy = \"department\", cascade = CascadeType.ALL, orphanRemoval = true)\n    private List<Employee> employees = new ArrayList<>();\n    \n    // Getters and setters\n}\n\n@Entity\nclass Employee {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    private String name;\n    private String position;\n    \n    @ManyToOne(fetch = FetchType.LAZY)\n    @JoinColumn(name = \"department_id\")\n    private Department department;\n    \n    // Getters and setters\n}\n\n// Many-to-Many relationship example\n@Entity\nclass Student {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    private String name;\n    \n    @ManyToMany\n    @JoinTable(\n        name = \"student_course\",\n        joinColumns = @JoinColumn(name = \"student_id\"),\n        inverseJoinColumns = @JoinColumn(name = \"course_id\")\n    )\n    private List<Course> courses = new ArrayList<>();\n    \n    // Getters and setters\n}\n\n@Entity\nclass Course {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    private String name;\n    private int credits;\n    \n    @ManyToMany(mappedBy = \"courses\")\n    private List<Student> students = new ArrayList<>();\n    \n    // Getters and setters\n}\n```",
            'key_points' => json_encode([
                'One-to-One: Each entity is related to exactly one instance of another entity',
                'One-to-Many: One entity can be related to multiple instances of another entity',
                'Many-to-Many: Multiple instances of one entity can be related to multiple instances of another entity',
                'Cascade operations determine how changes to a parent entity affect related child entities',
                'EAGER fetching loads related entities immediately, while LAZY fetching loads them only when accessed'
            ]),
            'teaching_strategy' => json_encode(['Start with simple relationships and gradually introduce more complex scenarios with bidirectional relationships and custom join tables.']),
            'estimated_minutes' => 70,
            'is_published' => true,
        ]);
        
        // Create exercise for JPA and Hibernate
        LessonExercise::create([
            'module_id' => $jpaModule->id,
            'title' => 'Blog Application Entities',
            'type' => 'coding',
            'description' => 'Design and implement JPA entities for a blog application.',
            'instructions' => "Design and implement JPA entities for a blog application with the following requirements:\n\n1. Create a User entity with id, username, email, and password fields\n2. Create a BlogPost entity with id, title, content, createdAt, and updatedAt fields\n3. Create a Comment entity with id, content, and createdAt fields\n4. Implement the following relationships:\n   - One-to-Many between User and BlogPost (a user can have many blog posts)\n   - One-to-Many between BlogPost and Comment (a blog post can have many comments)\n   - Many-to-One between Comment and User (a comment is written by one user)\n5. Add appropriate JPA annotations, cascading, and fetch strategies",
            'starter_code' => "import jakarta.persistence.*;\nimport java.time.LocalDateTime;\nimport java.util.List;\n\n// Implement the User entity\n\n// Implement the BlogPost entity\n\n// Implement the Comment entity",
            'solution' => json_encode([
                "import jakarta.persistence.*;\nimport java.time.LocalDateTime;\nimport java.util.ArrayList;\nimport java.util.List;\n\n@Entity\n@Table(name = \"users\")\npublic class User {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    @Column(nullable = false, unique = true)\n    private String username;\n    \n    @Column(nullable = false, unique = true)\n    private String email;\n    \n    @Column(nullable = false)\n    private String password;\n    \n    @OneToMany(mappedBy = \"author\", cascade = CascadeType.ALL, orphanRemoval = true)\n    private List<BlogPost> blogPosts = new ArrayList<>();\n    \n    @OneToMany(mappedBy = \"author\", cascade = CascadeType.ALL, orphanRemoval = true)\n    private List<Comment> comments = new ArrayList<>();\n    \n    // Getters and setters\n    public Long getId() { return id; }\n    public void setId(Long id) { this.id = id; }\n    \n    public String getUsername() { return username; }\n    public void setUsername(String username) { this.username = username; }\n    \n    public String getEmail() { return email; }\n    public void setEmail(String email) { this.email = email; }\n    \n    public String getPassword() { return password; }\n    public void setPassword(String password) { this.password = password; }\n    \n    public List<BlogPost> getBlogPosts() { return blogPosts; }\n    public void setBlogPosts(List<BlogPost> blogPosts) { this.blogPosts = blogPosts; }\n    \n    public List<Comment> getComments() { return comments; }\n    public void setComments(List<Comment> comments) { this.comments = comments; }\n    \n    // Helper methods for bidirectional relationship\n    public void addBlogPost(BlogPost blogPost) {\n        blogPosts.add(blogPost);\n        blogPost.setAuthor(this);\n    }\n    \n    public void removeBlogPost(BlogPost blogPost) {\n        blogPosts.remove(blogPost);\n        blogPost.setAuthor(null);\n    }\n}\n\n@Entity\n@Table(name = \"blog_posts\")\npublic class BlogPost {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    @Column(nullable = false)\n    private String title;\n    \n    @Column(nullable = false, length = 10000)\n    private String content;\n    \n    @Column(nullable = false)\n    private LocalDateTime createdAt;\n    \n    private LocalDateTime updatedAt;\n    \n    @ManyToOne(fetch = FetchType.LAZY)\n    @JoinColumn(name = \"user_id\", nullable = false)\n    private User author;\n    \n    @OneToMany(mappedBy = \"blogPost\", cascade = CascadeType.ALL, orphanRemoval = true)\n    private List<Comment> comments = new ArrayList<>();\n    \n    @PrePersist\n    protected void onCreate() {\n        createdAt = LocalDateTime.now();\n    }\n    \n    @PreUpdate\n    protected void onUpdate() {\n        updatedAt = LocalDateTime.now();\n    }\n    \n    // Getters and setters\n    public Long getId() { return id; }\n    public void setId(Long id) { this.id = id; }\n    \n    public String getTitle() { return title; }\n    public void setTitle(String title) { this.title = title; }\n    \n    public String getContent() { return content; }\n    public void setContent(String content) { this.content = content; }\n    \n    public LocalDateTime getCreatedAt() { return createdAt; }\n    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }\n    \n    public LocalDateTime getUpdatedAt() { return updatedAt; }\n    public void setUpdatedAt(LocalDateTime updatedAt) { this.updatedAt = updatedAt; }\n    \n    public User getAuthor() { return author; }\n    public void setAuthor(User author) { this.author = author; }\n    \n    public List<Comment> getComments() { return comments; }\n    public void setComments(List<Comment> comments) { this.comments = comments; }\n    \n    // Helper methods for bidirectional relationship\n    public void addComment(Comment comment) {\n        comments.add(comment);\n        comment.setBlogPost(this);\n    }\n    \n    public void removeComment(Comment comment) {\n        comments.remove(comment);\n        comment.setBlogPost(null);\n    }\n}\n\n@Entity\n@Table(name = \"comments\")\npublic class Comment {\n    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n    \n    @Column(nullable = false, length = 1000)\n    private String content;\n    \n    @Column(nullable = false)\n    private LocalDateTime createdAt;\n    \n    @ManyToOne(fetch = FetchType.LAZY)\n    @JoinColumn(name = \"user_id\", nullable = false)\n    private User author;\n    \n    @ManyToOne(fetch = FetchType.LAZY)\n    @JoinColumn(name = \"blog_post_id\", nullable = false)\n    private BlogPost blogPost;\n    \n    @PrePersist\n    protected void onCreate() {\n        createdAt = LocalDateTime.now();\n    }\n    \n    // Getters and setters\n    public Long getId() { return id; }\n    public void setId(Long id) { this.id = id; }\n    \n    public String getContent() { return content; }\n    public void setContent(String content) { this.content = content; }\n    \n    public LocalDateTime getCreatedAt() { return createdAt; }\n    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }\n    \n    public User getAuthor() { return author; }\n    public void setAuthor(User author) { this.author = author; }\n    \n    public BlogPost getBlogPost() { return blogPost; }\n    public void setBlogPost(BlogPost blogPost) { this.blogPost = blogPost; }\n}"
            ]),
            'test_cases' => json_encode([
                ['input' => '', 'output' => '']
            ]),
            'hints' => json_encode([
                'Use @Entity, @Id, and @GeneratedValue annotations for all entities',
                'Use @Column to specify constraints like nullable=false or unique=true',
                'Define @OneToMany and @ManyToOne for relationships',
                'For bidirectional relationships, use the mappedBy attribute on the parent side',
                'Consider using @PrePersist and @PreUpdate for automatic timestamp management',
                'Use ArrayList() for initializing collections to avoid NullPointerException'
            ]),
            'difficulty' => 4,
            'points' => 45,
            'order_index' => 1,
            'is_required' => true,
        ]);
    }
}
