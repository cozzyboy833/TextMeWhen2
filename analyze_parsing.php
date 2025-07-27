<?php
// analyze_parsing.php - Analyze AI parsing accuracy and suggest improvements

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🧠 AI PARSING ACCURACY ANALYSIS\n";
    echo "===============================\n\n";
    
    // Get all reminders
    $stmt = $pdo->query("SELECT * FROM reminders ORDER BY created_at DESC");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($reminders)) {
        echo "No reminders found. Create some test cases first!\n";
        exit;
    }
    
    // Get notifications for success analysis
    $stmt = $pdo->query("SELECT reminder_id, sent_at FROM notification_log");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notificationMap = array();
    foreach ($notifications as $notif) {
        $notificationMap[$notif['reminder_id']] = $notif['sent_at'];
    }
    
    // 1. PARSING ACCURACY BY TYPE
    echo "📊 1. PARSING ACCURACY BY TYPE\n";
    echo "===============================\n";
    
    $typeAccuracy = array();
    $totalParsed = 0;
    $correctlyParsed = 0;
    
    foreach ($reminders as $reminder) {
        $text = strtolower($reminder['original_text']);
        $parsed = json_decode($reminder['parsed_data'], true);
        $parsedType = $parsed['event_type'] ?? 'unknown';
        
        // Determine what the type SHOULD be based on text analysis
        $expectedType = 'generic';
        
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', $text)) {
            $expectedType = 'time_relative';
        } elseif (preg_match('/when it is (\d{1,2}):?(\d{2})?/i', $text)) {
            $expectedType = 'time_absolute';
        } elseif (preg_match('/\b(jets|giants|knicks|yankees|mets|lakers|warriors)\b/i', $text)) {
            $expectedType = 'sports_game';
        } elseif (preg_match('/\b(stock|shares?)\b.*\$?(\d+)/i', $text)) {
            $expectedType = 'stock_price';
        } elseif (preg_match('/\b(weather|rain|snow|temperature)\b/i', $text)) {
            $expectedType = 'weather';
        }
        
        $isCorrect = ($parsedType === $expectedType);
        
        if (!isset($typeAccuracy[$expectedType])) {
            $typeAccuracy[$expectedType] = array('total' => 0, 'correct' => 0, 'examples' => array());
        }
        
        $typeAccuracy[$expectedType]['total']++;
        if ($isCorrect) {
            $typeAccuracy[$expectedType]['correct']++;
            $correctlyParsed++;
        } else {
            $typeAccuracy[$expectedType]['examples'][] = array(
                'text' => $reminder['original_text'],
                'expected' => $expectedType,
                'parsed' => $parsedType
            );
        }
        
        $totalParsed++;
    }
    
    $overallAccuracy = $totalParsed > 0 ? ($correctlyParsed / $totalParsed) * 100 : 0;
    
    echo "Overall Parsing Accuracy: " . round($overallAccuracy, 1) . "% ($correctlyParsed/$totalParsed)\n\n";
    
    foreach ($typeAccuracy as $type => $data) {
        $accuracy = $data['total'] > 0 ? ($data['correct'] / $data['total']) * 100 : 0;
        echo ucfirst(str_replace('_', ' ', $type)) . ": " . round($accuracy, 1) . "% ({$data['correct']}/{$data['total']})\n";
        
        if (!empty($data['examples'])) {
            echo "  Parsing Errors:\n";
            foreach (array_slice($data['examples'], 0, 3) as $example) {
                echo "    \"" . $example['text'] . "\"\n";
                echo "      Expected: " . $example['expected'] . ", Got: " . $example['parsed'] . "\n";
            }
        }
        echo "\n";
    }
    
    // 2. TIME PARSING ACCURACY
    echo "⏰ 2. TIME PARSING ACCURACY\n";
    echo "===========================\n";
    
    $timeReminders = array_filter($reminders, function($r) {
        $parsed = json_decode($r['parsed_data'], true);
        return in_array($parsed['event_type'] ?? '', ['time_relative', 'time_absolute']);
    });
    
    $timeAccuracyStats = array('total' => 0, 'accurate' => 0, 'examples' => array());
    
    foreach ($timeReminders as $reminder) {
        $text = $reminder['original_text'];
        $parsed = json_decode($reminder['parsed_data'], true);
        $timeAccuracyStats['total']++;
        
        // Check if notification was sent at the right time
        if (isset($notificationMap[$reminder['id']]) && isset($parsed['target_time'])) {
            $targetTime = strtotime($parsed['target_time']);
            $sentTime = strtotime($notificationMap[$reminder['id']]);
            $diffMinutes = abs($sentTime - $targetTime) / 60;
            
            if ($diffMinutes <= 5) { // Within 5 minutes is considered accurate
                $timeAccuracyStats['accurate']++;
            } else {
                $timeAccuracyStats['examples'][] = array(
                    'text' => $text,
                    'target' => date('H:i:s', $targetTime),
                    'sent' => date('H:i:s', $sentTime),
                    'diff_minutes' => round($diffMinutes, 1)
                );
            }
        }
    }
    
    if ($timeAccuracyStats['total'] > 0) {
        $timeAccuracy = ($timeAccuracyStats['accurate'] / $timeAccuracyStats['total']) * 100;
        echo "Time-based Reminders: " . count($timeReminders) . "\n";
        echo "Timing Accuracy: " . round($timeAccuracy, 1) . "% ({$timeAccuracyStats['accurate']}/{$timeAccuracyStats['total']})\n";
        
        if (!empty($timeAccuracyStats['examples'])) {
            echo "\nTiming Issues:\n";
            foreach ($timeAccuracyStats['examples'] as $example) {
                echo "  \"" . $example['text'] . "\"\n";
                echo "    Target: " . $example['target'] . ", Sent: " . $example['sent'];
                echo " (off by " . $example['diff_minutes'] . " min)\n";
            }
        }
    } else {
        echo "No time-based reminders with notifications yet.\n";
    }
    
    // 3. SUCCESS RATE ANALYSIS
    echo "\n🎯 3. SUCCESS RATE ANALYSIS\n";
    echo "===========================\n";
    
    $successStats = array();
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $type = $parsed['event_type'] ?? 'unknown';
        $wasNotified = isset($notificationMap[$reminder['id']]);
        $isCompleted = $reminder['status'] === 'completed';
        
        if (!isset($successStats[$type])) {
            $successStats[$type] = array('total' => 0, 'notified' => 0, 'completed' => 0);
        }
        
        $successStats[$type]['total']++;
        if ($wasNotified) $successStats[$type]['notified']++;
        if ($isCompleted) $successStats[$type]['completed']++;
    }
    
    foreach ($successStats as $type => $stats) {
        $notificationRate = $stats['total'] > 0 ? ($stats['notified'] / $stats['total']) * 100 : 0;
        $completionRate = $stats['total'] > 0 ? ($stats['completed'] / $stats['total']) * 100 : 0;
        
        echo ucfirst(str_replace('_', ' ', $type)) . " (" . $stats['total'] . " reminders):\n";
        echo "  Notification Rate: " . round($notificationRate, 1) . "% ({$stats['notified']}/{$stats['total']})\n";
        echo "  Completion Rate: " . round($completionRate, 1) . "% ({$stats['completed']}/{$stats['total']})\n\n";
    }
    
    // 4. IMPROVEMENT SUGGESTIONS
    echo "💡 4. IMPROVEMENT SUGGESTIONS\n";
    echo "=============================\n";
    
    if ($overallAccuracy < 80) {
        echo "❌ PARSING ACCURACY TOO LOW (" . round($overallAccuracy, 1) . "%)\n";
        echo "Suggestions:\n";
        echo "- Add more regex patterns for common phrases\n";
        echo "- Implement fuzzy matching for team/company names\n";
        echo "- Add training data for edge cases\n";
        echo "- Consider using OpenAI API for better parsing\n\n";
    } else {
        echo "✅ Good parsing accuracy (" . round($overallAccuracy, 1) . "%)\n\n";
    }
    
    // Find most common parsing errors
    $errorTypes = array();
    foreach ($typeAccuracy as $type => $data) {
        if (!empty($data['examples'])) {
            $errorTypes[$type] = count($data['examples']);
        }
    }
    
    if (!empty($errorTypes)) {
        arsort($errorTypes);
        echo "Most Common Parsing Errors:\n";
        foreach (array_slice($errorTypes, 0, 3, true) as $type => $count) {
            echo "- " . ucfirst(str_replace('_', ' ', $type)) . ": $count errors\n";
        }
        echo "\n";
    }
    
    // 5. TRAINING DATA RECOMMENDATIONS
    echo "🎓 5. TRAINING DATA RECOMMENDATIONS\n";
    echo "===================================\n";
    
    $recommendations = array();
    
    if (count($timeReminders) < 10) {
        $recommendations[] = "Add more time-based examples (currently: " . count($timeReminders) . ")";
    }
    
    $sportsReminders = count(array_filter($reminders, function($r) {
        $p = json_decode($r['parsed_data'], true);
        return ($p['event_type'] ?? '') === 'sports_game';
    }));
    
    if ($sportsReminders < 5) {
        $recommendations[] = "Add more sports-related examples (currently: $sportsReminders)";
    }
    
    if (count($reminders) < 20) {
        $recommendations[] = "Collect more training data overall (currently: " . count($reminders) . ")";
    }
    
    if (!empty($recommendations)) {
        foreach ($recommendations as $rec) {
            echo "- $rec\n";
        }
    } else {
        echo "✅ Good training data coverage!\n";
    }
    
    echo "\n📈 NEXT STEPS:\n";
    echo "==============\n";
    echo "1. Export training data: php export_training_data.php\n";
    echo "2. View dashboard: http://localhost:8000/dashboard.php\n";
    echo "3. Test more examples to improve accuracy\n";
    echo "4. Consider integrating OpenAI API for better parsing\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>