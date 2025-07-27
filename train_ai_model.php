<?php
// train_ai_model.php - Train your AI with collected data

$config = require 'config.php';

class AITrainer {
    private $pdo;
    private $trainingData = [];
    
    public function __construct() {
        try {
            $config = require 'config.php';
            $this->pdo = new PDO("sqlite:" . $config['database']['path']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage() . "\nMake sure textmewhen.db exists and run: php setup_database.php");
        }
    }
    
    public function loadTrainingData() {
        echo "🤖 Loading Training Data from Database...\n";
        echo "========================================\n\n";
        
        try {
            // Load all reminders
            $stmt = $this->pdo->query("SELECT * FROM reminders ORDER BY created_at DESC");
            $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($reminders)) {
                echo "❌ No reminders found in database!\n";
                echo "💡 Try creating some reminders first using the web interface.\n";
                return [];
            }
            
            foreach ($reminders as $reminder) {
                $parsed = json_decode($reminder['parsed_data'], true);
                
                $this->trainingData[] = [
                    'input' => $reminder['original_text'],
                    'event_type' => $parsed['event_type'] ?? 'unknown',
                    'entity' => $parsed['entity'] ?? null,
                    'has_time' => isset($parsed['target_time']) && !empty($parsed['target_time']),
                    'target_time' => $parsed['target_time'] ?? null,
                    'keywords' => $parsed['keywords'] ?? [],
                    'success' => $reminder['status'] === 'completed'
                ];
            }
            
            echo "✅ Loaded " . count($this->trainingData) . " training examples\n\n";
            return $this->trainingData;
            
        } catch (Exception $e) {
            echo "❌ Error loading training data: " . $e->getMessage() . "\n";
            echo "💡 Make sure the database exists and has been set up properly.\n";
            echo "   Run: php setup_database.php\n";
            return [];
        }
    }
    
    public function analyzePatterns() {
        echo "📊 Analyzing Patterns in Your Data...\n";
        echo "====================================\n\n";
        
        if (empty($this->trainingData)) {
            echo "❌ No training data to analyze!\n";
            return ['event_types' => [], 'time_based' => 0, 'successful' => 0, 'common_words' => []];
        }
        
        $eventTypes = [];
        $timeBasedCount = 0;
        $successfulCount = 0;
        $commonWords = [];
        
        foreach ($this->trainingData as $example) {
            // Count event types
            $type = $example['event_type'];
            $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;
            
            // Count time-based
            if ($example['has_time']) {
                $timeBasedCount++;
            }
            
            // Count successful
            if ($example['success']) {
                $successfulCount++;
            }
            
            // Extract common words
            $words = explode(' ', strtolower($example['input']));
            foreach ($words as $word) {
                if (strlen($word) > 3) {
                    $commonWords[$word] = ($commonWords[$word] ?? 0) + 1;
                }
            }
        }
        
        // Display analysis
        echo "Event Type Distribution:\n";
        foreach ($eventTypes as $type => $count) {
            $percentage = round(($count / count($this->trainingData)) * 100, 1);
            echo "  $type: $count examples ($percentage%)\n";
        }
        
        echo "\nTime-based reminders: $timeBasedCount\n";
        echo "Successful reminders: $successfulCount\n";
        
        echo "\nMost common words:\n";
        arsort($commonWords);
        $topWords = array_slice($commonWords, 0, 10, true);
        foreach ($topWords as $word => $count) {
            echo "  '$word': $count times\n";
        }
        
        return [
            'event_types' => $eventTypes,
            'time_based' => $timeBasedCount,
            'successful' => $successfulCount,
            'common_words' => $topWords
        ];
    }
    
    public function generateImprovedParser() {
        echo "\n🧠 Generating Improved AI Parser...\n";
        echo "==================================\n";
        
        $patterns = $this->extractPatterns();
        
        $parserCode = "<?php
// improved_ai_parser.php - Generated from your training data
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
            'target_time' => null
        ];
        
        // Time-based parsing (highest priority)
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
        
        // Pattern-based parsing
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
        
        return $patterns;
    }
    
    private function extractPatterns() {
        $patterns = [];
        
        foreach ($this->trainingData as $example) {
            $text = strtolower($example['input']);
            $type = $example['event_type'];
            
            // Extract key phrases for each type
            if ($type === 'sports_game') {
                if (preg_match('/\b(yankees|jets|giants|knicks|mets)\b/', $text, $matches)) {
                    $patterns[] = [
                        'trigger' => $matches[1],
                        'type' => 'sports_game',
                        'entity' => ucfirst($matches[1]),
                        'confidence' => 0.8
                    ];
                }
            }
            
            if (strpos($text, 'stock') !== false || strpos($text, 'market') !== false) {
                $patterns[] = [
                    'trigger' => 'stock',
                    'type' => 'stock_price',
                    'entity' => 'market',
                    'confidence' => 0.7
                ];
            }
            
            if (strpos($text, 'temperature') !== false || strpos($text, 'degrees') !== false) {
                $patterns[] = [
                    'trigger' => 'temperature',
                    'type' => 'weather',
                    'entity' => 'temperature',
                    'confidence' => 0.7
                ];
            }
        }
        
        // Remove duplicates
        $patterns = array_unique($patterns, SORT_REGULAR);
        
        return $patterns;
    }
    
    public function exportForExternalTraining() {
        echo "\n📤 Exporting Data for External ML Training...\n";
        echo "============================================\n";
        
        // Format for popular ML frameworks
        $datasets = [
            'sklearn_format' => [],
            'tensorflow_format' => [],
            'simple_csv' => []
        ];
        
        foreach ($this->trainingData as $example) {
            // Scikit-learn format
            $datasets['sklearn_format'][] = [
                'text' => $example['input'],
                'label' => $example['event_type']
            ];
            
            // TensorFlow format
            $datasets['tensorflow_format'][] = [
                'inputs' => $example['input'],
                'targets' => $example['event_type']
            ];
            
            // Simple CSV format
            $datasets['simple_csv'][] = [
                $example['input'],
                $example['event_type'],
                $example['has_time'] ? 'yes' : 'no',
                $example['success'] ? 'successful' : 'failed'
            ];
        }
        
        // Save files
        file_put_contents('ml_training_sklearn.json', json_encode($datasets['sklearn_format'], JSON_PRETTY_PRINT));
        file_put_contents('ml_training_tensorflow.json', json_encode($datasets['tensorflow_format'], JSON_PRETTY_PRINT));
        
        // CSV format
        $csv = fopen('ml_training_simple.csv', 'w');
        fputcsv($csv, ['input_text', 'event_type', 'has_time', 'success']);
        foreach ($datasets['simple_csv'] as $row) {
            fputcsv($csv, $row);
        }
        fclose($csv);
        
        echo "✅ Exported:\n";
        echo "  - ml_training_sklearn.json (for scikit-learn)\n";
        echo "  - ml_training_tensorflow.json (for TensorFlow)\n";
        echo "  - ml_training_simple.csv (for general use)\n";
        
        return $datasets;
    }
    
    public function runFullTraining() {
        echo "🚀 Starting Full AI Training Process...\n";
        echo "======================================\n\n";
        
        $trainingData = $this->loadTrainingData();
        
        if (empty($trainingData)) {
            echo "❌ Cannot train AI without training data!\n";
            echo "🎯 Next Steps:\n";
            echo "  1. Create some reminders using the web interface\n";
            echo "  2. Run the monitor to process them: php serpapi_monitor.php\n";
            echo "  3. Then try training again\n";
            return ['training_examples' => 0, 'patterns_found' => 0, 'analysis' => []];
        }
        
        $analysis = $this->analyzePatterns();
        $patterns = $this->generateImprovedParser();
        $exports = $this->exportForExternalTraining();
        
        echo "\n✅ Training Complete!\n";
        echo "====================\n";
        echo "📁 Generated Files:\n";
        echo "  - improved_ai_parser.php (drop-in replacement)\n";
        echo "  - ml_training_*.json/csv (for external ML)\n";
        echo "\n🎯 Next Steps:\n";
        echo "  1. Test: php test_improved_parser.php\n";
        echo "  2. Deploy: Replace parser in process_reminder.php\n";
        echo "  3. Monitor: Check accuracy with new reminders\n";
        
        return [
            'training_examples' => count($this->trainingData),
            'patterns_found' => count($patterns),
            'analysis' => $analysis
        ];
    }
}

// Run the training
if (php_sapi_name() === 'cli') {
    try {
        $trainer = new AITrainer();
        $results = $trainer->runFullTraining();
        
        echo "\n📊 Training Summary:\n";
        echo "  Examples processed: " . $results['training_examples'] . "\n";
        echo "  Patterns extracted: " . $results['patterns_found'] . "\n";
        echo "  Most common type: " . array_search(max($results['analysis']['event_types']), $results['analysis']['event_types']) . "\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>