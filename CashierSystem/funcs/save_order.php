<?php
// PHP/save_order.php
require 'db.php';           // provides $pdo (PDO)
require_once 'check_session.php'; // session validation

header('Content-Type: application/json');

// Get the payload from the iPad
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['cart']) || empty($data['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty or invalid data structure.']);
    exit;
}

$cart = $data['cart'];
// Authoritatively grab user ID from backend session instead of trusting POST strings
$userId = $_SESSION['user_id'] ?? null;

try {
    // Start Transaction
    $pdo->beginTransaction();

    // Prepare queries outside the loop for optimal performance
    $stmtCheck = $pdo->prepare("SELECT product_name, stock_quantity, unit_price FROM products WHERE product_id = ? FOR UPDATE");
    $stmtInsert = $pdo->prepare("INSERT INTO product_transactions (product_id, quantity, unit_price, transaction_date, user_id) VALUES (?, ?, ?, NOW(), ?)");
    $stmtUpdate = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");

    foreach ($cart as $item) {
        $productId = (int) $item['id'];
        $qtyRequested = (int) $item['qty'];

        if ($qtyRequested <= 0) {
            throw new \Exception("Invalid quantity specified.");
        }

        // A. Fetch current database information and lock the row ('FOR UPDATE') to block race conditions
        $stmtCheck->execute([$productId]);
        $product = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new \Exception("Product ID {$productId} no longer exists in inventory.");
        }

        // B. Strict Inventory Validation Check
        if ($product['stock_quantity'] < $qtyRequested) {
            throw new \Exception("Insufficient stock for '{$product['product_name']}'. Available: {$product['stock_quantity']}, Requested: {$qtyRequested}");
        }

        // C. Secure Authoritative Pricing Look-up
        $realPrice = $product['unit_price'];

        // D. Commit transactional line items
        $stmtInsert->execute([$productId, $qtyRequested, $realPrice, $userId]);

        // E. Safely deduct inventory stock volume balance
        $stmtUpdate->execute([$qtyRequested, $productId]);
    }

    // If everything passes cleanly, commit changes safely to disk
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order processed securely and completed successfully!']);

} catch (\Exception $e) {
    // Safely roll back completely if an issue or stock depletion triggers a failure code path
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log explicit database errors safely in house
    error_log('Checkout System Exception: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Transaction aborted: ' . $e->getMessage()
    ]);
}
?>