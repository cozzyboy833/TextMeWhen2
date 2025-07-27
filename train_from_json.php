<?php
// train_from_json.php - Train AI from simple_training_data.json

echo "🤖 Training AI from JSON File...\n";
echo "================================\n\n";

// Check if JSON file exists
if (!file_exists('simple_training_data.json')) {
    echo "❌ simple_training_data.json not found!\n";
    echo "   Make sure the file exists in the current directory.\n";
    exit(1);
}

// Load JSON data
$jsonData = file_get_contents('simple_training_data.json');
$trainingData = json_decode($jsonData, true);

if (!$trainingData) {
    echo "❌ Invalid JSON data!\n";
    exit(1);
}

echo "✅ Loaded " . count($trainingData) . " training examples from JSON\n\n";

// Analyze the data
echo "📊 Analyzing Training Data:\n";
echo "===========================\n";

$eventTypes = [];
$timeBasedCount = 0;
$successfulCount = 0;

foreach ($trainingData as $example) {
    $type = $example['event_type'] ?? 'unknown';
    $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;
    
    if ($example['has_target_time'] ?? false) {
        $timeBasedCount++;
    }
    
    if ($example['status'] === 'completed') {
        $successfulCount++;
    }
}

echo "Event Type Distribution:\n";
foreach ($eventTypes as $type => $count) {
    $percentage = round(($count / count($trainingData)) * 100, 1);
    echo "  $type: $count examples ($percentage%)\n";
}

echo "\nTime-based reminders: $timeBasedCount\n";
echo "Successful reminders: $successfulCount\n";

// Generate patterns from the data
echo "\n🧠 Generating AI Patterns:\n";
echo "==========================\n";

$patterns = [];

foreach ($trainingData as $example) {
    $text = strtolower($example['input']);
    $type = $example['event_type'];
    
    // Extract patterns based on actual examples
    if ($type === 'sports_game') {
        if (preg_match('/\b(yankees|jets|giants|knicks|mets)\b/', $text, $matches)) {
            $patterns[] = [
                'trigger' => $matches[1],
                'type' => 'sports_game',
                'entity' => ucfirst($matches[1]),
                'confidence' => 0.85
            ];
        }
    }
    
    if ($type === 'time_relative') {
        $patterns[] = [
            'trigger' => 'in',
            'type' => 'time_relative',
            'entity' => 'timer',
            'confidence' => 0.95
        ];
    }
    
    if ($type === 'time_absolute') {
        $patterns[] = [
            'trigger' => 'when it is',
            'type' => 'time_absolute',
            'entity' => 'clock',
            'confidence' => 0.9
        ];
    }
    
    if (strpos($text, 'stock') !== false || strpos($text, 'market') !== false) {
        $patterns[] = [
            'trigger' => 'stock',
            'type' => 'stock_price',
            'entity' => 'market',
            'confidence' => 0.7
        ];
    }
    
    if (strpos($text, 'degree') !== false || strpos($text, 'temperature') !== false) {
        $patterns[] = [
            'trigger' => 'degree',
            'type' => 'weather',
            'entity' => 'temperature',
            'confidence' => 0.75
        ];
    }
}

// Remove duplicates
$patterns = array_unique($patterns, SORT_REGULAR);

echo "Found " . count($patterns) . " patterns\n";

// Generate improved parser
echo "\n⚡ Generating Improved Parser:\n";
echo "=============================\n";

$parserCode = "<?php
// improved_ai_parser.php - Generated from simple_training_data.json
// Generated on: " . date('Y-m-d H:i:s') . "

class ImprovedAIParser {
    private \$patterns;
    
    public function __construct() {
        \$this->patterns = " . var_export($patterns, true) . ";
    }
    
    public function parseReminder(\$text) {
        \$text = strtolower(\$text);
        \$result = [
            'event_type' => 'generic',
            'entity' => 'unknown',
            'condition' => 'event_occurs',
            'confidence' => 0.5,
            'target_time' => null,
            'keywords' => explode(' ', \$text)
        ];
        
        // High-priority time parsing
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', \$text, \$matches)) {
            \$amount = intval(\$matches[1]);
            \$unit = strtolower(\$matches[2]);
            \$minutes = strpos(\$unit, 'hour') === 0 ? \$amount * 60 : \$amount;
            
            \$result['event_type'] = 'time_relative';
            \$result['entity'] = 'timer';
            \$result['target_time'] = date('Y-m-d H:i:s', strtotime(\"+{\$minutes} minutes\"));
            \$result['confidence'] = 0.95;
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
            return \$result;
        }
        
        // Pattern-based parsing from training data
        foreach (\$this->patterns as \$pattern) {
            if (stripos(\$text, \$pattern['trigger']) !== false) {
                \$result['event_type'] = \$pattern['type'];
                \$result['entity'] = \$pattern['entity'];
                \$result['confidence'] = \$pattern['confidence'];
                break;
            }
        }
        
        return \$result;
    }
}
?>";

file_put_contents('improved_ai_parser.php', $parserCode);
echo "✅ Generated improved_ai_parser.php\n";

// Export training data in different formats
echo "\n📤 Exporting Training Data:\n";
echo "==========================\n";

// CSV format for easy analysis
$csv = fopen('training_data_from_json.csv', 'w');
fputcsv($csv, ['input_text', 'event_type', 'has_target_time', 'status']);

foreach ($trainingData as $example) {
    fputcsv($csv, [
        $example['input'],
        $example['event_type'],
        $example['has_target_time'] ? 'yes' : 'no',
        $example['status']
    ]);
}
fclose($csv);

echo "✅ Exported training_data_from_json.csv\n";

echo "\n🎯 Training Complete!\n";
echo "====================\n";
echo "📁 Generated Files:\n";
echo "  - improved_ai_parser.php (improved AI parser)\n";
echo "  - training_data_from_json.csv (data analysis)\n";
echo "\n🧪 Test Your AI:\n";
echo "   php test_improved_parser.php\n";

echo "\n📊 Training Summary:\n";
echo "  Total Examples: " . count($trainingData) . "\n";
echo "  Patterns Found: " . count($patterns) . "\n";
echo "  Success Rate: " . round(($successfulCount / count($trainingData)) * 100, 1) . "%\n";

$mostCommonType = array_search(max($eventTypes), $eventTypes);
echo "  Most Common Type: $mostCommonType\n";

echo "\n✅ Your AI is now trained and ready to use!\n";
?>