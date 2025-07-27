<?php
// data_editor_api.php - API for the AI Data Editor

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    if ($method === 'GET' && $action === 'load_reminders') {
        // Load all reminders with their parsed data
        $stmt = $pdo->query("
            SELECT r.*, n.sent_at 
            FROM reminders r 
            LEFT JOIN notification_log n ON r.id = n.reminder_id 
            ORDER BY r.created_at DESC
        ");
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_reminders = [];
        
        foreach ($reminders as $reminder) {
            $parsed_data = json_decode($reminder['parsed_data'], true) ?: [];
            
            $formatted_reminders[] = [
                'id' => $reminder['id'],
                'original_text' => $reminder['original_text'],
                'status' => $reminder['status'],
                'created_at' => $reminder['created_at'],
                'sent_at' => $reminder['sent_at'],
                'current_checks' => $reminder['current_checks'],
                'max_checks' => $reminder['max_checks'],
                'current_data' => [
                    'event_type' => $parsed_data['event_type'] ?? 'unknown',
                    'entity' => $parsed_data['entity'] ?? 'unknown',
                    'condition' => $parsed_data['condition'] ?? 'unknown',
                    'confidence' => $parsed_data['confidence'] ?? 0.5,
                    'target_time' => $parsed_data['target_time'] ?? null,
                    'keywords' => $parsed_data['keywords'] ?? [],
                    'value' => $parsed_data['value'] ?? null
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'reminders' => $formatted_reminders
        ]);
        
    } elseif ($method === 'POST' && $action === 'save_corrections') {
        // Save corrections and retrain AI
        $input = json_decode(file_get_contents('php://input'), true);
        $corrections = $input['corrections'] ?? [];
        
        if (empty($corrections)) {
            echo json_encode(['success' => false, 'message' => 'No corrections provided']);
            exit;
        }
        
        // Create corrections table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ai_corrections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reminder_id INTEGER,
                original_text TEXT,
                old_classification TEXT,
                new_classification TEXT,
                correction_data TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Save each correction
        $stmt = $pdo->prepare("
            INSERT INTO ai_corrections 
            (reminder_id, original_text, old_classification, new_classification, correction_data) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($corrections as $correction) {
            $stmt->execute([
                $correction['id'],
                $correction['original_text'],
                json_encode($correction['old_data']),
                json_encode($correction['new_data']),
                json_encode($correction)
            ]);
        }
        
        // Generate improved AI parser
        $improved_parser = generateImprovedParser($pdo, $corrections);
        
        echo json_encode([
            'success' => true,
            'message' => 'Corrections saved and AI retrained',
            'corrections_count' => count($corrections),
            'parser_generated' => $improved_parser
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generateImprovedParser($pdo, $new_corrections) {
    // Get all corrections from database
    $stmt = $pdo->query("SELECT * FROM ai_corrections ORDER BY created_at DESC");
    $all_corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Analyze patterns from corrections
    $patterns = [];
    $game_end_patterns = [];
    $game_start_patterns = [];
    $entity_mappings = [];
    
    foreach ($all_corrections as $correction) {
        $new_data = json_decode($correction['new_classification'], true);
        $text = strtolower($correction['original_text']);
        
        // Build entity mappings
        if (isset($new_data['entity']) && $new_data['entity'] !== 'unknown') {
            $entity_mappings[$new_data['entity']] = $new_data['event_type'];
        }
        
        // Special handling for sports games
        if ($new_data['event_type'] === 'sports_game_end') {
            $team = null;
            if (preg_match('/\b(yankees|jets|giants|knicks|mets|lakers|warriors)\b/', $text, $matches)) {
                $team = $matches[1];
            }
            
            $game_end_patterns[] = [
                'team' => $team ?: $new_data['entity'],
                'keywords' => ['end', 'over', 'finished', 'final', 'done', 'complete'],
                'confidence' => 0.9
            ];
        }
        
        if ($new_data['event_type'] === 'sports_game_start') {
            $team = null;
            if (preg_match('/\b(yankees|jets|giants|knicks|mets|lakers|warriors)\b/', $text, $matches)) {
                $team = $matches[1];
            }
            
            $game_start_patterns[] = [
                'team' => $team ?: $new_data['entity'],
                'keywords' => ['start', 'begin', 'kick off', 'first pitch', 'opening'],
                'confidence' => 0.9
            ];
        }
        
        // General patterns
        $patterns[] = [
            'text' => $text,
            'type' => $new_data['event_type'],
            'entity' => $new_data['entity'],
            'condition' => $new_data['condition'] ?? 'event_occurs'
        ];
    }
    
    // Generate enhanced parser code
    $parser_code = "<?php
// enhanced_ai_parser.php - Generated from human corrections
// Generated on: " . date('Y-m-d H:i:s') . "
// Based on " . count($all_corrections) . " human corrections

class EnhancedAIParser {
    private \$patterns;
    private \$gameEndPatterns;
    private \$gameStartPatterns;
    private \$entityMappings;
    
    public function __construct() {
        \$this->patterns = " . var_export($patterns, true) . ";
        \$this->gameEndPatterns = " . var_export($game_end_patterns, true) . ";
        \$this->gameStartPatterns = " . var_export($game_start_patterns, true) . ";
        \$this->entityMappings = " . var_export($entity_mappings, true) . ";
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
        
        // PRIORITY 1: Enhanced Sports Game Detection
        foreach (\$this->gameEndPatterns as \$pattern) {
            if (\$pattern['team'] && stripos(\$text, \$pattern['team']) !== false) {
                foreach (\$pattern['keywords'] as \$keyword) {
                    if (stripos(\$text, \$keyword) !== false) {
                        \$result['event_type'] = 'sports_game_end';
                        \$result['entity'] = ucfirst(\$pattern['team']);
                        \$result['condition'] = 'game_over';
                        \$result['confidence'] = \$pattern['confidence'];
                        \$result['reasoning'] = \"Game end: {\$pattern['team']} + {\$keyword}\";
                        return \$result;
                    }
                }
            }
        }
        
        foreach (\$this->gameStartPatterns as \$pattern) {
            if (\$pattern['team'] && stripos(\$text, \$pattern['team']) !== false) {
                foreach (\$pattern['keywords'] as \$keyword) {
                    if (stripos(\$text, \$keyword) !== false) {
                        \$result['event_type'] = 'sports_game_start';
                        \$result['entity'] = ucfirst(\$pattern['team']);
                        \$result['condition'] = 'game_starts';
                        \$result['confidence'] = \$pattern['confidence'];
                        \$result['reasoning'] = \"Game start: {\$pattern['team']} + {\$keyword}\";
                        return \$result;
                    }
                }
            }
        }
        
        // PRIORITY 2: Time-based parsing
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', \$text, \$matches)) {
            \$amount = intval(\$matches[1]);
            \$unit = strtolower(\$matches[2]);
            \$minutes = strpos(\$unit, 'hour') === 0 ? \$amount * 60 : \$amount;
            
            \$result['event_type'] = 'time_relative';
            \$result['entity'] = 'timer';
            \$result['target_time'] = date('Y-m-d H:i:s', strtotime(\"+{\$minutes} minutes\"));
            \$result['confidence'] = 0.95;
            \$result['reasoning'] = \"Relative time: {\$amount} {\$unit}\";
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
            \$result['reasoning'] = \"Absolute time: {\$targetHour}:{\$targetMinute}\";
            return \$result;
        }
        
        // PRIORITY 3: Human-corrected patterns
        foreach (\$this->patterns as \$pattern) {
            \$patternWords = explode(' ', \$pattern['text']);
            \$matchCount = 0;
            \$totalWords = count(\$patternWords);
            
            foreach (\$patternWords as \$word) {
                if (strlen(\$word) > 2 && stripos(\$text, \$word) !== false) {
                    \$matchCount++;
                }
            }
            
            \$matchRatio = \$totalWords > 0 ? \$matchCount / \$totalWords : 0;
            
            if (\$matchRatio >= 0.5 || \$matchCount >= 3) {
                \$result['event_type'] = \$pattern['type'];
                \$result['entity'] = \$pattern['entity'];
                \$result['condition'] = \$pattern['condition'];
                \$result['confidence'] = 0.8 * \$matchRatio;
                \$result['reasoning'] = \"Pattern match: {\$matchCount}/{\$totalWords} words\";
                return \$result;
            }
        }
        
        return \$result;
    }
}
?>";
    
    file_put_contents('enhanced_ai_parser.php', $parser_code);
    
    return true;
}
?>