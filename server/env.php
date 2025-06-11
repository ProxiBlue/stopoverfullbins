<?php
// Load environment variables
$envFile = __DIR__ . '/../.env';
$env = parse_ini_file($envFile);

// Set content type to JavaScript
header('Content-Type: application/javascript');

// Output environment variables as JavaScript constants
$defaultMessage = isset($env['default_message']) ? $env['default_message'] : '';
// Replace escaped newlines with actual newlines for proper display in textarea
$defaultMessage = str_replace('\n', "\n", $defaultMessage);
echo "const DEFAULT_MESSAGE = " . json_encode($defaultMessage) . ";";
echo "const GOOGLE_MAPS_API_KEY = " . json_encode(isset($env['google_maps_api_key']) ? $env['google_maps_api_key'] : '') . ";";

// Add destination email for display in the UI
if (!isset($env['dest_email']) || empty($env['dest_email'])) {
    echo "const DEST_EMAIL = 'Not configured';";
} else {
    echo "const DEST_EMAIL = " . json_encode($env['dest_email']) . ";";
}
?>
