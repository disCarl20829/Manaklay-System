<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

// Fetch Settings for JS calculation
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$settingsJson = json_encode($settings);

// Fetch ALL accommodations for multi-picker
$accStmt = $pdo->query("SELECT id, type, number, price_per_day, status FROM accommodations ORDER BY type, number");
$accommodationsList = $accStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Date-aware availability ───────────────────────────────────────────────────
// Blocked = accommodation is in an active log where check_in_time is TODAY
// Future reservations (check_in_time > today) do NOT block today's new entries
$blockedStmt = $pdo->query(
    "SELECT DISTINCT accommodation FROM customer_logs
     WHERE check_out_time IS NULL
       AND DATE(check_in_time) = CURDATE()"
);
$blockedRows = $blockedStmt->fetchAll(PDO::FETCH_COLUMN);

// Build a flat set of blocked accommodation names
$blockedAccNames = [];
foreach ($blockedRows as $accString) {
    foreach (explode(', ', $accString) as $name) {
        $blockedAccNames[trim($name)] = true;
    }
}

// Handle Search Query
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM customer_logs";
$params = [];

if (!empty($search)) {
    $query .= " WHERE customer_name LIKE ? OR check_in_time LIKE ? OR accommodation LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY check_in_time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calculate Dashboard KPIs ---
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

        /* Accommodation picker */
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

        /* Blocked today = occupied by an active guest today */
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

        /* Legend */
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

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Total Records</div>
                <div class="kpi-value"><?= $totalCustomers ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Currently Checked In</div>
                <div class="kpi-value" style="color: var(--accent-orange)"><?= $currentlyCheckedIn ?></div>
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
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="logbook.php" class="btn btn-clear">Clear</a>
                    <?php endif; ?>
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
                                <td style="font-weight:600; color:var(--text-dark);">
                                    <?= htmlspecialchars($row['customer_name']) ?></td>
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
                                <td style="max-width:160px; white-space:pre-wrap; font-size:13px; color:var(--text-muted);">
                                    <?= htmlspecialchars($row['notes'] ?? '') ?>
                                </td>
                                <td style="display:flex; gap:5px;">
                                    <?php if (!$row['check_out_time']): ?>
                                        <button class="btn btn-success" style="padding:6px 10px; font-size:12px;"
                                            onclick="openCheckoutModal(<?= $row['id'] ?>)">Out</button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary" onclick="openEditModal(
                                    <?= $row['id'] ?>,
                                    '<?= htmlspecialchars(addslashes($row['customer_name'])) ?>',
                                    '<?= htmlspecialchars(addslashes($row['contact_number'])) ?>',
                                    '<?= $row['customer_type'] ?>',
                                    <?= $row['adults'] ?? 0 ?>,
                                    <?= $row['seniors'] ?? 0 ?>,
                                    <?= $row['children'] ?? 0 ?>,
                                    '<?= htmlspecialchars(addslashes($row['accommodation'])) ?>',
                                    '<?= $row['overnight'] ?>',
                                    <?= $row['entrance_fee'] ?? 0 ?>,
                                    <?= $row['accommodation_fee'] ?? 0 ?>,
                                    '<?= $row['payment_status'] ?>',
                                    '<?= date('Y-m-d\TH:i', strtotime($row['check_in_time'])) ?>',
                                    '<?= htmlspecialchars(addslashes($row['notes'] ?? '')) ?>'
                                )">Edit</button>
                                    <form method="POST" action="actions.php" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="customer_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:6px 10px; font-size:12px;"
                                            onclick="return confirm('Delete this record?');">Del</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center; padding:30px; color:var(--text-muted);">No records
                                found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ══════════════════════════════════════════════════════════
     ADD MODAL
══════════════════════════════════════════════════════════ -->
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
                        <label>Accommodations <span style="color:var(--text-muted); font-weight:400;">(click to select
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
                                    data-base-price="<?= htmlspecialchars($acc['price_per_day']) ?>"
                                    onclick="toggleAccCard(this)"
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
                            placeholder="e.g. 500.00">
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
                        style="background:var(--bg-light); padding:15px; border-radius:8px; text-align:right;">
                        <span style="font-size:14px; color:var(--text-muted);">Total Expected Fees:</span>
                        <strong style="font-size:24px; color:var(--accent-orange); display:block;">₱<span
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

    <!-- ══════════════════════════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════════════════════════ -->
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
                        <label>Accommodations <span style="color:var(--text-muted); font-weight:400;">(click to select
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
                                    data-base-price="<?= htmlspecialchars($acc['price_per_day']) ?>"
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
                                style="color:var(--text-muted); font-weight:400;">(optional)</span></label>
                        <input type="number" name="amount_paid" id="edit_amount_paid" min="0" step="0.01"
                            placeholder="Leave blank to skip">
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
                        style="background:var(--bg-light); padding:15px; border-radius:8px; text-align:right;">
                        <span style="font-size:14px; color:var(--text-muted);">Total Expected Fees:</span>
                        <strong style="font-size:24px; color:var(--accent-orange); display:block;">₱<span
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

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content" style="width:400px;">
            <h3>Confirm Check Out</h3>
            <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">The check out time will be recorded
                as right now. Proceed?</p>
            <form id="checkoutForm" method="POST" action="actions.php">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="customer_id" id="checkout_customer_id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" style="background:none; color:var(--text-muted);"
                        onclick="closeModal('checkoutModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Check Out</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ── PHP data passed to JS ──────────────────────────────────────────────────
        const fees = <?= $settingsJson ?>;

        // blockedAccNames: set of accommodation names occupied by an ACTIVE guest TODAY
        // Used as the server-side baseline; we re-check on date change via AJAX.
        const serverBlockedToday = <?= json_encode(array_keys($blockedAccNames)) ?>;

        // All accommodations with their data (for dynamic re-blocking on date change)
        const allAccommodations = <?= json_encode(array_values(array_map(function ($a) {
            return [
                'name' => $a['type'] . ' ' . $a['number'],
                'price' => (float) $a['price_per_day'],
                'status' => $a['status'],
                'oos' => ($a['status'] === 'Out of Service'),
            ];
        }, $accommodationsList))) ?>;

        const RATES = {
            daytour: {
                adult: parseFloat(fees.fee_adult_day) || 50,
                child: parseFloat(fees.fee_child_day) || 30,
                senior: parseFloat(fees.fee_senior_day) || 40
            },
            overnight: {
                adult: parseFloat(fees.fee_adult_overnight) || 80,
                child: parseFloat(fees.fee_child_overnight) || 50,
                senior: parseFloat(fees.fee_senior_overnight) || 70
            }
        };

        // ── Modal helpers ──────────────────────────────────────────────────────────
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function openCheckoutModal(customerId) {
            document.getElementById('checkout_customer_id').value = customerId;
            openModal('checkoutModal');
        }

        // ── Entrance fee calc ──────────────────────────────────────────────────────
        function getEntranceFee(adults, seniors, children, isOvernight) {
            const r = isOvernight ? RATES.overnight : RATES.daytour;
            return (adults * r.adult) + (seniors * r.senior) + (children * r.child);
        }

        // ── ADD modal: accommodation picker ───────────────────────────────────────

        /**
         * Re-evaluate which cards should be blocked in the ADD picker based on the
         * selected check-in date.  We do a lightweight AJAX call to the server so we
         * can ask "which accommodations are occupied on DATE X?".
         */
        function refreshAddPicker() {
            const dateStr = document.getElementById('add_check_in_time').value;
            if (!dateStr) return;
            const date = dateStr.split('T')[0]; // YYYY-MM-DD

            fetch('get_blocked_accs.php?date=' + encodeURIComponent(date))
                .then(r => r.json())
                .then(blocked => {
                    document.querySelectorAll('#acc_picker .acc-card').forEach(card => {
                        const isOos = card.dataset.oos === '1';
                        const isBlocked = blocked.includes(card.dataset.name) || isOos;
                        card.dataset.blocked = isBlocked ? '1' : '0';
                        card.classList.toggle('blocked-today', isBlocked);
                        // Deselect if now blocked
                        if (isBlocked) card.classList.remove('selected');
                    });
                    syncAccPicker();
                })
                .catch(() => { }); // silently fail — server-side defaults still apply
        }

        function toggleAccCard(card) {
            if (card.dataset.blocked === '1') return;
            card.classList.toggle('selected');
            syncAccPicker();
        }

        function syncAccPicker() {
            const cards = document.querySelectorAll('#acc_picker .acc-card.selected');
            const names = [], prices = [];
            cards.forEach(c => { names.push(c.dataset.name); prices.push(parseFloat(c.dataset.price) || 0); });
            const totalAccFee = prices.reduce((a, b) => a + b, 0);
            document.getElementById('hidden_accommodation').value = names.join(', ');
            document.getElementById('accommodation_fee').value = totalAccFee.toFixed(2);
            document.getElementById('acc_selected_summary').textContent =
                names.length ? names.join(', ') + '  —  ₱' + totalAccFee.toFixed(2) : 'None selected';
            calculateFees();
        }

        // ── EDIT modal: accommodation picker ──────────────────────────────────────

        /**
         * Re-evaluate blocking for the EDIT picker.
         * We exclude the *current record's own* accommodations from blocking
         * (a record being edited should be able to keep its own rooms).
         * The current record ID is stored in edit_customer_id.
         */
        function refreshEditPicker() {
            const dateStr = document.getElementById('edit_check_in_time').value;
            if (!dateStr) return;
            const date = dateStr.split('T')[0];
            const recordId = document.getElementById('edit_customer_id').value;

            fetch('get_blocked_accs.php?date=' + encodeURIComponent(date) + '&exclude_id=' + encodeURIComponent(recordId))
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
            if (card.dataset.blocked === '1') return;
            card.classList.toggle('selected');
            syncEditAccPicker();
        }

        function syncEditAccPicker() {
            const cards = document.querySelectorAll('#edit_acc_picker .acc-card.selected');
            const names = [], prices = [];
            cards.forEach(c => { names.push(c.dataset.name); prices.push(parseFloat(c.dataset.price) || 0); });
            const totalAccFee = prices.reduce((a, b) => a + b, 0);
            document.getElementById('edit_hidden_accommodation').value = names.join(', ');
            document.getElementById('edit_accommodation_fee').value = totalAccFee.toFixed(2);
            document.getElementById('edit_acc_selected_summary').textContent =
                names.length ? names.join(', ') + '  —  ₱' + totalAccFee.toFixed(2) : 'None selected';
            calculateEditFees();
        }

        // ── Fee calculations ───────────────────────────────────────────────────────
        function calculateFees() {
            const adults = parseInt(document.getElementById('adults').value) || 0;
            const seniors = parseInt(document.getElementById('seniors').value) || 0;
            const children = parseInt(document.getElementById('children').value) || 0;
            const accFee = parseFloat(document.getElementById('accommodation_fee').value) || 0;
            const isOver = document.getElementById('overnight_toggle').checked;

            const totalPax = adults + seniors + children;
            const entranceFee = getEntranceFee(adults, seniors, children, isOver);
            const totalAmount = entranceFee + accFee;

            document.getElementById('hidden_pax').value = totalPax;
            document.getElementById('hidden_entrance_fee').value = entranceFee.toFixed(2);
            document.getElementById('hidden_total_amount').value = totalAmount.toFixed(2);
            document.getElementById('display_total').innerText = totalAmount.toFixed(2);
        }

        function calculateEditFees() {
            const adults = parseInt(document.getElementById('edit_adults').value) || 0;
            const seniors = parseInt(document.getElementById('edit_seniors').value) || 0;
            const children = parseInt(document.getElementById('edit_children').value) || 0;
            const accFee = parseFloat(document.getElementById('edit_accommodation_fee').value) || 0;
            const isOver = document.getElementById('edit_overnight_toggle').checked;

            const totalPax = adults + seniors + children;
            const entranceFee = getEntranceFee(adults, seniors, children, isOver);
            const totalAmount = entranceFee + accFee;

            document.getElementById('hidden_edit_pax').value = totalPax;
            document.getElementById('hidden_edit_entrance_fee').value = entranceFee.toFixed(2);
            document.getElementById('hidden_edit_total_amount').value = totalAmount.toFixed(2);
            document.getElementById('display_edit_total').innerText = totalAmount.toFixed(2);
        }

        // ── Open Edit Modal ────────────────────────────────────────────────────────
        function openEditModal(id, name, contact, type, adults, seniors, children, acc, overnight, entrance, acc_fee, payment, checkIn, notes) {
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

            // Set overnight toggle
            document.getElementById('edit_overnight_toggle').checked = (overnight === 'Yes');

            // Pre-select accommodations (all selectable in edit — blocking refreshed below)
            const selectedNames = acc ? acc.split(', ').map(s => s.trim()) : [];
            let totalAccFee = 0;
            document.querySelectorAll('#edit_acc_picker .acc-card').forEach(card => {
                // Reset blocked state first (will be re-applied by refreshEditPicker)
                card.dataset.blocked = '0';
                card.classList.remove('blocked-today');
                if (selectedNames.includes(card.dataset.name)) {
                    card.classList.add('selected');
                    totalAccFee += parseFloat(card.dataset.price) || 0;
                } else {
                    card.classList.remove('selected');
                }
            });
            document.getElementById('edit_hidden_accommodation').value = selectedNames.join(', ');
            document.getElementById('edit_accommodation_fee').value = totalAccFee.toFixed(2);
            document.getElementById('edit_acc_selected_summary').textContent =
                selectedNames.length ? selectedNames.join(', ') + '  —  ₱' + totalAccFee.toFixed(2) : 'None selected';

            calculateEditFees();
            openModal('editModal');

            // Refresh blocking for this record's date (excluding itself)
            refreshEditPicker();
        }
    </script>

</body>

</html>