<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'db.php';

// Handle Search Query for Payments
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM customer_logs";
$params = [];

if (!empty($search)) {
    $query .= " WHERE customer_name LIKE ? OR accommodation LIKE ? OR payment_status LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY check_in_time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calculate Financial KPIs ---
$totalRevenue = 0;
$fullPaymentsCount = 0;
$partialPaymentsCount = 0;

foreach ($payments as $pay) {
    $totalRevenue += $pay['total_amount'];
    if ($pay['payment_status'] === 'Full')
        $fullPaymentsCount++;
    if ($pay['payment_status'] === 'Partial')
        $partialPaymentsCount++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manaklay Resort - Payments</title>
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
            grid-template-columns: repeat(3, 1fr);
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

        .status-full {
            background-color: #DEF7EC;
            color: #03543F;
        }

        .status-partial {
            background-color: #FEF3C7;
            color: #92400E;
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

        .modal input:readonly {
            background-color: #E5E7EB;
            color: var(--text-muted);
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
            <a href="logbook.php" >Logs</a>
            <a href="payments.php" class="active">Payments</a>
            <a href="accommodations.php">Accommodations</a>            
            <a href="reports.php">Reports</a>
            <a href="settings.php">Settings</a>
        </nav>
        <a href="logout.php" class="logout-link">Log out</a>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Payment Records</h1>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Gross Expected Revenue</div>
                <div class="kpi-value" style="color: var(--success-green)">
                    ₱<?= number_format($totalRevenue, 2) ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Full Payments</div>
                <div class="kpi-value"><?= $fullPaymentsCount ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Partial Payments</div>
                <div class="kpi-value" style="color: var(--accent-orange)"><?= $partialPaymentsCount ?></div>
            </div>
        </div>

        <div class="logbook-container">
            <div class="toolbar">
                <form class="search-form" method="GET" action="payments.php">
                    <input type="text" name="search" class="search-input"
                        placeholder="Search customer, accommodation or status..."
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="payments.php" class="btn btn-clear">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Accommodation</th>
                        <th>Entrance Fee</th>
                        <th>Accommodation Fee</th>
                        <th>Total Cost</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $row): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-dark);">
                                    <?= htmlspecialchars($row['customer_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['accommodation']) ?></td>
                                <td>₱<?= number_format($row['entrance_fee'], 2) ?></td>
                                <td>₱<?= number_format($row['accommodation_fee'], 2) ?></td>
                                <td style="font-weight: 600; color: var(--primary-blue);">
                                    ₱<?= number_format($row['total_amount'], 2) ?></td>
                                <td>
                                    <?php if ($row['payment_status'] === 'Full'): ?>
                                        <span class="status-badge status-full">Full Payment</span>
                                    <?php else: ?>
                                        <span class="status-badge status-partial">Partial Payment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary"
                                        style="padding: 6px 10px; font-size: 12px; background-color: var(--accent-orange); color: var(--sidebar-bg);"
                                        onclick="openPaymentModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['customer_name'])) ?>', <?= $row['entrance_fee'] ?>,  <?= $row['accommodation_fee'] ?>, '<?= $row['payment_status'] ?>')">
                                        Update Bill
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 30px; color: var(--text-muted);">No payment
                                records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Update Bill Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <h3>Update Customer Bill</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="customer_id" id="pay_customer_id">

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Customer Name</label>
                        <input type="text" id="pay_customer_name" readonly>
                    </div>
                    <div class="form-group">
                        <label>Entrance Fee (₱)</label>
                        <input type="number" step="0.01" name="entrance_fee" id="pay_entrance_fee"
                            oninput="calculateTotal()" required>
                    </div>
                    <div class="form-group">
                        <label>Accommodation Fee (₱)</label>
                        <input type="number" step="0.01" name="accommodation_fee" id="pay_accommodation_fee"
                            oninput="calculateTotal()" required>
                    </div>
                    <div class="form-group">
                        <label>Total Bill Amount (₱)</label>
                        <input type="text" id="pay_total_amount" readonly>
                    </div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" id="pay_payment_status">
                            <option value="Partial">Partial</option>
                            <option value="Full">Full Payment</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" style="background:none; color: var(--text-muted);"
                        onclick="closeModal('paymentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Invoice</button>
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

        function openPaymentModal(id, name, entrance, accommodation, status) {
            document.getElementById('pay_customer_id').value = id;
            document.getElementById('pay_customer_name').value = name;
            document.getElementById('pay_entrance_fee').value = entrance;
            document.getElementById('pay_accommodation_fee').value = accommodation;
            document.getElementById('pay_payment_status').value = status;

            calculateTotal();
            openModal('paymentModal');
        }

        function calculateTotal() {
            const entrance = parseFloat(document.getElementById('pay_entrance_fee').value) || 0;
            const accommodation = parseFloat(document.getElementById('pay_accommodation_fee').value) || 0;
            const total = entrance + accommodation;
            document.getElementById('pay_total_amount').value = total.toFixed(2);
        }
    </script>
</body>

</html>