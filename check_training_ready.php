<?php
// check_training_ready.php - Check if system is ready for AI training

echo "🔍 Checking Training Prerequisites...\n";
echo "=====================================\n\n";

// 1. Check config file
if (!file_exists('config.php')) {
    echo "❌ config.php not found!\n";
    echo "   Create config.php with database settings.\n";
    exit(1);
} else {
    echo "✅ config.php found\n";
}

// 2. Load config and check database
try {
    $config = require 'config.php';
    
    if (!isset($config['database']['path'])) {
        echo "❌ Database path not configured in config.php\n";
        exit(1);
    }
    
    $dbPath = $config['database']['path'];
    echo "📁 Database path: $dbPath\n";
    
    if (!file_exists($dbPath)) {
        echo "❌ Database file doesn't exist!\n";
        echo "   Run: php setup_database.php\n";
        exit(1);
    } else {
        echo "✅ Database file found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error loading config: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Check database connection and tables
try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful\n";
    
    // Check if reminders table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reminders'");
    if ($stmt->fetch()) {
        echo "✅ Reminders table exists\n";
    } else {
        echo "❌ Reminders table not found!\n";
        echo "   Run: php setup_database.php\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Check for training data
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reminders");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Found $count reminders in database\n";
    
    if ($count == 0) {
        echo "⚠️  No training data available!\n";
        echo "   Next steps:\n";
        echo "   1. Create reminders using the web interface (index.html)\n";
        echo "   2. Process them: php serpapi_monitor.php\n";
        echo "   3. Then try training again\n\n";
        
        echo "💡 Or use the existing simple_training_data.json file\n";
        if (file_exists('simple_training_data.json')) {
            echo "✅ Found simple_training_data.json\n";
            $simpleData = json_decode(file_get_contents('simple_training_data.json'), true);
            echo "   Contains " . count($simpleData) . " examples\n";
        }
        
    } else {
        echo "✅ Training data available!\n";
        
        // Show some sample data
        $stmt = $pdo->query("SELECT original_text, parsed_data, status FROM reminders ORDER BY created_at DESC LIMIT 3");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n📝 Sample training data:\n";
        foreach ($samples as $i => $sample) {
            echo "   " . ($i+1) . ". \"" . $sample['original_text'] . "\"\n";
            $parsed = json_decode($sample['parsed_data'], true);
            echo "      Type: " . ($parsed['event_type'] ?? 'unknown') . ", Status: " . $sample['status'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error checking training data: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎯 SYSTEM STATUS:\n";
echo "================\n";

if ($count > 0) {
    echo "✅ Ready for AI training!\n";
    echo "   Run: php train_ai_model.php\n";
} else {
    echo "⚠️  Need more training data first\n";
    echo "   1. Create reminders via web interface\n";
    echo "   2. Or import existing data\n";
    echo "   3. Then run training\n";
}

echo "\n📚 Available commands:\n";
echo "   php train_ai_model.php        - Train AI with your data\n";
echo "   php export_training_data.php  - Export data for ML\n";
echo "   php inspect_database_fixed.php - View all data\n";
echo "   php serpapi_monitor.php       - Process reminders\n";
echo "   http://localhost:8000/dashboard.php - Web dashboard\n";

echo "\n✅ Prerequisites check complete!\n";
?>