<?php
// CashierSystem/funcs/get_products.php
require 'db.php';           // provides $pdo (PDO)
require_once 'check_session.php'; // session validation

header('Content-Type: application/json');

$category_id = isset($_GET['category_id']) && $_GET['category_id'] !== ''
    ? (int) $_GET['category_id']
    : null;

$search = isset($_GET['search']) ? trim($_GET['search']) : null;

$sql = "SELECT product_id, product_name, unit_price, image, stock_quantity
           FROM products
           WHERE 1=1";
$params = [];

if ($category_id !== null) {
    $sql .= " AND category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if ($search !== null && $search !== '') {
    $sql .= " AND product_name LIKE :search";
    $params[':search'] = "%" . $search . "%";
}

$sql .= " ORDER BY product_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(); // PDO::FETCH_ASSOC set globally in db.php
    $products = [];

    foreach ($rows as $row) {
        if (!empty($row['image']) && $row['image'] !== 'default.png') {
            $image = './../CanteenSystem/' . $row['image'];
        } else {
            $image = './../resources/logo.jpg';
        }

        $products[] = [
            'product_id' => (int) $row['product_id'],
            'product_name' => $row['product_name'],
            'unit_price' => (float) $row['unit_price'],
            'stock_quantity' => (int) $row['stock_quantity'],
            'image' => $image
        ];
    }

    echo json_encode(['success' => true, 'data' => $products]);

} catch (PDOException $e) {
    error_log('get_products.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve products.']);
}
?>