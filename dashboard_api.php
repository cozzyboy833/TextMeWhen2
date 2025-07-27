<?php
// dashboard_api.php - API endpoint for dashboard data

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Get statistics
    $stats = array();
    
    // Total reminders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders");
    $stats['total'] = intval($stmt->fetch(PDO::FETCH_ASSOC)['count']);
    
    // Active reminders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders WHERE status = 'active'");
    $stats['active'] = intval($stmt->fetch(PDO::FETCH_ASSOC)['count']);
    
    // Completed reminders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders WHERE status = 'completed'");
    $stats['completed'] = intval($stmt->fetch(PDO::FETCH_ASSOC)['count']);
    
    // Notifications sent
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notification_log");
    $stats['notifications'] = intval($stmt->fetch(PDO::FETCH_ASSOC)['count']);
    
    // 2. Get all active reminders with parsed data
    $stmt = $pdo->query("SELECT * FROM reminders WHERE status = 'active' ORDER BY created_at DESC");
    $activeReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process reminders to add computed fields
    $readyReminders = array();
    $normalActiveReminders = array();
    
    foreach ($activeReminders as $reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        
        // Add computed fields
        $reminder['event_type'] = $parsed['event_type'] ?? 'unknown';
        $reminder['entity'] = $parsed['entity'] ?? null;
        $reminder['target_time'] = $parsed['target_time'] ?? null;
        $reminder['time_remaining'] = null;
        $reminder['is_ready'] = false;
        
        // Calculate time remaining for time-based reminders
        if ($reminder['target_time']) {
            $targetTimestamp = strtotime($reminder['target_time']);
            $currentTimestamp = time();
            $diffSeconds = $targetTimestamp - $currentTimestamp;
            
            if ($diffSeconds <= 0) {
                $reminder['is_ready'] = true;
                $reminder['time_remaining'] = 'READY TO TRIGGER!';
            } else {
                $diffMinutes = round($diffSeconds / 60, 1);
                if ($diffMinutes < 60) {
                    $reminder['time_remaining'] = $diffMinutes . ' minutes remaining';
                } else {
                    $diffHours = round($diffMinutes / 60, 1);
                    $reminder['time_remaining'] = $diffHours . ' hours remaining';
                }
            }
        }
        
        // Categorize reminders
        if ($reminder['is_ready']) {
            $readyReminders[] = $reminder;
        } else {
            $normalActiveReminders[] = $reminder;
        }
    }
    
    // Count ready reminders
    $stats['ready'] = count($readyReminders);
    
    // 3. Get recently completed reminders
    $stmt = $pdo->query("SELECT * FROM reminders WHERE status = 'completed' ORDER BY completed_at DESC LIMIT 10");
    $completedReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add computed fields to completed reminders
    foreach ($completedReminders as &$reminder) {
        $parsed = json_decode($reminder['parsed_data'], true);
        $reminder['event_type'] = $parsed['event_type'] ?? 'unknown';
        $reminder['entity'] = $parsed['entity'] ?? null;
        $reminder['target_time'] = $parsed['target_time'] ?? null;
    }
    
    // 4. Get recent activity (last 24 hours)
    $stmt = $pdo->query("
        SELECT 'reminder' as type, created_at as timestamp, original_text as description 
        FROM reminders 
        WHERE datetime(created_at) > datetime('now', '-24 hours')
        
        UNION ALL
        
        SELECT 'notification' as type, sent_at as timestamp, 
               'Sent to ' || recipient as description
        FROM notification_log 
        WHERE datetime(sent_at) > datetime('now', '-24 hours')
        
        ORDER BY timestamp DESC 
        LIMIT 20
    ");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Prepare response
    $response = array(
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => $stats,
        'ready_reminders' => $readyReminders,
        'active_reminders' => $normalActiveReminders,
        'completed_reminders' => $completedReminders,
        'recent_activity' => $recentActivity
    );
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
        'stats' => array(
            'total' => 0,
            'active' => 0,
            'ready' => 0,
            'completed' => 0,
            'notifications' => 0
        ),
        'ready_reminders' => array(),
        'active_reminders' => array(),
        'completed_reminders' => array(),
        'recent_activity' => array()
    ));
}
?>