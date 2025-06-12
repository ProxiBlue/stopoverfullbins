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

// Load environment variables
$envFile = __DIR__ . '/../.env';
$env = parse_ini_file($envFile);

// Set headers for JSON response
header('Content-Type: application/json');

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Process file uploads
$uploadedFiles = [];
$uploadDir = __DIR__ . '/../uploads/';

// Include image utility functions
require_once __DIR__ . '/image_utils.php';

// Include email utility functions
require_once __DIR__ . '/email_utils.php';

// Include counter utility functions
require_once __DIR__ . '/counter_utils.php';

// Create uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if this is a file upload only request (HTML5 individual file upload)
if (isset($_POST['file_upload_only']) && $_POST['file_upload_only'] === 'true') {
    // Process single file upload
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'])) {
        $fileName = $_FILES['images']['name'];
        $fileTmpName = $_FILES['images']['tmp_name'];
        $fileType = $_FILES['images']['type'];
        $fileError = $_FILES['images']['error'];

        // Check for errors
        if ($fileError !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            exit;
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }

        // Generate unique filename
        $newFileName = uniqid() . '_' . $fileName;
        $destination = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $destination)) {
            // Process image for email attachment
            $processedPath = processImageForEmail($destination);

            echo json_encode([
                'success' => true, 
                'message' => 'File uploaded successfully',
                'filename' => $newFileName,
                'original_name' => $fileName,
                'path' => $processedPath
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }
}

// For regular form submission
// Get form data
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$address = filter_input(INPUT_POST, 'address');
if ($address !== null) {
    $address = htmlspecialchars(strip_tags($address), ENT_QUOTES, 'UTF-8');
}
$message = filter_input(INPUT_POST, 'message');
if ($message !== null) {
    $message = htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8');
}

// Validate inputs
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address']);
    exit;
}

if (empty($address)) {
    echo json_encode(['success' => false, 'message' => 'Please provide an address']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a message']);
    exit;
}

// Check if we have uploaded files from HTML5 upload or traditional file uploads
$hasUploadedFiles = (isset($_POST['uploaded_files']) && is_array($_POST['uploaded_files']) && !empty($_POST['uploaded_files'])) || 
                   (isset($_FILES['images']) && !empty($_FILES['images']['name'][0]));

if (!$hasUploadedFiles) {
    echo json_encode(['success' => false, 'message' => 'Please upload at least one image']);
    exit;
}

// Get destination email from .env
if (!isset($env['dest_email']) || empty($env['dest_email'])) {
    log_error("Destination email not set in .env file");
    echo json_encode(['success' => false, 'message' => 'Server configuration error: Destination email not set']);
    exit;
}
$destEmail = $env['dest_email'];

// Database connection
$dbHost = isset($env['db_host']) ? $env['db_host'] : 'localhost';
$dbName = isset($env['db_name']) ? $env['db_name'] : 'stopoverfullbins';
$dbUser = isset($env['db_user']) ? $env['db_user'] : 'db';
$dbPass = isset($env['db_pass']) ? $env['db_pass'] : 'db';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
    exit;
}

// Generate verification token (compatible with older PHP versions)
function generateSecureToken($length = 64) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    } else {
        // Fallback to less secure method if neither function is available
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $token;
    }
}

$verificationToken = generateSecureToken();

// Check if we have uploaded files from HTML5 upload
if (isset($_POST['uploaded_files']) && is_array($_POST['uploaded_files'])) {
    foreach ($_POST['uploaded_files'] as $filename) {
        $filePath = $uploadDir . $filename;
        if (file_exists($filePath)) {
            $originalName = preg_replace('/^[a-f0-9]+_/', '', $filename); // Extract original name
            $uploadedFiles[] = [
                'path' => $filePath,
                'original_name' => $originalName,
                'filename' => $filename
            ];
        }
    }
}

