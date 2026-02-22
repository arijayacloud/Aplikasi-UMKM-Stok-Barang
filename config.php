<?php
// Database configuration
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = ''; // Change this to your database password if set
const DB_NAME = 'stock_management';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}
