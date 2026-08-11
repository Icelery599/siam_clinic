<?php
/**
 * Database connection (PDO, MySQL). Prepared statements used throughout.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'siam_clinic';
$DB_USER = 'root';
$DB_PASS = ''; // set your XAMPP MySQL password here if you have one

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please check config/database.php.');
}
