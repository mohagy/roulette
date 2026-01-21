<?php
/**
 * Check Draw Tables
 * 
 * This script checks if the necessary database tables for the draw history exist.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set headers
header('Content-Type: application/json');

// Function to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Initialize response
$response = [
    'status' => 'success',
    'message' => 'Database tables check completed',
    'data' => [
        'tables' => [],
        'missing_tables' => []
    ]
];

// List of tables to check
$tables = [
    'detailed_draw_results',
    'game_history',
    'next_draw_winning_number',
    'roulette_state',
    'roulette_analytics',
    'roulette_settings'
];

// Check each table
foreach ($tables as $table) {
    $exists = tableExists($pdo, $table);
    $response['data']['tables'][$table] = $exists;
    
    if (!$exists) {
        $response['data']['missing_tables'][] = $table;
    }
}

// Set overall status
if (count($response['data']['missing_tables']) > 0) {
    $response['message'] = 'Some tables are missing. Please run setup_draw_tables.php to create them.';
    $response['status'] = 'warning';
}

// Return the response
echo json_encode($response);
