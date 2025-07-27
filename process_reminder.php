<?php
// process_reminder.php - Enhanced with relative time parsing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load PHPMailer if available
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// Load configuration
$config = require_once 'config.php';

class ReminderSystem {
    protected $pdo;
    private $config;
    
    public function __construct() {
        $this->config = require 'config.php';
        
        try {
            // SQLite connection
            $this->pdo = new PDO(
                "sqlite:" . $this->config['database']['path'],
                null,
                null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create tables if they don't exist
            $this->createTablesIfNeeded();
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function createTablesIfNeeded() {
        // Read and execute the schema
        $schema = file_get_contents('schema_sqlite.sql');
        if ($schema) {
            $this->pdo->exec($schema);
        }
    }
    
    public function processReminder($email, $phone, $reminder) {
        // Step 1: Parse reminder using enhanced AI
        $parsedReminder = $this->parseReminderWithEnhancedAI($reminder);
        
        if (!$parsedReminder) {
            return ['success' => false, 'message' => 'Could not understand the reminder request'];
        }
        
        // Step 2: Store in database
        $reminderId = $this->storeReminder($email, $phone, $reminder, $parsedReminder);
        
        // Step 3: Send confirmation email
        $emailSent = $this->sendConfirmationEmail($email, $reminder, $parsedReminder);
        
        // Step 4: Schedule the monitoring
        $this->scheduleMonitoring($reminderId, $parsedReminder);
        
        return [
            'success' => true, 
            'message' => $emailSent ? 
                'Reminder set successfully! You\'ll receive an email confirmation shortly.' :
                'Reminder set successfully! (Email notification failed - check server logs)',
            'reminder_id' => $reminderId
        ];
    }
    
    private function parseReminderWithEnhancedAI($reminder) {
        $parsedReminder = [
            'event_type' => 'generic',
            'entity' => 'unknown',
            'condition' => 'event_occurs',
            'value' => null,
            'target_time' => null,
            'keywords' => explode(' ', strtolower($reminder))
        ];
        
        // 1. RELATIVE TIME PARSING - "in X minutes/hours"
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', $reminder, $matches)) {
            $amount = intval($matches[1]);
            $unit = strtolower($matches[2]);
            
            $minutes = $amount;
            if (strpos($unit, 'hour') === 0) {
                $minutes = $amount * 60;
            }
            
            // Calculate target time
            $targetTime = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            
            $parsedReminder['event_type'] = 'time_relative';
            $parsedReminder['entity'] = 'timer';
            $parsedReminder['condition'] = 'time_reached';
            $parsedReminder['value'] = $minutes;
            $parsedReminder['target_time'] = $targetTime;
            $parsedReminder['keywords'] = ['timer', 'relative time', "{$amount} {$unit}"];
            
            return $parsedReminder;
        }
        
        // 2. ABSOLUTE TIME PARSING - "when it is 3:45"
        elseif (preg_match('/when it is (\d{1,2}):?(\d{2})?/i', $reminder, $matches)) {
            $targetHour = intval($matches[1]);
            $targetMinute = isset($matches[2]) ? intval($matches[2]) : 0;
            
            // Calculate target time for today (or tomorrow if time has passed)
            $targetTime = date('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $targetHour, $targetMinute);
            $currentTime = date('Y-m-d H:i:s');
            
            if ($targetTime <= $currentTime) {
                // Time has passed today, set for tomorrow
                $targetTime = date('Y-m-d', strtotime('+1 day')) . ' ' . sprintf('%02d:%02d:00', $targetHour, $targetMinute);
            }
            
            $parsedReminder['event_type'] = 'time_absolute';
            $parsedReminder['entity'] = 'clock';
            $parsedReminder['condition'] = 'time_reached';
            $parsedReminder['target_time'] = $targetTime;
            $parsedReminder['keywords'] = ['time', 'clock', "{$targetHour}:{$targetMinute}"];
            
            return $parsedReminder;
        }
        
        // 3. SPORTS PARSING
        elseif (preg_match('/\b(jets|giants|knicks|yankees|mets)\b/i', $reminder, $matches)) {
            $parsedReminder['event_type'] = 'sports_game';
            $parsedReminder['entity'] = ucfirst(strtolower($matches[1]));
            $parsedReminder['condition'] = 'game_over';
            $parsedReminder['keywords'] = [$matches[1] . ' game', 'final score', 'game over'];
        }
        
        // 4. STOCK PARSING
        elseif (preg_match('/\b(stock|shares?)\b.*\$?(\d+)/i', $reminder, $matches)) {
            $parsedReminder['event_type'] = 'stock_price';
            $parsedReminder['condition'] = 'price_above';
            $parsedReminder['value'] = isset($matches[2]) ? floatval($matches[2]) : null;
        }
        
        // 5. WEATHER PARSING
        elseif (preg_match('/\b(weather|rain|snow|temperature)\b/i', $reminder)) {
            $parsedReminder['event_type'] = 'weather';
            $parsedReminder['condition'] = 'weather_change';
        }
        
        return $parsedReminder;
    }
    
    private function storeReminder($email, $phone, $reminder, $parsedReminder) {
        $sql = "INSERT INTO reminders (email, phone, original_text, parsed_data, status, created_at) 
                VALUES (?, ?, ?, ?, 'active', datetime('now'))";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $email,
            $phone,
            $reminder,
            json_encode($parsedReminder)
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function sendConfirmationEmail($email, $reminder, $parsedReminder) {
        $subject = "Reminder Set: " . $reminder;
        
        // Enhanced message with target time
        $timeInfo = '';
        if (isset($parsedReminder['target_time']) && $parsedReminder['target_time']) {
            $timeInfo = "<p><strong>Target Time:</strong> " . date('M j, Y g:i A', strtotime($parsedReminder['target_time'])) . "</p>";
        }
        
        $message = "
        <html>
        <body>
            <h2>✅ Your reminder has been set!</h2>
            <p><strong>Reminder:</strong> $reminder</p>
            <p><strong>We understood this as:</strong></p>
            <ul>
                <li>Event Type: " . ($parsedReminder['event_type'] ?? 'Unknown') . "</li>
                <li>Entity: " . ($parsedReminder['entity'] ?? 'Unknown') . "</li>
                <li>Condition: " . ($parsedReminder['condition'] ?? 'Unknown') . "</li>
            </ul>
            $timeInfo
            <p>🤖 We'll monitor this for you and send you a notification when the event occurs.</p>
            <hr>
            <p><small>This is an automated message from TextMeWhen AI</small></p>
        </body>
        </html>
        ";
        
        return $this->sendGmailEmail($email, $subject, $message);
    }
    
    public function sendGmailEmail($to, $subject, $message) {
        // Check if PHPMailer is available
        if (!file_exists('vendor/autoload.php')) {
            error_log("PHPMailer not installed. Run: composer install");
            return false;
        }
        
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log("PHPMailer class not found. Check installation.");
            return false;
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['gmail']['username'];
            $mail->Password = $this->config['gmail']['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom($this->config['gmail']['username'], 'TextMeWhen AI');
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function scheduleMonitoring($reminderId, $parsedReminder) {
        // Log the scheduled monitoring
        error_log("Scheduled monitoring for reminder ID: $reminderId");
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['reminder'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    try {
        $reminderSystem = new ReminderSystem();
        $result = $reminderSystem->processReminder(
            $input['email'],
            $input['phone'] ?? null,
            $input['reminder']
        );
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>