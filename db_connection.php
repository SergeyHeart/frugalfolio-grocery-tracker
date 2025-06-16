<?php
// db_connection.php

if (!defined('FRUGALFOLIO_ACCESS')) {
    die('Direct access to this script is not permitted.');
    // Or: header('HTTP/1.0 403 Forbidden'); exit;
}
// Helps see potential warnings
error_reporting(E_ALL);
ini_set('display_errors', 1);

    // Database credentials
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "grocery_expenses_db";

    // Create a connection
    $conn = new mysqli($servername, $username, $password, $database);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4 to support a wider range of characters
    if (!$conn->set_charset("utf8mb4")) {
        die("Error setting charset: " . $conn->error);
    }
?>