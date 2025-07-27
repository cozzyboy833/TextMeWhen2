<?php
// interactive_trainer.php - Correct AI mistakes and retrain

$config = require 'config.php';

class InteractiveAITrainer {
    private $pdo;
    private $corrections = [];
    
    public function __construct() {
        $config = require 'config.php';
        $this->pdo = new PDO("sqlite:" . $config['database']['path']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create corrections table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ai_corrections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_text TEXT,
                wrong_classification TEXT,
                correct_classification TEXT,
                correct_entity TEXT,
                correct_condition TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function runInteractiveTraining() {
        echo "🎓 INTERACTIVE AI TRAINER\n";
        echo "========================\n\n";
        echo "This tool helps you correct AI mistakes and improve the model.\n\n";
        
        while (true) {
            $this->showMenu();
            $choice = $this->getInput("Choose an option (1-6): ");
            
            switch ($choice) {
                case '1':
                    $this->reviewFailedReminders();
                    break;
                case '2':
                    $this->addManualCorrection();
                    break;
                case '3':
                    $this->testNewExample();
                    break;
                case '4':
                    $this->viewCorrections();
                    break;
                case '5':
                    $this->generateImprovedModel();
                    break;
                case '6':
                    echo "Goodbye! 👋\n";
                    exit;
                default:
                    echo "Invalid choice. Please try again.\n\n";
            }
        }
    }
    
    private function showMenu() {
        echo "🎯 What would you like to do?\n";
        echo "=============================\n";
        echo "1. 🔍 Review failed reminders and correct them\n";
        echo "2. ➕ Add manual correction (teach new pattern)\n";
        echo "3. 🧪 Test new example with current AI\n";
        echo "4. 📋 View all corrections made so far\n";
        echo "5. 🚀 Generate improved AI model\n";
        echo "6. 🚪 Exit\n\n";
    }
    
    private function reviewFailedReminders() {
        echo "\n🔍 REVIEWING FAILED REMINDERS\n";
        echo "============================\n";
        
        // Get reminders that might have failed
        $stmt = $this->pdo->query("
            SELECT r.*, n.sent_at 
            FROM reminders r 
            LEFT JOIN notification_log n ON r.id = n.reminder_id 
            WHERE r.status = 'completed' 
            ORDER BY r.created_at DESC 
            LIMIT 10
        ");
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reminders as $i => $reminder) {
            $parsed = json_decode($reminder['parsed_data'], true);
            
            echo "\n--- Reminder #" . ($i + 1) . " ---\n";
            echo "📝 Original: \"" . $reminder['original_text'] . "\"\n";
            echo "🤖 AI Classification:\n";
            echo "   Type: " . ($parsed['event_type'] ?? 'unknown') . "\n";
            echo "   Entity: " . ($parsed['entity'] ?? 'unknown') . "\n";
            echo "   Condition: " . ($parsed['condition'] ?? 'unknown') . "\n";
            
            if ($reminder['sent_at']) {
                $createdTime = strtotime($reminder['created_at']);
                $sentTime = strtotime($reminder['sent_at']);
                $hoursDiff = ($sentTime - $createdTime) / 3600;
                echo "📧 Sent after: " . round($hoursDiff, 1) . " hours\n";
            }
            
            $correct = $this->getInput("Was this classification correct? (y/n/s=skip): ");
            
            if (strtolower($correct) === 'n') {
                $this->correctReminder($reminder, $parsed);
            } elseif (strtolower($correct) === 's') {
                continue;
            } else {
                echo "✅ Marked as correct\n";
            }
        }
    }
    
    private function correctReminder($reminder, $wrongParsed) {
        echo "\n🔧 CORRECTING REMINDER\n";
        echo "=====================\n";
        echo "Original: \"" . $reminder['original_text'] . "\"\n";
        echo "Wrong AI classification: " . ($wrongParsed['event_type'] ?? 'unknown') . "\n\n";
        
        echo "What SHOULD this be classified as?\n";
        echo "1. sports_game_start (game beginning)\n";
        echo "2. sports_game_end (game ending)\n";
        echo "3. time_relative (in X minutes/hours)\n";
        echo "4. time_absolute (at specific time)\n";
        echo "5. stock_price (stock reaches price)\n";
        echo "6. weather (temperature/conditions)\n";
        echo "7. product_announcement\n";
        echo "8. generic\n";
        
        $typeChoice = $this->getInput("Choose correct type (1-8): ");
        
        $typeMap = [
            '1' => 'sports_game_start',
            '2' => 'sports_game_end',
            '3' => 'time_relative',
            '4' => 'time_absolute',
            '5' => 'stock_price',
            '6' => 'weather',
            '7' => 'product_announcement',
            '8' => 'generic'
        ];
        
        $correctType = $typeMap[$typeChoice] ?? 'generic';
        $correctEntity = $this->getInput("What's the main entity? (e.g., 'Yankees', 'timer', 'AAPL'): ");
        $correctCondition = $this->getInput("What's the condition? (e.g., 'game_over', 'price_above'): ");
        $notes = $this->getInput("Any notes about why this was wrong? ");
        
        // Save correction
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_corrections 
            (original_text, wrong_classification, correct_classification, correct_entity, correct_condition, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $reminder['original_text'],
            json_encode($wrongParsed),
            $correctType,
            $correctEntity,
            $correctCondition,
            $notes
        ]);
        
        echo "✅ Correction saved! This will improve future training.\n";
        
        $this->corrections[] = [
            'text' => $reminder['original_text'],
            'correct_type' => $correctType,
            'correct_entity' => $correctEntity,
            'correct_condition' => $correctCondition
        ];
    }
    
    private function addManualCorrection() {
        echo "\n➕ ADD MANUAL CORRECTION\n";
        echo "=======================\n";
        echo "Teach the AI a new pattern by example.\n\n";
        
        $text = $this->getInput("Enter example text: ");
        
        echo "How should \"$text\" be classified?\n";
        echo "1. sports_game_start\n";
        echo "2. sports_game_end\n";
        echo "3. time_relative\n";
        echo "4. time_absolute\n";
        echo "5. stock_price\n";
        echo "6. weather\n";
        echo "7. product_announcement\n";
        echo "8. generic\n";
        
        $typeChoice = $this->getInput("Choose type (1-8): ");
        
        $typeMap = [
            '1' => 'sports_game_start',
            '2' => 'sports_game_end',
            '3' => 'time_relative',
            '4' => 'time_absolute',
            '5' => 'stock_price',
            '6' => 'weather',
            '7' => 'product_announcement',
            '8' => 'generic'
        ];
        
        $correctType = $typeMap[$typeChoice] ?? 'generic';
        $correctEntity = $this->getInput("Entity: ");
        $correctCondition = $this->getInput("Condition: ");
        $notes = $this->getInput("Teaching notes: ");
        
        // Save as correction
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_corrections 
            (original_text, wrong_classification, correct_classification, correct_entity, correct_condition, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $text,
            'manual_training',
            $correctType,
            $correctEntity,
            $correctCondition,
            $notes
        ]);
        
        echo "✅ Training example added!\n";
    }
    
