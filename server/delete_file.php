<?php
// Prevent PHP errors from being output before JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include logger
require_once __DIR__ . '/logger.php';

// Set up error handler to capture errors
function handleError($errno, $errstr, $errfile, $errline) {
    // Log error to file
    log_error("PHP Error: [$errno] $errstr", [
        'file' => $errfile,
        'line' => $errline
    ]);

    // Return true to prevent standard PHP error handler
    return true;
}
set_error_handler('handleError');

// Set up exception handler
function handleException($exception) {
    // Log exception to file
    log_error("Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    exit;
}
set_exception_handler('handleException');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if filename is provided
if (!isset($_POST['filename']) || empty($_POST['filename'])) {
    echo json_encode(['success' => false, 'message' => 'No filename provided']);
    exit;
}

// Get filename from request
$filename = $_POST['filename'];

// Validate filename to prevent directory traversal attacks
if (preg_match('/\.\./', $filename) || !preg_match('/^[a-zA-Z0-9_-]+_[a-zA-Z0-9_.-]+$/', $filename)) {
    log_error('Invalid filename format', ['filename' => $filename]);
    echo json_encode(['success' => false, 'message' => 'Invalid filename format']);
    exit;
}

// Define uploads directory
$uploadDir = __DIR__ . '/../uploads/';
$filePath = $uploadDir . $filename;

// Check if file exists
if (!file_exists($filePath)) {
    log_warning('File not found for deletion', ['filename' => $filename, 'path' => $filePath]);
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Try to delete the file
if (unlink($filePath)) {
    log_info('File deleted successfully', ['filename' => $filename]);
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
} else {
    log_error('Failed to delete file', ['filename' => $filename, 'path' => $filePath]);
    echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
}