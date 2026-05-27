<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Fetch Settings for JS calculation
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$settingsJson = json_encode($settings);

// Fetch Accommodations for dropdown
$accStmt = $pdo->query("SELECT type, number, price_per_day FROM accommodations WHERE status = 'Open' ORDER BY type, number");
$accommodationsList = $accStmt->fetchAll(PDO::FETCH_ASSOC);

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

        /* Sidebar Styling */
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

        /* Main Content Styling */
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

        /* KPI Cards */
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

        /* Table Container */
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

        /* Modal Styles */
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
        .modal select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
        }

        .modal input:focus,
        .modal select:focus {
            border-color: var(--primary-blue);
        }

        .modal-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
                <div class="kpi-value">
                    <?= $totalCustomers ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Currently Checked In</div>
                <div class="kpi-value" style="color: var(--accent-orange)">
                    <?= $currentlyCheckedIn ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Walk-ins</div>
                <div class="kpi-value">
                    <?= $walkIns ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Reservations</div>
                <div class="kpi-value">
                    <?= $reservations ?>
                </div>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $row): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-dark);">
                                    <?= htmlspecialchars($row['customer_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['pax']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['customer_type']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['accommodation']) ?>
                                </td>
                                <td>
                                    <?php if (!$row['check_out_time']): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-closed">Checked Out</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('M d, y g:i A', strtotime($row['check_in_time'])) ?>
                                </td>
                                <td>
                                    <?= $row['check_out_time'] ? date('M d, y g:i A', strtotime($row['check_out_time'])) : '--' ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['contact_number']) ?>
                                </td>
                                <td style="display:flex; gap: 5px;">
                                    <?php if (!$row['check_out_time']): ?>
                                        <button class="btn btn-success" style="padding: 6px 10px; font-size: 12px;"
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
    '<?= date('Y-m-d\TH:i', strtotime($row['check_in_time'])) ?>'
)">Edit</button>
                                    <form method="POST" action="actions.php" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="customer_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;"
                                            onclick="return confirm('Delete this record?');">Del</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding: 30px; color: var(--text-muted);">No records
                                found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="addModal" class="modal">
        <div class="modal-content" style="width: 600px;">
            <h3>Add New Customer</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="add">
                <!-- Hidden inputs to store calculated values for the database -->
                <input type="hidden" name="pax" id="hidden_pax" value="0">
                <input type="hidden" name="entrance_fee" id="hidden_entrance_fee" value="0">
                <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Check In Time</label>
                        <input type="datetime-local" name="check_in_time" value="<?= date('Y-m-d\TH:i') ?>" required>
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
                        <label>Overnight</label>
                        <select name="overnight">
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
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
                        <label>Accommodation</label>
                        <select name="accommodation" id="accommodation_select" onchange="updateAccommodationPrice()"
                            required>
                            <option value="" data-price="0">-- Select Room/Cottage --</option>
                            <?php foreach ($accommodationsList as $acc): ?>
                                <option value="<?= $acc['type'] . ' ' . $acc['number'] ?>"
                                    data-price="<?= htmlspecialchars($acc['price_per_day']) ?>">
                                    <?= $acc['type'] . ' ' . $acc['number'] ?>
                                    (₱<?= number_format($acc['price_per_day'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group"><label>Accommodation Fee (₱)</label><input type="number" step="0.01"
                            name="accommodation_fee" id="accommodation_fee" oninput="calculateFees()"></div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status">
                            <option value="Partial">Partial</option>
                            <option value="Full">Complete</option>
                        </select>
                    </div>

                    <div class="form-group form-group-full"
                        style="background: var(--bg-light); padding: 15px; border-radius: 8px; text-align: right;">
                        <span style="font-size: 14px; color: var(--text-muted);">Total Expected Fees:</span>
                        <strong style="font-size: 24px; color: var(--accent-orange); display: block;">₱<span
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

    <div id="editModal" class="modal">
        <div class="modal-content" style="width: 600px;">
            <h3>Edit Customer</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="customer_id" id="edit_customer_id">

                <!-- Hidden inputs to store calculated values for the database -->
                <input type="hidden" name="pax" id="hidden_edit_pax" value="0">
                <input type="hidden" name="entrance_fee" id="hidden_edit_entrance_fee" value="0">
                <input type="hidden" name="total_amount" id="hidden_edit_total_amount" value="0">

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Check In Time</label>
                        <input type="datetime-local" name="check_in_time" id="edit_check_in_time" required>
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
                        <label>Overnight</label>
                        <select name="overnight" id="edit_overnight">
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>

                    <!-- Unique IDs and an updated oninput function -->
                    <div class="form-group"><label>Adults</label><input type="number" name="adults" id="edit_adults"
                            min="0" oninput="calculateEditFees()"></div>
                    <div class="form-group"><label>Seniors / PWD</label><input type="number" name="seniors"
                            id="edit_seniors" min="0" oninput="calculateEditFees()"></div>
                    <div class="form-group"><label>Children</label><input type="number" name="children"
                            id="edit_children" min="0" oninput="calculateEditFees()"></div>
                    <div class="form-group"><label>Contact</label><input type="text" name="contact_number"
                            id="edit_contact_number" required></div>

                    <div class="form-group form-group-full">
                        <label>Accommodation</label>
                        <select name="accommodation" id="edit_accommodation_select"
                            onchange="updateEditAccommodationPrice()" required>
                            <option value="" data-price="0">-- Select Room/Cottage --</option>
                            <?php foreach ($accommodationsList as $acc): ?>
                                <option value="<?= $acc['type'] . ' ' . $acc['number'] ?>"
                                    data-price="<?= htmlspecialchars($acc['price_per_day']) ?>">
                                    <?= $acc['type'] . ' ' . $acc['number'] ?>
                                    (₱<?= number_format($acc['price_per_day'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group"><label>Accommodation Fee (₱)</label><input type="number" step="0.01"
                            name="accommodation_fee" id="edit_accommodation_fee" oninput="calculateEditFees()"></div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" id="edit_payment_status">
                            <option value="Partial">Partial</option>
                            <option value="Full">Complete</option>
                        </select>
                    </div>

                    <div class="form-group form-group-full"
                        style="background: var(--bg-light); padding: 15px; border-radius: 8px; text-align: right;">
                        <span style="font-size: 14px; color: var(--text-muted);">Total Expected Fees:</span>
                        <strong style="font-size: 24px; color: var(--accent-orange); display: block;">₱<span
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

    <div id="checkoutModal" class="modal">
        <div class="modal-content" style="width: 400px;">
            <h3>Confirm Check Out</h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">The check out time will be
                recorded as right now. Proceed?</p>
            <form id="checkoutForm" method="POST" action="actions.php">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="customer_id" id="checkout_customer_id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" style="background:none; color: var(--text-muted);"
                        onclick="closeModal('checkoutModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Check Out</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openCheckoutModal(customerId) {
            document.getElementById('checkout_customer_id').value = customerId;
            openModal('checkoutModal');
        }

        // Add this or replace your existing edit function at the bottom of logbook.php
        function openEditModal(id, name, contact, type, adults, seniors, children, acc, overnight, entrance, acc_fee, payment, checkIn) {
            // Set the main customer ID hidden input
            document.getElementById('edit_customer_id').value = id;

            // Set text and selection values using the actual HTML IDs
            document.getElementById('edit_check_in_time').value = checkIn;
            document.getElementById('edit_customer_name').value = name;
            document.getElementById('edit_customer_type').value = type;
            document.getElementById('edit_overnight').value = overnight;

            // Set numerical counts
            document.getElementById('edit_adults').value = adults;
            document.getElementById('edit_seniors').value = seniors;
            document.getElementById('edit_children').value = children;
            document.getElementById('edit_contact_number').value = contact;

            // Set accommodation and its fee
            document.getElementById('edit_accommodation_select').value = acc;
            document.getElementById('edit_accommodation_fee').value = acc_fee;
            document.getElementById('edit_payment_status').value = payment;

            // Recalculate fees for the edit modal so total displays correctly on open
            calculateEditFees();

            // Open the correct modal container ID
            openModal('editModal');
        }

        const fees = <?= $settingsJson ?>;

        function calculateFees() {
            // 1. Gather counts and fees
            let adults = parseInt(document.getElementById('adults').value) || 0;
            let seniors = parseInt(document.getElementById('seniors').value) || 0;
            let children = parseInt(document.getElementById('children').value) || 0;
            let accFee = parseFloat(document.getElementById('accommodation_fee').value) || 0;

            // 2. Calculate pax and breakdown totals
            let totalPax = adults + seniors + children;
            let entranceFee = (adults * parseFloat(fees.fee_adult)) +
                (seniors * parseFloat(fees.fee_senior)) +
                (children * parseFloat(fees.fee_child));

            // 3. Calculate final amount
            let totalAmount = entranceFee + accFee;

            // 4. Update hidden inputs for actions.php
            document.getElementById('hidden_pax').value = totalPax;
            document.getElementById('hidden_entrance_fee').value = entranceFee.toFixed(2);
            document.getElementById('hidden_total_amount').value = totalAmount.toFixed(2);

            // 5. Update front-end display
            document.getElementById('display_total').innerText = totalAmount.toFixed(2);
        }

        function calculateEditFees() {
            // 1. Gather counts and fees from the Edit inputs
            let adults = parseInt(document.getElementById('edit_adults').value) || 0;
            let seniors = parseInt(document.getElementById('edit_seniors').value) || 0;
            let children = parseInt(document.getElementById('edit_children').value) || 0;
            let accFee = parseFloat(document.getElementById('edit_accommodation_fee').value) || 0;

            // 2. Calculate pax and breakdown totals
            let totalPax = adults + seniors + children;
            let entranceFee = (adults * parseFloat(fees.fee_adult)) +
                (seniors * parseFloat(fees.fee_senior)) +
                (children * parseFloat(fees.fee_child));

            // 3. Calculate final amount
            let totalAmount = entranceFee + accFee;

            // 4. Update hidden inputs for actions.php
            document.getElementById('hidden_edit_pax').value = totalPax;
            document.getElementById('hidden_edit_entrance_fee').value = entranceFee.toFixed(2);
            document.getElementById('hidden_edit_total_amount').value = totalAmount.toFixed(2);

            // 5. Update front-end display
            document.getElementById('display_edit_total').innerText = totalAmount.toFixed(2);
        }

        function updateEditAccommodationPrice() {
            let select = document.getElementById('edit_accommodation_select');
            let price = select.options[select.selectedIndex].getAttribute('data-price');

            document.getElementById('edit_accommodation_fee').value = price ? parseFloat(price).toFixed(2) : 0;

            // Automatically recalculate total when accommodation changes
            calculateEditFees();
        }

        function updateAccommodationPrice() {
            // Get the selected accommodation option
            let select = document.getElementById('accommodation_select');
            let selectedOption = select.options[select.selectedIndex];

            // Get the price from the data-price attribute
            let price = selectedOption.getAttribute('data-price');

            // Update the accommodation fee input field
            let feeInput = document.getElementById('accommodation_fee');
            if (price && price > 0) {
                feeInput.value = parseFloat(price).toFixed(2);
            } else {
                feeInput.value = '';
            }

            // Trigger the existing calculation to update the total
            calculateFees();
        }
    </script>
</body>

</html>