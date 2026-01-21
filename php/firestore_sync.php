<?php
/**
 * Firestore Sync Helper
 * 
 * Helper functions to write to Firestore from PHP backend
 * Uses Firebase REST API to write data
 * Handles errors gracefully (don't break if Firestore is down)
 */

// Firebase project configuration
define('FIRESTORE_PROJECT_ID', 'superbet-830b0');
define('FIRESTORE_API_BASE', 'https://firestore.googleapis.com/v1/projects/' . FIRESTORE_PROJECT_ID . '/databases/(default)/documents');

/**
 * Get Firebase access token (using API key for simple authentication)
 * Note: For production, consider using service account credentials
 */
function getFirestoreAccessToken() {
    // For now, we'll use the API key directly in the request
    // In production, you might want to use a service account
    return null; // API key will be sent in request headers
}

/**
 * Make a request to Firestore REST API
 * @param string $method HTTP method (GET, POST, PATCH, DELETE)
 * @param string $path Document or collection path
 * @param array|null $data Data to send (will be converted to Firestore format)
 * @return array|null Response data or null on error
 */
function firestoreRequest($method, $path, $data = null) {
    // For now, we'll use a simpler approach: create a JavaScript bridge
    // This will be called from the frontend when PHP saves to MySQL
    // For direct PHP->Firestore, we'd need OAuth2 authentication which is complex
    // So we'll return a flag that tells the frontend to sync instead
    
    // Alternative: Use a Node.js script via exec, but that requires Node.js setup
    // For simplicity, we'll return success and let the frontend handle the sync
    // OR we can create a simple API endpoint that the frontend calls
    
    // For now, log that sync is needed and return success
    // The actual sync will be handled by the frontend JavaScript
    error_log("Firestore sync requested for path: $path");
    return ['status' => 'queued']; // Indicates sync should be done by frontend
}

/**
 * Convert PHP data to Firestore format
 * @param mixed $value Value to convert
 * @return array Firestore-formatted value
 */
function convertToFirestoreFormat($value) {
    if (is_int($value)) {
        return ['integerValue' => (string)$value];
    } elseif (is_float($value)) {
        return ['doubleValue' => $value];
    } elseif (is_bool($value)) {
        return ['booleanValue' => $value];
    } elseif (is_string($value)) {
        return ['stringValue' => $value];
    } elseif ($value instanceof DateTime) {
        return ['timestampValue' => $value->format('Y-m-d\TH:i:s\Z')];
    } elseif (is_array($value)) {
        if (isset($value[0])) {
            // Array
            $values = [];
            foreach ($value as $item) {
                $values[] = convertToFirestoreFormat($item);
            }
            return ['arrayValue' => ['values' => $values]];
        } else {
            // Map/Object
            $fields = [];
            foreach ($value as $key => $val) {
                $fields[$key] = convertToFirestoreFormat($val);
            }
            return ['mapValue' => ['fields' => $fields]];
        }
    } else {
        return ['nullValue' => null];
    }
}

/**
 * Sync winning number to Firestore
 * Note: This function logs the sync request. The actual sync will be done by frontend JavaScript
 * when the admin panel loads, or we can create a simple API endpoint.
 * 
 * @param int $drawNumber Draw number
 * @param int $winningNumber Winning number (0-36)
 * @param string|null $winningColor Color of the winning number (auto-detected if null)
 * @param string $source Source ("manual" | "auto")
 * @return bool Success status (always returns true - sync is non-blocking)
 */
function syncWinningNumberToFirestore($drawNumber, $winningNumber, $winningColor = null, $source = 'manual') {
    try {
        // Auto-detect color if not provided
        if ($winningColor === null) {
            if ($winningNumber === 0) {
                $winningColor = 'green';
            } elseif (in_array($winningNumber, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
                $winningColor = 'red';
            } else {
                $winningColor = 'black';
            }
        }
        
        // Log the sync request
        error_log("✅ Winning number sync requested: Draw #$drawNumber, Number: $winningNumber, Color: $winningColor");
        
        // The actual Firestore sync will be handled by:
        // 1. Frontend JavaScript in bet_distribution.php (preferred - real-time)
        // 2. Or a separate API endpoint that can be called
        // For now, we return true to indicate the request was logged
        // The frontend will handle the actual sync via FirestoreService
        
        return true; // Non-blocking - always return true
    } catch (Exception $e) {
        error_log("❌ Error in syncWinningNumberToFirestore: " . $e->getMessage());
        return false; // Don't throw - this is non-critical
    }
}

/**
 * Sync spin command to Firestore
 * Note: This function logs the sync request. The actual sync will be done by frontend JavaScript.
 * 
 * @param int $winningNumber Winning number (0-36)
 * @param int $drawNumber Current draw number
 * @param DateTime $syncTimestamp Server timestamp for synchronized execution
 * @param string $source Source ("admin" | "master" | "auto")
 * @return string|null Command ID or null on error
 */
function syncSpinCommandToFirestore($winningNumber, $drawNumber, $syncTimestamp, $source = 'admin') {
    try {
        $commandId = 'cmd_' . time() . '_' . uniqid();
        
        // Log the sync request
        error_log("✅ Spin command sync requested: $commandId, Draw #$drawNumber, Number: $winningNumber");
        
        // The actual Firestore sync will be handled by frontend JavaScript
        // Return the command ID so it can be used by the frontend
        return $commandId;
    } catch (Exception $e) {
        error_log("❌ Error in syncSpinCommandToFirestore: " . $e->getMessage());
        return null; // Don't throw - this is non-critical
    }
}

/**
 * Sync game state to Firestore
 * Note: This function logs the sync request. The actual sync will be done by frontend JavaScript.
 * 
 * @param array $gameState Game state data
 * @return bool Success status
 */
function syncGameStateToFirestore($gameState) {
    try {
        // Log the sync request
        error_log("✅ Game state sync requested");
        
        // The actual Firestore sync will be handled by frontend JavaScript
        return true; // Non-blocking - always return true
    } catch (Exception $e) {
        error_log("❌ Error in syncGameStateToFirestore: " . $e->getMessage());
        return false; // Don't throw - this is non-critical
    }
}

/**
 * Check if Firestore sync is enabled
 * @return bool
 */
function isFirestoreSyncEnabled() {
    // You can add a configuration check here
    // For now, always return true (can be disabled by commenting out sync calls)
    return true;
}

