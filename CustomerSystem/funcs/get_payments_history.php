<?php
/**
 * get_payment_history.php
 *
 * Returns a JSON array of payment_transactions rows for a given customer_log_id.
 *
 * Query params:
 *   customer_id – customer_logs.id  (required)
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

require '../db.php';
header('Content-Type: application/json');

$customerId = (int) ($_GET['customer_id'] ?? 0);
if ($customerId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, amount_paid, payment_method, remarks, created_at
     FROM payment_transactions
     WHERE customer_log_id = ?
     ORDER BY created_at ASC"
);
$stmt->execute([$customerId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));