// Also check for traditional file uploads (fallback)
if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    // Check if too many files
    if (count($_FILES['images']['name']) > 5) {
        echo json_encode(['success' => false, 'message' => 'Maximum 5 images allowed']);
        exit;
    }

    // Process each file
    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
        $fileName = $_FILES['images']['name'][$i];
        $fileTmpName = $_FILES['images']['tmp_name'][$i];
        $fileType = $_FILES['images']['type'][$i];
        $fileError = $_FILES['images']['error'][$i];

        // Check for errors
        if ($fileError !== UPLOAD_ERR_OK) {
            continue;
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileType, $allowedTypes)) {
            continue;
        }

        // Generate unique filename
        $newFileName = uniqid() . '_' . $fileName;
        $destination = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $destination)) {
            // Process image for email attachment
            $processedPath = processImageForEmail($destination);

            $uploadedFiles[] = [
                'path' => $processedPath,
                'original_name' => $fileName,
                'filename' => $newFileName
            ];
        }
    }
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Insert submission into database
    $stmt = $pdo->prepare("INSERT INTO submissions (email, address, message, verification_token) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $address, $message, $verificationToken]);
    $submissionId = $pdo->lastInsertId();

    // Insert images into database
    if (!empty($uploadedFiles)) {
        $stmt = $pdo->prepare("INSERT INTO images (submission_id, filename, original_filename, file_path) VALUES (?, ?, ?, ?)");
        foreach ($uploadedFiles as $file) {
            $stmt->execute([$submissionId, $file['filename'], $file['original_name'], $file['path']]);
        }
    }

    // Commit transaction
    $pdo->commit();

    // Prepare verification email
    $verificationUrl = "https://{$_SERVER['HTTP_HOST']}/server/verify.php?token=" . urlencode($verificationToken);
    $subject = "Verify your Bin Report Submission for address: {$address}";

    // Create email body with message preview and images
    $boundary = md5(time());

    // Get from_address from .env
    $fromAddress = isset($env['from_address']) ? $env['from_address'] : 'noreply@stopoverfullbins.au';
    $headers = "From: " . $fromAddress . "\r\n";
    $headers .= "Reply-To: " . $fromAddress . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= "<html><body>";
    $body .= "<h2>Email Verification Required</h2>";
    $body .= "<p><strong>IMPORTANT: Your message has NOT been sent to the council yet.</strong></p>";
    $body .= "<p>Please click the link below to verify your email address and send your message to the council:</p>";
    $body .= "<p><a href=\"{$verificationUrl}\">Validate Email and Release Message to Council</a></p>";
    $body .= "<h3>Message Preview:</h3>";
    $body .= "<p>" . nl2br(html_entity_decode(htmlspecialchars($message), ENT_QUOTES, 'UTF-8')) . "</p>";

    if (!empty($uploadedFiles)) {
        $body .= "<p>Your submission includes " . count($uploadedFiles) . " image(s).</p>";
    }

    $body .= "<p>Thank you for your report.</p>";
    $body .= "</body></html>\r\n\r\n";

    // Attach images to the verification email
    foreach ($uploadedFiles as $file) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"" . $file['original_name'] . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $file['original_name'] . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($file['path']))) . "\r\n";
    }

    $body .= "--{$boundary}--";

    // Make sure autoloader is included
    require_once __DIR__ . '/../vendor/autoload.php';

    // Extract HTML content from the multipart message
    preg_match('/<html>.*<\/html>/s', $body, $htmlMatches);
    $htmlContent = !empty($htmlMatches) ? $htmlMatches[0] : '';

    // Prepare attachments
    $attachments = [];
    foreach ($uploadedFiles as $file) {
        $attachments[] = [
            'ContentType' => mime_content_type($file['path']),
            'Filename' => $file['original_name'],
            'Base64Content' => base64_encode(file_get_contents($file['path']))
        ];
    }

    // Use sendEmail function to send the verification email
    $mailSent = sendEmail(
        $fromAddress,
        'Stop Overfull Bins',
        $email,
        $subject,
        $htmlContent,
        $attachments,
        $email, // Set reply-to to the user's email
        false   // Don't use reply-to as FROM for verification email
    );

    if ($mailSent) {
        // Increment the verification emails sent counter
        incrementCounter('verification_emails_sent', $pdo);

        echo json_encode([
            'success' => true, 
            'message' => 'Your report has been submitted. Please check your email to verify your submission.'
        ]);
    } else {
        // If email fails, still keep the submission in the database
        echo json_encode([
            'success' => true, 
            'message' => 'Your report has been submitted, but we could not send a verification email. Please contact support.'
        ]);
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    // Clean up any uploaded files
    foreach ($uploadedFiles as $file) {
        if (file_exists($file['path'])) {
            unlink($file['path']);
        }
    }

    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your submission. Please try again later.']);
}
