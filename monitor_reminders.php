<?php
// monitor_reminders.php
// This script should be run as a cron job every 5 minutes
// Add to crontab: */5 * * * * /usr/bin/php /path/to/monitor_reminders.php

require_once 'process_reminder.php'; // Include the main class

class ReminderMonitor extends ReminderSystem {
    
    public function runMonitoring() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting reminder monitoring...\n";
        
        try {
            // Get all active reminders that need checking
            $reminders = $this->getRemindersToCheck();
            
            echo "Found " . count($reminders) . " reminders to check.\n";
            
            foreach ($reminders as $reminder) {
                echo "Checking reminder ID: {$reminder['id']}\n";
                $this->checkReminder($reminder);
            }
            
            // Clean up old cache entries
            $this->cleanupCache();
            
        } catch (Exception $e) {
            echo "Error during monitoring: " . $e->getMessage() . "\n";
            error_log("Monitor error: " . $e->getMessage());
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Monitoring complete.\n";
    }
    
    private function getRemindersToCheck() {
        // SQLite date functions
        $sql = "SELECT * FROM reminders 
                WHERE status = 'active' 
                AND (last_checked IS NULL OR datetime(last_checked, '+' || check_frequency || ' seconds') < datetime('now'))
                AND current_checks < max_checks";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function checkReminder($reminder) {
        $parsedData = json_decode($reminder['parsed_data'], true);
        
        // Update check count and last checked time
        $this->updateReminderCheck($reminder['id']);
        
        $eventOccurred = false;
        
        switch ($parsedData['event_type']) {
            case 'sports_game':
                $eventOccurred = $this->checkSportsGame($parsedData);
                break;
            case 'stock_price':
                $eventOccurred = $this->checkStockPrice($parsedData);
                break;
            case 'weather':
                $eventOccurred = $this->checkWeather($parsedData);
                break;
            case 'product_announcement':
                $eventOccurred = $this->checkProductAnnouncement($parsedData);
                break;
            default:
                $eventOccurred = $this->checkGenericEvent($parsedData);
        }
        
        if ($eventOccurred) {
            echo "Event detected for reminder ID: {$reminder['id']}\n";
            $this->triggerNotification($reminder, $parsedData);
            $this->markReminderCompleted($reminder['id']);
        } else {
            echo "No event detected for reminder ID: {$reminder['id']}\n";
        }
    }
    
    private function checkSportsGame($parsedData) {
        // Search for game status
        $searchQuery = $parsedData['entity'] . " game today score final";
        $results = $this->searchWeb($searchQuery);
        
        // Look for indicators that the game is over
        $gameOverIndicators = [
            'final score',
            'game over',
            'final:',
            'wins',
            'defeats',
            'beats'
        ];
        
        foreach ($gameOverIndicators as $indicator) {
            if (stripos($results, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function checkStockPrice($parsedData) {
        // For now, return false until we add proper stock API
        return false;
    }
    
    private function checkWeather($parsedData) {
        // For now, return false until we add proper weather API
        return false;
    }
    
    private function checkProductAnnouncement($parsedData) {
        $product = $parsedData['entity'];
        $searchQuery = $product . " announcement release news today";
        $results = $this->searchWeb($searchQuery);
        
        // Look for recent announcement indicators
        $announcementIndicators = [
            'announced',
            'launches',
            'releases',
            'introduces',
            'unveils'
        ];
        
        foreach ($announcementIndicators as $indicator) {
            if (stripos($results, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function checkGenericEvent($parsedData) {
        // For testing purposes, randomly trigger events for demo
        // 5% chance of triggering for testing
        return rand(1, 100) <= 5;
    }
    
    private function searchWeb($query) {
        // Check cache first
        $cached = $this->getCachedSearch($query);
        if ($cached) {
            return $cached;
        }
        
        // Simple web search simulation for testing
        $results = "Sample search results for: " . $query;
        
        // Cache the results for 5 minutes
        $this->cacheSearch($query, $results, 5);
        
        return $results;
    }
    
    private function getCachedSearch($query) {
        $sql = "SELECT results FROM search_cache WHERE search_query = ? AND datetime(expires_at) > datetime('now')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$query]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['results'] : null;
    }
    
    private function cacheSearch($query, $results, $minutes) {
        $sql = "INSERT OR REPLACE INTO search_cache (search_query, results, expires_at) 
                VALUES (?, ?, datetime('now', '+' || ? || ' minutes'))";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$query, $results, $minutes]);
    }
    
    private function updateReminderCheck($reminderId) {
        $sql = "UPDATE reminders 
                SET last_checked = datetime('now'), current_checks = current_checks + 1 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reminderId]);
    }
    
    private function triggerNotification($reminder, $parsedData) {
        $subject = "🎯 Reminder Alert: " . $reminder['original_text'];
        $message = "
        <html>
        <body>
            <h2>🎯 Your reminder has been triggered!</h2>
            <p><strong>Original request:</strong> {$reminder['original_text']}</p>
            <p><strong>Event detected:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p>The condition you specified has been met. Check the latest information online for details.</p>
            <hr>
            <p><small>This is an automated message from TextMeWhen AI</small></p>
        </body>
        </html>
        ";
        
        $this->sendGmailEmail($reminder['email'], $subject, $message);
        
        // Log the notification
        $this->logNotification($reminder['id'], 'email', $reminder['email'], $subject, $message);
        
        echo "Notification sent for reminder ID: {$reminder['id']}\n";
    }
    
    private function markReminderCompleted($reminderId) {
        $sql = "UPDATE reminders SET status = 'completed', completed_at = datetime('now') WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reminderId]);
    }
    
    private function logNotification($reminderId, $type, $recipient, $subject, $message) {
        $sql = "INSERT INTO notification_log (reminder_id, type, recipient, subject, message) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reminderId, $type, $recipient, $subject, $message]);
    }
    
    private function cleanupCache() {
        $sql = "DELETE FROM search_cache WHERE datetime(expires_at) < datetime('now')";
        $this->pdo->exec($sql);
        echo "Cleaned up expired cache entries.\n";
    }
}

// Run the monitoring if called from command line
if (php_sapi_name() === 'cli') {
    try {
        $monitor = new ReminderMonitor();
        $monitor->runMonitoring();
    } catch (Exception $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        error_log("Monitor fatal error: " . $e->getMessage());
    }
}
?>