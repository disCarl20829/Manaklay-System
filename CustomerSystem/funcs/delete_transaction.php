<?php
/**
 * delete_transaction.php
 * Deletes a single row from payment_transactions.
 * POST params: txn_id (int)
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../db.php';
header('Content-Type: application/json');

$txnId = (int) ($_POST['txn_id'] ?? 0);

if ($txnId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid transaction ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM payment_transactions WHERE id = ?");
    $stmt->execute([$txnId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}