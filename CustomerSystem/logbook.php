<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$settingsJson = json_encode($settings);

$accStmt = $pdo->query("SELECT id, type, number, price_per_day, status FROM accommodations ORDER BY type, number");
$accommodationsList = $accStmt->fetchAll(PDO::FETCH_ASSOC);

$blockedStmt = $pdo->query(
    "SELECT DISTINCT accommodation FROM customer_logs
     WHERE check_out_time IS NULL
       AND DATE(check_in_time) = CURDATE()"
);
$blockedRows = $blockedStmt->fetchAll(PDO::FETCH_COLUMN);

$blockedAccNames = [];
foreach ($blockedRows as $accString) {
    foreach (explode(', ', $accString) as $name) {
        $blockedAccNames[trim($name)] = true;
    }
}

$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'reservation_date';
$query = "SELECT customer_logs.*, SUM(COALESCE(payment_transactions.amount_paid, 0)) AS total_amount_paid FROM customer_logs
 LEFT JOIN payment_transactions ON customer_logs.id = payment_transactions.customer_log_id GROUP BY customer_logs.id";
$params = [];

if (!empty($search)) {
    $query .= " WHERE customer_name LIKE ? OR check_in_time LIKE ? OR accommodation LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($sort) {
    case 'customer_name':
        $query .= " ORDER BY customer_name ASC";
        break;

    case 'date_added':
        $query .= " ORDER BY id DESC";
        break;

    case 'reservation_date':
    default:
        $query .= " ORDER BY check_in_time DESC";
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCustomers = count($logs);
$walkIns = 0;
$reservations = 0;
$currentlyCheckedIn = 0;

foreach ($logs as $log) {
    if ($log['customer_type'] === 'Walk-in')
        $walkIns++;
    if ($log['customer_type'] === 'Reservation')
        $reservations++;
    if (is_null($log['check_out_time']))
        $currentlyCheckedIn++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer System - Dashboard</title>
    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">
    <style>
        :root {
            --sidebar-bg: #0A192F;
            --primary-blue: #1E3A8A;
            --accent-orange: #F59E0B;
            --bg-light: #F4F7F6;
            --card-bg: #FFFFFF;
            --text-dark: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --danger-red: #EF4444;
            --success-green: #10B981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 30px 20px;
        }

        .sidebar-brand {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 50px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar-brand span {
            color: var(--accent-orange);
        }

        .sidebar nav {
            flex: 1;
        }

        .sidebar nav a {
            display: block;
            color: #94A3B8;
            text-decoration: none;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background-color: var(--primary-blue);
            color: white;
        }

        .logout-link {
            text-decoration: none;
            color: white;
            padding: 12px 15px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            text-align: center;
            margin-top: auto;
            transition: background 0.3s;
        }

        .logout-link:hover {
            background-color: var(--danger-red);
        }

        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            color: var(--text-dark);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .kpi-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .kpi-title {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-blue);
        }

        .logbook-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            width: 50%;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--bg-light);
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary-blue);
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: white;
            transition: opacity 0.3s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background-color: var(--primary-blue);
        }

        .btn-accent {
            background-color: var(--accent-orange);
            color: var(--sidebar-bg);
        }

        .btn-success {
            background-color: var(--success-green);
        }

        .btn-danger {
            background-color: var(--danger-red);
        }

        .btn-clear {
            background-color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tbody tr:hover {
            background-color: #F9FAFB;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background-color: #DEF7EC;
            color: #03543F;
        }

        .status-closed {
            background-color: #F3F4F6;
            color: #4B5563;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(10, 25, 47, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 100;
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--sidebar-bg);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group-full {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .modal input,
        .modal select,
        .modal textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
            font-family: inherit;
            font-size: 14px;
        }

        .modal input:focus,
        .modal select:focus,
        .modal textarea:focus {
            border-color: var(--primary-blue);
        }

        .modal textarea {
            resize: vertical;
            min-height: 70px;
        }

        .modal-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .hidden-toggle {
            display: none;
        }

        .toggle-button-label {
            display: inline-flex;
            cursor: pointer;
            border: 2px solid #ccc;
            border-radius: 20px;
            padding: 5px;
            background: #f0f0f0;
            font-weight: bold;
        }

        .toggle-button-label span {
            padding: 8px 20px;
            border-radius: 15px;
            transition: 0.3s ease;
        }

        .toggle-button-label .status-day {
            background: #ffca28;
            color: #000;
        }

        .toggle-button-label .status-night {
            color: #888;
        }

        .hidden-toggle:checked+.toggle-button-label .status-day {
            background: transparent;
            color: #888;
        }

        .hidden-toggle:checked+.toggle-button-label .status-night {
            background: #3f51b5;
            color: #fff;
        }

        .acc-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 6px;
            max-height: 160px;
            overflow-y: auto;
            padding: 2px;
        }

        .acc-card {
            border: 1.5px solid var(--border-color);
            border-radius: 5px;
            padding: 4px 9px;
            cursor: pointer;
            font-size: 12px;
            line-height: 1.4;
            transition: all 0.15s;
            user-select: none;
            background: #fff;
            white-space: nowrap;
            pointer-events: auto;
        }

        .acc-card * {
            pointer-events: none;
        }

        .acc-card:hover {
            border-color: var(--primary-blue);
            background: #F8FAFF;
        }

        .acc-card.selected {
            border-color: var(--primary-blue);
            background: #EFF6FF;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .acc-card.blocked-today {
            opacity: 0.4;
            cursor: not-allowed;
            background: #FEF2F2;
            border-color: #FECACA;
        }

        .acc-card .acc-status-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
        }

        .acc-card .acc-status-dot.open {
            background: #10B981;
        }

        .acc-card .acc-status-dot.active {
            background: #F59E0B;
        }

        .acc-card .acc-status-dot.reserved {
            background: #6366F1;
        }

        .acc-card .acc-status-dot.oos {
            background: #EF4444;
        }

        .acc-selected-summary {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
            min-height: 16px;
        }

        .acc-legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 6px;
            font-size: 11px;
            color: var(--text-muted);
        }

        .acc-legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .acc-legend-swatch {
            width: 10px;
            height: 10px;
            border-radius: 2px;
        }

        .swatch-available {
            background: #EFF6FF;
            border: 1.5px solid #1E3A8A;
        }

        .swatch-blocked {
            background: #FEF2F2;
            border: 1.5px solid #FECACA;
        }

        .checkin-hint {
            font-size: 11px;
            color: var(--accent-orange);
            margin-top: 4px;
            min-height: 14px;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-orange)" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                </path>
            </svg>
            Manaklay
        </div>
        <nav>
            <a href="logbook.php" class="active">Logs</a>
            <a href="payments.php">Payments</a>
            <a href="accommodations.php">Accommodations</a>
            <a href="reports.php">Reports</a>
            <a href="settings.php">Settings</a>
        </nav>
        <a href="logout.php" class="logout-link">Log out</a>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
        </div>

        <div class="calendar-dashboard"
            style="display:flex;gap:20px;margin-bottom:30px;background:var(--card-bg);padding:20px;border-radius:12px;border:1px solid var(--border-color);box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <div class="calendar-widget" style="flex:1;min-width:300px;">
                <div class="calendar-header"
                    style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                    <h3 id="calendarMonthYear" style="color:var(--sidebar-bg);font-size:18px;"></h3>
                    <div>
                        <button type="button" class="btn btn-primary" style="padding:5px 10px;font-size:12px;"
                            onclick="changeMonth(-1)">&lt;</button>
                        <button type="button" class="btn btn-primary" style="padding:5px 10px;font-size:12px;"
                            onclick="changeMonth(1)">&gt;</button>
                    </div>
                </div>
                <div
                    style="display:grid;grid-template-columns:repeat(7,1fr);gap:5px;text-align:center;font-weight:600;font-size:12px;color:var(--text-muted);margin-bottom:8px;">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>
                <div id="calendarDatesGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:5px;"></div>
            </div>
            <div
                style="flex:1.5;border-left:1px solid var(--border-color);padding-left:20px;max-height:290px;overflow-y:auto;">
                <h3 style="color:var(--sidebar-bg);font-size:16px;margin-bottom:12px;">
                    Bookings for <span id="selectedDateLabel"
                        style="color:var(--accent-orange);font-weight:bold;">(Select a Date)</span>
                </h3>
                <div id="calendarReservationsContainer">
                    <p style="color:var(--text-muted);font-size:14px;font-style:italic;">Click on any calendar day with
                        data to review scheduled check-ins.</p>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Total Records</div>
                <div class="kpi-value"><?= $totalCustomers ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Currently Checked In</div>
                <div class="kpi-value" style="color:var(--accent-orange)"><?= $currentlyCheckedIn ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Walk-ins</div>
                <div class="kpi-value"><?= $walkIns ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Reservations</div>
                <div class="kpi-value"><?= $reservations ?></div>
            </div>
        </div>

        <div class="logbook-container">
            <div class="toolbar">
                <form class="search-form" method="GET" action="logbook.php">
                    <input type="text" name="search" class="search-input"
                        placeholder="Search transactions, customers, accommodations..."
                        value="<?= htmlspecialchars($search) ?>">
                    <select name="sort" class="search-input" style="max-width:180px;">
                        <option value="reservation_date" <?= $sort == 'reservation_date' ? 'selected' : '' ?>>
                            Reservation Date
                        </option>
                        <option value="customer_name" <?= $sort == 'customer_name' ? 'selected' : '' ?>>
                            Customer Name
                        </option>
                        <option value="date_added" <?= $sort == 'date_added' ? 'selected' : '' ?>>
                            Date Added
                        </option>
                    </select>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?><a href="logbook.php?sort=<?= urlencode($sort) ?>" class="btn btn-clear">Clear</a><?php endif; ?>
                </form>
                <button class="btn btn-accent" onclick="openModal('addModal')">+ Add Customer</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Pax</th>
                        <th>Type</th>
                        <th>Accomm.</th>
                        <th>Status</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Contact</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $row): ?>
                            <tr>
                                <td style="font-weight:600;color:var(--text-dark);">
                                    <?= htmlspecialchars($row['customer_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['pax']) ?></td>
                                <td><?= htmlspecialchars($row['customer_type']) ?></td>
                                <td><?= htmlspecialchars($row['accommodation']) ?></td>
                                <td>
                                    <?php if (!$row['check_out_time']): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-closed">Checked Out</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, y g:i A', strtotime($row['check_in_time'])) ?></td>
                                <td><?= $row['check_out_time'] ? date('M d, y g:i A', strtotime($row['check_out_time'])) : '--' ?>
                                </td>
                                <td><?= htmlspecialchars($row['contact_number']) ?></td>
                                <td style="max-width:160px;white-space:pre-wrap;font-size:13px;color:var(--text-muted);">
                                    <?= htmlspecialchars($row['notes'] ?? '') ?>
                                </td>
                                <td style="display:flex;gap:5px;">
                                    <?php if (!$row['check_out_time']): ?>
                                        <button class="btn btn-success" style="padding:6px 10px;font-size:12px;"
                                            onclick="openCheckoutModal(<?= $row['id'] ?>)">Out</button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary" onclick='openEditModal(
                                    <?= json_encode($row["id"]) ?>,
                                    <?= json_encode($row["customer_name"]) ?>,
                                    <?= json_encode($row["contact_number"]) ?>,
                                    <?= json_encode($row["customer_type"]) ?>,
                                    <?= json_encode($row["adults"] ?? 0) ?>,
                                    <?= json_encode($row["seniors"] ?? 0) ?>,
                                    <?= json_encode($row["children"] ?? 0) ?>,
                                    <?= json_encode($row["accommodation"]) ?>,
                                    <?= json_encode($row["overnight"]) ?>,
                                    <?= json_encode($row["entrance_fee"] ?? 0) ?>,
                                    <?= json_encode($row["accommodation_fee"] ?? 0) ?>,
                                    <?= json_encode($row["payment_status"]) ?>,
<?= json_encode(date('Y-m-d\TH:i', strtotime($row['check_in_time']))) ?>,
                                    <?= json_encode($row["notes"] ?? "") ?>,
                                    <?= json_encode((float) $row["total_amount_paid"]) ?>
                                )'>Edit</button>

                                    <form method="POST" action="actions.php" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="customer_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="total_amount_paid" value="<?= $row['total_amount_paid'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:6px 10px;font-size:12px;"
                                            onclick="return confirm('Delete this record?');">Del</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center;padding:30px;color:var(--text-muted);">No records
                                found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ADD MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="width:600px;">
            <h3>Add New Customer</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="pax" id="hidden_pax" value="0">
                <input type="hidden" name="entrance_fee" id="hidden_entrance_fee" value="0">
                <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">
                <input type="hidden" name="overnight" value="No">

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Check In Time</label>
                        <input type="datetime-local" name="check_in_time" id="add_check_in_time"
                            value="<?= date('Y-m-d\TH:i') ?>" required onchange="refreshAddPicker()">
                        <div class="checkin-hint" id="add_checkin_hint"></div>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Type</label>
                        <select name="customer_type">
                            <option value="Walk-in">Walk-in</option>
                            <option value="Reservation">Reservation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tour Type:</label>
                        <div class="tour-toggle-container">
                            <input type="checkbox" id="overnight_toggle" name="overnight" value="Yes"
                                class="hidden-toggle" onchange="calculateFees()">
                            <label for="overnight_toggle" class="toggle-button-label">
                                <span class="status-day">☀️ Daytour</span>
                                <span class="status-night">🌙 Overnight</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group"><label>Adults</label><input type="number" name="adults" id="adults" min="0"
                            oninput="calculateFees()"></div>
                    <div class="form-group"><label>Seniors / PWD</label><input type="number" name="seniors" id="seniors"
                            min="0" oninput="calculateFees()"></div>
                    <div class="form-group"><label>Children</label><input type="number" name="children" id="children"
                            min="0" oninput="calculateFees()"></div>
                    <div class="form-group"><label>Contact</label><input type="text" name="contact_number" required>
                    </div>

                    <div class="form-group form-group-full">
                        <label>Accommodations <span style="color:var(--text-muted);font-weight:400;">(click to select
                                one or more)</span></label>
                        <div class="acc-legend">
                            <div class="acc-legend-item">
                                <div class="acc-legend-swatch swatch-available"></div> Available
                            </div>
                            <div class="acc-legend-item">
                                <div class="acc-legend-swatch swatch-blocked"></div> Occupied today
                            </div>
                        </div>
                        <div class="acc-picker" id="acc_picker">
                            <?php foreach ($accommodationsList as $acc):
                                $accName = $acc['type'] . ' ' . $acc['number'];
                                $statusLow = strtolower($acc['status']);
                                $isOos = ($acc['status'] === 'Out of Service');
                                $blockedNow = isset($blockedAccNames[$accName]);
                                $dotClass = ['open' => 'open', 'active' => 'active', 'reserved' => 'reserved', 'out of service' => 'oos'][$statusLow] ?? 'open';
                                ?>
                                <div class="acc-card <?= ($isOos || $blockedNow) ? 'blocked-today' : '' ?>"
                                    data-name="<?= htmlspecialchars($accName) ?>"
                                    data-price="<?= htmlspecialchars($acc['price_per_day']) ?>"
                                    data-blocked="<?= ($isOos || $blockedNow) ? '1' : '0' ?>"
                                    data-oos="<?= $isOos ? '1' : '0' ?>" onclick="toggleAccCard(this)"
                                    title="<?= $blockedNow ? 'Occupied today' : ($isOos ? 'Out of service' : '') ?>">
                                    <span class="acc-status-dot <?= $dotClass ?>"></span>
                                    <?= htmlspecialchars($accName) ?>
                                    <br><small
                                        style="color:var(--text-muted);">₱<?= number_format($acc['price_per_day'], 2) ?>
                                        &bull; <?= $acc['status'] ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="acc-selected-summary" id="acc_selected_summary">None selected</div>
                        <input type="hidden" name="accommodation" id="hidden_accommodation" value="">
                        <input type="hidden" name="accommodation_fee" id="accommodation_fee" value="0">
                    </div>

                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status">
                            <option value="Partial">Partial</option>
                            <option value="Full">Complete</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount Paid Now (₱)</label>
                        <input type="number" name="amount_paid" id="add_amount_paid" min="0" step="0.01"
                            placeholder="e.g. 500.00" oninput="calculateFees()">
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="Cash">💵 Cash</option>
                            <option value="GCash">📱 GCash</option>
                            <option value="Card">💳 Card</option>
                            <option value="Bank Transfer">🏦 Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Payment Remarks</label>
                        <input type="text" name="remarks" placeholder="e.g. Downpayment, Full cash, etc.">
                    </div>
                    <div class="form-group form-group-full">
                        <label>Notes</label>
                        <textarea name="notes"
                            placeholder="Optional notes about this customer or booking..."></textarea>
                    </div>
                    <div class="form-group form-group-full"
                        style="background:var(--bg-light);padding:15px;border-radius:8px;text-align:right;">
                        <span style="font-size:14px;color:var(--text-muted);">Total Expected Fees:</span>
                        <strong style="font-size:24px;color:var(--accent-orange);display:block;">₱<span
                                id="display_total">0.00</span></strong>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="width:600px;">
            <h3>Edit Customer</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <input type="hidden" name="pax" id="hidden_edit_pax" value="0">
                <input type="hidden" name="entrance_fee" id="hidden_edit_entrance_fee" value="0">
                <input type="hidden" name="total_amount" id="hidden_edit_total_amount" value="0">
                <input type="hidden" name="overnight" value="No">

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Check In Time</label>
                        <input type="datetime-local" name="check_in_time" id="edit_check_in_time" required
                            onchange="refreshEditPicker()">
                        <div class="checkin-hint" id="edit_checkin_hint"></div>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" id="edit_customer_name" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Type</label>
                        <select name="customer_type" id="edit_customer_type">
                            <option value="Walk-in">Walk-in</option>
                            <option value="Reservation">Reservation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tour Type:</label>
                        <div class="tour-toggle-container">
                            <input type="checkbox" id="edit_overnight_toggle" name="overnight" value="Yes"
                                class="hidden-toggle" onchange="calculateEditFees()">
                            <label for="edit_overnight_toggle" class="toggle-button-label">
                                <span class="status-day">☀️ Daytour</span>
                                <span class="status-night">🌙 Overnight</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group"><label>Adults</label><input type="number" name="adults" id="edit_adults"
                            min="0" oninput="calculateEditFees()"></div>
                    <div class="form-group"><label>Seniors / PWD</label><input type="number" name="seniors"
                            id="edit_seniors" min="0" oninput="calculateEditFees()"></div>
                    <div class="form-group"><label>Children</label><input type="number" name="children"
                            id="edit_children" min="0" oninput="calculateEditFees()"></div>
                    <div class="form-group"><label>Contact</label><input type="text" name="contact_number"
                            id="edit_contact_number" required></div>

                    <div class="form-group form-group-full">
                        <label>Accommodations <span style="color:var(--text-muted);font-weight:400;">(click to select
                                one or more)</span></label>
                        <div class="acc-legend">
                            <div class="acc-legend-item">
                                <div class="acc-legend-swatch swatch-available"></div> Available
                            </div>
                            <div class="acc-legend-item">
                                <div class="acc-legend-swatch swatch-blocked"></div> Occupied today
                            </div>
                        </div>
                        <div class="acc-picker" id="edit_acc_picker">
                            <?php foreach ($accommodationsList as $acc):
                                $accName = $acc['type'] . ' ' . $acc['number'];
                                $statusLow = strtolower($acc['status']);
                                $isOos = ($acc['status'] === 'Out of Service');
                                $dotClass = ['open' => 'open', 'active' => 'active', 'reserved' => 'reserved', 'out of service' => 'oos'][$statusLow] ?? 'open';
                                ?>
                                <div class="acc-card" data-name="<?= htmlspecialchars($accName) ?>"
                                    data-price="<?= htmlspecialchars($acc['price_per_day']) ?>"
                                    data-oos="<?= $isOos ? '1' : '0' ?>" data-blocked="0" onclick="toggleEditAccCard(this)">
                                    <span class="acc-status-dot <?= $dotClass ?>"></span>
                                    <?= htmlspecialchars($accName) ?>
                                    <br><small
                                        style="color:var(--text-muted);">₱<?= number_format($acc['price_per_day'], 2) ?>
                                        &bull; <?= $acc['status'] ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="acc-selected-summary" id="edit_acc_selected_summary">None selected</div>
                        <input type="hidden" name="accommodation" id="edit_hidden_accommodation" value="">
                        <input type="hidden" name="accommodation_fee" id="edit_accommodation_fee" value="0">
                    </div>

                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" id="edit_payment_status">
                            <option value="Partial">Partial</option>
                            <option value="Full">Complete</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount Paid Now (₱) <span
                                style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                        <input type="number" name="amount_paid" id="edit_amount_paid" min="0" step="0.01"
                            placeholder="Leave blank to skip" oninput="calculateEditFees()">
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" id="edit_payment_method">
                            <option value="Cash">💵 Cash</option>
                            <option value="GCash">📱 GCash</option>
                            <option value="Card">💳 Card</option>
                            <option value="Bank Transfer">🏦 Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Payment Remarks</label>
                        <input type="text" name="remarks" id="edit_remarks" placeholder="e.g. Balance payment, etc.">
                    </div>
                    <div class="form-group form-group-full">
                        <label>Notes</label>
                        <textarea name="notes" id="edit_notes"
                            placeholder="Optional notes about this customer or booking..."></textarea>
                    </div>
                    <div class="form-group form-group-full"
                        style="background:var(--bg-light);padding:15px;border-radius:8px;text-align:right;">
                        <span style="font-size:14px;color:var(--text-muted);">Total Expected Fees:</span>
                        <strong style="font-size:24px;color:var(--accent-orange);display:block;">₱<span
                                id="display_edit_total">0.00</span></strong>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CHECKOUT MODAL -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content" style="width:400px;">
            <h3>Confirm Check Out</h3>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">The check out time will be recorded as
                right now. Proceed?</p>
            <form id="checkoutForm" method="POST" action="actions.php">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="customer_id" id="checkout_customer_id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" style="background:none;color:var(--text-muted);"
                        onclick="closeModal('checkoutModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Check Out</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const fees = <?= $settingsJson ?>;
        const serverBlockedToday = <?= json_encode(array_keys($blockedAccNames)) ?>;
        const allAccommodations = <?= json_encode(array_values(array_map(function ($a) {
            return ['name' => $a['type'] . ' ' . $a['number'], 'price' => (float) $a['price_per_day'], 'status' => $a['status'], 'oos' => ($a['status'] === 'Out of Service')];
        }, $accommodationsList))) ?>;

        const RATES = {
            daytour: { adult: parseFloat(fees.fee_adult_day) || 50, child: parseFloat(fees.fee_child_day) || 30, senior: parseFloat(fees.fee_senior_day) || 40 },
            overnight: { adult: parseFloat(fees.fee_adult_overnight) || 80, child: parseFloat(fees.fee_child_overnight) || 50, senior: parseFloat(fees.fee_senior_overnight) || 70 }
        };

        // ── Modal helpers ──────────────────────────────────────────────────────────
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function openCheckoutModal(id) { document.getElementById('checkout_customer_id').value = id; openModal('checkoutModal'); }

        // ── Entrance fee ──────────────────────────────────────────────────────────
        function getEntranceFee(adults, seniors, children, isOvernight) {
            const r = isOvernight ? RATES.overnight : RATES.daytour;
            return (adults * r.adult) + (seniors * r.senior) + (children * r.child);
        }

        // ── Auto check-in time ────────────────────────────────────────────────────
        // Room/Dorm → 14:00 | Cottage → 17:00 | Daytour/none → 07:00
        function autoSetCheckInTime(pickerId, inputId, hintId) {
            const cards = [...document.querySelectorAll('#' + pickerId + ' .acc-card.selected')];
            const names = cards.map(c => c.dataset.name.toLowerCase());
            const hasRoom = names.some(n => n.startsWith('room'));
            const hasDorm = names.some(n => n.startsWith('dormitory'));
            const hasCottage = names.some(n => n.startsWith('cottage'));

            const input = document.getElementById(inputId);
            const hint = document.getElementById(hintId);

            // Preserve the date, only replace the time
            const existing = input.value || '';
            const datePart = existing.includes('T') ? existing.split('T')[0] : (function () {
                const n = new Date(), p = x => String(x).padStart(2, '0');
                return n.getFullYear() + '-' + p(n.getMonth() + 1) + '-' + p(n.getDate());
            })();

            let time, hintText;
            if (hasRoom || hasDorm) {
                time = '14:00'; hintText = '🏠 Room/Dorm — 2:00 PM check-in (window: 2pm–11am)';
            } else if (hasCottage) {
                time = '17:00'; hintText = '🛖 Cottage — 5:00 PM check-in (window: 5pm–7am)';
            } else {
                time = '07:00'; hintText = cards.length ? '☀️ Daytour — 7:00 AM check-in (window: 7am–5pm)' : '';
            }

            input.value = datePart + 'T' + time;
            if (hint) hint.textContent = hintText;
        }

        // ── ADD picker ────────────────────────────────────────────────────────────
        function refreshAddPicker() {
            const dateStr = document.getElementById('add_check_in_time').value;
            if (!dateStr) return;
            fetch('funcs/get_blocked_accs.php?date=' + encodeURIComponent(dateStr.split('T')[0]))
                .then(r => r.json())
                .then(blocked => {
                    document.querySelectorAll('#acc_picker .acc-card').forEach(card => {
                        const isOos = card.dataset.oos === '1';
                        const isBlocked = blocked.includes(card.dataset.name) || isOos;
                        card.dataset.blocked = isBlocked ? '1' : '0';
                        card.classList.toggle('blocked-today', isBlocked);
                        if (isBlocked) card.classList.remove('selected');
                    });
                    syncAccPicker();
                })
                .catch(() => { });
        }

        function toggleAccCard(card) {
            // Walk up to the actual .acc-card in case a child element was clicked
            const el = card.closest('.acc-card');
            if (!el || el.dataset.blocked === '1') return;
            el.classList.toggle('selected');
            syncAccPicker();
        }

        function syncAccPicker() {
            const selected = [...document.querySelectorAll('#acc_picker .acc-card.selected')];
            const names = selected.map(c => c.dataset.name);
            const total = selected.reduce((s, c) => s + (parseFloat(c.dataset.price) || 0), 0);
            document.getElementById('hidden_accommodation').value = names.join(', ');
            document.getElementById('accommodation_fee').value = total.toFixed(2);
            document.getElementById('acc_selected_summary').textContent =
                names.length ? names.join(', ') + '  —  ₱' + total.toFixed(2) : 'None selected';
            autoSetCheckInTime('acc_picker', 'add_check_in_time', 'add_checkin_hint');
            calculateFees();
        }

        // ── EDIT picker ───────────────────────────────────────────────────────────
        function refreshEditPicker() {
            const dateStr = document.getElementById('edit_check_in_time').value;
            const recordId = document.getElementById('edit_customer_id').value;
            if (!dateStr) return;
            fetch('funcs/get_blocked_accs.php?date=' + encodeURIComponent(dateStr.split('T')[0]) + '&exclude_id=' + encodeURIComponent(recordId))
                .then(r => r.json())
                .then(blocked => {
                    document.querySelectorAll('#edit_acc_picker .acc-card').forEach(card => {
                        const isOos = card.dataset.oos === '1';
                        const isBlocked = blocked.includes(card.dataset.name) || isOos;
                        card.dataset.blocked = isBlocked ? '1' : '0';
                        card.classList.toggle('blocked-today', isBlocked);
                        if (isBlocked) card.classList.remove('selected');
                    });
                    syncEditAccPicker();
                })
                .catch(() => { });
        }

        function toggleEditAccCard(card) {
            const el = card.closest('.acc-card');
            if (!el || el.dataset.blocked === '1') return;
            el.classList.toggle('selected');
            syncEditAccPicker();
        }

        function syncEditAccPicker() {
            const selected = [...document.querySelectorAll('#edit_acc_picker .acc-card.selected')];
            const names = selected.map(c => c.dataset.name);
            const total = selected.reduce((s, c) => s + (parseFloat(c.dataset.price) || 0), 0);
            document.getElementById('edit_hidden_accommodation').value = names.join(', ');
            document.getElementById('edit_accommodation_fee').value = total.toFixed(2);
            document.getElementById('edit_acc_selected_summary').textContent =
                names.length ? names.join(', ') + '  —  ₱' + total.toFixed(2) : 'None selected';
            autoSetCheckInTime('edit_acc_picker', 'edit_check_in_time', 'edit_checkin_hint');
            calculateEditFees();
        }

        // ── Fee calculations ──────────────────────────────────────────────────────
        function calculateFees() {
            const adults = parseInt(document.getElementById('adults').value) || 0;
            const seniors = parseInt(document.getElementById('seniors').value) || 0;
            const children = parseInt(document.getElementById('children').value) || 0;
            const accFee = parseFloat(document.getElementById('accommodation_fee').value) || 0;
            const paid = parseFloat(document.getElementById('add_amount_paid').value) || 0;
            const isOver = document.getElementById('overnight_toggle').checked;
            const entrance = getEntranceFee(adults, seniors, children, isOver);
            const total = entrance + accFee - paid;
            document.getElementById('hidden_pax').value = adults + seniors + children;
            document.getElementById('hidden_entrance_fee').value = entrance.toFixed(2);
            document.getElementById('hidden_total_amount').value = total.toFixed(2);
            document.getElementById('display_total').innerText = total.toFixed(2);
        }

        function calculateEditFees() {
            const adults = parseInt(document.getElementById('edit_adults').value) || 0;
            const seniors = parseInt(document.getElementById('edit_seniors').value) || 0;
            const children = parseInt(document.getElementById('edit_children').value) || 0;
            const accFee = parseFloat(document.getElementById('edit_accommodation_fee').value) || 0;
            const alreadyPaid = parseFloat(document.getElementById('edit_customer_id').dataset.totalPaid) || 0;
            const newPayment = parseFloat(document.getElementById('edit_amount_paid').value) || 0;
            const isOver = document.getElementById('edit_overnight_toggle').checked;
            const entrance = getEntranceFee(adults, seniors, children, isOver);
            const total = entrance + accFee;
            const balance = total - alreadyPaid - newPayment;
            document.getElementById('hidden_edit_pax').value = adults + seniors + children;
            document.getElementById('hidden_edit_entrance_fee').value = entrance.toFixed(2);
            document.getElementById('hidden_edit_total_amount').value = total.toFixed(2);
            document.getElementById('display_edit_total').innerText = balance.toFixed(2);
        }

        // ── Open Edit Modal ───────────────────────────────────────────────────────
        function openEditModal(id, name, contact, type, adults, seniors, children, acc, overnight, entrance, acc_fee, payment, checkIn, notes, total_paid) {
            document.getElementById('edit_customer_id').value = id;
            document.getElementById('edit_check_in_time').value = checkIn;
            document.getElementById('edit_customer_name').value = name;
            document.getElementById('edit_customer_type').value = type;
            document.getElementById('edit_adults').value = adults;
            document.getElementById('edit_seniors').value = seniors;
            document.getElementById('edit_children').value = children;
            document.getElementById('edit_contact_number').value = contact;
            document.getElementById('edit_payment_status').value = payment;
            document.getElementById('edit_notes').value = notes || '';
            document.getElementById('edit_amount_paid').value = '';
            document.getElementById('edit_remarks').value = '';
            document.getElementById('edit_customer_id').dataset.totalPaid = total_paid;
            document.getElementById('edit_overnight_toggle').checked = (overnight === 'Yes');

            // Pre-select accommodations
            const selectedNames = acc ? acc.split(', ').map(s => s.trim()) : [];
            let accTotal = 0;
            document.querySelectorAll('#edit_acc_picker .acc-card').forEach(card => {
                card.dataset.blocked = '0';
                card.classList.remove('blocked-today');
                if (selectedNames.includes(card.dataset.name)) {
                    card.classList.add('selected');
                    accTotal += parseFloat(card.dataset.price) || 0;
                } else {
                    card.classList.remove('selected');
                }
            });
            document.getElementById('edit_hidden_accommodation').value = selectedNames.join(', ');
            document.getElementById('edit_accommodation_fee').value = accTotal.toFixed(2);
            document.getElementById('edit_acc_selected_summary').textContent =
                selectedNames.length ? selectedNames.join(', ') + '  —  ₱' + accTotal.toFixed(2) : 'None selected';

            // Show hint without overriding the saved check-in time
            const lc = selectedNames.map(n => n.toLowerCase());
            const hint = document.getElementById('edit_checkin_hint');
            if (hint) {
                if (lc.some(n => n.startsWith('room')) || lc.some(n => n.startsWith('dormitory')))
                    hint.textContent = '🏠 Room/Dorm — window: 2pm–11am';
                else if (lc.some(n => n.startsWith('cottage')))
                    hint.textContent = '🛖 Cottage — window: 5pm–7am';
                else
                    hint.textContent = selectedNames.length ? '☀️ Daytour — window: 7am–5pm' : '';
            }

            calculateEditFees();
            openModal('editModal');
            refreshEditPicker();
        }

        // ── Calendar ──────────────────────────────────────────────────────────────
        let currentCalendarDate = new Date();
        let globalSelectedDateStr = '';

        function initCalendar() { renderCalendarStructure(); }

        function renderCalendarStructure() {
            const year = currentCalendarDate.getFullYear();
            const month = currentCalendarDate.getMonth();
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            document.getElementById('calendarMonthYear').innerText = months[month] + ' ' + year;

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const grid = document.getElementById('calendarDatesGrid');
            grid.innerHTML = '';

            for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));

            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement('div');
                cell.innerText = day;
                const ds = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                cell.dataset.date = ds;
                cell.style.cssText = 'padding:8px;text-align:center;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;background:#fff;border:1px solid var(--border-color);transition:all 0.2s;';
                if (ds === globalSelectedDateStr) { cell.style.background = 'var(--primary-blue)'; cell.style.color = '#fff'; }
                cell.onmouseenter = () => { if (cell.dataset.date !== globalSelectedDateStr) cell.style.background = '#EFF6FF'; };
                cell.onmouseleave = () => { if (cell.dataset.date !== globalSelectedDateStr) cell.style.background = '#fff'; };
                cell.onclick = () => selectCalendarDate(ds);
                grid.appendChild(cell);
            }
        }

        function changeMonth(dir) { currentCalendarDate.setMonth(currentCalendarDate.getMonth() + dir); renderCalendarStructure(); }

        function selectCalendarDate(dateString) {
            globalSelectedDateStr = dateString;
            renderCalendarStructure();
            document.getElementById('selectedDateLabel').innerText = new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const container = document.getElementById('calendarReservationsContainer');
            container.innerHTML = '<p style="color:var(--text-muted);font-size:14px;">Loading...</p>';
            fetch('funcs/get_calendar_reservations.php?date=' + encodeURIComponent(dateString))
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.length) { container.innerHTML = '<p style="color:var(--text-muted);font-size:14px;font-style:italic;">No entries recorded for this date.</p>'; return; }
                    let html = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--bg-light);"><th style="padding:6px;font-size:11px;">Customer</th><th style="padding:6px;font-size:11px;">Type</th><th style="padding:6px;font-size:11px;">Accomm.</th><th style="padding:6px;font-size:11px;">Pax</th></tr></thead><tbody>';
                    data.forEach(r => {
                        html += '<tr style="border-bottom:1px solid var(--border-color);"><td style="padding:8px;font-weight:600;color:var(--text-dark);">' + r.customer_name + '</td><td style="padding:8px;"><span style="background:#EBF5FF;color:#1E429F;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:bold;">' + r.customer_type + '</span></td><td style="padding:8px;color:var(--text-muted);">' + (r.accommodation || '--') + '</td><td style="padding:8px;text-align:center;">' + r.pax + '</td></tr>';
                    });
                    container.innerHTML = html + '</tbody></table>';
                })
                .catch(() => { container.innerHTML = '<p style="color:var(--danger-red);font-size:14px;">Error reading logs from database.</p>'; });
        }

        window.addEventListener('DOMContentLoaded', () => {
            initCalendar();
            selectCalendarDate(new Date().toISOString().split('T')[0]);
        });
    </script>

</body>

</html>