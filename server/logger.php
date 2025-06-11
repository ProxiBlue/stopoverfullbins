<?php
/**
 * Logger utility for the Stop Overfull Bins application
 * 
 * This file provides logging functionality to write logs to a dedicated log file.
 */

// Define log levels
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_DEBUG', 'DEBUG');

/**
 * Write a log entry to the application log file
 * 
 * @param string $message The message to log
 * @param string $level The log level (ERROR, WARNING, INFO, DEBUG)
 * @param array $context Additional context data to include in the log
 * @return bool True if the log was written successfully, false otherwise
 */
function app_log($message, $level = LOG_LEVEL_INFO, $context = []) {
    // Define log file path
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/app.log';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            // If we can't create the log directory, fall back to PHP's error_log
            error_log("Failed to create log directory: {$logDir}");
            error_log($message);
            return false;
        }
    }
    
    // Format the log entry
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = empty($context) ? '' : ' ' . json_encode($context);
    $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
    
    // Write to log file
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // If writing to the log file failed, fall back to PHP's error_log
    if ($result === false) {
        error_log("Failed to write to log file: {$logFile}");
        error_log($message);
        return false;
    }
    
    return true;
}

/**
 * Log an error message
 * 
 * @param string $message The error message
 * @param array $context Additional context data
 * @return bool True if the log was written successfully
 */
function log_error($message, $context = []) {
    return app_log($message, LOG_LEVEL_ERROR, $context);
}

/**
 * Log a warning message
 * 
 * @param string $message The warning message
 * @param array $context Additional context data
 * @return bool True if the log was written successfully
 */
function log_warning($message, $context = []) {
    return app_log($message, LOG_LEVEL_WARNING, $context);
}

/**
 * Log an info message
 * 
 * @param string $message The info message
 * @param array $context Additional context data
 * @return bool True if the log was written successfully
 */
function log_info($message, $context = []) {
    return app_log($message, LOG_LEVEL_INFO, $context);
}

/**
 * Log a debug message
 * 
 * @param string $message The debug message
 * @param array $context Additional context data
 * @return bool True if the log was written successfully
 */
function log_debug($message, $context = []) {
    return app_log($message, LOG_LEVEL_DEBUG, $context);
}