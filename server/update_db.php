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
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if address column exists in submissions table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `submissions` LIKE 'address'");
    $stmt->execute();
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$columnExists) {
        // Add address column to submissions table
        $pdo->exec("ALTER TABLE `submissions` ADD COLUMN `address` VARCHAR(255) NOT NULL AFTER `email`");
        echo "Address column added to submissions table.\n";
    } else {
        echo "Address column already exists in submissions table.\n";
    }

    echo "Database update completed successfully.\n";
} catch (PDOException $e) {
    die("Database update failed: " . $e->getMessage() . "\n");
}