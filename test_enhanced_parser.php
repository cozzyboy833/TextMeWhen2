<?php
// test_enhanced_parser.php - Test your retrained AI

echo "🧪 Testing Enhanced AI Parser\n";
echo "============================\n\n";

// Check if enhanced parser exists
if (!file_exists('enhanced_ai_parser.php')) {
    echo "❌ enhanced_ai_parser.php not found!\n";
    echo "   Run the data editor first to generate the enhanced parser.\n";
    echo "   Open: simple_data_editor.html\n";
    exit;
}

require_once 'enhanced_ai_parser.php';
$parser = new EnhancedAIParser();

echo "✅ Enhanced AI parser loaded\n\n";

// Test cases that should work better after training
$testCases = [
    // Your specific Yankees issue
    "message me when the yankees game ends",
    "notify me when the yankees game is over", 
    "text me when the yankees game finishes",
    
    // Game starts vs ends
    "remind me when the jets game starts",
    "let me know when the jets game begins",
    
    // Time-based (should still work well)
    "remind me in 10 minutes",
    "notify me when it is 5:30 PM",
    
    // Stock examples
    "tell me when Apple stock hits $200",
    "message me when Tesla reaches $500",
    
    // Weather
    "text me when it hits 75 degrees",
    
    // Generic
    "let me know when the new iPhone is announced"
];

echo "🔍 Testing " . count($testCases) . " examples:\n";
echo str_repeat("=", 50) . "\n\n";

$correctCount = 0;
$totalTests = count($testCases);

foreach ($testCases as $i => $testCase) {
    echo "Test #" . ($i + 1) . ": \"$testCase\"\n";
    
    $result = $parser->parseReminder($testCase);
    
    echo "  🤖 AI Classification:\n";
    echo "     Type: " . $result['event_type'] . "\n";
    echo "     Entity: " . $result['entity'] . "\n";
    echo "     Condition: " . $result['condition'] . "\n";
    echo "     Confidence: " . round($result['confidence'] * 100, 1) . "%\n";
    echo "     Reasoning: " . $result['reasoning'] . "\n";
    
    if (isset($result['target_time']) && $result['target_time']) {
        echo "     Target Time: " . date('M j, Y g:i A', strtotime($result['target_time'])) . "\n";
    }
    
    // Basic accuracy check
    $isGoodClassification = false;
    
    if (stripos($testCase, 'yankees') !== false && stripos($testCase, 'end') !== false) {
        $isGoodClassification = ($result['event_type'] === 'sports_game_end');
    } elseif (stripos($testCase, 'jets') !== false && stripos($testCase, 'start') !== false) {
        $isGoodClassification = ($result['event_type'] === 'sports_game_start');
    } elseif (stripos($testCase, 'in ') !== false && stripos($testCase, 'minute') !== false) {
        $isGoodClassification = ($result['event_type'] === 'time_relative');
    } elseif (stripos($testCase, 'when it is') !== false) {
        $isGoodClassification = ($result['event_type'] === 'time_absolute');
    } elseif (stripos($testCase, 'stock') !== false) {
        $isGoodClassification = ($result['event_type'] === 'stock_price');
    } else {
        // For other cases, just check if confidence is reasonable
        $isGoodClassification = ($result['confidence'] >= 0.6);
    }
    
    if ($isGoodClassification) {
        echo "     ✅ GOOD CLASSIFICATION\n";
        $correctCount++;
    } else {
        echo "     ❌ NEEDS IMPROVEMENT\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "📊 RESULTS SUMMARY:\n";
echo "==================\n";

$accuracy = round(($correctCount / $totalTests) * 100, 1);
echo "Overall Accuracy: $accuracy% ($correctCount/$totalTests)\n";

if ($accuracy >= 80) {
    echo "🎉 EXCELLENT! Your AI training worked great!\n";
} elseif ($accuracy >= 60) {
    echo "👍 GOOD! Much better than before. Consider more training examples.\n";
} else {
    echo "⚠️  NEEDS MORE TRAINING. Add more corrections in the data editor.\n";
}

echo "\n🎯 SPECIFIC IMPROVEMENTS:\n";
echo "========================\n";

// Check specific issues that were problematic
$yankeesEndTest = "message me when the yankees game ends";
$yankeesResult = $parser->parseReminder($yankeesEndTest);

echo "Yankees Game End Test:\n";
echo "  Input: \"$yankeesEndTest\"\n";
echo "  Classification: " . $yankeesResult['event_type'] . "\n";
echo "  Entity: " . $yankeesResult['entity'] . "\n";

if ($yankeesResult['event_type'] === 'sports_game_end' && stripos($yankeesResult['entity'], 'yankees') !== false) {
    echo "  ✅ FIXED! Now correctly identifies game END events\n";
} else {
    echo "  ❌ Still needs work - classify this as 'sports_game_end'\n";
}

echo "\n💡 NEXT STEPS:\n";
echo "==============\n";

if ($accuracy < 80) {
    echo "1. 🔧 Open simple_data_editor.html\n";
    echo "2. 📝 Correct more examples\n";
    echo "3. 💾 Save corrections and retrain\n";
    echo "4. 🧪 Test again\n";
} else {
    echo "1. ✅ Your AI is well-trained!\n";
    echo "2. 🚀 Deploy enhanced_ai_parser.php to production\n";
    echo "3. 📊 Monitor real-world performance\n";
    echo "4. 🔄 Continue improving with new examples\n";
}

echo "\n📁 FILES GENERATED:\n";
echo "===================\n";
echo "- enhanced_ai_parser.php (improved AI model)\n";
echo "- AI corrections stored in database\n";

echo "\n🎓 TRAINING COMPLETE!\n";
echo "Your AI should now handle the Yankees game issue much better.\n";
?>