    private function testNewExample() {
        echo "\n🧪 TEST NEW EXAMPLE\n";
        echo "==================\n";
        
        $text = $this->getInput("Enter text to test: ");
        
        // Load current parser if it exists
        if (file_exists('improved_ai_parser.php')) {
            require_once 'improved_ai_parser.php';
            $parser = new ImprovedAIParser();
            $result = $parser->parseReminder($text);
            
            echo "\n🤖 AI PREDICTION:\n";
            echo "   Type: " . $result['event_type'] . "\n";
            echo "   Entity: " . $result['entity'] . "\n";
            echo "   Confidence: " . round($result['confidence'] * 100, 1) . "%\n";
            
            if (isset($result['target_time']) && $result['target_time']) {
                echo "   Target Time: " . $result['target_time'] . "\n";
            }
            
            $correct = $this->getInput("Is this correct? (y/n): ");
            
            if (strtolower($correct) === 'n') {
                echo "Let's correct this...\n";
                // Add correction logic here
            }
            
        } else {
            echo "❌ No trained model found. Run training first.\n";
        }
    }
    
    private function viewCorrections() {
        echo "\n📋 ALL CORRECTIONS\n";
        echo "=================\n";
        
        $stmt = $this->pdo->query("SELECT * FROM ai_corrections ORDER BY created_at DESC");
        $corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($corrections)) {
            echo "No corrections made yet.\n";
            return;
        }
        
        foreach ($corrections as $i => $correction) {
            echo "\n--- Correction #" . ($i + 1) . " ---\n";
            echo "📝 Text: \"" . $correction['original_text'] . "\"\n";
            echo "✅ Should be: " . $correction['correct_classification'] . "\n";
            echo "🏷️ Entity: " . $correction['correct_entity'] . "\n";
            echo "🎯 Condition: " . $correction['correct_condition'] . "\n";
            
            if ($correction['notes']) {
                echo "📝 Notes: " . $correction['notes'] . "\n";
            }
            
            echo "📅 Added: " . $correction['created_at'] . "\n";
        }
        
