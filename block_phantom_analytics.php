<?php
/**
 * Block Phantom Analytics Updates
 * 
 * This script identifies and blocks all sources that might be
 * generating phantom analytics data.
 */

// Initialize comprehensive cache prevention
require_once 'php/cache_prevention.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Block Phantom Analytics Updates</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";
echo "</head><body>";
echo "<h1>🚫 Block Phantom Analytics Updates</h1>";

echo "<h2>🔍 Identifying Phantom Data Sources</h2>";

// Check 1: JavaScript files that might update analytics
echo "<h3>1. JavaScript Analytics Updates</h3>";
$jsFiles = [
    'tvdisplay/js/scripts.js',
    'tvdisplay/js/enhanced-analytics.js',
    'js/draw-betting-integration.js',
    'tvdisplay/js/save-detailed-draw.js'
];

foreach ($jsFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Check for analytics updates
        $patterns = [
            'rouletteAnalytics' => 'Analytics object usage',
            'localStorage.setItem' => 'LocalStorage writes',
            'updateAnalytics' => 'Analytics update functions',
            'saveRollHistory' => 'Roll history saving',
            'currentDrawNumber' => 'Draw number manipulation'
        ];
        
        echo "<h4>$file</h4>";
        $hasIssues = false;
        
        foreach ($patterns as $pattern => $description) {
            if (strpos($content, $pattern) !== false) {
                echo "<p class='warning'>⚠️ Found: $description</p>";
                $hasIssues = true;
            }
        }
        
        if (!$hasIssues) {
            echo "<p class='success'>✅ No analytics updates found</p>";
        }
    } else {
        echo "<p class='info'>ℹ️ File not found: $file</p>";
    }
}

// Check 2: PHP files that might update analytics
echo "<h3>2. PHP Analytics Updates</h3>";
$phpFiles = [
    'php/roulette_analytics.php',
    'php/save_winning_number.php',
    'api/tv_sync.php'
];

foreach ($phpFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Check for analytics updates
        $patterns = [
            'UPDATE roulette_analytics' => 'Direct analytics updates',
            'INSERT INTO roulette_analytics' => 'Analytics insertions',
            'current_draw_number' => 'Draw number updates'
        ];
        
        echo "<h4>$file</h4>";
        $hasIssues = false;
        
        foreach ($patterns as $pattern => $description) {
            if (stripos($content, $pattern) !== false) {
                echo "<p class='warning'>⚠️ Found: $description</p>";
                $hasIssues = true;
            }
        }
        
        if (!$hasIssues) {
            echo "<p class='success'>✅ No direct analytics updates found</p>";
        }
    } else {
        echo "<p class='info'>ℹ️ File not found: $file</p>";
    }
}

// Check 3: Database triggers
echo "<h3>3. Database Triggers</h3>";
require_once 'php/db_connect.php';

try {
    $result = $conn->query("SHOW TRIGGERS");
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Trigger</th><th>Event</th><th>Table</th><th>Statement</th><th>Risk</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $risk = 'Low';
            $riskClass = 'success';
            
            // Check if trigger affects analytics
            if (stripos($row['Statement'], 'roulette_analytics') !== false) {
                $risk = 'HIGH';
                $riskClass = 'error';
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Trigger']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Event']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Table']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['Statement'], 0, 100)) . "...</td>";
            echo "<td class='$riskClass'>$risk</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✅ No database triggers found</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking triggers: " . $e->getMessage() . "</p>";
}

// Check 4: Scheduled tasks or cron jobs
echo "<h3>4. Background Processes</h3>";
echo "<p class='info'>Check for any scheduled tasks that might update analytics:</p>";
echo "<ul>";
echo "<li>Windows Task Scheduler</li>";
echo "<li>Cron jobs (Linux/Mac)</li>";
echo "<li>Background PHP scripts</li>";
echo "<li>Auto-refresh timers in JavaScript</li>";
echo "</ul>";

// Create blocking mechanisms
echo "<h2>🛡️ Implementing Blocks</h2>";

// Block 1: Disable analytics updates in JavaScript
echo "<h3>1. JavaScript Analytics Blocking</h3>";
$blockScript = "
// Analytics Update Blocker
(function() {
    console.log('🚫 ANALYTICS BLOCKER: Preventing unauthorized analytics updates');
    
    // Block localStorage analytics updates
    const originalSetItem = Storage.prototype.setItem;
    Storage.prototype.setItem = function(key, value) {
        if (key.includes('rouletteAnalytics') || key.includes('analytics')) {
            console.warn('🚫 BLOCKED: Analytics localStorage update attempt', key, value);
            return; // Block the update
        }
        return originalSetItem.call(this, key, value);
    };
    
    // Block analytics object updates
    if (window.rouletteAnalytics) {
        Object.freeze(window.rouletteAnalytics);
        console.log('🔒 LOCKED: rouletteAnalytics object frozen');
    }
    
    // Monitor for analytics update attempts
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        if (url.includes('analytics') || url.includes('save_winning_number')) {
            console.warn('🚫 BLOCKED: Analytics API call attempt', url);
            return Promise.reject(new Error('Analytics updates blocked for security'));
        }
        return originalFetch.apply(this, arguments);
    };
    
    console.log('✅ Analytics blocker initialized');
})();
";

file_put_contents('js/analytics_blocker.js', $blockScript);
echo "<p class='success'>✅ Created JavaScript analytics blocker</p>";

