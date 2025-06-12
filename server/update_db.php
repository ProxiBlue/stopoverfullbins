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

    // Check if usage_counters table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'usage_counters'");
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tableExists) {
        // Create usage_counters table
        $pdo->exec("CREATE TABLE `usage_counters` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `counter_name` VARCHAR(50) NOT NULL,
            `counter_value` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX `idx_counter_name` (`counter_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Insert initial counter records
        $pdo->exec("INSERT INTO `usage_counters` (`counter_name`, `counter_value`) VALUES 
            ('verification_emails_sent', 0),
            ('emails_verified', 0),
            ('successful_reports_sent', 0)");

        echo "Usage counters table created and initialized.\n";
    } else {
        // Check if all required counters exist
        $requiredCounters = ['verification_emails_sent', 'emails_verified', 'successful_reports_sent'];

        foreach ($requiredCounters as $counter) {
            $stmt = $pdo->prepare("SELECT * FROM `usage_counters` WHERE `counter_name` = ?");
            $stmt->execute([$counter]);
            $counterExists = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$counterExists) {
                $pdo->prepare("INSERT INTO `usage_counters` (`counter_name`, `counter_value`) VALUES (?, 0)")
                     ->execute([$counter]);
                echo "Added missing counter: $counter\n";
            }
        }

        echo "Usage counters table already exists.\n";
    }

    echo "Database update completed successfully.\n";
} catch (PDOException $e) {
    die("Database update failed: " . $e->getMessage() . "\n");
}
