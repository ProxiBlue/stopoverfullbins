<?php
// Load environment variables
$envFile = __DIR__ . '/../.env';
$env = parse_ini_file($envFile);

// Database credentials
$dbHost = isset($env['db_host']) ? $env['db_host'] : 'localhost';
$dbName = isset($env['db_name']) ? $env['db_name'] : 'db';
$dbUser = isset($env['db_user']) ? $env['db_user'] : 'root';
$dbPass = isset($env['db_pass']) ? $env['db_pass'] : 'root';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created or already exists.\n";

    // Connect to the database
    $pdo->exec("USE `$dbName`");

    // Create submissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `submissions` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `address` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `verification_token` VARCHAR(64) NOT NULL,
        `verified` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `verified_at` TIMESTAMP NULL,
        INDEX `idx_verification_token` (`verification_token`),
        INDEX `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Submissions table created or already exists.\n";

    // Create images table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `images` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `submission_id` INT UNSIGNED NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `original_filename` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
        INDEX `idx_submission_id` (`submission_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Images table created or already exists.\n";

    echo "Database setup completed successfully.\n";
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
