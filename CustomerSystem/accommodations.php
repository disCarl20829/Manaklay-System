<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$search = $_GET['search'] ?? '';
$query = "SELECT * FROM accommodations";
if (!empty($search)) {
    $query .= " WHERE type LIKE ? OR number LIKE ? OR status LIKE ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query($query);
}
$accommodations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Customer System - Accommodations</title>
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

        /* Container & Table */
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            <a href="logbook.php">Logs</a>
            <a href="payments.php">Payments</a>
            <a href="accommodations.php" class="active">Accommodations</a>
            <a href="reports.php">Reports</a>
            <a href="settings.php">Settings</a>
        </nav>
        <a href="logout.php" class="logout-link">Log out</a>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Accommodations</h1>
        </div>

        <div class="logbook-container">
            <div class="toolbar">
                <form class="search-form" method="GET">
                    <input type="text" name="search" class="search-input" placeholder="Search rooms or cottages..."
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="accommodations.php" class="btn btn-clear">Clear</a>
                    <?php endif; ?>
                </form>
                <button class="btn btn-accent" onclick="openModal('addAccModal')">+ Add Accommodation</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Number</th>
                        <th>Price/Day</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accommodations as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['type']) ?></td>
                            <td><?= htmlspecialchars($row['number']) ?></td>
                            <td>₱<?= htmlspecialchars($row['price_per_day']) ?></td>
                            <td><span class="status-badge"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td><?= htmlspecialchars($row['notes']) ?></td>
                            <td style="display:flex; gap: 5px;">

                                <button class="btn btn-primary" style="padding: 6px 10px; font-size: 12px;"
                                    onclick="openEditAccModal(<?= $row['id'] ?>, '<?= $row['type'] ?>', '<?= $row['number'] ?>', '<?= $row['price_per_day'] ?>', '<?= $row['status'] ?>', '<?= htmlspecialchars($row['notes']) ?>')">Edit</button>

                                <form method="POST" action="actions.php" style="margin:0;"
                                    onsubmit="return confirm('Are you sure you want to completely delete this accommodation?');">
                                    <input type="hidden" name="action" value="delete_acc">
                                    <input type="hidden" name="acc_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-danger"
                                        style="padding: 6px 10px; font-size: 12px;">Delete</button>
                                </form>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="addAccModal" class="modal">
        <div class="modal-content">
            <h3>Add Accommodation</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="add_acc">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="Room">Room</option>
                            <option value="Cottage">Cottage</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Number/Name</label>
                        <input type="text" name="number" required>
                    </div>
                    <div class="form-group">
                        <label>Price Per Day (₱)</label>
                        <input type="number" step="0.01" name="price_per_day" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Open">Open</option>
                            <option value="Out of Service">Out of Service</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Notes</label>
                        <input type="text" name="notes">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" onclick="closeModal('addAccModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editAccModal" class="modal">
        <div class="modal-content">
            <h3>Edit Accommodation</h3>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="edit_acc">
                <input type="hidden" name="acc_id" id="edit_acc_id">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" id="edit_type">
                            <option value="Room">Room</option>
                            <option value="Cottage">Cottage</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Number/Name</label>
                        <input type="text" name="number" id="edit_number" required>
                    </div>
                    <div class="form-group">
                        <label>Price Per Day (₱)</label>
                        <input type="number" step="0.01" name="price_per_day" id="edit_price" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="Open">Open</option>
                            <option value="Reserved">Reserved</option>
                            <option value="Active">Active</option>
                            <option value="Out of Service">Out of Service</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Notes</label>
                        <input type="text" name="notes" id="edit_notes">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-clear" onclick="closeModal('editAccModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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

        function openEditAccModal(id, type, number, price, status, notes) {
            document.getElementById('edit_acc_id').value = id;
            document.getElementById('edit_type').value = type;
            document.getElementById('edit_number').value = number;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_notes').value = notes;

            openModal('editAccModal');
        }
    </script>
</body>

</html>