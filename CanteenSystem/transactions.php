<?php
require_once './config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (isset($_POST['action'])) {

    header('Content-Type: application/json');

    // GET TRANSACTIONS
    if ($_POST['action'] == 'get_transactions') {

        try {

            $search = isset($_POST['search'])
                ? trim($_POST['search'])
                : '';

            $sql = "
                SELECT
                    pt.transaction_id,
                    pt.transaction_date,
                    pt.quantity,
                    pt.unit_price,
                    p.product_name,
                    u.full_name,
                    u.username
                FROM product_transactions pt
                JOIN products p
                    ON pt.product_id = p.product_id
                LEFT JOIN users u
                    ON pt.user_id = u.user_id
                WHERE 1=1
            ";

            $params = [];
            $types = "";

            if (!empty($search)) {

                $sql .= " AND (
                    p.product_name LIKE ?
                    OR pt.transaction_id LIKE ?
                ) ";

                $searchTerm = "%" . $search . "%";

                $params[] = $searchTerm;
                $params[] = $searchTerm;

                $types .= "ss";
            }

            $sql .= "
                ORDER BY pt.transaction_date DESC
                LIMIT 200
            ";

            $stmt = $conn->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();

            $result = $stmt->get_result();

            $transactions = [];

            while ($row = $result->fetch_assoc()) {

                $quantity = intval($row['quantity']);

                $unit_price = floatval($row['unit_price']);

                $subtotal = $quantity * $unit_price;

                $transactions[] = [
                    'transaction_id' => $row['transaction_id'],
                    'date' => $row['transaction_date'],
                    'cashier' => $row['full_name']
                        ?: $row['username']
                        ?: 'Unknown',
                    'product_name' => $row['product_name'],
                    'qty' => $quantity,
                    'price' => $unit_price,
                    'subtotal' => $subtotal
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (Exception $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Accounting & Inventory System - Transactions</title>

    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">

    <link rel="stylesheet" href="css/style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 8px;
            margin: 0 2px;
            border-radius: 5px;
        }

        .btn-icon:hover {
            background: #f1f5f9;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            width: 95%;
            max-width: 700px;
            border-radius: 10px;
            overflow: hidden;
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .close {
            cursor: pointer;
            font-size: 24px;
        }
    </style>

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
                    <i class="fas fa-money-bill-wave"></i>
                    Transactions
                </h1>
            </div>

            <div class="filters-bar">

                <div class="search-box">
                    <input type="text" id="searchTransaction" placeholder="Search transaction or product">
                    <i class="fas fa-search"></i>
                </div>

                <button class="btn btn-secondary" onclick="loadTransactions()">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>

            </div>

            <div class="table-container">

                <table class="table">

                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Cashier</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="transactionsList">
                        <tr>
                            <td colspan="8" class="text-center">
                                Loading transactions...
                            </td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- VIEW MODAL -->
    <div id="transactionModal" class="modal">

        <div class="modal-content">

            <div class="modal-header">

                <h3>Transaction Details</h3>

                <span class="close" onclick="$('#transactionModal').hide()">

                    &times;

                </span>

            </div>

            <div id="transactionDetails" class="modal-body"></div>

        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>

        $(document).ready(function () {

            loadTransactions();

            $('#searchTransaction').on('keyup', function () {
                loadTransactions();
            });

        });

        function toggleSidebar() {

            document
                .querySelector('.sidebar')
                .classList.toggle('open');
        }

        function loadTransactions() {

            $.ajax({

                url: 'transactions.php',

                type: 'POST',

                data: {
                    action: 'get_transactions',
                    search: $('#searchTransaction').val()
                },

                dataType: 'json',

                success: function (response) {

                    if (response.success) {

                        displayTransactions(response.data);

                    } else {

                        $('#transactionsList').html(`
                            <tr>
                                <td colspan="8" class="text-center">
                                    ${response.message}
                                </td>
                            </tr>
                        `);
                    }
                }

            });
        }

        function displayTransactions(transactions) {

            let html = '';

            if (transactions.length > 0) {

                transactions.forEach(function (t) {

                    html += `
                        <tr>

                            <td>
                                #${t.transaction_id}
                            </td>

                            <td>
                                ${new Date(t.date).toLocaleString()}
                            </td>

                            <td>
                                ${escapeHtml(t.cashier)}
                            </td>

                            <td>
                                ${escapeHtml(t.product_name)}
                            </td>

                            <td>
                                ${t.qty}
                            </td>

                            <td>
                                ₱${parseFloat(t.price).toFixed(2)}
                            </td>

                            <td>
                                ₱${parseFloat(t.subtotal).toFixed(2)}
                            </td>

                            <td>

                                <button class="btn-icon"
                                    onclick='viewTransaction(
                                        ${JSON.stringify(t)}
                                    )'>

                                    <i class="fas fa-eye"></i>

                                </button>

                            </td>

                        </tr>
                    `;
                });

            } else {

                html = `
                    <tr>
                        <td colspan="8" class="text-center">
                            No transactions found.
                        </td>
                    </tr>
                `;
            }

            $('#transactionsList').html(html);
        }

        function escapeHtml(text) {

            if (!text) return '';

            return text.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function viewTransaction(t) {

            $('#transactionDetails').html(`

                <div style="
                    background:#f8fafc;
                    padding:16px;
                    border-radius:10px;
                    margin-bottom:20px;
                ">

                    <p>
                        <strong>Transaction ID:</strong>
                        #${t.transaction_id}
                    </p>

                    <p>
                        <strong>Date:</strong>
                        ${new Date(t.date).toLocaleString()}
                    </p>

                    <p>
                        <strong>Cashier:</strong>
                        ${escapeHtml(t.cashier)}
                    </p>

                </div>

                <table class="table">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr>

                            <td>
                                ${escapeHtml(t.product_name)}
                            </td>

                            <td>
                                ${t.qty}
                            </td>

                            <td>
                                ₱${parseFloat(t.price).toFixed(2)}
                            </td>

                            <td>
                                ₱${parseFloat(t.subtotal).toFixed(2)}
                            </td>

                        </tr>

                    </tbody>

                </table>

            `);

            $('#transactionModal').css('display', 'flex');
        }

    </script>

</body>

</html>