<?php
// test.php - Simple test to see if PHP is working

header('Content-Type: application/json');

try {
    // Test basic PHP
    echo json_encode([
        'php_version' => PHP_VERSION,
        'status' => 'PHP is working!',
        'time' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>