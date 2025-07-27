<?php
// inspect_database.php - Comprehensive database analysis

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "📊 TEXTMEWHEN DATABASE ANALYSIS\n";
    echo "===============================\n\n";
    
    // 1. OVERVIEW STATISTICS
    echo "📈 OVERVIEW:\n";
    echo "------------\n";
    
    $stats = array();
    
    // Total reminders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // By status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reminders GROUP BY status");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total Reminders: " . $stats['total'] . "\n";
    foreach ($statusCounts as $status) {
        echo "  " . ucfirst($status['status']) . ": " . $status['count'] . "\n";
    }
    
    // By event type
    echo "\nEvent Types:\n";
    $stmt = $pdo->query("SELECT original_text, parsed_data FROM reminders");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $eventTypes = array();
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $type = $parsed['event_type'] ?? 'unknown';
        $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;
    }
    
    foreach ($eventTypes as $type => $count) {
        echo "  " . ucfirst($type) . ": $count\n";
    }
    
    // 2. RECENT REMINDERS DETAILED VIEW
    echo "\n📝 RECENT REMINDERS (Detailed):\n";
    echo "===============================\n";
    
    $stmt = $pdo->query("SELECT * FROM reminders ORDER BY created_at DESC LIMIT 10");
    $recentReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentReminders as $i => $reminder) {
        echo "\n--- Reminder #" . ($i + 1) . " ---\n";
        echo "ID: " . $reminder['id'] . "\n";
        echo "Email: " . $reminder['email'] . "\n";
        echo "Original Text: \"" . $reminder['original_text'] . "\"\n";
        echo "Status: " . $reminder['status'] . "\n";
        echo "Created: " . $reminder['created_at'] . "\n";
        
        if ($reminder['completed_at']) {
            echo "Completed: " . $reminder['completed_at'] . "\n";
        }
        
        echo "Checks: " . $reminder['current_checks'] . "/" . $reminder['max_checks'] . "\n";
        
        if ($reminder['last_checked']) {
            echo "Last Checked: " . $reminder['last_checked'] . "\n";
        }
        
        // Parse the JSON data
        $parsed = json_decode($reminder['parsed_data'], true);
        if ($parsed) {
            echo "\nParsed Data:\n";
            echo "  Event Type: " . ($parsed['event_type'] ?? 'unknown') . "\n";
            echo "  Entity: " . ($parsed['entity'] ?? 'unknown') . "\n";
            echo "  Condition: " . ($parsed['condition'] ?? 'unknown') . "\n";
            
            if (isset($parsed['value']) && $parsed['value'] !== null) {
                echo "  Value: " . $parsed['value'] . "\n";
            }
            
            if (isset($parsed['target_time']) && $parsed['target_time']) {
                echo "  Target Time: " . date('M j, Y g:i A', strtotime($parsed['target_time'])) . "\n";
                
                $now = time();
                $target = strtotime($parsed['target_time']);
                $diff = $target - $now;
                
                if ($diff > 0) {
                    $minutes = round($diff / 60, 1);
                    echo "  Status: ⏳ $minutes minutes remaining\n";
                } else {
                    $minutes = round(abs($diff) / 60, 1);
                    echo "  Status: 🎯 OVERDUE by $minutes minutes\n";
                }
            }
            
            if (isset($parsed['keywords']) && is_array($parsed['keywords'])) {
                echo "  Keywords: " . implode(', ', $parsed['keywords']) . "\n";
            }
        }
    }
    
    // 3. PARSING ACCURACY ANALYSIS
    echo "\n🧠 PARSING ACCURACY ANALYSIS:\n";
    echo "=============================\n";
    
    $parsingStats = array(
        'time_relative' => 0,
        'time_absolute' => 0,
        'sports_game' => 0,
        'stock_price' => 0,
        'weather' => 0,
        'generic' => 0,
        'unknown' => 0
    );
    
    $timeParsingExamples = array();
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $type = $parsed['event_type'] ?? 'unknown';
        
        if (isset($parsingStats[$type])) {
            $parsingStats[$type]++;
        } else {
            $parsingStats['unknown']++;
        }
        
        // Collect time parsing examples
        if ($type === 'time_relative' || $type === 'time_absolute') {
            $timeParsingExamples[] = array(
                'original' => $reminder['original_text'],
                'type' => $type,
                'target_time' => $parsed['target_time'] ?? null,
                'value' => $parsed['value'] ?? null
            );
        }
    }
    
    echo "Parsing Distribution:\n";
    foreach ($parsingStats as $type => $count) {
        if ($count > 0) {
            $percentage = round(($count / $stats['total']) * 100, 1);
            echo "  " . ucfirst(str_replace('_', ' ', $type)) . ": $count ($percentage%)\n";
        }
    }
    
    // 4. TIME PARSING EXAMPLES
    if (!empty($timeParsingExamples)) {
        echo "\n⏰ TIME PARSING EXAMPLES:\n";
        echo "========================\n";
        
        foreach ($timeParsingExamples as $example) {
            echo "Input: \"" . $example['original'] . "\"\n";
            echo "  Type: " . $example['type'] . "\n";
            
            if ($example['target_time']) {
                echo "  Target: " . date('M j, Y g:i A', strtotime($example['target_time'])) . "\n";
            }
            
            if ($example['value']) {
                echo "  Value: " . $example['value'] . " minutes\n";
            }
            
            echo "\n";
        }
    }
    
    // 5. NOTIFICATION LOG
    echo "📧 NOTIFICATION HISTORY:\n";
    echo "=======================\n";
    
    $stmt = $pdo->query("SELECT * FROM notification_log ORDER BY sent_at DESC LIMIT 5");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "No notifications sent yet.\n";
    } else {
        foreach ($notifications as $notification) {
            echo "Reminder ID: " . $notification['reminder_id'] . "\n";
            echo "To: " . $notification['recipient'] . "\n";
            echo "Subject: " . $notification['subject'] . "\n";
            echo "Type: " . $notification['type'] . "\n";
            echo "Sent: " . $notification['sent_at'] . "\n";
            echo "Status: " . $notification['status'] . "\n";
            echo "---\n";
        }
    }
    
    // 6. TRAINING DATA EXPORT PREVIEW
    echo "\n🤖 TRAINING DATA EXPORT PREVIEW:\n";
    echo "================================\n";
    echo "Here's how your data could be used for ML training:\n\n";
    
    $trainingExamples = array();
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        
        $trainingExamples[] = array(
            'input' => $reminder['original_text'],
            'event_type' => $parsed['event_type'] ?? 'unknown',
            'entity' => $parsed['entity'] ?? null,
            'condition' => $parsed['condition'] ?? null,
            'has_time' => isset($parsed['target_time']) && !empty($parsed['target_time']),
            'has_value' => isset($parsed['value']) && $parsed['value'] !== null,
            'status' => $reminder['status'],
            'was_triggered' => $reminder['status'] === 'completed'
        );
    }
    
    echo "Training Examples Generated: " . count($trainingExamples) . "\n";
    echo "Sample format:\n";
    
    if (!empty($trainingExamples)) {
        echo json_encode($trainingExamples[0], JSON_PRETTY_PRINT) . "\n";
    }
    
    echo "\n✅ Analysis Complete!\n";
    echo "\n💡 Insights:\n";
    echo "- You have " . $stats['total'] . " total test cases\n";
    echo "- Most common type: " . array_search(max($eventTypes), $eventTypes) . "\n";
    echo "- Notifications sent: " . count($notifications) . "\n";
    
    echo "\n🚀 Next Steps:\n";
    echo "- Export data: php export_training_data.php\n";
    echo "- Run monitor: php serpapi_monitor.php\n";
    echo "- View live data: php auto_monitor.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>