<?php
// inspect_database_fixed.php - Safe database analysis with error handling

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "📊 TEXTMEWHEN DATABASE ANALYSIS (Fixed)\n";
    echo "======================================\n\n";
    
    // First, let's check the database structure
    echo "🔍 DATABASE STRUCTURE CHECK:\n";
    echo "============================\n";
    
    // Check what tables exist
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n";
    
    // Check reminders table structure
    if (in_array('reminders', $tables)) {
        $stmt = $pdo->query("PRAGMA table_info(reminders)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nReminders table columns:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['name'] . " (" . $column['type'] . ")\n";
        }
    }
    
    echo "\n";
    
    // 1. OVERVIEW STATISTICS (with safe array access)
    echo "📈 OVERVIEW:\n";
    echo "------------\n";
    
    // Total reminders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders");
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total Reminders: $totalCount\n";
    
    // Check if status column exists and get counts
    try {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reminders GROUP BY status");
        $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($statusCounts)) {
            echo "By Status:\n";
            foreach ($statusCounts as $status) {
                $statusName = $status['status'] ?? 'unknown';
                echo "  " . ucfirst($statusName) . ": " . $status['count'] . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Status column not available or has issues\n";
    }
    
    // 2. RECENT REMINDERS WITH SAFE ACCESS
    echo "\n📝 ALL REMINDERS (Safe Analysis):\n";
    echo "=================================\n";
    
    $stmt = $pdo->query("SELECT * FROM reminders ORDER BY created_at DESC");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($reminders)) {
        echo "No reminders found in database.\n";
        exit;
    }
    
    foreach ($reminders as $i => $reminder) {
        echo "\n--- Reminder #" . ($i + 1) . " ---\n";
        echo "ID: " . ($reminder['id'] ?? 'N/A') . "\n";
        echo "Email: " . ($reminder['email'] ?? 'N/A') . "\n";
        echo "Original Text: \"" . ($reminder['original_text'] ?? 'N/A') . "\"\n";
        echo "Status: " . ($reminder['status'] ?? 'unknown') . "\n";
        echo "Created: " . ($reminder['created_at'] ?? 'N/A') . "\n";
        
        if (isset($reminder['completed_at']) && $reminder['completed_at']) {
            echo "Completed: " . $reminder['completed_at'] . "\n";
        }
        
        $currentChecks = $reminder['current_checks'] ?? 0;
        $maxChecks = $reminder['max_checks'] ?? 0;
        echo "Checks: $currentChecks/$maxChecks\n";
        
        if (isset($reminder['last_checked']) && $reminder['last_checked']) {
            echo "Last Checked: " . $reminder['last_checked'] . "\n";
        }
        
        // Safely parse JSON data
        $parsedData = null;
        if (isset($reminder['parsed_data']) && $reminder['parsed_data']) {
            $parsedData = json_decode($reminder['parsed_data'], true);
            
            if ($parsedData) {
                echo "\nParsed Data:\n";
                echo "  Event Type: " . ($parsedData['event_type'] ?? 'unknown') . "\n";
                echo "  Entity: " . ($parsedData['entity'] ?? 'unknown') . "\n";
                echo "  Condition: " . ($parsedData['condition'] ?? 'unknown') . "\n";
                
                if (isset($parsedData['value']) && $parsedData['value'] !== null) {
                    echo "  Value: " . $parsedData['value'] . "\n";
                }
                
                if (isset($parsedData['target_time']) && $parsedData['target_time']) {
                    echo "  Target Time: " . date('M j, Y g:i A', strtotime($parsedData['target_time'])) . "\n";
                    
                    $now = time();
                    $target = strtotime($parsedData['target_time']);
                    $diff = $target - $now;
                    
                    if ($diff > 0) {
                        $minutes = round($diff / 60, 1);
                        echo "  Status: ⏳ $minutes minutes remaining\n";
                    } else {
                        $minutes = round(abs($diff) / 60, 1);
                        echo "  Status: 🎯 OVERDUE by $minutes minutes\n";
                    }
                }
                
                if (isset($parsedData['keywords']) && is_array($parsedData['keywords'])) {
                    echo "  Keywords: " . implode(', ', $parsedData['keywords']) . "\n";
                }
            } else {
                echo "\nParsed Data: Invalid JSON\n";
            }
        } else {
            echo "\nParsed Data: Not available\n";
        }
    }
    
    // 3. EVENT TYPE ANALYSIS
    echo "\n🧠 EVENT TYPE ANALYSIS:\n";
    echo "=======================\n";
    
    $eventTypes = array();
    $timeBasedCount = 0;
    $overdueCount = 0;
    
    foreach ($reminders as $reminder) {
        $parsedData = json_decode($reminder['parsed_data'] ?? '{}', true);
        $eventType = $parsedData['event_type'] ?? 'unknown';
        
        $eventTypes[$eventType] = ($eventTypes[$eventType] ?? 0) + 1;
        
        // Count time-based reminders
        if (in_array($eventType, ['time_relative', 'time_absolute'])) {
            $timeBasedCount++;
            
            // Check if overdue
            if (isset($parsedData['target_time'])) {
                $targetTime = strtotime($parsedData['target_time']);
                if ($targetTime < time()) {
                    $overdueCount++;
                }
            }
        }
    }
    
    echo "Event Type Distribution:\n";
    foreach ($eventTypes as $type => $count) {
        $percentage = round(($count / count($reminders)) * 100, 1);
        echo "  " . ucfirst(str_replace('_', ' ', $type)) . ": $count ($percentage%)\n";
    }
    
    echo "\nTime-based Reminders: $timeBasedCount\n";
    echo "Currently Overdue: $overdueCount\n";
    
    // 4. NOTIFICATION ANALYSIS
    echo "\n📧 NOTIFICATION ANALYSIS:\n";
    echo "========================\n";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM notification_log");
        $notificationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Total Notifications Sent: $notificationCount\n";
        
        if ($notificationCount > 0) {
            $stmt = $pdo->query("SELECT * FROM notification_log ORDER BY sent_at DESC LIMIT 5");
            $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nRecent Notifications:\n";
            foreach ($recentNotifications as $notification) {
                echo "  ID " . ($notification['reminder_id'] ?? 'N/A');
                echo " → " . ($notification['recipient'] ?? 'N/A');
                echo " at " . ($notification['sent_at'] ?? 'N/A') . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Notification log not available or has issues\n";
    }
    
    // 5. RECOMMENDATIONS
    echo "\n💡 QUICK INSIGHTS:\n";
    echo "==================\n";
    
    $insights = array();
    
    if (count($reminders) < 5) {
        $insights[] = "You have " . count($reminders) . " reminders - create more to get better insights";
    }
    
    if ($timeBasedCount > 0) {
        $insights[] = "You have $timeBasedCount time-based reminders";
        if ($overdueCount > 0) {
            $insights[] = "$overdueCount time-based reminders are overdue - run the monitor!";
        }
    }
    
    $mostCommonType = array_search(max($eventTypes), $eventTypes);
    $insights[] = "Most common reminder type: " . str_replace('_', ' ', $mostCommonType);
    
    if (!empty($insights)) {
        foreach ($insights as $insight) {
            echo "- $insight\n";
        }
    }
    
    echo "\n🚀 NEXT STEPS:\n";
    echo "==============\n";
    echo "1. Run monitor: php serpapi_monitor.php\n";
    echo "2. View dashboard: http://localhost:8000/dashboard.php\n";
    echo "3. Test more reminders for better data\n";
    
    // 6. SIMPLE TRAINING DATA EXPORT
    echo "\n🤖 SIMPLE TRAINING DATA:\n";
    echo "========================\n";
    
    $trainingData = array();
    foreach ($reminders as $reminder) {
        $parsedData = json_decode($reminder['parsed_data'] ?? '{}', true);
        
        $trainingData[] = array(
            'input' => $reminder['original_text'] ?? '',
            'event_type' => $parsedData['event_type'] ?? 'unknown',
            'has_target_time' => isset($parsedData['target_time']) && !empty($parsedData['target_time']),
            'status' => $reminder['status'] ?? 'unknown'
        );
    }
    
    echo "Generated " . count($trainingData) . " training examples\n";
    
    if (!empty($trainingData)) {
        echo "Sample:\n";
        echo json_encode($trainingData[0], JSON_PRETTY_PRINT) . "\n";
    }
    
    // Save simple data
    file_put_contents('simple_training_data.json', json_encode($trainingData, JSON_PRETTY_PRINT));
    echo "✅ Saved to: simple_training_data.json\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure database file exists: " . ($config['database']['path'] ?? 'config missing') . "\n";
    echo "2. Run database setup: php setup_database.php\n";
    echo "3. Create some reminders first via the web form\n";
}
?>