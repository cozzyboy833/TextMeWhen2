<?php
// test_relative_time.php - Test the enhanced time parsing

require_once 'process_reminder.php';

echo "🧪 Testing Enhanced Time Parsing\n";
echo "================================\n\n";

// Create a test instance
$reminderSystem = new ReminderSystem();

// Test cases
$testReminders = [
    'message me in 5 minutes',
    'text me in 1 minute',
    'notify me in 2 hours',
    'remind me in 30 minutes',
    'when it is 3:45',
    'text me when the Jets game is over',
    'notify me when Apple stock hits $200'
];

foreach ($testReminders as $i => $reminder) {
    echo "Test " . ($i + 1) . ": \"$reminder\"\n";
    echo str_repeat('-', 50) . "\n";
    
    // Use reflection to access the private method
    $reflection = new ReflectionClass($reminderSystem);
    $method = $reflection->getMethod('parseReminderWithEnhancedAI');
    $method->setAccessible(true);
    
    $result = $method->invoke($reminderSystem, $reminder);
    
    echo "Event Type: " . ($result['event_type'] ?? 'unknown') . "\n";
    echo "Entity: " . ($result['entity'] ?? 'unknown') . "\n";
    echo "Condition: " . ($result['condition'] ?? 'unknown') . "\n";
    
    if (isset($result['target_time']) && $result['target_time']) {
        echo "Target Time: " . date('M j, Y g:i A', strtotime($result['target_time'])) . "\n";
        $minutesFromNow = round((strtotime($result['target_time']) - time()) / 60, 1);
        echo "Minutes from now: $minutesFromNow\n";
    }
    
    if (isset($result['value']) && $result['value']) {
        echo "Value: " . $result['value'] . "\n";
    }
    
    echo "Keywords: " . implode(', ', $result['keywords'] ?? []) . "\n";
    echo "\n";
}

// Test what's in the database
echo "📊 Current Active Reminders in Database:\n";
echo "========================================\n";

try {
    $stmt = $reminderSystem->pdo->query("SELECT id, original_text, parsed_data, created_at FROM reminders WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($reminders)) {
        echo "No active reminders found. Create some using the web form!\n";
    } else {
        foreach ($reminders as $reminder) {
            echo "ID: " . $reminder['id'] . "\n";
            echo "Text: " . $reminder['original_text'] . "\n";
            
            $parsed = json_decode($reminder['parsed_data'], true);
            if (isset($parsed['target_time']) && $parsed['target_time']) {
                echo "Target: " . date('M j, Y g:i A', strtotime($parsed['target_time'])) . "\n";
                $minutesLeft = round((strtotime($parsed['target_time']) - time()) / 60, 1);
                echo "Status: ";
                if ($minutesLeft > 0) {
                    echo "⏳ $minutesLeft minutes remaining\n";
                } else {
                    echo "🎯 READY TO TRIGGER!\n";
                }
            } else {
                echo "Type: " . ($parsed['event_type'] ?? 'unknown') . "\n";
            }
            echo "Created: " . $reminder['created_at'] . "\n";
            echo "---\n";
        }
    }
} catch (Exception $e) {
    echo "Error reading database: " . $e->getMessage() . "\n";
}

echo "\n✅ Test complete!\n";
echo "\n💡 To test the system:\n";
echo "1. Start web server: php -S localhost:8000\n";
echo "2. Go to: http://localhost:8000/index.html\n";
echo "3. Create reminder: 'message me in 2 minutes'\n";
echo "4. Run monitor: php serpapi_monitor.php\n";
echo "5. Wait 2 minutes and run monitor again!\n";
?>