<?php
// serpapi_monitor.php - Fixed version

// Load configuration first
$config = require 'config.php';

// Load dependencies
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// We need the ReminderSystem class but NOT the request handling code
// So let's define our own version that doesn't trigger the web handler

class SerpApiReminderMonitor {
    protected $pdo;
    private $config;
    
    public function __construct() {
        $this->config = require 'config.php';
        
        try {
            $this->pdo = new PDO(
                "sqlite:" . $this->config['database']['path'],
                null,
                null,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function runMonitoring() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting SerpApi monitoring...\n";
        
        // Check if SerpApi key is configured
        $serpApiKey = '';
        if (isset($this->config['serpapi']) && isset($this->config['serpapi']['api_key'])) {
            $serpApiKey = $this->config['serpapi']['api_key'];
        }
        
        if (empty($serpApiKey) || $serpApiKey === 'YOUR_SERPAPI_KEY_HERE') {
            echo "❌ SerpApi key not configured. Please update config.php\n";
            echo "   Get your free key from: https://serpapi.com/\n";
            echo "   Then update the 'serpapi' section in config.php\n";
            return;
        }
        
        echo "✅ SerpApi key configured (starts with: " . substr($serpApiKey, 0, 5) . "...)\n\n";
        
        try {
            $reminders = $this->getRemindersToCheck();
            echo "Found " . count($reminders) . " reminders to check.\n\n";
            
            if (count($reminders) === 0) {
                echo "💡 No active reminders to check. Try creating some reminders first!\n";
                return;
            }
            
            foreach ($reminders as $reminder) {
                echo "🔍 Checking: \"" . $reminder['original_text'] . "\"\n";
                $this->checkReminder($reminder, $serpApiKey);
                echo "\n";
                
                // Rate limiting
                sleep(1);
            }
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Monitoring complete.\n";
    }
    
    public function getRemindersToCheck() {
        $sql = "SELECT * FROM reminders 
                WHERE status = 'active' 
                AND (last_checked IS NULL OR datetime(last_checked, '+' || check_frequency || ' seconds') < datetime('now'))
                AND current_checks < max_checks";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function checkReminder($reminder, $serpApiKey) {
        $this->updateReminderCheck($reminder['id']);
        
        $originalText = strtolower($reminder['original_text']);
        $parsedData = json_decode($reminder['parsed_data'], true);
        $eventOccurred = false;
        
        // Enhanced time-based reminders
        if (isset($parsedData['event_type']) && 
            ($parsedData['event_type'] === 'time_relative' || $parsedData['event_type'] === 'time_absolute')) {
            $eventOccurred = $this->checkEnhancedTimeCondition($parsedData);
        }
        // Old format time check (fallback)
        elseif (preg_match('/when it is (\d{1,2}):?(\d{2})?/', $originalText, $matches)) {
            $eventOccurred = $this->checkTimeCondition($matches);
        }
        // Test trigger
        elseif (strpos($originalText, 'test now') !== false) {
            echo "  🧪 Test trigger detected!\n";
            $eventOccurred = true;
        }
        // Sports games
        elseif ((isset($parsedData['event_type']) && $parsedData['event_type'] === 'sports_game') ||
                preg_match('/\b(jets|giants|knicks|yankees|mets|lakers|warriors)\b/i', $originalText)) {
            $eventOccurred = $this->checkSports($originalText, $serpApiKey);
        }
        // Stock prices
        elseif ((isset($parsedData['event_type']) && $parsedData['event_type'] === 'stock_price') ||
                preg_match('/\b(stock|shares?)\b.*\$?(\d+)/i', $originalText)) {
            $eventOccurred = $this->checkStock($originalText, $serpApiKey);
        }
        // News/announcements
        elseif (strpos($originalText, 'announce') !== false || strpos($originalText, 'release') !== false) {
            $eventOccurred = $this->checkNews($originalText, $serpApiKey);
        }
        // Generic search
        else {
            $eventOccurred = $this->checkGeneric($originalText, $serpApiKey);
        }
        
        if ($eventOccurred) {
            echo "  🎯 EVENT DETECTED! Sending notification...\n";
            $this->triggerNotification($reminder);
            $this->markReminderCompleted($reminder['id']);
        } else {
            echo "  ⏳ No event detected yet.\n";
        }
    }
    
    public function checkEnhancedTimeCondition($parsedData) {
        if (!isset($parsedData['target_time']) || !$parsedData['target_time']) {
            echo "  ❌ No target time found in parsed data\n";
            return false;
        }
        
        $targetTime = $parsedData['target_time'];
        $currentTime = date('Y-m-d H:i:s');
        
        echo "  ⏰ Enhanced time check:\n";
        echo "     Target: " . date('M j, Y g:i:s A', strtotime($targetTime)) . "\n";
        echo "     Current: " . date('M j, Y g:i:s A', strtotime($currentTime)) . "\n";
        
        // Check if current time has reached or passed target time
        if ($currentTime >= $targetTime) {
            echo "  ✅ Target time reached!\n";
            return true;
        } else {
            $secondsLeft = strtotime($targetTime) - strtotime($currentTime);
            $minutesLeft = round($secondsLeft / 60, 1);
            echo "  ⏳ Time remaining: {$minutesLeft} minutes\n";
            return false;
        }
    }
    
    public function checkTimeCondition($matches) {
        $targetHour = intval($matches[1]);
        $targetMinute = 0;
        if (isset($matches[2])) {
            $targetMinute = intval($matches[2]);
        }
        
        $currentHour = intval(date('H'));
        $currentMinute = intval(date('i'));
        
        echo "  ⏰ Time check: Target " . $targetHour . ":" . sprintf('%02d', $targetMinute) . ", Current " . date('H:i') . "\n";
        
        if ($currentHour > $targetHour) {
            return true;
        }
        if ($currentHour == $targetHour && $currentMinute >= $targetMinute) {
            return true;
        }
        
        return false;
    }
    
    public function checkSports($originalText, $serpApiKey) {
        // Extract team name
        $team = 'Jets'; // Default
        if (preg_match('/\b(jets|giants|knicks|yankees|mets|lakers|warriors)\b/i', $originalText, $matches)) {
            $team = ucfirst(strtolower($matches[1]));
        }
        
        $query = $team . " game today final score result";
        echo "  🏈 Searching for: " . $query . "\n";
        
        $results = $this->searchSerpApi($query, $serpApiKey);
        
        if (!$results) {
            echo "  ❌ Search failed\n";
            return false;
        }
        
        // Look for game completion indicators
        $searchText = strtolower(json_encode($results));
        $indicators = array('final score', 'game over', 'final:', 'wins', 'defeats', 'beats');
        
        foreach ($indicators as $indicator) {
            if (strpos($searchText, $indicator) !== false) {
                echo "  ✅ Found indicator: '" . $indicator . "'\n";
                return true;
            }
        }
        
        return false;
    }
    
    public function checkStock($originalText, $serpApiKey) {
        // Extract stock symbol or company
        $symbol = 'AAPL'; // Default
        if (preg_match('/\b(apple|tesla|google|microsoft|amazon)\b/i', $originalText, $matches)) {
            $company = strtolower($matches[1]);
            $symbols = array(
                'apple' => 'AAPL',
                'tesla' => 'TSLA',
                'google' => 'GOOGL',
                'microsoft' => 'MSFT',
                'amazon' => 'AMZN'
            );
            if (isset($symbols[$company])) {
                $symbol = $symbols[$company];
            }
        }
        
        $query = $symbol . " stock price current";
        echo "  📈 Searching for: " . $query . "\n";
        
        $results = $this->searchSerpApi($query, $serpApiKey);
        
        if (!$results) {
            return false;
        }
        
        // For now, just return false until we implement price checking
        echo "  📊 Stock search completed (price checking not yet implemented)\n";
        return false;
    }
    
    public function checkNews($originalText, $serpApiKey) {
        // Extract key terms
        $words = explode(' ', $originalText);
        $searchTerms = array();
        
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, array('when', 'text', 'notify', 'remind', 'announce'))) {
                $searchTerms[] = $word;
            }
        }
        
        $query = implode(' ', array_slice($searchTerms, 0, 3)) . " announcement news today";
        echo "  📰 Searching news for: " . $query . "\n";
        
        $results = $this->searchSerpApi($query, $serpApiKey);
        
        if (!$results) {
            return false;
        }
        
        // Check for news results
        $newsCount = 0;
        if (isset($results['news_results'])) {
            $newsCount = count($results['news_results']);
        }
        
        echo "  📊 Found " . $newsCount . " recent news articles\n";
        return $newsCount > 0;
    }
    
