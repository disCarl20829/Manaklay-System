<?php
// PHP/get_history.php
require 'db.php';
header('Content-Type: application/json');

try {
    // Fetch all transactions, joining with products and users for names
    $sql = "
        SELECT 
            pt.transaction_date, 
            p.product_name, 
            pt.quantity, 
            pt.unit_price, 
            u.full_name, 
            u.username 
        FROM product_transactions pt
        JOIN products p ON pt.product_id = p.product_id
        LEFT JOIN users u ON pt.user_id = u.user_id
        ORDER BY pt.transaction_date DESC
    ";
    
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    // Group the items by the exact transaction timestamp
    $groupedHistory = [];
    foreach ($results as $row) {
        $date = $row['transaction_date'];
        
        // If this is the first item of a new order timestamp, set up the array
        if (!isset($groupedHistory[$date])) {
            $groupedHistory[$date] = [
                'date' => $date,
                'cashier' => $row['full_name'] ?: $row['username'] ?: 'Unknown',
                'total_qty' => 0,
                'total_price' => 0,
                'items' => []
            ];
        }

        // Add this item's math to the order totals
        $subtotal = $row['quantity'] * $row['unit_price'];
        $groupedHistory[$date]['total_qty'] += $row['quantity'];
        $groupedHistory[$date]['total_price'] += $subtotal;

        // Push the item into the order's item list
        $groupedHistory[$date]['items'][] = [
            'name' => $row['product_name'],
            'price' => $row['unit_price'],
            'qty' => $row['quantity'],
            'subtotal' => $subtotal
        ];
    }

    // Convert the associative array into a clean indexed array for JavaScript
    echo json_encode(['success' => true, 'data' => array_values($groupedHistory)]);

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>