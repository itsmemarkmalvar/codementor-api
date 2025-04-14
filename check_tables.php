<?php

// Set up database connection using the .env file
$env = file_get_contents('.env');
preg_match('/DB_HOST=(.*)/', $env, $host);
preg_match('/DB_DATABASE=(.*)/', $env, $database);
preg_match('/DB_USERNAME=(.*)/', $env, $username);
preg_match('/DB_PASSWORD=(.*)/', $env, $password);

$host = trim($host[1]);
$database = trim($database[1]);
$username = trim($username[1]);
$password = trim($password[1]);

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Database Tables:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nMigration Status:\n";
    $stmt = $pdo->query('SELECT * FROM migrations');
    $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($migrations as $migration) {
        echo "- {$migration['migration']} (Batch: {$migration['batch']})\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 