<?php
// setup_database.php
// Run this once to create your SQLite database

$config = require 'config.php';

try {
    // Create SQLite database connection
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating SQLite database: " . $config['database']['path'] . "\n";
    
    // Read and execute schema
    $schema = file_get_contents('schema_sqlite.sql');
    if (!$schema) {
        throw new Exception("Could not read schema_sqlite.sql file");
    }
    
    $pdo->exec($schema);
    
    echo "Database tables created successfully!\n";
    echo "Database file location: " . realpath($config['database']['path']) . "\n";
    
    // Test the connection
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Created tables: " . implode(', ', $tables) . "\n";
    echo "Setup complete! You can now use the system.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>