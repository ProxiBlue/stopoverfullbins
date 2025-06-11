<?php
/**
 * Email utility functions for sending emails
 * 
 * This file provides email functionality using SendGrid or falling back to PHP mail()
 */

// Include logger
require_once __DIR__ . '/logger.php';

/**
 * Send an email via SendGrid or fallback to PHP mail()
 * 
 * @param string $from The sender's email address
 * @param string $fromName The sender's name
 * @param string $to The recipient's email address
 * @param string $subject The email subject
 * @param string $htmlContent The HTML content of the email
 * @param array $attachments Optional array of attachments
 * @param string $replyTo Optional reply-to email address (defaults to $from)
 * @param bool $useReplyToAsFrom Whether to use the reply-to address as the FROM address
 * @return bool True if the email was sent successfully, false otherwise
 */
function sendEmail($from, $fromName, $to, $subject, $htmlContent, $attachments = [], $replyTo = null, $useReplyToAsFrom = false) {
    // Load environment variables if not already loaded
    if (!isset($GLOBALS['env'])) {
        $envFile = __DIR__ . '/../.env';
        $GLOBALS['env'] = parse_ini_file($envFile);
    }
    $env = $GLOBALS['env'];

    // Make sure autoloader is included
    require_once __DIR__ . '/../vendor/autoload.php';

    // Get SendGrid API key from .env
    $sendgridApiKey = isset($env['sendgrid_api_key']) ? $env['sendgrid_api_key'] : '';

    // If replyTo is not provided, use from address
    if ($replyTo === null) {
        $replyTo = $from;
    }

    if (empty($sendgridApiKey) || $sendgridApiKey === 'YOUR_SENDGRID_API_KEY_HERE') {
        // Fallback to PHP mail() if SendGrid key is not available
        $headers = "From: {$from}" . "\r\n";
        $headers .= "Reply-To: {$replyTo}" . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return mail($to, $subject, $htmlContent, $headers);
    }

    try {
        // Initialize SendGrid client
        $sendgrid = new \SendGrid($sendgridApiKey);
        $email = new \SendGrid\Mail\Mail();

        // Set from, to, subject, and content
        // Note: For better deliverability, it's recommended to use a verified domain for the FROM address
        // However, if the bonus requirement is to set FROM to the user's email, we can do that
        // but it might affect deliverability
        if ($useReplyToAsFrom && $replyTo) {
            // Use the reply-to address as the FROM address
            // This satisfies the bonus requirement but might affect deliverability
            $email->setFrom($replyTo, $fromName);
        } else {
            // Otherwise, use the default FROM address
            $email->setFrom($from, $fromName);
        }
        $email->addTo($to);
        $email->setSubject($subject);
        $email->addContent("text/html", $htmlContent);

        // Set reply-to
        $email->setReplyTo($replyTo);

        // Add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $file_encoded = $attachment['Base64Content'];
                $file_type = $attachment['ContentType'];
                $file_name = $attachment['Filename'];

                $email->addAttachment(
                    $file_encoded,
                    $file_type,
                    $file_name,
                    'attachment'
                );
            }
        }

        // Send email
        $response = $sendgrid->send($email);

        // Log response for debugging
        log_debug('SendGrid API Response', [
            'status_code' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body()
        ]);

        // Return true if successful (status code 2xx)
        return $response->statusCode() >= 200 && $response->statusCode() < 300;
    } catch (\Exception $e) {
        log_error('SendGrid SDK Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return false;
    }
}