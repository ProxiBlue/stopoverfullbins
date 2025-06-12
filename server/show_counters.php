<?php
/**
 * Script to display the current counter values
 */

// Include counter utility functions
require_once __DIR__ . '/counter_utils.php';

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
    
    // Get all counters
    $stmt = $pdo->query("SELECT * FROM `usage_counters` ORDER BY `counter_name`");
    $counters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Display as HTML
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Usage Counters</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #4a6fa5;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .counter-name {
            font-weight: bold;
        }
        .counter-value {
            text-align: center;
        }
        .counter-date {
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Usage Counters</h1>
        <table>
            <thead>
                <tr>
                    <th>Counter</th>
                    <th>Value</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($counters as $counter) {
        echo "<tr>
                <td class='counter-name'>" . htmlspecialchars($counter['counter_name']) . "</td>
                <td class='counter-value'>" . htmlspecialchars($counter['counter_value']) . "</td>
                <td class='counter-date'>" . htmlspecialchars($counter['last_updated']) . "</td>
            </tr>";
    }
    
    echo "    </tbody>
        </table>
    </div>
</body>
</html>";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}