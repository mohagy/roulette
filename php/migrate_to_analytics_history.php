<?php
/**
 * Migration Script: Move analytics data from roulette_analytics to analytics_history
 * This script migrates existing data and sets up the new analytics system
 */

require_once 'db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Analytics Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Analytics Migration to analytics_history</h1>";

try {
    $pdo->beginTransaction();
    
    // Step 1: Create new tables
    echo "<h2>Step 1: Creating new tables...</h2>";
    
    // Create analytics_history table
    $createAnalyticsHistory = "
        CREATE TABLE IF NOT EXISTS `analytics_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `draw_number` INT NOT NULL COMMENT 'Draw number based on server time',
            `winning_number` INT NOT NULL COMMENT 'Winning number (0-36)',
            `winning_color` VARCHAR(10) NOT NULL COMMENT 'Color: red, black, or green',
            `draw_time` DATETIME NOT NULL COMMENT 'Server time when draw occurred',
            `server_timezone` VARCHAR(50) DEFAULT 'America/Guyana' COMMENT 'Server timezone used',
            `source` VARCHAR(50) DEFAULT 'preset_schedule' COMMENT 'Source: preset_schedule, manual, or random',
            `preset_schedule_id` INT NULL COMMENT 'Reference to preset_schedule.id if from preset',
            `is_preset` TINYINT(1) DEFAULT 0 COMMENT 'Whether this was from a preset schedule',
            `pattern_type` VARCHAR(50) NULL COMMENT 'Pattern type if from preset',
            `scheduled_time` DATETIME NULL COMMENT 'Scheduled time from preset if applicable',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_draw_number` (`draw_number`),
            INDEX `idx_draw_time` (`draw_time`),
            INDEX `idx_source` (`source`),
            INDEX `idx_preset_schedule` (`preset_schedule_id`),
            UNIQUE KEY `unique_draw_number` (`draw_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Analytics history table - stores draw results with server time and preset schedule data'
    ";
    
    try {
        $pdo->exec($createAnalyticsHistory);
        echo "<p class='success'>✓ analytics_history table created</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "<p class='info'>ℹ analytics_history table already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Create analytics_summary table
    $createAnalyticsSummary = "
        CREATE TABLE IF NOT EXISTS `analytics_summary` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `date` DATE NOT NULL COMMENT 'Date for this summary',
            `total_draws` INT DEFAULT 0 COMMENT 'Total draws for this date',
            `red_count` INT DEFAULT 0 COMMENT 'Count of red numbers',
            `black_count` INT DEFAULT 0 COMMENT 'Count of black numbers',
            `green_count` INT DEFAULT 0 COMMENT 'Count of green (0) numbers',
            `number_frequency` TEXT COMMENT 'JSON object with frequency of each number',
            `last_draw_number` INT DEFAULT 0 COMMENT 'Last draw number for this date',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Daily analytics summary for quick access'
    ";
    
    try {
        $pdo->exec($createAnalyticsSummary);
        echo "<p class='success'>✓ analytics_summary table created</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "<p class='info'>ℹ analytics_summary table already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Step 2: Migrate data from detailed_draw_results to analytics_history
    echo "<h2>Step 2: Migrating data from detailed_draw_results...</h2>";
    
    // Check which columns exist in detailed_draw_results
    $columnCheck = $pdo->query("SHOW COLUMNS FROM detailed_draw_results");
    $columns = $columnCheck->fetchAll(PDO::FETCH_COLUMN);
    
    // Determine column names based on what exists
    $colorColumn = in_array('winning_color', $columns) ? 'winning_color' : (in_array('color', $columns) ? 'color' : 'winning_color');
    $timeColumn = in_array('draw_time', $columns) ? 'draw_time' : (in_array('timestamp', $columns) ? 'timestamp' : 'draw_time');
    $hasNotes = in_array('notes', $columns);
    
    // Build SELECT query based on available columns
    $selectFields = "draw_number, winning_number, {$colorColumn} as winning_color, {$timeColumn} as draw_time";
    if ($hasNotes) {
        $selectFields .= ", notes";
    } else {
        $selectFields .= ", NULL as notes";
    }
    
    // Get all draws from detailed_draw_results
    $stmt = $pdo->query("
        SELECT {$selectFields}
        FROM detailed_draw_results
        WHERE winning_number IS NOT NULL
        ORDER BY draw_number ASC
    ");
    
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $migratedCount = 0;
    $skippedCount = 0;
    
    foreach ($draws as $draw) {
        $drawNumber = intval($draw['draw_number']);
        $winningNumber = intval($draw['winning_number']);
        $winningColor = $draw['winning_color'];
        $drawTime = $draw['draw_time'];
        
        // If winning_color is null or empty, determine it from winning_number
        if (empty($winningColor)) {
            if ($winningNumber === 0) {
                $winningColor = 'green';
            } else {
                $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                $winningColor = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
            }
        }
        
        // Determine source
        $source = 'manual';
        $isPreset = 0;
        $presetScheduleId = null;
        $patternType = null;
        
        // Check if this draw is in preset_schedule
        if (strpos($draw['notes'] ?? '', 'preset') !== false || strpos($draw['notes'] ?? '', 'Preset') !== false) {
            $presetStmt = $pdo->prepare("
                SELECT id, pattern_type 
                FROM preset_schedule 
                WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $presetStmt->execute([$drawNumber, $drawNumber]);
            $preset = $presetStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($preset) {
                $source = 'preset_schedule';
                $isPreset = 1;
                $presetScheduleId = $preset['id'];
                $patternType = $preset['pattern_type'];
            }
        }
        
        // Insert or update analytics_history
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO analytics_history 
                (draw_number, winning_number, winning_color, draw_time, source, preset_schedule_id, is_preset, pattern_type, server_timezone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'America/Guyana')
                ON DUPLICATE KEY UPDATE
                    winning_number = VALUES(winning_number),
                    winning_color = VALUES(winning_color),
                    draw_time = VALUES(draw_time),
                    source = VALUES(source),
                    preset_schedule_id = VALUES(preset_schedule_id),
                    is_preset = VALUES(is_preset),
                    pattern_type = VALUES(pattern_type),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $insertStmt->execute([
                $drawNumber,
                $winningNumber,
                $winningColor,
                $drawTime,
                $source,
                $presetScheduleId,
                $isPreset,
                $patternType
            ]);
            
            $migratedCount++;
        } catch (PDOException $e) {
            $skippedCount++;
            echo "<p class='warning'>⚠ Skipped draw #{$drawNumber}: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p class='success'>✓ Migrated {$migratedCount} draws, skipped {$skippedCount}</p>";
    
    // Step 3: Update analytics_summary
    echo "<h2>Step 3: Creating analytics summaries...</h2>";
    
    // Get draws grouped by date
    $summaryStmt = $pdo->query("
        SELECT 
            DATE(draw_time) as date,
            COUNT(*) as total_draws,
            SUM(CASE WHEN winning_color = 'red' THEN 1 ELSE 0 END) as red_count,
            SUM(CASE WHEN winning_color = 'black' THEN 1 ELSE 0 END) as black_count,
            SUM(CASE WHEN winning_color = 'green' THEN 1 ELSE 0 END) as green_count,
            MAX(draw_number) as last_draw_number
        FROM analytics_history
        GROUP BY DATE(draw_time)
    ");
    
    $summaries = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summaries as $summary) {
        // Calculate number frequency for this date
        $freqStmt = $pdo->prepare("
            SELECT winning_number, COUNT(*) as count
            FROM analytics_history
            WHERE DATE(draw_time) = ?
            GROUP BY winning_number
        ");
        $freqStmt->execute([$summary['date']]);
        $frequencies = $freqStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $numberFrequency = array_fill(0, 37, 0);
        foreach ($frequencies as $freq) {
            $numberFrequency[intval($freq['winning_number'])] = intval($freq['count']);
        }
        
        $insertSummaryStmt = $pdo->prepare("
            INSERT INTO analytics_summary 
            (date, total_draws, red_count, black_count, green_count, number_frequency, last_draw_number)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_draws = VALUES(total_draws),
                red_count = VALUES(red_count),
                black_count = VALUES(black_count),
                green_count = VALUES(green_count),
                number_frequency = VALUES(number_frequency),
                last_draw_number = VALUES(last_draw_number),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $insertSummaryStmt->execute([
            $summary['date'],
            $summary['total_draws'],
            $summary['red_count'],
            $summary['black_count'],
            $summary['green_count'],
            json_encode($numberFrequency),
            $summary['last_draw_number']
        ]);
    }
    
    echo "<p class='success'>✓ Created " . count($summaries) . " daily summaries</p>";
    
    // Only commit if transaction is still active
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    echo "<h2>Migration Complete!</h2>";
    echo "<p class='success'>✓ All data has been migrated to analytics_history table</p>";
    echo "<p class='info'>ℹ The roulette_analytics table can now be deprecated (not deleted yet for safety)</p>";
    
} catch (Exception $e) {
    // Only rollback if transaction is still active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p class='error'>❌ Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";

