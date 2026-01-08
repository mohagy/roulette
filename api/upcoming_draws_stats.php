<?php
/**
 * Upcoming Draws Statistics API
 * Provides upcoming draw numbers with betting slip counts and stake amounts
 * for the cashier interface upcoming draws panel
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Include database connection
require_once '../php/db_connect.php';

// Default response
$response = [
    'status' => 'error',
    'message' => 'Failed to fetch upcoming draws information',
    'timestamp' => time()
];

/**
 * Log messages for debugging
 */
function logUpcomingDraws($message, $type = 'INFO') {
    $logFile = '../logs/upcoming_draws_stats.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";

    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Get the last completed draw number
 */
function getLastCompletedDraw($conn) {
    try {
        $stmt = $conn->prepare("SELECT MAX(draw_number) as max_completed_draw FROM detailed_draw_results");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)($row['max_completed_draw'] ?? 0);
        }

        return 0;
    } catch (Exception $e) {
        logUpcomingDraws("Error getting last completed draw: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Get betting slip statistics for a specific draw number
 */
function getDrawSlipStats($conn, $drawNumber) {
    $stats = [
        'betting_slips_count' => 0,
        'total_stake_amount' => 0.00,
        'total_potential_payout' => 0.00
    ];

    try {
        // Get betting slip counts and amounts for this draw
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as slip_count,
                COALESCE(SUM(total_stake), 0) as total_stake,
                COALESCE(SUM(potential_payout), 0) as total_potential_payout
            FROM betting_slips
            WHERE draw_number = ?
            AND is_cancelled = 0
        ");

        $stmt->bind_param("i", $drawNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stats['betting_slips_count'] = (int)$row['slip_count'];
            $stats['total_stake_amount'] = (float)$row['total_stake'];
            $stats['total_potential_payout'] = (float)$row['total_potential_payout'];
        }

        $stmt->close();

    } catch (Exception $e) {
        logUpcomingDraws("Error getting slip stats for draw $drawNumber: " . $e->getMessage(), 'ERROR');
    }

    return $stats;
}

/**
 * Generate upcoming draws with statistics
 */
function generateUpcomingDrawsWithStats($conn, $baseDrawNumber, $count = 10) {
    $upcomingDraws = [];
    $currentTime = new DateTime();

    for ($i = 1; $i <= $count; $i++) {
        $drawNumber = $baseDrawNumber + $i;

        // Calculate estimated time (every 3 minutes)
        $estimatedTime = new DateTime();
        $estimatedTime->add(new DateInterval('PT' . ($i * 3) . 'M'));

        // Get betting slip statistics for this draw
        $stats = getDrawSlipStats($conn, $drawNumber);

        $upcomingDraws[] = [
            'draw_number' => $drawNumber,
            'estimated_time' => $estimatedTime->format('H:i'),
            'estimated_datetime' => $estimatedTime->format('Y-m-d H:i:s'),
            'betting_slips_count' => $stats['betting_slips_count'],
            'total_stake_amount' => $stats['total_stake_amount'],
            'total_potential_payout' => $stats['total_potential_payout'],
            'is_next' => ($i === 1),
            'minutes_from_now' => $i * 3
        ];
    }

    return $upcomingDraws;
}

/**
 * Get system statistics
 */
function getSystemStats($conn) {
    $stats = [
        'total_active_slips' => 0,
        'total_active_stake' => 0.00,
        'last_draw_time' => null,
        'system_time' => date('Y-m-d H:i:s')
    ];

    try {
        // Get total active betting slips
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as active_slips,
                COALESCE(SUM(total_stake), 0) as active_stake
            FROM betting_slips
            WHERE is_paid = 0
            AND is_cancelled = 0
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stats['total_active_slips'] = (int)$row['active_slips'];
            $stats['total_active_stake'] = (float)$row['active_stake'];
        }

        $stmt->close();

        // Get last draw time
        $timeColumn = 'timestamp'; // Default

        // Check which time column exists
        $columnCheck = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'timestamp'");
        $columnCheck->execute();
        $columnResult = $columnCheck->get_result();

        if ($columnResult->num_rows === 0) {
            $columnCheck = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'draw_time'");
            $columnCheck->execute();
            $columnResult = $columnCheck->get_result();

            if ($columnResult->num_rows > 0) {
                $timeColumn = 'draw_time';
            }
        }

        $stmt = $conn->prepare("SELECT $timeColumn FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stats['last_draw_time'] = $row[$timeColumn];
        }

        $stmt->close();

    } catch (Exception $e) {
        logUpcomingDraws("Error getting system stats: " . $e->getMessage(), 'ERROR');
    }

    return $stats;
}

try {
    logUpcomingDraws("Upcoming draws stats request received");

    // Get the last completed draw
    $lastCompletedDraw = getLastCompletedDraw($conn);

    if ($lastCompletedDraw === 0) {
        logUpcomingDraws("No completed draws found, using default base", 'WARNING');
        $lastCompletedDraw = 0; // Will generate draws starting from #1
    }

    // Generate upcoming draws with statistics
    $drawCount = isset($_GET['count']) ? min(20, max(1, (int)$_GET['count'])) : 10;
    $upcomingDraws = generateUpcomingDrawsWithStats($conn, $lastCompletedDraw, $drawCount);

    // Get system statistics
    $systemStats = getSystemStats($conn);

    // Prepare successful response
    $response = [
        'status' => 'success',
        'data' => [
            'upcoming_draws' => $upcomingDraws,
            'base_draw' => $lastCompletedDraw,
            'next_draw' => $lastCompletedDraw + 1,
            'draw_count' => count($upcomingDraws),
            'system_stats' => $systemStats
        ],
        'message' => 'Upcoming draws with statistics retrieved successfully',
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ];

    logUpcomingDraws("Successfully retrieved " . count($upcomingDraws) . " upcoming draws with stats");

} catch (Exception $e) {
    logUpcomingDraws("Error in upcoming draws stats: " . $e->getMessage(), 'ERROR');

    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
