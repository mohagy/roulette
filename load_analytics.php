<?php
/**
 * Load Analytics for Display
 *
 * This API loads analytics data for legitimate display purposes
 * while maintaining security against phantom data generation.
 */

// Initialize cache prevention for fresh data
require_once 'php/cache_prevention.php';

header('Content-Type: application/json');

// Enable more detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'php/db_connect.php';

// Log all requests for debugging
logCachePrevention("load_analytics.php called for display", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'timestamp' => date('Y-m-d H:i:s')
]);

// Analytics data is stored in localStorage in the browser, but we can create a centralized place to store it
// This is a simple implementation - in a production environment you might want to create a separate table

try {
    // Get analytics data from database using fresh data (no cache)
    $analyticsData = getFreshData("SELECT SQL_NO_CACHE * FROM roulette_analytics WHERE id = 1");

    if (!empty($analyticsData)) {
        $result = $analyticsData[0];

        logCachePrevention("Analytics data retrieved for display", [
            'current_draw_number' => $result['current_draw_number'],
            'spins_count' => strlen($result['all_spins']),
            'last_updated' => $result['last_updated']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Analytics data loaded successfully',
            'all_spins' => $result['all_spins'],
            'number_frequency' => $result['number_frequency'],
            'rolled_numbers_array' => $result['rolled_numbers_array'] ?? '[]',
            'rolled_numbers_color_array' => $result['rolled_numbers_color_array'] ?? '[]',
            'current_draw_number' => intval($result['current_draw_number']),
            'total_spins' => intval($result['total_spins'] ?? 0),
            'last_updated' => $result['last_updated'],
            'data_source' => 'database_fresh',
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_buster' => time() . rand(1000, 9999)
        ]);
    } else {
        // No data found, return default values
        logCachePrevention("No analytics data found, returning defaults for display");

        echo json_encode([
            'status' => 'success',
            'message' => 'No analytics data found, using defaults',
            'all_spins' => '[]',
            'number_frequency' => json_encode(array_fill(0, 37, 0)),
            'rolled_numbers_array' => '[]',
            'rolled_numbers_color_array' => '[]',
            'current_draw_number' => 0,
            'total_spins' => 0,
            'last_updated' => null,
            'data_source' => 'defaults',
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_buster' => time() . rand(1000, 9999)
        ]);
    }
} catch (Exception $e) {
    logCachePrevention("Error loading analytics for display", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    // Return error along with default values so the app can still run
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'all_spins' => '[]',
        'number_frequency' => json_encode(array_fill(0, 37, 0)),
        'rolled_numbers_array' => '[]',
        'rolled_numbers_color_array' => '[]',
        'current_draw_number' => 0,
        'total_spins' => 0,
        'last_updated' => null,
        'data_source' => 'error_fallback',
        'timestamp' => date('Y-m-d H:i:s'),
        'cache_buster' => time() . rand(1000, 9999)
    ]);
}
?>