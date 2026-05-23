<?php
require 'db.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Ensure timestamps match local time
date_default_timezone_set('Asia/Manila'); 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO customer_logs (customer_name, pax, customer_type, overnight, accommodation, contact_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['customer_name'],
            $_POST['pax'],
            $_POST['customer_type'],
            $_POST['overnight'],
            $_POST['accommodation'],
            $_POST['contact_number']
        ]);
    } 
    elseif ($action === 'checkout') {
        $id = $_POST['customer_id'];
        $checkout_time = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE customer_logs SET check_out_time = ? WHERE id = ?");
        $stmt->execute([$checkout_time, $id]);
    }
    elseif ($action === 'delete') {
        $id = $_POST['customer_id'];
        $stmt = $pdo->prepare("DELETE FROM customer_logs WHERE id = ?");
        $stmt->execute([$id]);
    }
    elseif ($action === 'edit') {
    $stmt = $pdo->prepare("UPDATE customer_logs SET 
        customer_name = ?, 
        pax = ?, 
        customer_type = ?, 
        overnight = ?, 
        accommodation = ?, 
        contact_number = ? 
        WHERE id = ?");
        
    $stmt->execute([
        $_POST['customer_name'],
        $_POST['pax'],
        $_POST['customer_type'],
        $_POST['overnight'],
        $_POST['accommodation'],
        $_POST['contact_number'],
        $_POST['customer_id']
    ]);
}

    // Redirect back to the logbook after executing the action
    header("Location: logbook.php");
    exit;
}
?>