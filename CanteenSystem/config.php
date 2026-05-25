<?php
session_start();

// Fetch environment variables dynamically
$env_host = getenv('MYSQLHOST') ?: 'localhost';
$env_user = getenv('MYSQLUSER') ?: 'root';
$env_pass = getenv('MYSQLPASSWORD') ?: '123456';
$env_name = getenv('MYSQLDATABASE') ?: 'manaklay_db';
$env_port = getenv('MYSQLPORT') ?: '3306';

// Database configuration constants
define('DB_HOST', $env_host);
define('DB_USER', $env_user);
define('DB_PASS', $env_pass);
define('DB_NAME', $env_name);
define('DB_PORT', $env_port);

// Create connection incorporating the port
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// ... (Keep the rest of your original functions down here)