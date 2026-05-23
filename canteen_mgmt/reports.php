<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

$reports_dir = __DIR__ . '/reports/';
if (!file_exists($reports_dir)) {
    mkdir($reports_dir, 0777, true);
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'generate_report') {
        $range = sanitize($_POST['range']);
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "report_$timestamp.xlsx";
        $filepath = $reports_dir . $filename;
        
        switch ($range) {
            case 'Today': $filter = "CURDATE()"; break;
            case '1 week': $filter = "DATE_SUB(NOW(), INTERVAL 1 WEEK)"; break;
            case '1 month': $filter = "DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
            case '6 months': $filter = "DATE_SUB(NOW(), INTERVAL 6 MONTH)"; break;
            default: $filter = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
        
        // Get expenses
        $exp_res = $conn->query("SELECT * FROM expenses WHERE expense_date >= $filter");
        $expenses = [];
        $total_expenses = 0;
        while ($row = $exp_res->fetch_assoc()) {
            $expenses[] = $row;
            $total_expenses += $row['amount'];
        }
        
        // Get payments
        $pay_res = $conn->query("SELECT * FROM payments WHERE payment_date >= $filter");
        $payments = [];
        $total_payments = 0;
        while ($row = $pay_res->fetch_assoc()) {
            $payments[] = $row;
            $total_payments += $row['amount'];
        }
        
        // Simple CSV output since Python may not be available
        $csv_content = "EXPENSES REPORT\n";
        $csv_content .= "Date,Category,Description,Amount,Payment Method,Reference,Notes\n";
        foreach ($expenses as $e) {
            $csv_content .= "{$e['expense_date']},{$e['category']},{$e['description']},{$e['amount']},{$e['payment_method']},{$e['reference']},{$e['notes']}\n";
        }
        $csv_content .= "\nTOTAL EXPENSES:,$total_expenses\n\n";
        
        $csv_content .= "PAYMENTS REPORT\n";
        $csv_content .= "Date,Order ID,Amount,Payment Method,Reference\n";
        foreach ($payments as $p) {
            $csv_content .= "{$p['payment_date']},{$p['order_id']},{$p['amount']},{$p['payment_method']},{$p['reference_number']}\n";
        }
        $csv_content .= "\nTOTAL PAYMENTS:,$total_payments\n";
        $csv_content .= "\nNET:," . ($total_payments - $total_expenses) . "\n";
        
        $csv_filename = "report_$timestamp.csv";
        $csv_filepath = $reports_dir . $csv_filename;
        file_put_contents($csv_filepath, $csv_content);
        
        $relative_path = "reports/" . $csv_filename;
        $name = "Financial Report ($range)";
        $stmt = $conn->prepare("INSERT INTO reports (report_name, file_path, date_range) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $relative_path, $range);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'file_path' => $relative_path]);
        exit;
    }
    
    if ($_POST['action'] == 'get_reports') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $sql = "SELECT * FROM reports WHERE report_name LIKE '%$search%' OR created_at LIKE '%$search%' ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $reports]);
        exit;
    }
    
    if ($_POST['action'] == 'delete_report') {
        $id = sanitize($_POST['report_id']);
        $file = sanitize($_POST['file_path']);
        $full_path = __DIR__ . '/' . $file;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        $conn->query("DELETE FROM reports WHERE report_id = '$id'");
        echo json_encode(['success' => true]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            
            <div class="content-header">
                <h1><i class="fas fa-file-invoice"></i> Reports Management</h1>
                <div>
                    <select id="reportRange">
                        <option value="Today">Today</option>
                        <option value="1 week">1 Week</option>
                        <option value="1 month" selected>1 Month</option>
                        <option value="6 months">6 Months</option>
                    </select>
                    <button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-cog"></i> Generate Report</button>
                </div>
            </div>
            
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchReport" placeholder="Search reports...">
                    <i class="fas fa-search"></i>
                </div>
                <button class="btn btn-secondary" onclick="loadReports()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Date Generated</th><th>Report Name</th><th>Range</th><th>Actions</th></tr></thead>
                    <tbody id="reportsList"><tr><td colspan="4" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }
        
        function loadReports() {
            const search = $('#searchReport').val();
            $.post('reports.php', { action: 'get_reports', search: search }, function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(report => {
                        html += `<tr>
                            <td>${report.created_at}</td>
                            <td>${report.report_name}</td>
                            <td>${report.date_range}</td>
                            <td><a href="${report.file_path}" class="btn btn-sm btn-secondary" download><i class="fas fa-download"></i> Download</a>
                                <button class="btn btn-sm btn-danger" onclick="deleteReport(${report.report_id}, '${report.file_path}')"><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        </tr>`;
                    });
                    $('#reportsList').html(html || '<tr><td colspan="4">No reports found</td></tr>');
                }
            }, 'json');
        }
        
        function generateReport() {
            const range = $('#reportRange').val();
            const btn = $('.btn-primary');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');
            
            $.ajax({
                url: 'reports.php',
                type: 'POST',
                data: { action: 'generate_report', range: range },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Report generated successfully!');
                        loadReports();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-cog"></i> Generate Report');
                }
            });
        }
        
        function deleteReport(id, path) {
            if (confirm('Delete this report?')) {
                $.post('reports.php', { action: 'delete_report', report_id: id, file_path: path }, function(response) {
                    if (response.success) {
                        loadReports();
                        alert('Report deleted');
                    }
                }, 'json');
            }
        }
        
        $(document).ready(function() {
            loadReports();
            $('#searchReport').on('keyup', function() { loadReports(); });
        });
    </script>
</body>
</html>