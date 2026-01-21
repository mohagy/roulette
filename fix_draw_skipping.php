<?php
/**
 * EMERGENCY FIX: Draw Number Skipping Issue
 *
 * This script fixes the draw number skipping by:
 * 1. Identifying gaps in the sequence
 * 2. Implementing a simple sequential system
 * 3. Providing a clean API for future spins
 */

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage(),
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

function getRouletteColor($number) {
    if ($number === 0) {
        return "green";
    } elseif (in_array($number, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36])) {
        return "red";
    } else {
        return "black";
    }
}

// Handle different actions
$action = $_GET['action'] ?? 'analyze';

if ($action === 'analyze') {
    // Analyze current gaps
    $result = $conn->query("SELECT draw_number FROM detailed_draw_results ORDER BY draw_number ASC");
    $draws = [];
    while ($row = $result->fetch_assoc()) {
        $draws[] = (int)$row['draw_number'];
    }

    $gaps = [];
    for ($i = 1; $i < count($draws); $i++) {
        $current = $draws[$i];
        $previous = $draws[$i-1];

        if ($current - $previous > 1) {
            for ($missing = $previous + 1; $missing < $current; $missing++) {
                $gaps[] = $missing;
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "current_draws" => $draws,
        "missing_draws" => $gaps,
        "total_gaps" => count($gaps),
        "next_sequential" => max($draws) + 1
    ]);

} elseif ($action === 'add_spin') {
    // Add a new spin with guaranteed sequential number
    $winningNumber = (int)($_POST['winning_number'] ?? $_GET['winning_number'] ?? 0);

    if ($winningNumber < 0 || $winningNumber > 36) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid winning number"
        ]);
        exit;
    }

    try {
        // Start transaction
        $conn->autocommit(false);

        // Get next sequential draw number
        $result = $conn->query("SELECT COALESCE(MAX(draw_number), 0) + 1 as next_draw FROM detailed_draw_results");
        $row = $result->fetch_assoc();
        $nextDraw = (int)$row['next_draw'];

        $color = getRouletteColor($winningNumber);
        $timestamp = date("Y-m-d H:i:s");

        // Insert into detailed_draw_results
        $stmt = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $nextDraw, $winningNumber, $color, $timestamp);

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert into detailed_draw_results");
        }

        // Insert into roulette_draws
        $stmt2 = $conn->prepare("INSERT INTO roulette_draws (draw_number, winning_number, winning_color, draw_time, is_manual, total_bets, total_stake, total_payout) VALUES (?, ?, ?, ?, 0, 0, 0.00, 0.00)");
        $stmt2->bind_param("iiss", $nextDraw, $winningNumber, $color, $timestamp);

        if (!$stmt2->execute()) {
            throw new Exception("Failed to insert into roulette_draws");
        }

        // Update analytics
        $analyticsResult = $conn->query("SELECT * FROM roulette_analytics WHERE id = 1");

        if ($analyticsResult->num_rows === 0) {
            $allSpins = [$winningNumber];
            $numberFrequency = array_fill(0, 37, 0);
            $numberFrequency[$winningNumber] = 1;
        } else {
            $analytics = $analyticsResult->fetch_assoc();
            $allSpins = json_decode($analytics["all_spins"], true) ?: [];
            $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);

            array_unshift($allSpins, $winningNumber);
            $allSpins = array_slice($allSpins, 0, 100);
            $numberFrequency[$winningNumber]++;
        }

        $allSpinsJson = json_encode($allSpins);
        $frequencyJson = json_encode($numberFrequency);

        if ($analyticsResult->num_rows === 0) {
            $stmt3 = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
            $stmt3->bind_param("ssi", $allSpinsJson, $frequencyJson, $nextDraw);
        } else {
            $stmt3 = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
            $stmt3->bind_param("ssi", $allSpinsJson, $frequencyJson, $nextDraw);
        }

        if (!$stmt3->execute()) {
            throw new Exception("Failed to update analytics");
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            "status" => "success",
            "draw_number" => $nextDraw,
            "winning_number" => $winningNumber,
            "winning_color" => $color,
            "timestamp" => $timestamp,
            "message" => "Spin added with sequential draw number"
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            "status" => "error",
            "message" => "Failed to add spin: " . $e->getMessage()
        ]);
    } finally {
        $conn->autocommit(true);
    }

} elseif ($action === 'get_next') {
    // Get next sequential draw number
    $result = $conn->query("SELECT COALESCE(MAX(draw_number), 0) + 1 as next_draw FROM detailed_draw_results");
    $row = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "next_draw_number" => (int)$row['next_draw']
    ]);

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action. Use: analyze, add_spin, or get_next"
    ]);
}

$conn->close();
?>
