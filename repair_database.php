<?php
// repair_database.php - Fix database schema issues

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 DATABASE REPAIR TOOL\n";
    echo "=======================\n\n";
    
    // 1. Check current schema
    echo "1. CHECKING CURRENT SCHEMA:\n";
    echo "===========================\n";
    
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Existing tables: " . implode(', ', $tables) . "\n\n";
    
    // 2. Check reminders table structure
    if (in_array('reminders', $tables)) {
        echo "2. REMINDERS TABLE STRUCTURE:\n";
        echo "=============================\n";
        
        $stmt = $pdo->query("PRAGMA table_info(reminders)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $existingColumns = array();
        echo "Current columns:\n";
        foreach ($columns as $column) {
            $existingColumns[] = $column['name'];
            echo "  ✓ " . $column['name'] . " (" . $column['type'] . ")\n";
        }
        
        // 3. Required columns
        $requiredColumns = array(
            'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'email' => 'TEXT NOT NULL',
            'phone' => 'TEXT',
            'original_text' => 'TEXT NOT NULL',
            'parsed_data' => 'TEXT',
            'status' => 'TEXT DEFAULT \'active\'',
            'created_at' => 'TEXT DEFAULT (datetime(\'now\'))',
            'completed_at' => 'TEXT',
            'last_checked' => 'TEXT',
            'check_frequency' => 'INTEGER DEFAULT 300',
            'max_checks' => 'INTEGER DEFAULT 2880',
            'current_checks' => 'INTEGER DEFAULT 0'
        );
        
        echo "\n3. CHECKING REQUIRED COLUMNS:\n";
        echo "=============================\n";
        
        $missingColumns = array();
        foreach ($requiredColumns as $columnName => $columnDef) {
            if (in_array($columnName, $existingColumns)) {
                echo "  ✓ $columnName - OK\n";
            } else {
                echo "  ❌ $columnName - MISSING\n";
                $missingColumns[$columnName] = $columnDef;
            }
        }
        
        // 4. Add missing columns
        if (!empty($missingColumns)) {
            echo "\n4. ADDING MISSING COLUMNS:\n";
            echo "==========================\n";
            
            foreach ($missingColumns as $columnName => $columnDef) {
                try {
                    $sql = "ALTER TABLE reminders ADD COLUMN $columnName $columnDef";
                    $pdo->exec($sql);
                    echo "  ✅ Added: $columnName\n";
                } catch (Exception $e) {
                    echo "  ❌ Failed to add $columnName: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "\n✅ All required columns present!\n";
        }
        
        // 5. Fix NULL status values
        echo "\n5. FIXING NULL VALUES:\n";
        echo "======================\n";
        
        // Update NULL status values
        $stmt = $pdo->prepare("UPDATE reminders SET status = 'active' WHERE status IS NULL OR status = ''");
        $stmt->execute();
        $updatedRows = $stmt->rowCount();
        
        if ($updatedRows > 0) {
            echo "  ✅ Fixed $updatedRows reminders with NULL status\n";
        } else {
            echo "  ✓ No NULL status values found\n";
        }
        
        // Update NULL check values
        $stmt = $pdo->prepare("UPDATE reminders SET current_checks = 0 WHERE current_checks IS NULL");
        $stmt->execute();
        $updatedRows = $stmt->rowCount();
        
        if ($updatedRows > 0) {
            echo "  ✅ Fixed $updatedRows reminders with NULL current_checks\n";
        }
        
        $stmt = $pdo->prepare("UPDATE reminders SET check_frequency = 300 WHERE check_frequency IS NULL");
        $stmt->execute();
        
        $stmt = $pdo->prepare("UPDATE reminders SET max_checks = 2880 WHERE max_checks IS NULL");
        $stmt->execute();
        
    } else {
        echo "❌ Reminders table doesn't exist! Creating it...\n";
        
        // Create the table from scratch
        $createTableSQL = "CREATE TABLE IF NOT EXISTS reminders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            phone TEXT,
            original_text TEXT NOT NULL,
            parsed_data TEXT,
            status TEXT DEFAULT 'active',
            created_at TEXT DEFAULT (datetime('now')),
            completed_at TEXT,
            last_checked TEXT,
            check_frequency INTEGER DEFAULT 300,
            max_checks INTEGER DEFAULT 2880,
            current_checks INTEGER DEFAULT 0
        )";
        
        $pdo->exec($createTableSQL);
        echo "✅ Created reminders table\n";
    }
    
    // 6. Create other tables if missing
    echo "\n6. CHECKING OTHER TABLES:\n";
    echo "=========================\n";
    
    // Notification log table
    if (!in_array('notification_log', $tables)) {
        echo "Creating notification_log table...\n";
        $sql = "CREATE TABLE notification_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reminder_id INTEGER,
            type TEXT NOT NULL,
            recipient TEXT NOT NULL,
            subject TEXT,
            message TEXT,
            sent_at TEXT DEFAULT (datetime('now')),
            status TEXT DEFAULT 'sent',
            FOREIGN KEY (reminder_id) REFERENCES reminders(id)
        )";
        $pdo->exec($sql);
        echo "  ✅ Created notification_log table\n";
    } else {
        echo "  ✓ notification_log table exists\n";
    }
    
    // Search cache table
    if (!in_array('search_cache', $tables)) {
        echo "Creating search_cache table...\n";
        $sql = "CREATE TABLE search_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            search_query TEXT NOT NULL UNIQUE,
            results TEXT,
            cached_at TEXT DEFAULT (datetime('now')),
            expires_at TEXT
        )";
        $pdo->exec($sql);
        echo "  ✅ Created search_cache table\n";
    } else {
        echo "  ✓ search_cache table exists\n";
    }
    
    // 7. Final verification
    echo "\n7. FINAL VERIFICATION:\n";
    echo "======================\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders");
    $reminderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total reminders in database: $reminderCount\n";
    
    if ($reminderCount > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders WHERE status IS NOT NULL");
        $validStatusCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Reminders with valid status: $validStatusCount\n";
        
        if ($validStatusCount == $reminderCount) {
            echo "✅ All reminders have valid status values\n";
        }
    }
    
    echo "\n🎉 DATABASE REPAIR COMPLETE!\n";
    echo "=============================\n";
    echo "Your database should now work properly with all scripts.\n";
    echo "\nNext steps:\n";
    echo "1. Run: php inspect_database_fixed.php\n";
    echo "2. Test: http://localhost:8000/dashboard.php\n";
    echo "3. Monitor: php serpapi_monitor.php\n";
    
} catch (Exception $e) {
    echo "❌ Repair failed: " . $e->getMessage() . "\n";
    echo "\nTry recreating the database:\n";
    echo "1. Delete the .db file\n";
    echo "2. Run: php setup_database.php\n";
    echo "3. Create new test reminders\n";
}
?>