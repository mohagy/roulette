<?php
// Database initialization script
require_once 'includes/db_connection.php';

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Create logs directory if it doesn't exist
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);
}

$logFile = 'logs/db_init.log';
$timestamp = date('Y-m-d H:i:s');

// Log function
function logInitMessage($message, $logFile, $timestamp) {
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $message . "<br>";
}

// Initialize tables
try {
    // Create roulette_analytics table if it doesn't exist
    if (!tableExists($conn, 'roulette_analytics')) {
        logInitMessage("Creating roulette_analytics table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE roulette_analytics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            current_draw_number INT NOT NULL DEFAULT 1,
            all_spins TEXT,
            number_frequency JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("roulette_analytics table created successfully", $logFile, $timestamp);
            
            // Insert initial record
            $initSql = "INSERT INTO roulette_analytics (id, current_draw_number, all_spins, number_frequency) 
                       VALUES (1, 1, '', '{}')";
                       
            if ($conn->query($initSql) === TRUE) {
                logInitMessage("Initial record inserted into roulette_analytics", $logFile, $timestamp);
            } else {
                throw new Exception("Error inserting initial record: " . $conn->error);
            }
        } else {
            throw new Exception("Error creating roulette_analytics table: " . $conn->error);
        }
    } else {
        logInitMessage("roulette_analytics table already exists", $logFile, $timestamp);
    }
    
    // Create roulette_state table if it doesn't exist
    if (!tableExists($conn, 'roulette_state')) {
        logInitMessage("Creating roulette_state table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE roulette_state (
            id INT PRIMARY KEY AUTO_INCREMENT,
            roll_history TEXT,
            roll_colors TEXT,
            countdown_time INT DEFAULT 120,
            last_draw TIMESTAMP NULL,
            next_draw TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("roulette_state table created successfully", $logFile, $timestamp);
            
            // Insert initial record
            $nextDraw = date('Y-m-d H:i:s', strtotime('+2 minutes'));
            $initSql = "INSERT INTO roulette_state (id, roll_history, roll_colors, countdown_time, last_draw, next_draw) 
                       VALUES (1, '', '', 120, NOW(), '$nextDraw')";
                       
            if ($conn->query($initSql) === TRUE) {
                logInitMessage("Initial record inserted into roulette_state", $logFile, $timestamp);
            } else {
                throw new Exception("Error inserting initial record: " . $conn->error);
            }
        } else {
            throw new Exception("Error creating roulette_state table: " . $conn->error);
        }
    } else {
        logInitMessage("roulette_state table already exists", $logFile, $timestamp);
    }
    
    // Create roulette_settings table if it doesn't exist
    if (!tableExists($conn, 'roulette_settings')) {
        logInitMessage("Creating roulette_settings table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE roulette_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            automatic_mode TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("roulette_settings table created successfully", $logFile, $timestamp);
            
            // Insert initial record
            $initSql = "INSERT INTO roulette_settings (id, automatic_mode) VALUES (1, 1)";
                       
            if ($conn->query($initSql) === TRUE) {
                logInitMessage("Initial record inserted into roulette_settings", $logFile, $timestamp);
            } else {
                throw new Exception("Error inserting initial record: " . $conn->error);
            }
        } else {
            throw new Exception("Error creating roulette_settings table: " . $conn->error);
        }
    } else {
        logInitMessage("roulette_settings table already exists", $logFile, $timestamp);
    }
    
    // Create next_draw_winning_number table if it doesn't exist
    if (!tableExists($conn, 'next_draw_winning_number')) {
        logInitMessage("Creating next_draw_winning_number table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE next_draw_winning_number (
            id INT PRIMARY KEY AUTO_INCREMENT,
            draw_number INT NOT NULL,
            winning_number INT NOT NULL,
            source VARCHAR(50) DEFAULT 'manual',
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("next_draw_winning_number table created successfully", $logFile, $timestamp);
        } else {
            throw new Exception("Error creating next_draw_winning_number table: " . $conn->error);
        }
    } else {
        logInitMessage("next_draw_winning_number table already exists", $logFile, $timestamp);
    }
    
    // Create betting_slips table if it doesn't exist
    if (!tableExists($conn, 'betting_slips')) {
        logInitMessage("Creating betting_slips table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE betting_slips (
            slip_id INT PRIMARY KEY AUTO_INCREMENT,
            slip_number VARCHAR(20) NOT NULL UNIQUE,
            player_id INT NOT NULL,
            draw_number INT NOT NULL,
            total_stake DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_potential_return DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_paid TINYINT(1) NOT NULL DEFAULT 0,
            is_cancelled TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("betting_slips table created successfully", $logFile, $timestamp);
        } else {
            throw new Exception("Error creating betting_slips table: " . $conn->error);
        }
    } else {
        logInitMessage("betting_slips table already exists", $logFile, $timestamp);
    }
    
    // Create players table if it doesn't exist
    if (!tableExists($conn, 'players')) {
        logInitMessage("Creating players table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE players (
            player_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("players table created successfully", $logFile, $timestamp);
            
            // Insert a guest player
            $initSql = "INSERT INTO players (player_id, username) VALUES (1, 'guest')";
                       
            if ($conn->query($initSql) === TRUE) {
                logInitMessage("Guest player inserted into players table", $logFile, $timestamp);
            } else {
                throw new Exception("Error inserting guest player: " . $conn->error);
            }
        } else {
            throw new Exception("Error creating players table: " . $conn->error);
        }
    } else {
        logInitMessage("players table already exists", $logFile, $timestamp);
    }
    
    // Create bets table if it doesn't exist
    if (!tableExists($conn, 'bets')) {
        logInitMessage("Creating bets table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE bets (
            bet_id INT PRIMARY KEY AUTO_INCREMENT,
            bet_type VARCHAR(50) NOT NULL,
            bet_description TEXT NOT NULL,
            bet_amount DECIMAL(10,2) NOT NULL,
            potential_return DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("bets table created successfully", $logFile, $timestamp);
        } else {
            throw new Exception("Error creating bets table: " . $conn->error);
        }
    } else {
        logInitMessage("bets table already exists", $logFile, $timestamp);
    }
    
    // Create slip_details table if it doesn't exist
    if (!tableExists($conn, 'slip_details')) {
        logInitMessage("Creating slip_details table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE slip_details (
            id INT PRIMARY KEY AUTO_INCREMENT,
            slip_id INT NOT NULL,
            bet_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (slip_id) REFERENCES betting_slips(slip_id) ON DELETE CASCADE,
            FOREIGN KEY (bet_id) REFERENCES bets(bet_id) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("slip_details table created successfully", $logFile, $timestamp);
        } else {
            throw new Exception("Error creating slip_details table: " . $conn->error);
        }
    } else {
        logInitMessage("slip_details table already exists", $logFile, $timestamp);
    }
    
    // Create detailed_draw_results table if it doesn't exist
    if (!tableExists($conn, 'detailed_draw_results')) {
        logInitMessage("Creating detailed_draw_results table...", $logFile, $timestamp);
        
        $sql = "CREATE TABLE detailed_draw_results (
            id INT PRIMARY KEY AUTO_INCREMENT,
            draw_number INT NOT NULL UNIQUE,
            winning_number INT NOT NULL,
            winning_color VARCHAR(10) NOT NULL,
            draw_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total_bets INT DEFAULT 0,
            total_stakes DECIMAL(10,2) DEFAULT 0.00,
            total_payouts DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            logInitMessage("detailed_draw_results table created successfully", $logFile, $timestamp);
        } else {
            throw new Exception("Error creating detailed_draw_results table: " . $conn->error);
        }
    } else {
        logInitMessage("detailed_draw_results table already exists", $logFile, $timestamp);
    }
    
    logInitMessage("Database initialization completed successfully", $logFile, $timestamp);
    
} catch (Exception $e) {
    logInitMessage("Error during database initialization: " . $e->getMessage(), $logFile, $timestamp);
}

// Close the connection
$conn->close();

echo "<p>Database initialization process completed. Check the logs for details.</p>";
?> 