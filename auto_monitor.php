<?php
// auto_monitor.php - Automatic monitoring loop

echo "🤖 Starting Automatic TextMeWhen Monitoring\n";
echo "==========================================\n";
echo "Press Ctrl+C to stop\n\n";

$checkInterval = 60; // Check every 60 seconds
$loopCount = 0;

while (true) {
    $loopCount++;
    echo "\n[Loop $loopCount - " . date('Y-m-d H:i:s') . "] Checking reminders...\n";
    
    try {
        // Run the monitoring script
        $output = [];
        $returnCode = 0;
        exec('php serpapi_monitor.php 2>&1', $output, $returnCode);
        
        // Display output
        foreach ($output as $line) {
            echo "  $line\n";
        }
        
        if ($returnCode !== 0) {
            echo "  ❌ Monitor script returned error code: $returnCode\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Error running monitor: " . $e->getMessage() . "\n";
    }
    
    echo "  ⏳ Waiting {$checkInterval} seconds until next check...\n";
    
    // Wait for next check
    sleep($checkInterval);
}
?>