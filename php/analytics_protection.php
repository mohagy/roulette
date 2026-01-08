<?php
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
?>