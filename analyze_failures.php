<?php
// analyze_failures.php - Analyze why your AI made mistakes

$config = require 'config.php';

echo "🔍 AI FAILURE ANALYSIS\n";
echo "======================\n\n";

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all reminders with their outcomes
    $stmt = $pdo->query("
        SELECT r.*, n.sent_at, n.status as notification_status 
        FROM reminders r 
        LEFT JOIN notification_log n ON r.id = n.reminder_id 
        ORDER BY r.created_at DESC
    ");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 ANALYZING " . count($reminders) . " REMINDERS:\n";
    echo "=====================================\n\n";
    
    foreach ($reminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $originalText = $reminder['original_text'];
        
        echo "--- REMINDER ANALYSIS ---\n";
        echo "📝 Original: \"$originalText\"\n";
        echo "🤖 AI Understood:\n";
        echo "   Event Type: " . ($parsed['event_type'] ?? 'unknown') . "\n";
        echo "   Entity: " . ($parsed['entity'] ?? 'unknown') . "\n";
        echo "   Condition: " . ($parsed['condition'] ?? 'unknown') . "\n";
        
        if (isset($parsed['target_time']) && $parsed['target_time']) {
            echo "   Target Time: " . $parsed['target_time'] . "\n";
        }
        
        echo "📋 Status: " . $reminder['status'] . "\n";
        echo "🔄 Checks: " . $reminder['current_checks'] . "/" . $reminder['max_checks'] . "\n";
        
        if ($reminder['sent_at']) {
            echo "📧 Notification sent: " . $reminder['sent_at'] . "\n";
            
            // Analyze timing accuracy for your Yankees example
            if (stripos($originalText, 'yankees') !== false && stripos($originalText, 'game') !== false) {
                echo "\n🏈 YANKEES GAME ANALYSIS:\n";
                echo "   You asked for: Game end notification\n";
                echo "   AI thought it was: " . ($parsed['event_type'] ?? 'unknown') . "\n";
                
                if (stripos($originalText, 'end') !== false) {
                    echo "   ⚠️  PROBLEM: You said 'ends' but AI may have missed this!\n";
                    echo "   🔧 FIX NEEDED: Train AI to recognize 'ends', 'over', 'finished'\n";
                }
                
                // Check if notification was sent at a reasonable time
                $sentTime = strtotime($reminder['sent_at']);
                $createdTime = strtotime($reminder['created_at']);
                $hoursDiff = ($sentTime - $createdTime) / 3600;
                
                echo "   ⏰ Notification sent " . round($hoursDiff, 1) . " hours after request\n";
                
                if ($hoursDiff > 6) {
                    echo "   ❌ ISSUE: Sent too late! Games don't last " . round($hoursDiff, 1) . " hours\n";
                    echo "   🔧 FIX: Improve game detection logic\n";
                }
            }
        } else {
            echo "📧 No notification sent yet\n";
        }
        
        // Suggest improvements
        echo "\n💡 IMPROVEMENT SUGGESTIONS:\n";
        
        if (stripos($originalText, 'end') !== false || stripos($originalText, 'over') !== false) {
            echo "   - Add 'game_end' as a specific event type\n";
            echo "   - Look for 'final score', 'game over', 'final:' keywords\n";
        }
        
        if (stripos($originalText, 'yankees') !== false || stripos($originalText, 'game') !== false) {
            echo "   - Improve sports team recognition\n";
            echo "   - Add real-time sports API integration\n";
            echo "   - Set maximum wait time for games (3-4 hours)\n";
        }
        
        if ($parsed['event_type'] === 'generic') {
            echo "   - This was classified as 'generic' - needs better pattern matching\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
    
    // Overall pattern analysis
    echo "🎯 OVERALL PATTERNS & ISSUES:\n";
    echo "=============================\n";
    
    $issues = [];
    $sportsMentions = 0;
    $timeMentions = 0;
    $endMentions = 0;
    
    foreach ($reminders as $reminder) {
        $text = strtolower($reminder['original_text']);
        $parsed = json_decode($reminder['parsed_data'], true);
        
        if (stripos($text, 'yankees') !== false || stripos($text, 'game') !== false) {
            $sportsMentions++;
        }
        
        if (stripos($text, 'end') !== false || stripos($text, 'over') !== false || stripos($text, 'finish') !== false) {
            $endMentions++;
        }
        
        if (isset($parsed['target_time'])) {
            $timeMentions++;
        }
        
        // Identify specific issues
        if ($parsed['event_type'] === 'generic' && stripos($text, 'game') !== false) {
            $issues[] = "Sports games being classified as 'generic'";
        }
        
        if (stripos($text, 'end') !== false && ($parsed['condition'] ?? '') !== 'game_over') {
            $issues[] = "'End' keywords not properly recognized";
        }
    }
    
    echo "📊 Pattern Statistics:\n";
    echo "   Sports-related requests: $sportsMentions\n";
    echo "   'End/Over/Finish' mentions: $endMentions\n";
    echo "   Time-based requests: $timeMentions\n";
    
    echo "\n❌ Identified Issues:\n";
    if (empty($issues)) {
        echo "   No obvious pattern issues found\n";
    } else {
        foreach (array_unique($issues) as $issue) {
            echo "   - $issue\n";
        }
    }
    
    echo "\n🔧 RECOMMENDED TRAINING IMPROVEMENTS:\n";
    echo "====================================\n";
    echo "1. Add specific patterns for 'game ends' vs 'game starts'\n";
    echo "2. Improve sports team recognition (Yankees, Jets, etc.)\n";
    echo "3. Add time-based constraints (games don't last >4 hours)\n";
    echo "4. Better keyword matching for 'over', 'finished', 'done'\n";
    echo "5. Add confidence scoring to catch uncertain classifications\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>