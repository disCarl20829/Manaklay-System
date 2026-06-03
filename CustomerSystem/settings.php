<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Customer System - Settings</title>
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

        .logbook-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: var(--primary-blue);
        }

        .btn {
            padding: 12px 20px;
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
            <a href="accommodations.php">Accommodations</a>
            <a href="reports.php">Reports</a>
            <a href="settings.php" class="active">Settings</a>
        </nav>
        <a href="logout.php" class="logout-link">Log out</a>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>System Settings</h1>
        </div>

        <div class="logbook-container" style="max-width: 500px;">
            <?php if (isset($_GET['success'])): ?>
                <div
                    style="background: #DEF7EC; color: #03543F; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
                    ✓ Settings updated successfully!
                </div>
            <?php endif; ?>

            <form action="actions.php" method="POST">
                <input type="hidden" name="action" value="update_settings">

                <h3 style="margin-bottom: 20px; color: var(--sidebar-bg);">☀️ Daytour Entrance Fees</h3>
                <div class="form-group">
                    <label>Adult Fee (₱)</label>
                    <input type="number" step="0.01" name="fee_adult_day"
                        value="<?= htmlspecialchars($settings['fee_adult_day'] ?? 50) ?>" required>
                </div>
                <div class="form-group">
                    <label>Child Fee (₱)</label>
                    <input type="number" step="0.01" name="fee_child_day"
                        value="<?= htmlspecialchars($settings['fee_child_day'] ?? 30) ?>" required>
                </div>
                <div class="form-group">
                    <label>Senior Fee (₱)</label>
                    <input type="number" step="0.01" name="fee_senior_day"
                        value="<?= htmlspecialchars($settings['fee_senior_day'] ?? 40) ?>" required>
                </div>

                <hr style="border:none; border-top:1px solid var(--border-color); margin: 24px 0;">

                <h3 style="margin-bottom: 20px; color: var(--sidebar-bg);">🌙 Overnight Entrance Fees</h3>
                <div class="form-group">
                    <label>Adult Fee (₱)</label>
                    <input type="number" step="0.01" name="fee_adult_overnight"
                        value="<?= htmlspecialchars($settings['fee_adult_overnight'] ?? 80) ?>" required>
                </div>
                <div class="form-group">
                    <label>Child Fee (₱)</label>
                    <input type="number" step="0.01" name="fee_child_overnight"
                        value="<?= htmlspecialchars($settings['fee_child_overnight'] ?? 50) ?>" required>
                </div>
                <div class="form-group">
                    <label>Senior Fee (₱)</label>
                    <input type="number" step="0.01" name="fee_senior_overnight"
                        value="<?= htmlspecialchars($settings['fee_senior_overnight'] ?? 70) ?>" required>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </main>
</body>

</html>