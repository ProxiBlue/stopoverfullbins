<?php
// Load environment variables
$envFile = __DIR__ . '/../.env';
$env = parse_ini_file($envFile);

// Include image utility functions
require_once __DIR__ . '/image_utils.php';

// Include logger
require_once __DIR__ . '/logger.php';

// Include email utility functions
require_once __DIR__ . '/email_utils.php';

// Get token from URL
$token = filter_input(INPUT_GET, 'token');
if ($token !== null) {
    $token = htmlspecialchars(strip_tags($token), ENT_QUOTES, 'UTF-8');
}

// Validate token
if (empty($token)) {
    die("Invalid verification link. Please check your email and try again.");
}

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
    die("Database connection failed: " . $e->getMessage());
}

// Get submission from database
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE verification_token = ? AND verified = 0");
$stmt->execute([$token]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    die("Invalid or already used verification link. Please check your email and try again.");
}

// Get images for this submission
$stmt = $pdo->prepare("SELECT * FROM images WHERE submission_id = ?");
$stmt->execute([$submission['id']]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize empty attachments array
$mailjetAttachments = [];

// Get destination email from .env
if (!isset($env['dest_email']) || empty($env['dest_email'])) {
    log_error("Destination email not set in .env file");
    die("Server configuration error: Destination email not set. Please contact the administrator.");
}
$destEmail = $env['dest_email'];

// Prepare email to council
$subject = "Overfull bin report: {$submission['address']}";
$headers = "From: {$submission['email']}" . "\r\n";
$headers .= "Reply-To: {$submission['email']}" . "\r\n";

// If there are attachments, send with attachments
if (!empty($images)) {
    // Generate a boundary string
    $boundary = md5(time());

    // Headers for attachment
    $headers = "From: {$submission['email']}" . "\r\n";
    $headers .= "Reply-To: {$submission['email']}" . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    // Email body
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= "I would like to report an overfull bin at address: {$submission['address']}.\r\n\r\n";
    $body .= "Message: {$submission['message']}\r\n\r\n";

    // Attach files
    foreach ($images as $image) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"" . $image['original_filename'] . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $image['original_filename'] . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($image['file_path']))) . "\r\n";
    }

    $body .= "--{$boundary}--";

    // Prepare attachments for Mailjet
    $mailjetAttachments = [];
    foreach ($images as $image) {
        $mailjetAttachments[] = [
            'ContentType' => mime_content_type($image['file_path']),
            'Filename' => $image['original_filename'],
            'Base64Content' => base64_encode(file_get_contents($image['file_path']))
        ];
    }

    // Send email with attachments via SendGrid
    $htmlContent = "<html><body><p>I would like to report an overfull bin at address: " . htmlspecialchars($submission['address']) . ".</p><p>Message: " . nl2br(htmlspecialchars($submission['message'])) . "</p></body></html>";
    $mailSent = sendEmail(
        $submission['email'],
        "Bin Report",
        $destEmail,
        $subject,
        $htmlContent,
        $mailjetAttachments,
        $submission['email'],
        true // Use reply-to as FROM (bonus requirement)
    );
} else {
    // Send regular email without attachments via SendGrid
    $htmlContent = "<html><body><p>I would like to report an overfull bin at address: " . htmlspecialchars($submission['address']) . ".</p><p>Message: " . nl2br(htmlspecialchars($submission['message'])) . "</p></body></html>";
    $mailSent = sendEmail(
        $submission['email'],
        "Overfull Bin Report",
        $destEmail,
        $subject,
        $htmlContent,
        [],
        $submission['email'],
        true // Use reply-to as FROM (bonus requirement)
    );
}

// Check if email was sent to council
if ($mailSent) {
    // Update submission status in database
    $stmt = $pdo->prepare("UPDATE submissions SET verified = 1, verified_at = NOW() WHERE id = ?");
    $stmt->execute([$submission['id']]);

    // Send confirmation email to user
    $confirmSubject = "Your Bin Report for address: {$submission['address']} has been sent to the council";
    // Get from_address from .env
    $fromAddress = isset($env['from_address']) ? $env['from_address'] : 'noreply@stopoverfullbins.au';
    $confirmHeaders = "From: " . $fromAddress . "\r\n";
    $confirmHeaders .= "Reply-To: " . $submission['email'] . "\r\n";
    $confirmHeaders .= "MIME-Version: 1.0\r\n";
    $confirmHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";

    $confirmBody = "<html><body>";
    $confirmBody .= "<h2>Your Bin Report has been sent to the council</h2>";
    $confirmBody .= "<p>Thank you for verifying your email address. Your report has been successfully sent to the council.</p>";
    $confirmBody .= "<h3>Reported Address:</h3>";
    $confirmBody .= "<p>" . htmlspecialchars($submission['address']) . "</p>";
    $confirmBody .= "<h3>Message:</h3>";
    $confirmBody .= "<p>" . nl2br(htmlspecialchars($submission['message'])) . "</p>";

    if (!empty($images)) {
        $confirmBody .= "<p>Your submission included " . count($images) . " image(s).</p>";
    }

    $confirmBody .= "<p>You may receive a response from the council at your email address.</p>";
    $confirmBody .= "</body></html>";

    // Send confirmation email to user via SendGrid
    sendEmail(
        $fromAddress,
        "Stop Overfull Bins",
        $submission['email'],
        $confirmSubject,
        $confirmBody,
        !empty($images) ? $mailjetAttachments : [],
        $submission['email'],
        false // Don't use reply-to as FROM for confirmation email
    );

    // Delete image files after successful verification and email sending
    if (!empty($images)) {
        $deletedCount = deleteSubmissionImages($images);
        log_info("Deleted image files after verification", [
            'count' => $deletedCount,
            'submission_id' => $submission['id']
        ]);
    }

    // Delete database entries after successful verification and email sending
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$submission['id']]);
    log_info("Deleted database entries after verification", [
        'submission_id' => $submission['id']
    ]);

    // Display success message
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Email Verified</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #4a6fa5;
        }
        .success-icon {
            color: #43a047;
            font-size: 48px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #4a6fa5;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='success-icon'>✓</div>
        <h1>Email Verified Successfully</h1>
        <p>Thank you for verifying your email address. Your report has been successfully sent to the council.</p>
        <p>You may receive a response from the council at your email address.</p>
        <a href='/' class='button'>Return to Home</a>
    </div>
</body>
</html>";
} else {
    // Display error message
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verification Failed</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #e53935;
        }
        .error-icon {
            color: #e53935;
            font-size: 48px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #4a6fa5;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-icon'>✗</div>
        <h1>Verification Failed</h1>
        <p>We were unable to send your report to the council. Please try submitting your report again.</p>
        <a href='/' class='button'>Return to Home</a>
    </div>
</body>
</html>";
}
