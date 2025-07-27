<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); 
define('DB_PASS', ''); 
define('DB_NAME', 'id_verification');
define('DB_CHARSET', 'utf8mb4');

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Set character set to utf8mb4
if (!$conn->set_charset(DB_CHARSET)) {
    die("Error setting charset: " . $conn->error);
}

// Set error reporting for development (remove or adjust for production)
$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
?>