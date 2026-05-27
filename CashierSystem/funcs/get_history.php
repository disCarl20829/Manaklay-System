<?php
// PHP/get_history.php
require 'db.php'; // Include the connection
require_once 'check_session.php'; // provides $conn (MySQLi) and session validation

header('Content-Type: application/json');

try {
    // 2. Optimized Query with Limit Constraints to prevent memory leaks
    // Ideal fix: Group by a dedicated 'transaction_id' or 'receipt_number' if added to your schema.
    // For now, we fetch the last 100 grouped transactional logs.
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
        LIMIT 200
    ";

    // Explicitly use FETCH_ASSOC to ensure data predictability
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Group items safely
    $groupedHistory = [];
    foreach ($results as $row) {
        $date = $row['transaction_date'];

        // Grouping Key Fallback: If you don't have a unique Transaction reference ID, 
        // fallback to combining the timestamp and cashier username to isolate concurrent register sales.
        $cashierKey = $row['username'] ?: 'system';
        $groupingKey = $date . '_' . $cashierKey;

        if (!isset($groupedHistory[$groupingKey])) {
            $groupedHistory[$groupingKey] = [
                'date' => $date,
                'cashier' => $row['full_name'] ?: $row['username'] ?: 'Unknown',
                'total_qty' => 0,
                'total_price' => 0,
                'items' => []
            ];
        }

        $quantity = intval($row['quantity']);
        $unit_price = floatval($row['unit_price']);
        $subtotal = $quantity * $unit_price;

        $groupedHistory[$groupingKey]['total_qty'] += $quantity;
        $groupedHistory[$groupingKey]['total_price'] += $subtotal;

        $groupedHistory[$groupingKey]['items'][] = [
            'name' => $row['product_name'],
            'price' => $unit_price,
            'qty' => $quantity,
            'subtotal' => $subtotal
        ];
    }

    // Convert to indexed array so JavaScript treats it as a standard array list, not an object literal
    echo json_encode([
        'success' => true,
        'data' => array_values($groupedHistory)
    ]);

} catch (\PDOException $e) {
    // Log the error internally
    error_log('Database Error in get_history.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Failed to parse historic transaction data records.'
    ]);
}
?>