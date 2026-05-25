<?php
// PHP/save_order.php
require 'db.php';
header('Content-Type: application/json');

// Get the cart and cashier data sent from the iPad
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['cart']) || empty($data['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit;
}

$cart = $data['cart'];
$cashierName = $data['cashierName'];

try {
    // Start a MySQL Transaction (Ensures all items save, or none do)
    $pdo->beginTransaction();

    // 1. Find the user_id based on the logged-in cashier's name
    $stmtUser = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR full_name = ? LIMIT 1");
    $stmtUser->execute([$cashierName, $cashierName]);
    $userRow = $stmtUser->fetch();
    $userId = $userRow ? $userRow['user_id'] : null; // Fallback to null if user deleted

    // 2. Loop through every item in the cart
    foreach ($cart as $item) {
        $productId = $item['id'];
        $qty = $item['qty'];
        $price = $item['price'];

        // Insert the record into product_transactions
        $stmtInsert = $pdo->prepare("INSERT INTO product_transactions (product_id, quantity, unit_price, transaction_date, user_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmtInsert->execute([$productId, $qty, $price, $userId]);

        // Deduct the sold quantity from the products table stock
        $stmtUpdate = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        $stmtUpdate->execute([$qty, $productId]);
    }

    // Commit the transaction to the database
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);

} catch (\Exception $e) {
    // If anything fails, roll back the changes so stock isn't ruined
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>