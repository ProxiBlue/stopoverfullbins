<?php
/**
 * Counter utility functions for tracking usage statistics
 */

// Include logger
require_once __DIR__ . '/logger.php';

/**
 * Increment a usage counter in the database
 * 
 * @param string $counterName The name of the counter to increment
 * @param PDO $pdo Optional PDO connection (if not provided, a new connection will be created)
 * @return bool True if the counter was incremented successfully, false otherwise
 */
function incrementCounter($counterName, $pdo = null) {
    // Create a new PDO connection if one wasn't provided
    $shouldClosePdo = false;
    if ($pdo === null) {
        // Load environment variables if not already loaded
        if (!isset($GLOBALS['env'])) {
            $envFile = __DIR__ . '/../.env';
            $GLOBALS['env'] = parse_ini_file($envFile);
        }
        $env = $GLOBALS['env'];
        
        // Database credentials
        $dbHost = isset($env['db_host']) ? $env['db_host'] : 'localhost';
        $dbName = isset($env['db_name']) ? $env['db_name'] : 'db';
        $dbUser = isset($env['db_user']) ? $env['db_user'] : 'root';
        $dbPass = isset($env['db_pass']) ? $env['db_pass'] : 'root';
        
        try {
            // Create PDO connection
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $shouldClosePdo = true;
        } catch (PDOException $e) {
            log_error("Failed to connect to database for counter increment", [
                'counter' => $counterName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    try {
        // Increment the counter
        $stmt = $pdo->prepare("UPDATE `usage_counters` SET `counter_value` = `counter_value` + 1 WHERE `counter_name` = ?");
        $result = $stmt->execute([$counterName]);
        
        // Log the increment
        if ($result && $stmt->rowCount() > 0) {
            log_debug("Incremented counter", [
                'counter' => $counterName
            ]);
        } else {
            log_warning("Failed to increment counter - counter may not exist", [
                'counter' => $counterName
            ]);
        }
        
        // Close the PDO connection if we created it
        if ($shouldClosePdo) {
            $pdo = null;
        }
        
        return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        log_error("Failed to increment counter", [
            'counter' => $counterName,
            'error' => $e->getMessage()
        ]);
        
        // Close the PDO connection if we created it
        if ($shouldClosePdo) {
            $pdo = null;
        }
        
        return false;
    }
}

/**
 * Get the current value of a usage counter
 * 
 * @param string $counterName The name of the counter to get
 * @param PDO $pdo Optional PDO connection (if not provided, a new connection will be created)
 * @return int|false The counter value, or false if an error occurred
 */
function getCounterValue($counterName, $pdo = null) {
    // Create a new PDO connection if one wasn't provided
    $shouldClosePdo = false;
    if ($pdo === null) {
        // Load environment variables if not already loaded
        if (!isset($GLOBALS['env'])) {
            $envFile = __DIR__ . '/../.env';
            $GLOBALS['env'] = parse_ini_file($envFile);
        }
        $env = $GLOBALS['env'];
        
        // Database credentials
        $dbHost = isset($env['db_host']) ? $env['db_host'] : 'localhost';
        $dbName = isset($env['db_name']) ? $env['db_name'] : 'db';
        $dbUser = isset($env['db_user']) ? $env['db_user'] : 'root';
        $dbPass = isset($env['db_pass']) ? $env['db_pass'] : 'root';
        
        try {
            // Create PDO connection
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $shouldClosePdo = true;
        } catch (PDOException $e) {
            log_error("Failed to connect to database for counter retrieval", [
                'counter' => $counterName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    try {
        // Get the counter value
        $stmt = $pdo->prepare("SELECT `counter_value` FROM `usage_counters` WHERE `counter_name` = ?");
        $stmt->execute([$counterName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Close the PDO connection if we created it
        if ($shouldClosePdo) {
            $pdo = null;
        }
        
        return $result ? (int)$result['counter_value'] : false;
    } catch (PDOException $e) {
        log_error("Failed to get counter value", [
            'counter' => $counterName,
            'error' => $e->getMessage()
        ]);
        
        // Close the PDO connection if we created it
        if ($shouldClosePdo) {
            $pdo = null;
        }
        
        return false;
    }
}