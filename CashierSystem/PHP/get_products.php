<?php
// PHP/get_products.php
require 'db.php';
header('Content-Type: application/json');

try {
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    $sql = "SELECT product_id, product_name, unit_price, image, stock_quantity FROM products WHERE 1=1";
    $params = [];

    if ($categoryId) {
        $sql .= " AND category_id = ?";
        $params[] = $categoryId;
    }

    if ($search) {
        $sql .= " AND product_name LIKE ?";
        $params[] = "%$search%";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $products]);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
