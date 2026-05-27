<?php
require_once './config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Low stock products
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Today's transactions
$result = $conn->query("SELECT COUNT(*) as count FROM product_transactions WHERE DATE(transaction_date) = CURDATE()");
$stats['today_transactions'] = $result->fetch_assoc()['count'];

// Today's sales
$result = $conn->query("SELECT COALESCE(SUM(unit_price * quantity), 0) as total FROM product_transactions WHERE DATE(transaction_date) = CURDATE()");
$stats['today_sales'] = $result->fetch_assoc()['total'];

// Monthly sales
$result = $conn->query("SELECT 
    DATE_FORMAT(transaction_date, '%Y-%m') as month,
    COUNT(DISTINCT CONCAT(
            DATE_FORMAT(transaction_date, '%Y-%m-%d %H:%i:%s'),
            '_',
            user_id
        )) as order_count,
    COALESCE(SUM(unit_price * quantity), 0) as total_sales
    FROM product_transactions 
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY month DESC");
$monthly_sales = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting & Inventory System - Dashboard</title>
    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
                <div class="date-display"><?php echo date('F d, Y'); ?></div>
            </div>
            <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['today_transactions']; ?></h3>
                        <p>Today's Transactions</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-details">
                        <h3>₱<?php echo number_format($stats['today_sales'], 2); ?></h3>
                        <p>Today's Sales</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Transactions</h3>
                        <a href="transactions.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Handled By</th>
                                    <th>Product</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="recent-transactions">
                                <tr>
                                    <td colspan="4" style="text-align: center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-circle"></i> Low Stock Alert</h3>
                        <a href="inventory.php" class="btn-link">Manage <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Reorder Level</th>
                                </tr>
                            </thead>
                            <tbody id="low-stock-items">
                                <tr>
                                    <td colspan="3" style="text-align: center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Sales (Last 6 Months)</h3>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" style="width:100%; height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Load recent transactions
        $.ajax({
            url: 'transactions.php',
            type: 'POST',
            data: { action: 'get_transactions' },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    let html = '';
                    response.data.forEach(function (trnsc) {
                        html += `<tr>
                            <td>#${trnsc.transaction_id}</td>
                            <td>${trnsc.full_name || 'Worker'}</td>
                            <td>${trnsc.product_name}</td>
                            <td>₱${parseFloat(trnsc.subtotal).toFixed(2)}</td>
                        </tr>`;
                    });
                    $('#recent-transactions').html(html || '<tr><td colspan="4">No transactions found</td></tr>');
                }
            }
        });

        // Load low stock items
        $.ajax({
            url: 'inventory.php',
            type: 'POST',
            data: { action: 'get_low_stock' },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    let html = '';
                    response.data.forEach(function (product) {
                        html += `<tr>
                            <td>${product.product_name}</td>
                            <td>${product.stock_quantity}</td>
                            <td>${product.reorder_level}</td>
                        </tr>`;
                    });
                    $('#low-stock-items').html(html || '<tr><td colspan="3">No low stock items</td></tr>');
                }
            }
        });

        // Chart
        const monthlyData = <?php echo json_encode($monthly_sales ?: []); ?>;
        if (monthlyData.length > 0) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const reversedData = [...monthlyData].reverse();

            const months = reversedData.map(d => d.month);
            const sales = reversedData.map(d => d.total_sales);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Sales (₱)',
                        data: sales,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true
                    }]
                }
            });
        }
    </script>
</body>

</html>