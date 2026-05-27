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

    /*
    |--------------------------------------------------------------------------
    | GENERATE REPORT
    |--------------------------------------------------------------------------
    */

    if ($_POST['action'] == 'generate_report') {

        $range = sanitize($_POST['range']);

        $timestamp = date('Y-m-d_H-i-s');

        $csv_filename = "report_$timestamp.csv";
        $csv_filepath = $reports_dir . $csv_filename;

        /*
        |--------------------------------------------------------------------------
        | DATE FILTERS
        |--------------------------------------------------------------------------
        */

        switch ($range) {

            case 'Today':

                $whereSales = "DATE(pt.transaction_date) = CURDATE()";
                $whereExpenses = "expense_date = CURDATE()";

                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');

                break;

            case '1 week':

                $whereSales = "pt.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                $whereExpenses = "expense_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";

                $start_date = date('Y-m-d', strtotime('-1 week'));
                $end_date = date('Y-m-d');

                break;

            case '1 month':

                $whereSales = "pt.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                $whereExpenses = "expense_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";

                $start_date = date('Y-m-d', strtotime('-1 month'));
                $end_date = date('Y-m-d');

                break;

            case '6 months':

                $whereSales = "pt.transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
                $whereExpenses = "expense_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";

                $start_date = date('Y-m-d', strtotime('-6 months'));
                $end_date = date('Y-m-d');

                break;

            default:

                $whereSales = "pt.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                $whereExpenses = "expense_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";

                $start_date = date('Y-m-d', strtotime('-1 month'));
                $end_date = date('Y-m-d');
        }

        /*
        |--------------------------------------------------------------------------
        | SALES QUERY
        |--------------------------------------------------------------------------
        */

        $sales_sql = "
            SELECT 
                pt.transaction_id,
                pt.transaction_date,
                p.product_name,
                pt.quantity,
                pt.unit_price,
                (pt.quantity * pt.unit_price) AS subtotal
            FROM product_transactions pt
            LEFT JOIN products p 
                ON pt.product_id = p.product_id
            WHERE $whereSales
            ORDER BY pt.transaction_date DESC
        ";

        $sales_res = $conn->query($sales_sql);

        $sales = [];
        $total_sales = 0;

        while ($row = $sales_res->fetch_assoc()) {

            $sales[] = $row;

            $total_sales += $row['subtotal'];
        }

        /*
        |--------------------------------------------------------------------------
        | EXPENSES QUERY
        |--------------------------------------------------------------------------
        */

        $exp_sql = "
            SELECT *
            FROM expenses
            WHERE $whereExpenses
            ORDER BY expense_date DESC
        ";

        $exp_res = $conn->query($exp_sql);

        $expenses = [];
        $total_expenses = 0;

        while ($row = $exp_res->fetch_assoc()) {

            $expenses[] = $row;

            $total_expenses += $row['amount'];
        }

        /*
        |--------------------------------------------------------------------------
        | CSV CONTENT
        |--------------------------------------------------------------------------
        */

        $csv_content = "";

        /*
        |--------------------------------------------------------------------------
        | SALES SECTION
        |--------------------------------------------------------------------------
        */

        $csv_content .= "SALES REPORT\n";
        $csv_content .= "Transaction ID,Date,Product,Quantity,Unit Price,Subtotal\n";

        foreach ($sales as $s) {

            $csv_content .=
                "{$s['transaction_id']}," .
                "{$s['transaction_date']}," .
                "\"{$s['product_name']}\"," .
                "{$s['quantity']}," .
                "{$s['unit_price']}," .
                "{$s['subtotal']}\n";
        }

        $csv_content .= "\nTOTAL SALES:,{$total_sales}\n\n";

        /*
        |--------------------------------------------------------------------------
        | EXPENSES SECTION
        |--------------------------------------------------------------------------
        */

        $csv_content .= "EXPENSES REPORT\n";
        $csv_content .= "Date,Category,Description,Amount,Reference,Payment Method\n";

        foreach ($expenses as $e) {

            $csv_content .=
                "{$e['expense_date']}," .
                "\"{$e['category']}\"," .
                "\"{$e['description']}\"," .
                "{$e['amount']}," .
                "\"{$e['reference']}\"," .
                "\"{$e['payment_method']}\"\n";
        }

        $csv_content .= "\nTOTAL EXPENSES:,{$total_expenses}\n";

        /*
        |--------------------------------------------------------------------------
        | NET INCOME
        |--------------------------------------------------------------------------
        */

        $net_income = $total_sales - $total_expenses;

        $csv_content .= "\nNET INCOME:,{$net_income}\n";

        /*
        |--------------------------------------------------------------------------
        | SAVE CSV
        |--------------------------------------------------------------------------
        */

        file_put_contents($csv_filepath, $csv_content);

        /*
        |--------------------------------------------------------------------------
        | SAVE REPORT RECORD
        |--------------------------------------------------------------------------
        */

        $relative_path = "reports/" . $csv_filename;

        $report_name = "Sales & Expenses Report ($range)";

        $stmt = $conn->prepare("
            INSERT INTO reports 
            (report_name, file_path, start_date, end_date)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $report_name,
            $relative_path,
            $start_date,
            $end_date
        );

        $stmt->execute();

        echo json_encode([
            'success' => true,
            'file_path' => $relative_path
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORTS
    |--------------------------------------------------------------------------
    */

    if ($_POST['action'] == 'get_reports') {

        $search = isset($_POST['search'])
            ? sanitize($_POST['search'])
            : '';

        $sql = "
            SELECT *
            FROM reports
            WHERE report_name LIKE '%$search%'
            OR created_at LIKE '%$search%'
            ORDER BY created_at DESC
        ";

        $result = $conn->query($sql);

        $reports = [];

        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $reports
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE REPORT
    |--------------------------------------------------------------------------
    */

    if ($_POST['action'] == 'delete_report') {

        $id = intval($_POST['report_id']);

        $file = sanitize($_POST['file_path']);

        $full_path = __DIR__ . '/' . $file;

        if (file_exists($full_path)) {
            unlink($full_path);
        }

        $conn->query("
            DELETE FROM reports 
            WHERE report_id = '$id'
        ");

        echo json_encode([
            'success' => true
        ]);

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Accounting & Inventory System - Reports</title>

    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">

    <link rel="stylesheet" href="css/style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>

<body>

    <div class="dashboard-container">

        <?php include 'sidebar.php'; ?>

        <div class="main-content">

            <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="content-header">

                <h1>
                    <i class="fas fa-file-invoice"></i>
                    Reports Management
                </h1>

                <div>

                    <select id="reportRange">

                        <option value="Today">Today</option>

                        <option value="1 week">1 Week</option>

                        <option value="1 month" selected>1 Month</option>

                        <option value="6 months">6 Months</option>

                    </select>

                    <button class="btn btn-primary" onclick="generateReport()">

                        <i class="fas fa-cog"></i>

                        Generate Report

                    </button>

                </div>

            </div>

            <div class="filters-bar">

                <div class="search-box">

                    <input type="text" id="searchReport" placeholder="Search reports...">

                    <i class="fas fa-search"></i>

                </div>

                <button class="btn btn-secondary" onclick="loadReports()">

                    <i class="fas fa-sync-alt"></i>

                    Refresh

                </button>

            </div>

            <div class="table-container">

                <table class="table">

                    <thead>

                        <tr>
                            <th>Date Generated</th>
                            <th>Report Name</th>
                            <th>Range</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody id="reportsList">

                        <tr>
                            <td colspan="4" class="text-center">
                                Loading...
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>

        function toggleSidebar() {

            document
                .querySelector('.sidebar')
                .classList
                .toggle('open');
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD REPORTS
        |--------------------------------------------------------------------------
        */

        function loadReports() {

            const search = $('#searchReport').val();

            $.post(
                'reports.php',
                {
                    action: 'get_reports',
                    search: search
                },
                function (response) {

                    if (response.success) {

                        let html = '';

                        response.data.forEach(report => {

                            html += `
                                <tr>

                                    <td>${report.created_at}</td>

                                    <td>${report.report_name}</td>

                                    <td>
                                        ${report.start_date}
                                        to
                                        ${report.end_date}
                                    </td>

                                    <td>

                                        <a href="${report.file_path}"
                                            class="btn btn-sm btn-secondary"
                                            download>

                                            <i class="fas fa-download"></i>

                                            Download

                                        </a>

                                        <button
                                            class="btn btn-sm btn-danger"
                                            onclick="deleteReport(${report.report_id}, '${report.file_path}')">

                                            <i class="fas fa-trash"></i>

                                            Delete

                                        </button>

                                    </td>

                                </tr>
                            `;
                        });

                        $('#reportsList').html(
                            html ||
                            '<tr><td colspan="4">No reports found</td></tr>'
                        );
                    }

                },
                'json'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE REPORT
        |--------------------------------------------------------------------------
        */

        function generateReport() {

            const range = $('#reportRange').val();

            const btn = $('.btn-primary');

            btn.prop('disabled', true);

            btn.html(
                '<i class="fas fa-spinner fa-spin"></i> Generating...'
            );

            $.ajax({

                url: 'reports.php',

                type: 'POST',

                data: {
                    action: 'generate_report',
                    range: range
                },

                dataType: 'json',

                success: function (response) {

                    if (response.success) {

                        alert('Report generated successfully!');

                        loadReports();

                    } else {

                        alert('Error: ' + response.message);
                    }
                },

                complete: function () {

                    btn.prop('disabled', false);

                    btn.html(
                        '<i class="fas fa-cog"></i> Generate Report'
                    );
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE REPORT
        |--------------------------------------------------------------------------
        */

        function deleteReport(id, path) {

            if (confirm('Delete this report?')) {

                $.post(
                    'reports.php',
                    {
                        action: 'delete_report',
                        report_id: id,
                        file_path: path
                    },
                    function (response) {

                        if (response.success) {

                            loadReports();

                            alert('Report deleted');
                        }

                    },
                    'json'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INIT
        |--------------------------------------------------------------------------
        */

        $(document).ready(function () {

            loadReports();

            $('#searchReport').on(
                'keyup',
                function () {
                    loadReports();
                }
            );
        });

    </script>

</body>

</html>