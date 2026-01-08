<?php
/**
 * Sequential Draw Manager
 *
 * Ensures draw numbers are always sequential and prevents skipping
 * Uses database-level locking to prevent race conditions
 */

require_once "cache_prevention.php";
require_once "db_connect.php";

header("Content-Type: application/json");

class SequentialDrawManager {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Get the next sequential draw number with database lock
     */
    public function getNextDrawNumber() {
        try {
            // Use database lock to prevent race conditions
            $this->conn->query("SELECT GET_LOCK('draw_number_lock', 10)");

            // Get current max draw number from detailed_draw_results
            $result = $this->conn->query("SELECT COALESCE(MAX(draw_number), 0) as max_draw FROM detailed_draw_results");
            $row = $result->fetch_assoc();
            $nextDraw = (int)$row['max_draw'] + 1;

            // Release lock
            $this->conn->query("SELECT RELEASE_LOCK('draw_number_lock')");

            return $nextDraw;

        } catch (Exception $e) {
            // Always release lock on error
            $this->conn->query("SELECT RELEASE_LOCK('draw_number_lock')");
            throw $e;
        }
    }

    /**
     * Save spin with guaranteed sequential draw number
     */
    public function saveSpinSequential($winningNumber, $timestamp = null) {
        try {
            // Start transaction
            $this->conn->autocommit(false);

            // Get lock to ensure sequential numbering
            $this->conn->query("SELECT GET_LOCK('draw_number_lock', 10)");

            // Get next sequential draw number
            $nextDraw = $this->getNextDrawNumber();

            // Determine color
            $color = $this->getRouletteColor($winningNumber);

            // Use current timestamp if none provided
            if (!$timestamp) {
                $timestamp = date("Y-m-d H:i:s");
            }

            // Insert into detailed_draw_results
            $stmt = $this->conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $nextDraw, $winningNumber, $color, $timestamp);

            if (!$stmt->execute()) {
                throw new Exception("Failed to insert into detailed_draw_results: " . $stmt->error);
            }

            // Insert into roulette_draws
            $stmt2 = $this->conn->prepare("INSERT INTO roulette_draws (draw_number, winning_number, winning_color, draw_time, is_manual, total_bets, total_stake, total_payout) VALUES (?, ?, ?, ?, 0, 0, 0.00, 0.00)");
            $stmt2->bind_param("iiss", $nextDraw, $winningNumber, $color, $timestamp);

            if (!$stmt2->execute()) {
                throw new Exception("Failed to insert into roulette_draws: " . $stmt2->error);
            }

            // Update roulette_analytics
            $this->updateAnalytics($winningNumber, $nextDraw);

            // Commit transaction
            $this->conn->commit();

            // Release lock
            $this->conn->query("SELECT RELEASE_LOCK('draw_number_lock')");

            return [
                'status' => 'success',
                'draw_number' => $nextDraw,
                'winning_number' => $winningNumber,
                'winning_color' => $color,
                'timestamp' => $timestamp
            ];

        } catch (Exception $e) {
            // Rollback and release lock
            $this->conn->rollback();
            $this->conn->query("SELECT RELEASE_LOCK('draw_number_lock')");
            throw $e;
        } finally {
            $this->conn->autocommit(true);
        }
    }

    private function getRouletteColor($number) {
        if ($number === 0) {
            return "green";
        } elseif (in_array($number, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36])) {
            return "red";
        } else {
            return "black";
        }
    }

    private function updateAnalytics($winningNumber, $drawNumber) {
        // Get current analytics
        $result = $this->conn->query("SELECT * FROM roulette_analytics WHERE id = 1");

        if ($result->num_rows === 0) {
            $allSpins = [];
            $numberFrequency = array_fill(0, 37, 0);
        } else {
            $analytics = $result->fetch_assoc();
            $allSpins = json_decode($analytics["all_spins"], true) ?: [];
            $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);
        }

        // Update analytics
        array_unshift($allSpins, $winningNumber);
        $allSpins = array_slice($allSpins, 0, 100);
        $numberFrequency[$winningNumber]++;

        $allSpinsJson = json_encode($allSpins);
        $frequencyJson = json_encode($numberFrequency);

        if ($result->num_rows === 0) {
            $stmt = $this->conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $drawNumber);
        } else {
            $stmt = $this->conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
            $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $drawNumber);
        }

        if (!$stmt->execute()) {
            throw new Exception("Failed to update analytics: " . $stmt->error);
        }
    }
}

// Handle API requests
$requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";
if ($requestMethod === "POST") {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data || !isset($data["winning_number"])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Missing winning_number",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        exit;
    }

    $winningNumber = (int)$data["winning_number"];
    $timestamp = $data["timestamp"] ?? null;

    if ($winningNumber < 0 || $winningNumber > 36) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid winning number. Must be between 0 and 36",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        exit;
    }

    try {
        $manager = new SequentialDrawManager($conn);
        $result = $manager->saveSpinSequential($winningNumber, $timestamp);
        echo json_encode($result);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save spin: " . $e->getMessage(),
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    }

} elseif ($requestMethod === "GET") {
    // Get next draw number
    try {
        $manager = new SequentialDrawManager($conn);
        $nextDraw = $manager->getNextDrawNumber();

        echo json_encode([
            "status" => "success",
            "next_draw_number" => $nextDraw,
            "timestamp" => date("Y-m-d H:i:s")
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to get next draw number: " . $e->getMessage(),
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
}
?>