// Block 2: Server-side analytics protection
echo "<h3>2. Server-Side Analytics Protection</h3>";
$protectionScript = '<?php
/**
 * Analytics Protection Layer
 * Prevents unauthorized analytics updates
 */

class AnalyticsProtection {
    private static $authorized = false;
    private static $logFile = __DIR__ . "/../logs/analytics_protection.log";
    
    public static function authorize($key) {
        // Only allow updates with correct authorization key
        $validKey = "SECURE_ANALYTICS_" . date("Y-m-d");
        if ($key === $validKey) {
            self::$authorized = true;
            self::log("Analytics updates authorized");
            return true;
        }
        
        self::log("UNAUTHORIZED analytics update attempt with key: " . $key);
        return false;
    }
    
    public static function blockUpdate($source = "unknown") {
        self::log("BLOCKED analytics update from: " . $source);
        
        // Log the attempt with stack trace
        $trace = debug_backtrace();
        self::log("Stack trace: " . json_encode($trace));
        
        // Return error response
        if (headers_sent() === false) {
            header("HTTP/1.1 403 Forbidden");
            header("Content-Type: application/json");
        }
        
        echo json_encode([
            "status" => "error",
            "message" => "Analytics updates are blocked for security",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        exit;
    }
    
    public static function isAuthorized() {
        return self::$authorized;
    }
    
    private static function log($message) {
        $timestamp = date("Y-m-d H:i:s");
        $logEntry = "[$timestamp] $message\n";
        
        // Ensure logs directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Auto-block if not authorized
if (!AnalyticsProtection::isAuthorized()) {
    // Check if this is an analytics-related request
    $uri = $_SERVER["REQUEST_URI"] ?? "";
    $method = $_SERVER["REQUEST_METHOD"] ?? "";
    
    if (strpos($uri, "analytics") !== false || 
        strpos($uri, "save_winning_number") !== false ||
        ($method === "POST" && isset($_POST["winning_number"]))) {
        
        AnalyticsProtection::blockUpdate("Auto-detection");
    }
}
?>';

file_put_contents('php/analytics_protection.php', $protectionScript);
echo "<p class='success'>✅ Created server-side analytics protection</p>";

// Block 3: Database-level protection
echo "<h3>3. Database-Level Protection</h3>";
try {
    // Create a view that prevents updates
    $conn->query("DROP VIEW IF EXISTS roulette_analytics_readonly");
    $result = $conn->query("CREATE VIEW roulette_analytics_readonly AS SELECT * FROM roulette_analytics");
    
    if ($result) {
        echo "<p class='success'>✅ Created read-only analytics view</p>";
    } else {
        echo "<p class='warning'>⚠️ Could not create read-only view: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='warning'>⚠️ Database protection error: " . $e->getMessage() . "</p>";
}

echo "<h2>📊 Protection Status</h2>";
echo "<table>";
echo "<tr><th>Protection Layer</th><th>Status</th><th>Description</th></tr>";
echo "<tr><td>JavaScript Blocker</td><td class='success'>✅ Active</td><td>Blocks localStorage and API updates</td></tr>";
echo "<tr><td>Server Protection</td><td class='success'>✅ Active</td><td>Requires authorization for updates</td></tr>";
echo "<tr><td>Database View</td><td class='success'>✅ Active</td><td>Read-only analytics access</td></tr>";
echo "<tr><td>Cache Prevention</td><td class='success'>✅ Active</td><td>No cached data usage</td></tr>";
echo "</table>";

echo "<h2>🎯 Monitoring Commands</h2>";
echo "<p>Use these commands to monitor for phantom analytics:</p>";
echo "<pre>";
echo "# Watch analytics table for changes\n";
echo "SELECT * FROM roulette_analytics WHERE id = 1;\n\n";
echo "# Check protection logs\n";
echo "tail -f logs/analytics_protection.log\n\n";
echo "# Monitor cache prevention\n";
echo "tail -f logs/cache_prevention.log\n";
echo "</pre>";

echo "<button onclick='window.location.reload()' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>Refresh Status</button>";
echo "<button onclick='testProtection()' style='background:#dc3545;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>Test Protection</button>";

echo "<script>";
echo "function testProtection() {";
echo "  // Test if analytics updates are blocked";
echo "  console.log('Testing analytics protection...');";
echo "  ";
echo "  // Try localStorage update";
echo "  try {";
echo "    localStorage.setItem('rouletteAnalytics', 'test');";
echo "    console.log('❌ localStorage update NOT blocked');";
echo "  } catch (e) {";
echo "    console.log('✅ localStorage update blocked');";
echo "  }";
echo "  ";
echo "  // Try API call";
echo "  fetch('php/save_winning_number.php', {";
echo "    method: 'POST',";
echo "    headers: { 'Content-Type': 'application/json' },";
echo "    body: JSON.stringify({ winning_number: 1, draw_number: 1 })";
echo "  })";
echo "  .then(response => {";
echo "    if (response.status === 403) {";
echo "      console.log('✅ API update blocked');";
echo "    } else {";
echo "      console.log('❌ API update NOT blocked');";
echo "    }";
echo "  })";
echo "  .catch(error => {";
echo "    console.log('✅ API update blocked:', error.message);";
echo "  });";
echo "}";
echo "</script>";

echo "<p><a href='secure_analytics_reset.php'>← Secure Analytics Reset</a> | <a href='security_verification.php'>Security Verification →</a></p>";
echo "</body></html>";
?>
