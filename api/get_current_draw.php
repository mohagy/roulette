<?php
/**
 * Get Current Draw API
 *
 * This API endpoint gets the current draw number and status.
 */

// Include database connection
require_once '../php/db_connect.php';

// Set headers
header('Content-Type: application/json');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

try {
    // Get the current draw number
    $stmt = $pdo->prepare("
        SELECT current_draw_number FROM roulette_analytics LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $currentDrawNumber = intval($result['current_draw_number']);

        // Get the forced number for the current draw
        $stmt = $pdo->prepare("
            SELECT winning_number, source, reason
            FROM next_draw_winning_number
            WHERE draw_number = ? LIMIT 1
        ");
        $stmt->execute([$currentDrawNumber]);
        $forcedNumber = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get the draw mode
        $stmt = $pdo->prepare("
            SELECT setting_value, automatic_mode
            FROM roulette_settings
            WHERE setting_name = 'draw_mode' LIMIT 1
        ");
        $stmt->execute();
        $drawMode = $stmt->fetch(PDO::FETCH_ASSOC);

        // Determine if automatic mode is enabled
        $isAutomatic = true;
        if ($drawMode) {
            $isAutomatic = $drawMode['automatic_mode'] == 1 || $drawMode['setting_value'] == 'automatic';
        }

        // Prepare response data
        $data = [
            'current_draw_number' => $currentDrawNumber,
            'is_automatic' => $isAutomatic,
            'has_forced_number' => ($forcedNumber !== false),
            'forced_number' => $forcedNumber ? intval($forcedNumber['winning_number']) : null,
            'forced_number_source' => $forcedNumber ? $forcedNumber['source'] : null,
            'forced_number_reason' => $forcedNumber ? $forcedNumber['reason'] : null
        ];

        // If there's a forced number, add the color
        if ($forcedNumber) {
            $number = intval($forcedNumber['winning_number']);
            if ($number === 0) {
                $data['forced_number_color'] = 'green';
            } else {
                $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                $data['forced_number_color'] = in_array($number, $redNumbers) ? 'red' : 'black';
            }
        }

        // Set success response
        $response['status'] = 'success';
        $response['message'] = 'Current draw information retrieved successfully';
        $response['data'] = $data;
    } else {
        // No draw number found, use default
        $response['status'] = 'success';
        $response['message'] = 'No draw number found, using default';
        $response['data'] = [
            'current_draw_number' => 1,
            'is_automatic' => true,
            'has_forced_number' => false,
            'forced_number' => null,
            'forced_number_source' => null,
            'forced_number_reason' => null
        ];
    }

} catch (PDOException $e) {
    // Set error response
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
