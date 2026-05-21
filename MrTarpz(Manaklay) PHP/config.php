<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'manaklay_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to sanitize input
function sanitize($input) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'status-pending',
        'in_progress' => 'status-progress',
        'completed' => 'status-completed',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled',
        'paid' => 'status-completed',
        'unpaid' => 'status-pending',
        'partial' => 'status-progress'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : 'status-pending';
}

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');

function sendWelcomeEmail($email, $name, $username) {
    error_log("Welcome email would be sent to: $email for user: $username");
    return true;
}
?>