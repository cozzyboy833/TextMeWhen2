<?php
// export_training_data.php - Export data for machine learning

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🤖 EXPORTING TRAINING DATA FOR ML\n";
    echo "=================================\n\n";
    
    // Get all reminders
    $stmt = $pdo->query("SELECT * FROM reminders ORDER BY created_at DESC");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all notifications
    $stmt = $pdo->query("SELECT reminder_id, type, sent_at, status FROM notification_log");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Index notifications by reminder_id
    $notificationsByReminder = array();
    foreach ($notifications as $notification) {
        $notificationsByReminder[$notification['reminder_id']] = $notification;
    }
    
    // 1. INTENT CLASSIFICATION DATASET
    echo "📋 1. INTENT CLASSIFICATION DATASET\n";
    echo "====================================\n";
    
    $intentData = array();
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        
        $intentData[] = array(
            'text' => $reminder['original_text'],
            'intent' => $parsed['event_type'] ?? 'unknown',
            'confidence' => 1.0  // All human-labeled data is high confidence
        );
    }
    
    $intentFile = 'training_data_intent_classification.json';
    file_put_contents($intentFile, json_encode($intentData, JSON_PRETTY_PRINT));
    echo "✅ Saved to: $intentFile (" . count($intentData) . " examples)\n\n";
    
    // 2. TIME EXTRACTION DATASET  
    echo "⏰ 2. TIME EXTRACTION DATASET\n";
    echo "=============================\n";
    
    $timeData = array();
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $text = $reminder['original_text'];
        
        // Time extraction labels
        $hasRelativeTime = false;
        $hasAbsoluteTime = false;
        $timeValue = null;
        $timeUnit = null;
        
        // Check for relative time patterns
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', $text, $matches)) {
            $hasRelativeTime = true;
            $timeValue = intval($matches[1]);
            $timeUnit = strtolower($matches[2]);
        }
        
        // Check for absolute time patterns
        if (preg_match('/when it is (\d{1,2}):?(\d{2})?/i', $text, $matches)) {
            $hasAbsoluteTime = true;
            $timeValue = $matches[1] . ':' . ($matches[2] ?? '00');
        }
        
        $timeData[] = array(
            'text' => $text,
            'has_relative_time' => $hasRelativeTime,
            'has_absolute_time' => $hasAbsoluteTime,
            'time_value' => $timeValue,
            'time_unit' => $timeUnit,
            'target_time' => $parsed['target_time'] ?? null,
            'parsed_correctly' => ($parsed['event_type'] === 'time_relative' || $parsed['event_type'] === 'time_absolute')
        );
    }
    
    $timeFile = 'training_data_time_extraction.json';
    file_put_contents($timeFile, json_encode($timeData, JSON_PRETTY_PRINT));
    echo "✅ Saved to: $timeFile (" . count($timeData) . " examples)\n\n";
    
    // 3. ENTITY EXTRACTION DATASET
    echo "🏷️  3. ENTITY EXTRACTION DATASET\n";
    echo "================================\n";
    
    $entityData = array();
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $text = $reminder['original_text'];
        
        // Extract entities mentioned
        $entities = array();
        
        // Sports teams
        if (preg_match_all('/\b(jets|giants|knicks|yankees|mets|lakers|warriors)\b/i', $text, $matches)) {
            foreach ($matches[1] as $team) {
                $entities[] = array('entity' => ucfirst(strtolower($team)), 'type' => 'sports_team');
            }
        }
        
        // Companies/Stocks
        if (preg_match_all('/\b(apple|tesla|google|microsoft|amazon)\b/i', $text, $matches)) {
            foreach ($matches[1] as $company) {
                $entities[] = array('entity' => ucfirst(strtolower($company)), 'type' => 'company');
            }
        }
        
        // Prices
        if (preg_match_all('/\$(\d+(?:\.\d{2})?)/i', $text, $matches)) {
            foreach ($matches[1] as $price) {
                $entities[] = array('entity' => '$' . $price, 'type' => 'price');
            }
        }
        
        $entityData[] = array(
            'text' => $text,
            'entities' => $entities,
            'parsed_entity' => $parsed['entity'] ?? null,
            'parsed_value' => $parsed['value'] ?? null
        );
    }
    
    $entityFile = 'training_data_entity_extraction.json';
    file_put_contents($entityFile, json_encode($entityData, JSON_PRETTY_PRINT));
    echo "✅ Saved to: $entityFile (" . count($entityData) . " examples)\n\n";
    
    // 4. SUCCESS PREDICTION DATASET
    echo "🎯 4. SUCCESS PREDICTION DATASET\n";
    echo "================================\n";
    
    $successData = array();
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $wasNotified = isset($notificationsByReminder[$reminder['id']]);
        
        $successData[] = array(
            'text' => $reminder['original_text'],
            'event_type' => $parsed['event_type'] ?? 'unknown',
            'has_target_time' => isset($parsed['target_time']) && !empty($parsed['target_time']),
            'check_count' => $reminder['current_checks'],
            'max_checks' => $reminder['max_checks'],
            'status' => $reminder['status'],
            'was_completed' => $reminder['status'] === 'completed',
            'was_notified' => $wasNotified,
            'success_score' => $wasNotified ? 1.0 : ($reminder['status'] === 'completed' ? 0.8 : 0.0)
        );
    }
    
    $successFile = 'training_data_success_prediction.json';
    file_put_contents($successFile, json_encode($successData, JSON_PRETTY_PRINT));
    echo "✅ Saved to: $successFile (" . count($successData) . " examples)\n\n";
    
    // 5. COMPREHENSIVE CSV EXPORT
    echo "📊 5. COMPREHENSIVE CSV EXPORT\n";
    echo "==============================\n";
    
    $csvFile = 'training_data_comprehensive.csv';
    $csvHandle = fopen($csvFile, 'w');
    
    // CSV headers
    $headers = array(
        'reminder_id', 'original_text', 'email', 'status', 'created_at', 'completed_at',
        'current_checks', 'max_checks', 'event_type', 'entity', 'condition', 'value',
        'target_time', 'keywords', 'was_notified', 'notification_sent_at',
        'parsing_confidence', 'time_accuracy'
    );
    
    fputcsv($csvHandle, $headers);
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $notification = $notificationsByReminder[$reminder['id']] ?? null;
        
        // Calculate parsing confidence (simple heuristic)
        $confidence = 0.5; // Default
        if (($parsed['event_type'] ?? '') !== 'generic') {
            $confidence = 0.8;
        }
        if (isset($parsed['target_time']) && !empty($parsed['target_time'])) {
            $confidence = 0.95;
        }
        
        // Calculate time accuracy for time-based reminders
        $timeAccuracy = null;
        if (isset($parsed['target_time']) && $notification) {
            $targetTime = strtotime($parsed['target_time']);
            $sentTime = strtotime($notification['sent_at']);
            $diffMinutes = abs($sentTime - $targetTime) / 60;
            $timeAccuracy = max(0, 1 - ($diffMinutes / 60)); // 1.0 = perfect, 0.0 = >1hr off
        }
        
        $row = array(
            $reminder['id'],
            $reminder['original_text'],
            $reminder['email'],
            $reminder['status'],
            $reminder['created_at'],
            $reminder['completed_at'],
            $reminder['current_checks'],
            $reminder['max_checks'],
            $parsed['event_type'] ?? '',
            $parsed['entity'] ?? '',
            $parsed['condition'] ?? '',
            $parsed['value'] ?? '',
            $parsed['target_time'] ?? '',
            implode(';', $parsed['keywords'] ?? []),
            $notification ? 'yes' : 'no',
            $notification ? $notification['sent_at'] : '',
            $confidence,
            $timeAccuracy
        );
        
        fputcsv($csvHandle, $row);
    }
    
    fclose($csvHandle);
    echo "✅ Saved to: $csvFile\n\n";
    
    // 6. SUMMARY STATISTICS
    echo "📈 EXPORT SUMMARY:\n";
    echo "==================\n";
    
    $totalReminders = count($reminders);
    $completedReminders = count(array_filter($reminders, function($r) { return $r['status'] === 'completed'; }));
    $notifiedReminders = count($notificationsByReminder);
    $timeBasedReminders = count(array_filter($reminders, function($r) {
        $parsed = json_decode($r['parsed_data'], true);
        return in_array($parsed['event_type'] ?? '', ['time_relative', 'time_absolute']);
    }));
    
    echo "Total Reminders: $totalReminders\n";
    echo "Completed: $completedReminders (" . round($completedReminders/$totalReminders*100, 1) . "%)\n";
    echo "Notified: $notifiedReminders (" . round($notifiedReminders/$totalReminders*100, 1) . "%)\n";
    echo "Time-based: $timeBasedReminders (" . round($timeBasedReminders/$totalReminders*100, 1) . "%)\n";
    
    echo "\n🎯 MODEL TRAINING SUGGESTIONS:\n";
    echo "==============================\n";
    echo "1. Intent Classification: Use intent classification dataset with transformer models\n";
    echo "2. Time Extraction: Train NER model on time extraction dataset\n"; 
    echo "3. Success Prediction: Use ensemble model on comprehensive CSV\n";
    echo "4. End-to-End: Fine-tune LLM on all your examples\n";
    
    echo "\n📁 Generated Files:\n";
    echo "- $intentFile\n";
    echo "- $timeFile\n";
    echo "- $entityFile\n";
    echo "- $successFile\n";
    echo "- $csvFile\n";
    
    echo "\n✅ Export Complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>