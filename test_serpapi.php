<?php
// test_serpapi.php - Test your SerpApi connection

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;

$config = require 'config.php';

$serpApiKey = $config['serpapi']['api_key'];

if (empty($serpApiKey) || $serpApiKey === 'YOUR_SERPAPI_KEY_HERE') {
    echo "❌ Please update your SerpApi key in config.php\n";
    echo "Get your key from: https://serpapi.com/\n";
    exit(1);
}

echo "🔑 Testing SerpApi with key: " . substr($serpApiKey, 0, 10) . "...\n\n";

$client = new Client();

try {
    // Test search
    $response = $client->get('https://serpapi.com/search', [
        'query' => [
            'q' => 'Jets game today score',
            'api_key' => $serpApiKey,
            'engine' => 'google',
            'num' => 3
        ],
        'timeout' => 10
    ]);
    
    $data = json_decode($response->getBody(), true);
    
    if (isset($data['error'])) {
        echo "❌ SerpApi Error: " . $data['error'] . "\n";
        exit(1);
    }
    
    echo "✅ SerpApi connection successful!\n\n";
    
    // Show search info
    if (isset($data['search_information'])) {
        $searchInfo = $data['search_information'];
        echo "📊 Search Information:\n";
        echo "  Query: " . ($searchInfo['query_displayed'] ?? 'N/A') . "\n";
        echo "  Results: " . ($searchInfo['total_results'] ?? 'N/A') . "\n";
        echo "  Time taken: " . ($searchInfo['time_taken_displayed'] ?? 'N/A') . "\n\n";
    }
    
    // Show some results
    if (isset($data['organic_results'])) {
        echo "🔍 Sample Results:\n";
        foreach (array_slice($data['organic_results'], 0, 3) as $i => $result) {
            echo "  " . ($i + 1) . ". " . ($result['title'] ?? 'No title') . "\n";
            echo "     " . substr($result['snippet'] ?? 'No snippet', 0, 100) . "...\n\n";
        }
    }
    
    // Show credits remaining
    if (isset($data['credits_left'])) {
        echo "💳 Credits remaining: " . $data['credits_left'] . "\n";
    }
    
    echo "\n✅ SerpApi is ready to use with your reminder system!\n";
    echo "📝 Try creating reminders like:\n";
    echo "  - 'Notify me when Jets win their next game'\n";
    echo "  - 'Text me when Apple announces new iPhone'\n";
    echo "  - 'Remind me when it rains in New York'\n";
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "Check your internet connection and API key.\n";
}
?>