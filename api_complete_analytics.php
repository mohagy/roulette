<?php
/**
 * API endpoint for TV Display - Complete Analytics from detailed_draw_results table
 * This replaces ALL old analytics data sources with accurate MySQL data
 */

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Database connection - try multiple paths
$conn = null;
$dbConnectPaths = [
    __DIR__ . '/../php/db_connect.php',
    __DIR__ . '/php/db_connect.php',
    'php/db_connect.php',
    '../php/db_connect.php'
];

foreach ($dbConnectPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// If db_connect.php didn't create $conn, create it manually
if (!isset($conn) || !$conn) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "roulette";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database connection failed: " . $conn->connect_error,
            "analytics" => [
                "last_8_spins" => [],
                "hot_numbers" => [],
                "cold_numbers" => [],
                "color_distribution" => [
                    "red" => ["count" => 0, "percentage" => 0],
                    "black" => ["count" => 0, "percentage" => 0],
                    "green" => ["count" => 0, "percentage" => 0]
                ],
                "odd_even_distribution" => [
                    "odd" => ["count" => 0, "percentage" => 0],
                    "even" => ["count" => 0, "percentage" => 0]
                ],
                "high_low_distribution" => [
                    "low" => ["count" => 0, "percentage" => 0],
                    "high" => ["count" => 0, "percentage" => 0]
                ],
                "dozens_distribution" => [
                    "first" => ["count" => 0, "percentage" => 0],
                    "second" => ["count" => 0, "percentage" => 0],
                    "third" => ["count" => 0, "percentage" => 0]
                ],
                "columns_distribution" => [
                    "first" => ["count" => 0, "percentage" => 0],
                    "second" => ["count" => 0, "percentage" => 0],
                    "third" => ["count" => 0, "percentage" => 0]
                ],
                "total_spins" => 0
            ],
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        exit;
    }
}

