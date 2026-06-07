<?php
/**
 * get_blocked_accs.php
 *
 * Returns a JSON array of accommodation names that are occupied
 * on the requested date (active check-ins with no check-out).
 *
 * Query params:
 *   date       – YYYY-MM-DD  (required)
 *   exclude_id – customer_logs.id to exclude (used when editing a record
 *                so the record's own rooms aren't flagged as blocked)
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

require '../db.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$excludeId = (int) ($_GET['exclude_id'] ?? 0);

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

// Fetch all active logs whose check_in date matches the requested date,
// optionally excluding a specific record (for the edit flow).
$sql = "SELECT accommodation FROM customer_logs
        WHERE check_out_time IS NULL
          AND DATE(check_in_time) = ?";
$params = [$date];

if ($excludeId > 0) {
    $sql .= " AND id != ?";
    $params[] = $excludeId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Flatten comma-separated accommodation strings into a unique name set
$blocked = [];
foreach ($rows as $accString) {
    foreach (explode(', ', $accString) as $name) {
        $name = trim($name);
        if ($name !== '')
            $blocked[$name] = true;
    }
}

echo json_encode(array_keys($blocked));