    public function checkGeneric($originalText, $serpApiKey) {
        // Clean up text for searching
        $query = str_replace(array('text me when', 'notify me when', 'remind me when'), '', $originalText);
        $query = trim($query);
        
        echo "  🔍 Generic search for: " . $query . "\n";
        
        $results = $this->searchSerpApi($query, $serpApiKey);
        
        if (!$results) {
            return false;
        }
        
        // Look for recent activity indicators
        $searchText = strtolower(json_encode($results));
        $indicators = array('today', 'just now', 'breaking', 'live', 'current');
        
        foreach ($indicators as $indicator) {
            if (strpos($searchText, $indicator) !== false) {
                echo "  ✅ Found recent activity indicator: '" . $indicator . "'\n";
                return true;
            }
        }
        
        return false;
    }
    
    public function searchSerpApi($query, $apiKey) {
        // Build URL
        $params = array(
            'q' => urlencode($query),
            'api_key' => $apiKey,
            'engine' => 'google',
            'num' => 5
        );
        
        $url = 'https://serpapi.com/search?' . http_build_query($params);
        
        // Use file_get_contents as fallback if Guzzle not available
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'method' => 'GET'
            )
        ));
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            echo "  ❌ Request failed\n";
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            echo "  ❌ SerpApi error: " . $data['error'] . "\n";
            return null;
        }
        
        $resultCount = 0;
        if (isset($data['organic_results'])) {
            $resultCount = count($data['organic_results']);
        }
        
        echo "  ✅ Search successful (" . $resultCount . " results)\n";
        return $data;
    }
    
    public function updateReminderCheck($reminderId) {
        $sql = "UPDATE reminders 
                SET last_checked = datetime('now'), current_checks = current_checks + 1 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($reminderId));
    }
    
    public function triggerNotification($reminder) {
        $subject = "🎯 Event Alert: " . $reminder['original_text'];
        $message = "
        <html>
        <body>
            <h2>🎯 Your reminder has been triggered!</h2>
            <p><strong>Original request:</strong> " . $reminder['original_text'] . "</p>
            <p><strong>Event detected at:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p>We found activity related to your reminder. Check the latest information online for details!</p>
            <hr>
            <p><small>Powered by TextMeWhen AI + SerpApi</small></p>
        </body>
        </html>
        ";
        
        $this->sendGmailEmail($reminder['email'], $subject, $message);
        $this->logNotification($reminder['id'], 'email', $reminder['email'], $subject, $message);
    }
    
    public function sendGmailEmail($to, $subject, $message) {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "  ❌ PHPMailer not available\n";
            return false;
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['gmail']['username'];
            $mail->Password = $this->config['gmail']['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom($this->config['gmail']['username'], 'TextMeWhen AI');
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            echo "  📧 Email sent successfully!\n";
            return true;
            
        } catch (Exception $e) {
            echo "  ❌ Email failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function logNotification($reminderId, $type, $recipient, $subject, $message) {
        $sql = "INSERT INTO notification_log (reminder_id, type, recipient, subject, message) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($reminderId, $type, $recipient, $subject, $message));
    }
    
    public function markReminderCompleted($reminderId) {
        $sql = "UPDATE reminders SET status = 'completed', completed_at = datetime('now') WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($reminderId));
    }
}

// Run the monitoring
if (php_sapi_name() === 'cli') {
    try {
        $monitor = new SerpApiReminderMonitor();
        $monitor->runMonitoring();
    } catch (Exception $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
    }
}
?>