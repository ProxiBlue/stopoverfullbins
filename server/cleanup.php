<?php
/**
 * Cleanup script to remove old files from the uploads directory
 * This script is intended to be run via cron job daily
 */

// Load image utility functions
require_once __DIR__ . '/image_utils.php';

// Define uploads directory
$uploadsDir = __DIR__ . '/../uploads/';

// Default to 24 hours (86400 seconds) for file age
$maxFileAge = 86400; // 1 day in seconds

// Check if a custom age was provided as a command-line argument
if (isset($argv[1]) && is_numeric($argv[1])) {
    $maxFileAge = intval($argv[1]);
}

// Run the cleanup
$stats = cleanupOldFiles($uploadsDir, $maxFileAge);

// Output results
echo "Cleanup completed:\n";
echo "- Files scanned: {$stats['scanned']}\n";
echo "- Files deleted: {$stats['deleted']}\n";
echo "- Files failed to delete: {$stats['failed']}\n";
echo "- Files skipped (not old enough): {$stats['skipped']}\n";

// Log the cleanup operation
log_info("Image cleanup completed", [
    'scanned' => $stats['scanned'],
    'deleted' => $stats['deleted'],
    'failed' => $stats['failed'],
    'skipped' => $stats['skipped'],
    'max_age' => $maxFileAge
]);

// Exit with success code
exit(0);
