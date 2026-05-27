<?php
// CashierSystem/funcs/check_session.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Silent Check: If not logged in, stop execution
if (!isset($_SESSION['user_id'])) {
    // If it's a direct API fetch request from JS, send JSON
    if (basename($_SERVER['SCRIPT_FILENAME']) === 'check_session.php') {
        header('Content-Type: application/json');
        echo json_encode(['authenticated' => false, 'message' => 'Not logged in']);
        exit();
    } else {
        // If it's required by get_products.php, kill the request or redirect
        header("Location: index.php");
        exit();
    }
}

// 2. Broadcast Check: ONLY echo JSON if JS is calling this file DIRECTLY
if (basename($_SERVER['SCRIPT_FILENAME']) === 'check_session.php') {
    header('Content-Type: application/json');
    echo json_encode([
        'authenticated' => true,
        'message' => 'Login successful',
        'username' => $_SESSION['username']
    ]);
    exit();
}

// If get_products.php called this file, it will smoothly drop past this line 
// back into your products logic without echo-ing or exiting!
?>