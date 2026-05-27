<?php
// get_categories.php
require 'db.php';           // provides $pdo (PDO)
require_once 'check_session.php'; // session validation

header('Content-Type: application/json');

try {
    // Assuming your column names are category_id and category_name. 
    // Adjust 'category_name' if your table uses a different column name for the text.
    $stmt = $pdo->query('SELECT category_id, category_name FROM product_categories ORDER BY category_id ASC');
    $categories = $stmt->fetchAll();

    // Send the data back as JSON
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);

} catch (\PDOException $e) {
    // If something goes wrong, send the error back safely
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>