try {
    // Check if detailed_draw_results table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        // Create the table
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `detailed_draw_results` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `draw_id` varchar(20) NOT NULL,
            `draw_number` int(11) NOT NULL,
            `winning_number` int(11) NOT NULL,
            `winning_color` varchar(10) DEFAULT NULL,
            `color` varchar(10) DEFAULT NULL,
            `draw_time` timestamp NULL DEFAULT NULL,
            `timestamp` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_draw_number` (`draw_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($createTableSQL)) {
            throw new Exception("Failed to create detailed_draw_results table: " . $conn->error);
        }
    }
    
    // Ensure both color columns exist (for compatibility)
    $columns = $conn->query("SHOW COLUMNS FROM detailed_draw_results");
    $hasWinningColor = false;
    $hasColor = false;
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] === 'winning_color') $hasWinningColor = true;
            if ($col['Field'] === 'color') $hasColor = true;
        }
    }
    
    if (!$hasWinningColor) {
        $conn->query("ALTER TABLE detailed_draw_results ADD COLUMN `winning_color` varchar(10) DEFAULT NULL AFTER `winning_number`");
    }
    if (!$hasColor) {
        $conn->query("ALTER TABLE detailed_draw_results ADD COLUMN `color` varchar(10) DEFAULT NULL AFTER `winning_color`");
    }
    
    // Populate color from winning_color if color is NULL
    $conn->query("UPDATE detailed_draw_results SET color = winning_color WHERE color IS NULL AND winning_color IS NOT NULL");
    
    // Populate winning_color from color if winning_color is NULL
    $conn->query("UPDATE detailed_draw_results SET winning_color = color WHERE winning_color IS NULL AND color IS NOT NULL");
    
    // Get all analytics data from detailed_draw_results table
    $analytics = [];

    // Get current draw number from roulette_analytics (source of truth)
    $currentDrawNumber = 1;
    try {
        $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1 LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $currentDrawNumber = (int)$row['current_draw_number'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // If roulette_analytics doesn't exist or query fails, use default value
        error_log("Warning: Could not get current_draw_number from roulette_analytics: " . $e->getMessage());
        $currentDrawNumber = 1;
    }

    // 1. GET LAST 8 SPINS
    // Build query based on available columns
    $last8Spins = [];
    try {
        // Check which columns exist
        $columns = $conn->query("SHOW COLUMNS FROM detailed_draw_results");
        $hasWinningColor = false;
        $hasColor = false;
        $hasDrawTime = false;
        $hasTimestamp = false;
        if ($columns) {
            while ($col = $columns->fetch_assoc()) {
                if ($col['Field'] === 'winning_color') $hasWinningColor = true;
                if ($col['Field'] === 'color') $hasColor = true;
                if ($col['Field'] === 'draw_time') $hasDrawTime = true;
                if ($col['Field'] === 'timestamp') $hasTimestamp = true;
            }
        }
        
        // Build SELECT based on available columns
        $colorSelect = $hasWinningColor && $hasColor ? "IFNULL(winning_color, color)" : ($hasWinningColor ? "winning_color" : ($hasColor ? "color" : "'black'"));
        $timeSelect = $hasDrawTime && $hasTimestamp ? "IFNULL(draw_time, IFNULL(timestamp, created_at))" : ($hasDrawTime ? "draw_time" : ($hasTimestamp ? "timestamp" : "created_at"));
        
        $stmt = $conn->prepare("
            SELECT
                draw_number,
                winning_number,
                $colorSelect as color,
                $timeSelect as timestamp,
                created_at
            FROM detailed_draw_results
            ORDER BY draw_number DESC
            LIMIT 8
        ");

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            $rows = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();

        } else {
            error_log("Warning: Could not prepare statement for last 8 spins");
        }
    } catch (Exception $e) {
        error_log("Error fetching last 8 spins: " . $e->getMessage());
        $rows = [];
    }

    // Only process if we have rows
    if (!empty($rows)) {
        // Calculate correct draw numbers based on current draw number
        // The most recent result should be for draw_number = currentDrawNumber - 1
        // (since currentDrawNumber is the NEXT draw, the last completed is currentDrawNumber - 1)
        $lastCompletedDraw = $currentDrawNumber > 1 ? ($currentDrawNumber - 1) : 1;
        
        // Reverse the rows so we process from oldest to newest
        $rows = array_reverse($rows);
        
        foreach ($rows as $index => $row) {
            // Calculate the correct draw number for this spin
            // Most recent spin (last in array) = lastCompletedDraw
            // Previous spin = lastCompletedDraw - 1, etc.
            $correctDrawNumber = $lastCompletedDraw - (count($rows) - 1 - $index);
            
            // Ensure draw number is at least 1
            if ($correctDrawNumber < 1) {
                $correctDrawNumber = 1;
            }
            
            // Ensure color is valid (red, black, or green)
            $color = $row["color"] ?? 'black';
            if (!in_array($color, ['red', 'black', 'green'])) {
                // Determine color from winning number if invalid
                $winningNum = (int)$row["winning_number"];
                if ($winningNum === 0) {
                    $color = 'green';
                } else {
                    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                    $color = in_array($winningNum, $redNumbers) ? 'red' : 'black';
                }
            }
            
            $last8Spins[] = [
                "draw_number" => $correctDrawNumber,
                "winning_number" => (int)$row["winning_number"],
                "color" => $color,
                "timestamp" => $row["timestamp"] ?? null,
                "created_at" => $row["created_at"] ?? null
            ];
        }
        
        // Reverse back to show newest first
        $last8Spins = array_reverse($last8Spins);
    }
    
    $analytics['last_8_spins'] = $last8Spins;

    // 2. GET RECENT 20 SPINS FOR HOT/COLD CALCULATIONS
    // Calculate hot/cold numbers from recent history only (last 20 spins)
    // This matches what's shown in "LAST 8 SPINS" section
    $recentFrequencies = [];
    $recentSpins = [];
    
    try {
        // Build color select for hot/cold query
        $colorSelectHotCold = $hasWinningColor && $hasColor ? "IFNULL(winning_color, color)" : ($hasWinningColor ? "winning_color" : ($hasColor ? "color" : "'black'"));
        
        $stmt = $conn->prepare("
            SELECT
                winning_number,
                $colorSelectHotCold as color
            FROM detailed_draw_results
            ORDER BY draw_number DESC
            LIMIT 20
        ");

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $number = (int)$row["winning_number"];
                    $color = $row["color"];
                    $recentSpins[] = $number;
                    
                    if (!isset($recentFrequencies[$number])) {
                        $recentFrequencies[$number] = [
                            "number" => $number,
                            "color" => $color,
                            "frequency" => 0
                        ];
                    }
                    $recentFrequencies[$number]["frequency"]++;
                }
            }
            $stmt->close();
        } else {
            error_log("Warning: Could not prepare statement for recent 20 spins");
        }
    } catch (Exception $e) {
        error_log("Error fetching recent 20 spins: " . $e->getMessage());
    }

    // Sort by frequency (descending) for hot numbers
    uasort($recentFrequencies, function($a, $b) {
        return $b['frequency'] - $a['frequency'];
    });

    // 3. CALCULATE HOT NUMBERS (Top 5 most frequent in last 20 spins)
    $hotNumbers = array_slice($recentFrequencies, 0, 5);
    // Filter out numbers with 0 frequency and re-index
    $hotNumbers = array_values(array_filter($hotNumbers, function($item) {
        return $item['frequency'] > 0;
    }));
    $analytics['hot_numbers'] = $hotNumbers;

    // 4. CALCULATE COLD NUMBERS (5 least frequent in last 20 spins that have appeared at least once)
    // Sort by frequency (ascending) for cold numbers
    uasort($recentFrequencies, function($a, $b) {
        return $a['frequency'] - $b['frequency'];
    });
    
    // Get numbers that appeared at least once
    $appearedNumbers = array_filter($recentFrequencies, function($item) {
        return $item['frequency'] > 0;
    });
    
    // Get bottom 5 (least frequent)
    $coldNumbers = array_slice($appearedNumbers, 0, 5);
    $analytics['cold_numbers'] = array_values($coldNumbers);
    
    // Total spins for distribution calculations (use all spins, not just recent)
    $totalSpins = 0;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM detailed_draw_results");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $totalSpins = (int)$row["total"];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error getting total spins count: " . $e->getMessage());
        $totalSpins = 0;
    }

    // 5. CALCULATE DISTRIBUTIONS
    if ($totalSpins > 0) {
        // Get color distribution
        // Note: Using IFNULL to handle both 'winning_color' and 'color' column names for compatibility
        $colorSelectDist = $hasWinningColor && $hasColor ? "IFNULL(winning_color, color)" : ($hasWinningColor ? "winning_color" : ($hasColor ? "color" : "'black'"));
        
        $stmt = $conn->prepare("
            SELECT
                $colorSelectDist as color,
                COUNT(*) as count
            FROM detailed_draw_results
            GROUP BY $colorSelectDist
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $colorCounts = ['red' => 0, 'black' => 0, 'green' => 0];
        while ($row = $result->fetch_assoc()) {
            $colorCounts[$row['color']] = (int)$row['count'];
        }

        $analytics['color_distribution'] = [
            'red' => [
                'count' => $colorCounts['red'],
                'percentage' => round(($colorCounts['red'] / $totalSpins) * 100)
            ],
            'black' => [
                'count' => $colorCounts['black'],
                'percentage' => round(($colorCounts['black'] / $totalSpins) * 100)
            ],
            'green' => [
                'count' => $colorCounts['green'],
                'percentage' => round(($colorCounts['green'] / $totalSpins) * 100)
            ]
        ];

        // Get odd/even distribution
        $stmt = $conn->prepare("
            SELECT
                CASE
                    WHEN winning_number = 0 THEN 'zero'
                    WHEN winning_number % 2 = 0 THEN 'even'
                    ELSE 'odd'
                END as parity,
                COUNT(*) as count
            FROM detailed_draw_results
            GROUP BY parity
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $parityCounts = ['odd' => 0, 'even' => 0, 'zero' => 0];
        while ($row = $result->fetch_assoc()) {
            $parityCounts[$row['parity']] = (int)$row['count'];
        }

        // Exclude zero from odd/even calculations
        $nonZeroSpins = $totalSpins - $parityCounts['zero'];

        $analytics['odd_even_distribution'] = [
            'odd' => [
                'count' => $parityCounts['odd'],
                'percentage' => $nonZeroSpins > 0 ? round(($parityCounts['odd'] / $nonZeroSpins) * 100) : 0
            ],
            'even' => [
                'count' => $parityCounts['even'],
                'percentage' => $nonZeroSpins > 0 ? round(($parityCounts['even'] / $nonZeroSpins) * 100) : 0
            ]
        ];

        // Get high/low distribution (1-18 vs 19-36, excluding 0)
        $stmt = $conn->prepare("
            SELECT
                CASE
                    WHEN winning_number = 0 THEN 'zero'
                    WHEN winning_number BETWEEN 1 AND 18 THEN 'low'
                    ELSE 'high'
                END as range_type,
                COUNT(*) as count
            FROM detailed_draw_results
            GROUP BY range_type
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $rangeCounts = ['low' => 0, 'high' => 0, 'zero' => 0];
        while ($row = $result->fetch_assoc()) {
            $rangeCounts[$row['range_type']] = (int)$row['count'];
        }

        $analytics['high_low_distribution'] = [
            'low' => [
                'count' => $rangeCounts['low'],
                'percentage' => $nonZeroSpins > 0 ? round(($rangeCounts['low'] / $nonZeroSpins) * 100) : 0
            ],
            'high' => [
                'count' => $rangeCounts['high'],
                'percentage' => $nonZeroSpins > 0 ? round(($rangeCounts['high'] / $nonZeroSpins) * 100) : 0
            ]
        ];

        // Get dozens distribution (1-12, 13-24, 25-36, excluding 0)
        $stmt = $conn->prepare("
            SELECT
                CASE
                    WHEN winning_number = 0 THEN 'zero'
                    WHEN winning_number BETWEEN 1 AND 12 THEN 'first'
                    WHEN winning_number BETWEEN 13 AND 24 THEN 'second'
                    ELSE 'third'
                END as dozen,
                COUNT(*) as count
            FROM detailed_draw_results
            GROUP BY dozen
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $dozenCounts = ['first' => 0, 'second' => 0, 'third' => 0, 'zero' => 0];
        while ($row = $result->fetch_assoc()) {
            $dozenCounts[$row['dozen']] = (int)$row['count'];
        }

        $analytics['dozens_distribution'] = [
            'first' => [
                'count' => $dozenCounts['first'],
                'percentage' => $nonZeroSpins > 0 ? round(($dozenCounts['first'] / $nonZeroSpins) * 100) : 0
            ],
            'second' => [
                'count' => $dozenCounts['second'],
                'percentage' => $nonZeroSpins > 0 ? round(($dozenCounts['second'] / $nonZeroSpins) * 100) : 0
            ],
            'third' => [
                'count' => $dozenCounts['third'],
                'percentage' => $nonZeroSpins > 0 ? round(($dozenCounts['third'] / $nonZeroSpins) * 100) : 0
            ]
        ];

        // Get columns distribution (1st, 2nd, 3rd column, excluding 0)
        $stmt = $conn->prepare("
            SELECT
                CASE
                    WHEN winning_number = 0 THEN 'zero'
                    WHEN winning_number % 3 = 1 THEN 'first'
                    WHEN winning_number % 3 = 2 THEN 'second'
                    ELSE 'third'
                END as column_type,
                COUNT(*) as count
            FROM detailed_draw_results
            GROUP BY column_type
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $columnCounts = ['first' => 0, 'second' => 0, 'third' => 0, 'zero' => 0];
        while ($row = $result->fetch_assoc()) {
            $columnCounts[$row['column_type']] = (int)$row['count'];
        }

        $analytics['columns_distribution'] = [
            'first' => [
                'count' => $columnCounts['first'],
                'percentage' => $nonZeroSpins > 0 ? round(($columnCounts['first'] / $nonZeroSpins) * 100) : 0
            ],
            'second' => [
                'count' => $columnCounts['second'],
                'percentage' => $nonZeroSpins > 0 ? round(($columnCounts['second'] / $nonZeroSpins) * 100) : 0
            ],
            'third' => [
                'count' => $columnCounts['third'],
                'percentage' => $nonZeroSpins > 0 ? round(($columnCounts['third'] / $nonZeroSpins) * 100) : 0
            ]
        ];

    } else {
        // No spins data - return zero distributions
        $analytics['color_distribution'] = [
            'red' => ['count' => 0, 'percentage' => 0],
            'black' => ['count' => 0, 'percentage' => 0],
            'green' => ['count' => 0, 'percentage' => 0]
        ];
        $analytics['odd_even_distribution'] = [
            'odd' => ['count' => 0, 'percentage' => 0],
            'even' => ['count' => 0, 'percentage' => 0]
        ];
        $analytics['high_low_distribution'] = [
            'low' => ['count' => 0, 'percentage' => 0],
            'high' => ['count' => 0, 'percentage' => 0]
        ];
        $analytics['dozens_distribution'] = [
            'first' => ['count' => 0, 'percentage' => 0],
            'second' => ['count' => 0, 'percentage' => 0],
            'third' => ['count' => 0, 'percentage' => 0]
        ];
        $analytics['columns_distribution'] = [
            'first' => ['count' => 0, 'percentage' => 0],
            'second' => ['count' => 0, 'percentage' => 0],
            'third' => ['count' => 0, 'percentage' => 0]
        ];
    }

    // Add metadata
    $analytics['total_spins'] = $totalSpins;
    $analytics['timestamp'] = date("Y-m-d H:i:s");
    $analytics['cache_buster'] = time() . "_" . rand(1000, 9999);

    // Success response
    echo json_encode([
        "status" => "success",
        "analytics" => $analytics,
        "message" => "Complete analytics retrieved successfully from detailed_draw_results table",
        "timestamp" => date("Y-m-d H:i:s"),
        "cache_buster" => time() . "_" . rand(1000, 9999)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    // Log the error for debugging
    error_log("api_complete_analytics.php Error: " . $e->getMessage());
    
    // Return a valid JSON response with empty analytics structure
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve analytics: " . $e->getMessage(),
        "analytics" => [
            "last_8_spins" => [],
            "hot_numbers" => [],
            "cold_numbers" => [],
            "color_distribution" => [
                "red" => ["count" => 0, "percentage" => 0],
                "black" => ["count" => 0, "percentage" => 0],
                "green" => ["count" => 0, "percentage" => 0]
            ],
            "odd_even_distribution" => [
                "odd" => ["count" => 0, "percentage" => 0],
                "even" => ["count" => 0, "percentage" => 0]
            ],
            "high_low_distribution" => [
                "low" => ["count" => 0, "percentage" => 0],
                "high" => ["count" => 0, "percentage" => 0]
            ],
            "dozens_distribution" => [
                "first" => ["count" => 0, "percentage" => 0],
                "second" => ["count" => 0, "percentage" => 0],
                "third" => ["count" => 0, "percentage" => 0]
            ],
            "columns_distribution" => [
                "first" => ["count" => 0, "percentage" => 0],
                "second" => ["count" => 0, "percentage" => 0],
                "third" => ["count" => 0, "percentage" => 0]
            ],
            "total_spins" => 0
        ],
        "timestamp" => date("Y-m-d H:i:s")
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