        echo "\nTotal corrections: " . count($corrections) . "\n";
    }
    
    private function generateImprovedModel() {
        echo "\n🚀 GENERATING IMPROVED AI MODEL\n";
        echo "==============================\n";
        
        // Get all corrections
        $stmt = $this->pdo->query("SELECT * FROM ai_corrections");
        $corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($corrections)) {
            echo "❌ No corrections available. Make some corrections first.\n";
            return;
        }
        
        echo "📊 Using " . count($corrections) . " corrections to improve AI...\n";
        
        // Generate enhanced patterns
        $patterns = [];
        $gameEndPatterns = [];
        $gameStartPatterns = [];
        
        foreach ($corrections as $correction) {
            $text = strtolower($correction['original_text']);
            $type = $correction['correct_classification'];
            $entity = $correction['correct_entity'];
            
            // Special handling for your Yankees game issue
            if ($type === 'sports_game_end') {
                if (preg_match('/\b(yankees|jets|giants|knicks|mets)\b/', $text, $matches)) {
                    $gameEndPatterns[] = [
                        'team' => $matches[1],
                        'keywords' => ['end', 'over', 'finished', 'final', 'done'],
                        'entity' => ucfirst($matches[1]),
                        'confidence' => 0.9
                    ];
                }
            }
            
            if ($type === 'sports_game_start') {
                if (preg_match('/\b(yankees|jets|giants|knicks|mets)\b/', $text, $matches)) {
                    $gameStartPatterns[] = [
                        'team' => $matches[1],
                        'keywords' => ['start', 'begin', 'kick off', 'first pitch'],
                        'entity' => ucfirst($matches[1]),
                        'confidence' => 0.9
                    ];
                }
            }
            
            // General pattern extraction
            $patterns[] = [
                'text' => $text,
                'type' => $type,
                'entity' => $entity,
                'condition' => $correction['correct_condition']
            ];
        }
        
        // Generate improved parser code
        $this->generateEnhancedParser($patterns, $gameEndPatterns, $gameStartPatterns);
        
        echo "✅ Enhanced AI model generated!\n";
        echo "📁 File: enhanced_ai_parser.php\n";
        echo "🧪 Test it: php test_enhanced_parser.php\n";
    }
    
    private function generateEnhancedParser($patterns, $gameEndPatterns, $gameStartPatterns) {
        $parserCode = "<?php
// enhanced_ai_parser.php - Generated with human corrections
// Generated on: " . date('Y-m-d H:i:s') . "
// Based on " . count($patterns) . " human corrections

class EnhancedAIParser {
    private \$patterns;
    private \$gameEndPatterns;
    private \$gameStartPatterns;
    
    public function __construct() {
        \$this->patterns = " . var_export($patterns, true) . ";
        \$this->gameEndPatterns = " . var_export($gameEndPatterns, true) . ";
        \$this->gameStartPatterns = " . var_export($gameStartPatterns, true) . ";
    }
    
    public function parseReminder(\$text) {
        \$originalText = \$text;
        \$text = strtolower(\$text);
        
        \$result = [
            'event_type' => 'generic',
            'entity' => 'unknown',
            'condition' => 'event_occurs',
            'confidence' => 0.5,
            'target_time' => null,
            'keywords' => explode(' ', \$text),
            'reasoning' => 'Default classification'
        ];
        
        // ENHANCED SPORTS GAME DETECTION
        // Check for game END patterns first (your Yankees issue!)
        foreach (\$this->gameEndPatterns as \$pattern) {
            if (stripos(\$text, \$pattern['team']) !== false) {
                foreach (\$pattern['keywords'] as \$keyword) {
                    if (stripos(\$text, \$keyword) !== false) {
                        \$result['event_type'] = 'sports_game_end';
                        \$result['entity'] = \$pattern['entity'];
                        \$result['condition'] = 'game_over';
                        \$result['confidence'] = \$pattern['confidence'];
                        \$result['reasoning'] = \"Found team '{$pattern['team']}' with end keyword '{\$keyword}'\";
                        return \$result;
                    }
                }
            }
        }
        
        // Check for game START patterns
        foreach (\$this->gameStartPatterns as \$pattern) {
            if (stripos(\$text, \$pattern['team']) !== false) {
                foreach (\$pattern['keywords'] as \$keyword) {
                    if (stripos(\$text, \$keyword) !== false) {
                        \$result['event_type'] = 'sports_game_start';
                        \$result['entity'] = \$pattern['entity'];
                        \$result['condition'] = 'game_starts';
                        \$result['confidence'] = \$pattern['confidence'];
                        \$result['reasoning'] = \"Found team '{$pattern['team']}' with start keyword '{\$keyword}'\";
                        return \$result;
                    }
                }
            }
        }
        
        // TIME PARSING (highest priority for non-sports)
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', \$text, \$matches)) {
            \$amount = intval(\$matches[1]);
            \$unit = strtolower(\$matches[2]);
            \$minutes = strpos(\$unit, 'hour') === 0 ? \$amount * 60 : \$amount;
            
            \$result['event_type'] = 'time_relative';
            \$result['entity'] = 'timer';
            \$result['target_time'] = date('Y-m-d H:i:s', strtotime(\"+{\$minutes} minutes\"));
            \$result['confidence'] = 0.95;
            \$result['reasoning'] = \"Relative time pattern: {\$amount} {\$unit}\";
            return \$result;
        }
        
        if (preg_match('/when it is (\d{1,2}):?(\d{2})?/i', \$text, \$matches)) {
            \$targetHour = intval(\$matches[1]);
            \$targetMinute = isset(\$matches[2]) ? intval(\$matches[2]) : 0;
            \$targetTime = date('Y-m-d') . ' ' . sprintf('%02d:%02d:00', \$targetHour, \$targetMinute);
            
            if (\$targetTime <= date('Y-m-d H:i:s')) {
                \$targetTime = date('Y-m-d', strtotime('+1 day')) . ' ' . sprintf('%02d:%02d:00', \$targetHour, \$targetMinute);
            }
            
            \$result['event_type'] = 'time_absolute';
            \$result['target_time'] = \$targetTime;
            \$result['confidence'] = 0.9;
            \$result['reasoning'] = \"Absolute time pattern: {\$targetHour}:{\$targetMinute}\";
            return \$result;
        }
        
        // HUMAN-CORRECTED PATTERNS
        foreach (\$this->patterns as \$pattern) {
            \$patternWords = explode(' ', \$pattern['text']);
            \$matchCount = 0;
            
            foreach (\$patternWords as \$word) {
                if (strlen(\$word) > 3 && stripos(\$text, \$word) !== false) {
                    \$matchCount++;
                }
            }
            
            // If enough words match, use this pattern
            if (\$matchCount >= min(3, count(\$patternWords) / 2)) {
                \$result['event_type'] = \$pattern['type'];
                \$result['entity'] = \$pattern['entity'];
                \$result['condition'] = \$pattern['condition'];
                \$result['confidence'] = 0.8;
                \$result['reasoning'] = \"Matched {\$matchCount} words from training pattern\";
                return \$result;
            }
        }
        
        return \$result;
    }
}
?>";
        
        file_put_contents('enhanced_ai_parser.php', $parserCode);
        
        // Also create a test script
        $testCode = "<?php
// test_enhanced_parser.php

require_once 'enhanced_ai_parser.php';

\$parser = new EnhancedAIParser();

\$testCases = [
    'message me when the yankees game ends',
    'notify me when the yankees game starts',
    'text me when the jets game is over',
    'remind me in 5 minutes',
    'let me know when it is 3:30 PM'
];

echo \"🧪 Testing Enhanced AI Parser\\n\";
echo \"============================\\n\\n\";

foreach (\$testCases as \$i => \$test) {
    echo \"Test \" . (\$i + 1) . \": \\\"\$test\\\"\\n\";
    \$result = \$parser->parseReminder(\$test);
    
    echo \"  Type: \" . \$result['event_type'] . \"\\n\";
    echo \"  Entity: \" . \$result['entity'] . \"\\n\";
    echo \"  Confidence: \" . round(\$result['confidence'] * 100, 1) . \"%\\n\";
    echo \"  Reasoning: \" . \$result['reasoning'] . \"\\n\";
    
    if (isset(\$result['target_time']) && \$result['target_time']) {
        echo \"  Target: \" . \$result['target_time'] . \"\\n\";
    }
    
    echo \"\\n\";
}
?>";
        
        file_put_contents('test_enhanced_parser.php', $testCode);
    }
    
    private function getInput($prompt) {
        echo $prompt;
        return trim(fgets(STDIN));
    }
}

// Run the interactive trainer
if (php_sapi_name() === 'cli') {
    try {
        $trainer = new InteractiveAITrainer();
        $trainer->runInteractiveTraining();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>