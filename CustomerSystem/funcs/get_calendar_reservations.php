<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require '../db.php';

$date = $_GET['date'] ?? '';

if (empty($date)) {
    echo json_encode([]);
    exit;
}

try {
    // Queries logs where the check-in date matches the selected date
    $stmt = $pdo->prepare("
        SELECT id, customer_name, customer_type, accommodation, pax, check_out_time
        FROM customer_logs 
        WHERE DATE(check_in_time) = ?
        ORDER BY check_in_time ASC
    ");
    $stmt->execute([$date]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($reservations);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}