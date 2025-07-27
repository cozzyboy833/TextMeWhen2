<?php
// quick_generate_parser.php - Generate enhanced parser from existing data

echo "🚀 Quick Enhanced Parser Generator\n";
echo "==================================\n\n";

$config = require 'config.php';

try {
    $pdo = new PDO("sqlite:" . $config['database']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get existing reminder data
    $stmt = $pdo->query("SELECT * FROM reminders ORDER BY created_at DESC");
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Found " . count($reminders) . " reminders in database\n";
    
    if (empty($reminders)) {
        echo "❌ No reminders found. Create some reminders first!\n";
        exit;
    }
    
    // Analyze existing patterns and create corrections for obvious issues
    $corrections = [];
    
    foreach ($reminders as $reminder) {
        $text = strtolower($reminder['original_text']);
        $parsed = json_decode($reminder['parsed_data'], true);
        $currentType = $parsed['event_type'] ?? 'unknown';
        
        echo "🔍 Analyzing: \"" . $reminder['original_text'] . "\"\n";
        echo "   Current: $currentType\n";
        
        // Auto-fix Yankees game end issue
        if (stripos($text, 'yankees') !== false && 
            (stripos($text, 'end') !== false || stripos($text, 'over') !== false || stripos($text, 'finish') !== false) &&
            $currentType !== 'sports_game_end') {
            
            $corrections[] = [
                'text' => $reminder['original_text'],
                'old_type' => $currentType,
                'new_type' => 'sports_game_end',
                'entity' => 'Yankees',
                'condition' => 'game_over',
                'keywords' => ['yankees', 'game', 'end', 'over', 'final']
            ];
            echo "   ✅ Fixed: sports_game_end\n";
        }
        
        // Auto-fix Yankees game start issue
        elseif (stripos($text, 'yankees') !== false && 
                (stripos($text, 'start') !== false || stripos($text, 'begin') !== false) &&
                $currentType !== 'sports_game_start') {
            
            $corrections[] = [
                'text' => $reminder['original_text'],
                'old_type' => $currentType,
                'new_type' => 'sports_game_start',
                'entity' => 'Yankees',
                'condition' => 'game_starts',
                'keywords' => ['yankees', 'game', 'start', 'begin', 'first']
            ];
            echo "   ✅ Fixed: sports_game_start\n";
        }
        
        // Auto-fix other sports teams
        elseif (preg_match('/\b(jets|giants|knicks|mets|lakers|warriors)\b/', $text, $matches)) {
            $team = ucfirst($matches[1]);
            
            if (stripos($text, 'end') !== false || stripos($text, 'over') !== false) {
                $corrections[] = [
                    'text' => $reminder['original_text'],
                    'old_type' => $currentType,
                    'new_type' => 'sports_game_end',
                    'entity' => $team,
                    'condition' => 'game_over',
                    'keywords' => [$matches[1], 'game', 'end', 'over', 'final']
                ];
                echo "   ✅ Fixed: sports_game_end ($team)\n";
            } elseif (stripos($text, 'start') !== false || stripos($text, 'begin') !== false) {
                $corrections[] = [
                    'text' => $reminder['original_text'],
                    'old_type' => $currentType,
                    'new_type' => 'sports_game_start',
                    'entity' => $team,
                    'condition' => 'game_starts',
                    'keywords' => [$matches[1], 'game', 'start', 'begin']
                ];
                echo "   ✅ Fixed: sports_game_start ($team)\n";
            }
        }
        
        // Auto-fix stock mentions
        elseif ((stripos($text, 'stock') !== false || stripos($text, 'apple') !== false || stripos($text, 'tesla') !== false) &&
                $currentType !== 'stock_price') {
            
            $entity = 'STOCK';
            if (stripos($text, 'apple') !== false) $entity = 'AAPL';
            if (stripos($text, 'tesla') !== false) $entity = 'TSLA';
            
            $corrections[] = [
                'text' => $reminder['original_text'],
                'old_type' => $currentType,
                'new_type' => 'stock_price',
                'entity' => $entity,
                'condition' => 'price_above',
                'keywords' => ['stock', 'price', 'hits', 'reaches']
            ];
            echo "   ✅ Fixed: stock_price ($entity)\n";
        }
        
        // Auto-fix weather mentions
        elseif ((stripos($text, 'degree') !== false || stripos($text, 'temperature') !== false || stripos($text, 'weather') !== false) &&
                $currentType !== 'weather') {
            
            $corrections[] = [
                'text' => $reminder['original_text'],
                'old_type' => $currentType,
                'new_type' => 'weather',
                'entity' => 'temperature',
                'condition' => 'temperature_reaches',
                'keywords' => ['degree', 'temperature', 'weather', 'hits']
            ];
            echo "   ✅ Fixed: weather\n";
        }
        
        else {
            echo "   → No obvious fixes needed\n";
        }
    }
    
    echo "\n📋 Generated " . count($corrections) . " automatic corrections\n\n";
    
    if (empty($corrections)) {
        echo "❌ No corrections needed - your AI might already be well-trained!\n";
        echo "💡 If you still have issues, use the web interface: simple_data_editor.html\n";
        exit;
    }
    
    // Generate enhanced parser with these corrections
    generateEnhancedParser($corrections);
    
    echo "✅ Enhanced AI Parser Generated!\n";
    echo "📁 File: enhanced_ai_parser.php\n";
    echo "🧪 Test it: php test_enhanced_parser.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function generateEnhancedParser($corrections) {
    // Build patterns from corrections
    $gameEndPatterns = [];
    $gameStartPatterns = [];
    $entityPatterns = [];
    
    foreach ($corrections as $correction) {
        if ($correction['new_type'] === 'sports_game_end') {
            $gameEndPatterns[] = [
                'team' => strtolower($correction['entity']),
                'keywords' => ['end', 'over', 'finished', 'final', 'done', 'complete'],
                'confidence' => 0.9
            ];
        } elseif ($correction['new_type'] === 'sports_game_start') {
            $gameStartPatterns[] = [
                'team' => strtolower($correction['entity']),
                'keywords' => ['start', 'begin', 'kick off', 'first pitch', 'opening'],
                'confidence' => 0.9
            ];
        }
        
        $entityPatterns[] = [
            'text' => strtolower($correction['text']),
            'type' => $correction['new_type'],
            'entity' => $correction['entity'],
            'condition' => $correction['condition']
        ];
    }
    
    $parserCode = "<?php
// enhanced_ai_parser.php - Auto-generated with smart corrections
// Generated on: " . date('Y-m-d H:i:s') . "
// Based on " . count($corrections) . " automatic corrections

class EnhancedAIParser {
    private \$gameEndPatterns;
    private \$gameStartPatterns;
    private \$entityPatterns;
    
    public function __construct() {
        \$this->gameEndPatterns = " . var_export($gameEndPatterns, true) . ";
        \$this->gameStartPatterns = " . var_export($gameStartPatterns, true) . ";
        \$this->entityPatterns = " . var_export($entityPatterns, true) . ";
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
        
        // PRIORITY 1: Enhanced Sports Game Detection (fixes your Yankees issue!)
        foreach (\$this->gameEndPatterns as \$pattern) {
            if (stripos(\$text, \$pattern['team']) !== false) {
                foreach (\$pattern['keywords'] as \$keyword) {
                    if (stripos(\$text, \$keyword) !== false) {
                        \$result['event_type'] = 'sports_game_end';
                        \$result['entity'] = ucfirst(\$pattern['team']);
                        \$result['condition'] = 'game_over';
                        \$result['confidence'] = \$pattern['confidence'];
                        \$result['reasoning'] = \"Game END detected: {\$pattern['team']} + {\$keyword}\";
                        return \$result;
                    }
                }
            }
        }
        
        foreach (\$this->gameStartPatterns as \$pattern) {
            if (stripos(\$text, \$pattern['team']) !== false) {
                foreach (\$pattern['keywords'] as \$keyword) {
                    if (stripos(\$text, \$keyword) !== false) {
                        \$result['event_type'] = 'sports_game_start';
                        \$result['entity'] = ucfirst(\$pattern['team']);
                        \$result['condition'] = 'game_starts';
                        \$result['confidence'] = \$pattern['confidence'];
                        \$result['reasoning'] = \"Game START detected: {\$pattern['team']} + {\$keyword}\";
                        return \$result;
                    }
                }
            }
        }
        
        // PRIORITY 2: Time-based parsing (should already work well)
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
        
        // PRIORITY 3: Smart pattern matching from corrections
        foreach (\$this->entityPatterns as \$pattern) {
            \$patternWords = explode(' ', \$pattern['text']);
            \$matchCount = 0;
            \$totalWords = count(\$patternWords);
            
            foreach (\$patternWords as \$word) {
                if (strlen(\$word) > 2 && stripos(\$text, \$word) !== false) {
                    \$matchCount++;
                }
            }
            
            \$matchRatio = \$totalWords > 0 ? \$matchCount / \$totalWords : 0;
            
            // If enough words match, use this pattern
            if (\$matchRatio >= 0.4 || \$matchCount >= 2) {
                \$result['event_type'] = \$pattern['type'];
                \$result['entity'] = \$pattern['entity'];
                \$result['condition'] = \$pattern['condition'];
                \$result['confidence'] = 0.7 + (\$matchRatio * 0.2);
                \$result['reasoning'] = \"Pattern match: {\$matchCount}/{\$totalWords} words from training\";
                return \$result;
            }
        }
        
        return \$result;
    }
}
?>";
    
    file_put_contents('enhanced_ai_parser.php', $parserCode);
}
?>