<?php
/**
 * Background Processor for Non-Critical Database Updates
 * 
 * Processes queued updates to analytics and secondary tables
 * without blocking the main TV display response
 */

require_once "db_connect.php";

// Performance logging
$start_time = microtime(true);
$processed_count = 0;
$error_count = 0;

echo "🔄 Background Processor Starting...\n";

// Check for queue file
$queue_file = "../logs/background_queue.log";
$processed_file = "../logs/background_processed.log";

if (!file_exists($queue_file)) {
    echo "📭 No queue file found. Nothing to process.\n";
    exit;
}

// Read and process queue
$queue_lines = file($queue_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (empty($queue_lines)) {
    echo "📭 Queue is empty. Nothing to process.\n";
    exit;
}

echo "📋 Found " . count($queue_lines) . " items in queue\n";

// Clear the queue file immediately to prevent duplicate processing
file_put_contents($queue_file, "");

foreach ($queue_lines as $line) {
    try {
        $data = json_decode($line, true);
        
        if (!$data || !isset($data['winning_number']) || !isset($data['draw_number'])) {
            echo "⚠️ Invalid queue item: " . substr($line, 0, 100) . "\n";
            $error_count++;
            continue;
        }
        
        $winningNumber = (int)$data['winning_number'];
        $drawNumber = (int)$data['draw_number'];
        $color = $data['color'] ?? 'unknown';
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        
        echo "🔄 Processing: Draw {$drawNumber}, Number {$winningNumber}\n";
        
        // Update analytics
        updateAnalytics($conn, $winningNumber, $drawNumber);
        
        // Save to roulette_draws
        saveRouletteDraws($conn, $drawNumber, $winningNumber, $color, $timestamp);
        
        // Log processed item
        $processed_log = json_encode([
            'processed_at' => date('Y-m-d H:i:s'),
            'original_data' => $data,
            'processing_time' => microtime(true) - $start_time
        ]);
        
        file_put_contents($processed_file, $processed_log . "\n", FILE_APPEND | LOCK_EX);
        
        $processed_count++;
        
    } catch (Exception $e) {
        echo "❌ Error processing item: " . $e->getMessage() . "\n";
        $error_count++;
        
        // Log error
        error_log("Background processor error: " . $e->getMessage() . " for data: " . $line);
    }
}

$total_time = microtime(true) - $start_time;

echo "✅ Background processing complete\n";
echo "📊 Processed: {$processed_count} items\n";
echo "❌ Errors: {$error_count} items\n";
echo "⏱️ Total time: " . round($total_time * 1000, 2) . " ms\n";
echo "⚡ Average time per item: " . round(($total_time / max($processed_count, 1)) * 1000, 2) . " ms\n";

/**
 * Update analytics table
 */
function updateAnalytics($conn, $winningNumber, $drawNumber) {
    try {
        $result = $conn->query("SELECT all_spins, number_frequency, current_draw_number FROM roulette_analytics WHERE id = 1");
        
        if ($result && $row = $result->fetch_assoc()) {
            $allSpins = json_decode($row["all_spins"], true) ?: [];
            $numberFrequency = json_decode($row["number_frequency"], true) ?: array_fill(0, 37, 0);
            $currentDrawNumber = (int)$row["current_draw_number"];
        } else {
            $allSpins = [];
            $numberFrequency = array_fill(0, 37, 0);
            $currentDrawNumber = 0;
        }
        
        // Update data
        array_unshift($allSpins, $winningNumber);
        $allSpins = array_slice($allSpins, 0, 100); // Keep last 100 spins
        $numberFrequency[$winningNumber]++;
        $newDrawNumber = max($currentDrawNumber, $drawNumber);
        
        $allSpinsJson = json_encode($allSpins);
        $frequencyJson = json_encode($numberFrequency);
        
        if ($result && $result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
            $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
        } else {
            $stmt = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
        }
        
        if ($stmt->execute()) {
            echo "✅ Analytics updated for number {$winningNumber}\n";
        } else {
            throw new Exception("Analytics update failed: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo "❌ Analytics update failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Save to roulette_draws table
 */
function saveRouletteDraws($conn, $drawNumber, $winningNumber, $color, $timestamp) {
    try {
        // Check if draw already exists
        $checkStmt = $conn->prepare("SELECT draw_id FROM roulette_draws WHERE draw_number = ?");
        $checkStmt->bind_param("i", $drawNumber);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "⚠️ Draw {$drawNumber} already exists in roulette_draws\n";
            $checkStmt->close();
            return;
        }
        
        $checkStmt->close();
        
        // Insert new draw
        $stmt = $conn->prepare("INSERT INTO roulette_draws (draw_number, winning_number, winning_color, draw_time, is_manual, total_bets, total_stake, total_payout) VALUES (?, ?, ?, ?, 0, 0, 0.00, 0.00)");
        $stmt->bind_param("iiss", $drawNumber, $winningNumber, $color, $timestamp);
        
        if ($stmt->execute()) {
            echo "✅ Roulette draw saved: {$drawNumber}\n";
        } else {
            throw new Exception("Roulette draw save failed: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo "❌ Roulette draw save failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

$conn->close();
?>
