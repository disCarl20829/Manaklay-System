<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'db.php';
date_default_timezone_set('Asia/Manila');

// Create reports directory if it doesn't exist
$reports_dir = __DIR__ . '/reports';
if (!is_dir($reports_dir)) {
    mkdir($reports_dir, 0777, true);
}

// Handle Report Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $selected_date = $_POST['selected_date'];

    // Determine Time Frame Boundaries
    if ($report_type === 'day') {
        $start_time = $selected_date . " 00:00:00";
        $end_time = $selected_date . " 23:59:59";
    } elseif ($report_type === 'week') {
        // Find the Monday and Sunday of the selected date's week
        $start_time = date('Y-m-d 00:00:00', strtotime('monday this week', strtotime($selected_date)));
        $end_time = date('Y-m-d 23:59:59', strtotime('sunday this week', strtotime($selected_date)));
    } elseif ($report_type === 'month') {
        $start_time = date('Y-m-01 00:00:00', strtotime($selected_date));
        $end_time = date('Y-m-t 23:59:59', strtotime($selected_date));
    }

    // --- REPLACES THE PREVIOUS CSV GENERATION BLOCK IN reports.php ---

    // Fetch Data
    $stmt = $pdo->prepare("SELECT customer_name, check_in_time, check_out_time, pax, adults, seniors, children, accommodation, overnight, payment_status, total_amount FROM customer_logs WHERE check_in_time BETWEEN ? AND ? ORDER BY check_in_time ASC");
    $stmt->execute([$start_time, $end_time]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) > 0) {
        $filename = "Manaklay_" . ucfirst($report_type) . "_Report_" . date('Ymd_His') . ".xlsx";
        $filepath = $reports_dir . '/' . $filename;

        // 1. Create a temporary JSON file for Python to read
        $temp_json = $reports_dir . '/temp_data_' . time() . '.json';
        file_put_contents($temp_json, json_encode($logs));

        // 2. Prepare dates for the Excel Title
        $display_start = date('M d, Y', strtotime($start_time));
        $display_end = date('M d, Y', strtotime($end_time));

        // 3. Execute the Python script
        // Change this to your exact Python path if 'python' doesn't work (see Step 2 below)
        $python_cmd = "python";
        $script_path = __DIR__ . "/generate_excel.py";

        // Added 2>&1 to capture standard errors from Python
        $cmd = escapeshellcmd("$python_cmd \"$script_path\" \"$temp_json\" \"$filepath\" \"$report_type\" \"$display_start\" \"$display_end\"") . " 2>&1";
        $output = shell_exec($cmd);

        // NEW DEBUGGING BLOCK: Check if file was actually created
        if (!file_exists($filepath)) {
            echo "<div style='padding:20px; background:#FEE2E2; border:1px solid #991B1B; color:#991B1B; font-family:sans-serif;'>";
            echo "<h2>Excel Generation Failed!</h2>";
            echo "<p><strong>Python Output / Error:</strong></p>";
            echo "<pre style='background:#fff; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($output) . "</pre>";
            echo "<p><strong>Executed Command:</strong><br><small>$cmd</small></p>";
            echo "</div>";
            exit; // Stop the script here so you can read the error
        }

        // 4. Clean up the temporary JSON file (Moved after our error check)
        if (file_exists($temp_json)) {
            unlink($temp_json);
        }

        // 5. Save Record to Database
        $stmt = $pdo->prepare("INSERT INTO report_history (filename, report_type, start_date, end_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$filename, $report_type, date('Y-m-d', strtotime($start_time)), date('Y-m-d', strtotime($end_time))]);

        header("Location: reports.php?success=1");
        exit;
    } else {
        $error = "No data found for the selected time frame.";
    }
}

// Fetch Report History
$history_stmt = $pdo->query("SELECT * FROM report_history ORDER BY generated_at DESC");
$reports_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer System - Reports</title>
    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">
    <style>
        /* Import existing styles from previous pages */
        :root {
            --sidebar-bg: #0A192F;
            --primary-blue: #1E3A8A;
            --accent-orange: #F59E0B;
            --bg-light: #F4F7F6;
            --card-bg: #FFFFFF;
            --text-dark: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
            --success-green: #10B981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
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
        }

        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .card-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .form-grid {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
            width: 200px;
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

        .btn-primary {
            background-color: var(--primary-blue);
        }

        .btn-success {
            background-color: var(--success-green);
            text-decoration: none;
            display: inline-block;
            padding: 6px 12px;
            font-size: 13px;
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
        }

        tbody tr:hover {
            background-color: #F9FAFB;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #DEF7EC;
            color: #03543F;
        }

        .alert-error {
            background-color: #FEE2E2;
            color: #991B1B;
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-orange)" stroke-width="2">
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
            <a href="reports.php" class="active">Reports</a>
            <a href="settings.php">Settings</a>
        </nav>
        <a href="logout.php" class="logout-link">Log out</a>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Generate Reports</h1>
            <p style="color: var(--text-muted); margin-top: 5px;">Export financial and demographic records to Excel
                (CSV).</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Report successfully generated and saved.</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="card-container">
            <form method="POST" action="reports.php" class="form-grid">
                <input type="hidden" name="generate_report" value="1">

                <div class="form-group">
                    <label>Time Frame Filter</label>
                    <select name="report_type" required>
                        <option value="day">Daily Data</option>
                        <option value="week">Weekly Data</option>
                        <option value="month">Monthly Data</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Date</label>
                    <input type="date" name="selected_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Generate Excel Sheet</button>
                </div>
            </form>
        </div>

        <div class="card-container">
            <h3 style="margin-bottom: 20px; color: var(--sidebar-bg);">Report History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date Generated</th>
                        <th>Report Type</th>
                        <th>Data Range</th>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reports_history) > 0): ?>
                        <?php foreach ($reports_history as $report): ?>
                            <tr>
                                <td><?= date('M d, Y h:i A', strtotime($report['generated_at'])) ?></td>
                                <td><strong style="text-transform: capitalize;"><?= $report['report_type'] ?></strong></td>
                                <td><?= date('M d, Y', strtotime($report['start_date'])) ?> to
                                    <?= date('M d, Y', strtotime($report['end_date'])) ?>
                                </td>
                                <td style="color: var(--text-muted);"><?= $report['filename'] ?></td>
                                <td>
                                    <a href="reports/<?= $report['filename'] ?>" download class="btn btn-success">Download
                                        Excel</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 20px; color: var(--text-muted);">No reports
                                generated yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>

</html>