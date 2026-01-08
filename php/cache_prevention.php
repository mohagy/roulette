<?php
/**
 * Cache Prevention Headers
 *
 * This file provides comprehensive cache prevention for all PHP responses
 * to ensure no data is cached on the server or client side for security reasons.
 */

/**
 * Set comprehensive no-cache headers
 */
function setCachePreventionHeaders() {
    // Prevent browser caching
    header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("ETag: \"" . uniqid() . "\"");

    // Additional security headers
    header("X-Cache-Control: no-cache");
    header("Vary: *");

    // Prevent proxy caching
    header("Surrogate-Control: no-store");

    // Clear any existing cache headers
    if (function_exists('header_remove')) {
        header_remove('Last-Modified');
        header_remove('ETag');
    }
}

/**
 * Disable PHP opcache for this request
 */
function disableOpcache() {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate(__FILE__, true);
    }
}

/**
 * Clear any session cache
 */
function clearSessionCache() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_cache_limiter('nocache');
        session_cache_expire(0);
    }
}

/**
 * Add cache-busting parameter to URLs
 */
function addCacheBuster($url) {
    $separator = strpos($url, '?') !== false ? '&' : '?';
    return $url . $separator . '_cb=' . time() . '_' . uniqid();
}

/**
 * Get fresh database connection with no caching
 */
function getFreshDatabaseConnection() {
    // Include the main database connection
    require_once __DIR__ . '/db_connect.php';

    // Disable query cache for this connection (only if supported)
    global $conn;
    if ($conn) {
        try {
            // Try to disable query cache (may not work on all MySQL configurations)
            $conn->query("SET SESSION query_cache_type = OFF");
            logCachePrevention('Query cache disabled for session');
        } catch (Exception $e) {
            // If query cache settings fail, just log and continue
            logCachePrevention('Query cache disable failed (this is normal on some MySQL configurations)', [
                'error' => $e->getMessage()
            ]);
        }
    }

    return $conn;
}

/**
 * Execute a query with no caching
 */
function executeNoCacheQuery($conn, $query) {
    // Add SQL_NO_CACHE hint to SELECT queries
    if (stripos(trim($query), 'SELECT') === 0) {
        $query = str_ireplace('SELECT', 'SELECT SQL_NO_CACHE', $query);
    }

    return $conn->query($query);
}

/**
 * Get current timestamp for cache busting
 */
function getCacheBuster() {
    return time() . '_' . uniqid() . '_' . mt_rand(1000, 9999);
}

/**
 * Log cache prevention activity
 */
function logCachePrevention($message, $data = null) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    $logFile = __DIR__ . '/../logs/cache_prevention.log';
    $logLine = json_encode($logEntry) . "\n";

    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Initialize comprehensive cache prevention
 */
function initializeCachePrevention() {
    // Set all cache prevention headers
    setCachePreventionHeaders();

    // Disable opcache
    disableOpcache();

    // Clear session cache
    clearSessionCache();

    // Log the cache prevention initialization
    logCachePrevention('Cache prevention initialized');

    // Add cache-busting to all output
    ob_start(function($buffer) {
        // Add cache-busting timestamp to any URLs in the output
        $cacheBuster = getCacheBuster();

        // Replace common URL patterns with cache-busted versions
        $patterns = [
            '/href="([^"]+\.css[^"]*)"/' => 'href="$1?' . $cacheBuster . '"',
            '/src="([^"]+\.js[^"]*)"/' => 'src="$1?' . $cacheBuster . '"',
            '/url\(([^)]+\.css[^)]*)\)/' => 'url($1?' . $cacheBuster . ')'
        ];

        foreach ($patterns as $pattern => $replacement) {
            $buffer = preg_replace($pattern, $replacement, $buffer);
        }

        return $buffer;
    });
}

/**
 * Force fresh data retrieval from database
 */
function getFreshData($query, $params = []) {
    // Get database connection
    require_once __DIR__ . '/db_connect.php';
    global $conn;

    if (!$conn) {
        logCachePrevention('Database connection failed', ['query' => $query]);
        return false;
    }

    try {
        // Add cache-busting comment to query to ensure fresh execution
        $cacheBustQuery = $query . " /* cache_bust_" . time() . "_" . uniqid() . " */";

        // Prepare statement with no caching
        if (!empty($params)) {
            $stmt = $conn->prepare($cacheBustQuery);
            if ($stmt) {
                $types = str_repeat('s', count($params)); // Assume all strings for simplicity
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                logCachePrevention('Fresh data retrieved with params', [
                    'query' => $query,
                    'params' => $params,
                    'rows' => count($data)
                ]);

                return $data;
            }
        } else {
            $result = $conn->query($cacheBustQuery);
            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);

                logCachePrevention('Fresh data retrieved', [
                    'query' => $query,
                    'rows' => count($data)
                ]);

                return $data;
            }
        }
    } catch (Exception $e) {
        logCachePrevention('Database query error', [
            'query' => $query,
            'error' => $e->getMessage()
        ]);
    }

    logCachePrevention('Failed to retrieve fresh data', ['query' => $query]);
    return false;
}

/**
 * Security function to ensure no cached data is used
 */
function ensureNoCache() {
    // Check if any caching headers are present and remove them
    $headers = headers_list();
    foreach ($headers as $header) {
        if (stripos($header, 'cache') !== false &&
            stripos($header, 'no-cache') === false) {
            // Remove problematic cache headers
            header_remove(explode(':', $header)[0]);
        }
    }

    // Re-apply our cache prevention headers
    setCachePreventionHeaders();

    logCachePrevention('Cache prevention enforced');
}

// Auto-initialize cache prevention when this file is included
if (!defined('CACHE_PREVENTION_INITIALIZED')) {
    define('CACHE_PREVENTION_INITIALIZED', true);
    initializeCachePrevention();
}
?>
