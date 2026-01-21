<?php
/**
 * Get Recent Draws API
 * 
 * Returns recent draws with their actual draw numbers from the database
 */

// Include database connection
require_once '../php/db_connect.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

try {
    // Get limit parameter (default to 8)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8;
    $limit = max(1, min(50, $limit)); // Ensure limit is between 1 and 50
    
    // Calculate current draw number based on server time (replacing roulette_analytics)
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    $currentDrawNumber = $drawIndex + 1;
    
    // ⏰ CRITICAL: Get the most recent entry for each draw_number first, then limit
    // This prevents duplicates and ensures we get the correct data even if there are multiple entries
    // Using a subquery to get MAX(id) for each draw_number (id is auto-increment, most recent = highest id)
    // This is more reliable than timestamp since id is always unique and increasing
    $stmt = $pdo->prepare("
        SELECT 
            d1.draw_number,
            d1.winning_number,
            d1.winning_color,
            COALESCE(d1.draw_time, d1.timestamp) as timestamp
        FROM detailed_draw_results d1
        INNER JOIN (
            SELECT 
                draw_number,
                MAX(id) as max_id
            FROM detailed_draw_results
            WHERE winning_number IS NOT NULL
            GROUP BY draw_number
        ) d2 ON d1.draw_number = d2.draw_number 
            AND d1.id = d2.max_id
        WHERE d1.winning_number IS NOT NULL
        ORDER BY d1.draw_number DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ⏰ CRITICAL: If we have draws and the most recent draw in results
    // is not (currentDrawNumber - 1), we might be missing the latest completed draw
    // This can happen due to timing issues. Let's verify we have the most recent draws.
    if (count($draws) > 0) {
        $mostRecentDrawNumber = intval($draws[0]['draw_number']);
        $expectedMostRecent = $currentDrawNumber - 1;
        
        // If we're missing the most recent completed draw, try to get it
        if ($mostRecentDrawNumber < $expectedMostRecent) {
            // Fetch the missing draw(s) - get the most recent entry for each missing draw number
            // Use MAX(id) instead of MAX(timestamp) for more reliable deduplication
            $missingStmt = $pdo->prepare("
                SELECT 
                    d1.draw_number,
                    d1.winning_number,
                    d1.winning_color,
                    COALESCE(d1.draw_time, d1.timestamp) as timestamp
                FROM detailed_draw_results d1
                INNER JOIN (
                    SELECT 
                        draw_number,
                        MAX(id) as max_id
                    FROM detailed_draw_results
                    WHERE winning_number IS NOT NULL
                      AND draw_number > ? AND draw_number <= ?
                    GROUP BY draw_number
                ) d2 ON d1.draw_number = d2.draw_number 
                    AND d1.id = d2.max_id
                WHERE d1.winning_number IS NOT NULL
                ORDER BY d1.draw_number DESC
            ");
            $missingStmt->execute([$mostRecentDrawNumber, $expectedMostRecent]);
            $missingDraws = $missingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepend missing draws to the results
            if (count($missingDraws) > 0) {
                $draws = array_merge($missingDraws, $draws);
                // Limit to requested number
                $draws = array_slice($draws, 0, $limit);
            }
        }
    }
    
    // Additional safety: Deduplicate by draw_number (shouldn't be needed with the query above, but just in case)
    $uniqueDrawsMap = [];
    foreach ($draws as $draw) {
        $drawNum = intval($draw['draw_number']);
        $timestamp = $draw['timestamp'];
        
        // If we haven't seen this draw number, or this entry is more recent, use it
        if (!isset($uniqueDrawsMap[$drawNum]) || 
            $timestamp > $uniqueDrawsMap[$drawNum]['timestamp']) {
            $uniqueDrawsMap[$drawNum] = [
                'draw_number' => $drawNum,
                'winning_number' => intval($draw['winning_number']),
                'winning_color' => $draw['winning_color'],
                'timestamp' => $timestamp
            ];
        }
    }
    
    // Convert to array and sort by draw_number DESC to maintain order
    $uniqueDraws = array_values($uniqueDrawsMap);
    usort($uniqueDraws, function($a, $b) {
        return $b['draw_number'] - $a['draw_number'];
    });
    
    // Limit to requested number (final safety check)
    $uniqueDraws = array_slice($uniqueDraws, 0, $limit);
    
    // Format the response
    $formattedDraws = [];
    foreach ($uniqueDraws as $draw) {
        $formattedDraws[] = [
            'draw_number' => $draw['draw_number'],
            'winning_number' => $draw['winning_number'],
            'winning_color' => $draw['winning_color'],
            'timestamp' => $draw['timestamp']
        ];
    }
    
    // Set success response
    $response['status'] = 'success';
    $response['message'] = 'Recent draws retrieved successfully';
    $response['data'] = $formattedDraws;
    
} catch (PDOException $e) {
    // Set error response
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    // Set error response
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
?>
