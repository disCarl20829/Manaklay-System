<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_low_stock') {
        $sql = "SELECT * FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5";
        $result = $conn->query($sql);
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_inventory') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $type = isset($_POST['type']) ? sanitize($_POST['type']) : '';
        
        $sql = "SELECT p.*, c.category_name FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
        }
        
        if (!empty($type)) {
            $sql .= " AND p.product_type = '$type'";
        }
        
        $sql .= " ORDER BY p.stock_quantity ASC";
        
        $result = $conn->query($sql);
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'add_stock') {
        $product_id = sanitize($_POST['product_id']);
        $quantity = sanitize($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            $stock_sql = "SELECT stock_quantity FROM products WHERE product_id = '$product_id'";
            $stock_result = $conn->query($stock_sql);
            $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
            
            $new_stock = $current_stock + $quantity;
            $update_sql = "UPDATE products SET stock_quantity = '$new_stock' WHERE product_id = '$product_id'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating stock');
            }
            
            $trans_sql = "INSERT INTO inventory_transactions (product_id, transaction_type, quantity, notes, user_id) 
                         VALUES ('$product_id', 'in', '$quantity', '$notes', '$user_id')";
            
            if (!$conn->query($trans_sql)) {
                throw new Exception('Error recording transaction');
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock added successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'remove_stock') {
        $product_id = sanitize($_POST['product_id']);
        $quantity = sanitize($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            $stock_sql = "SELECT stock_quantity FROM products WHERE product_id = '$product_id'";
            $stock_result = $conn->query($stock_sql);
            $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
            
            if ($current_stock < $quantity) {
                throw new Exception('Insufficient stock. Available: ' . $current_stock);
            }
            
            $new_stock = $current_stock - $quantity;
            $update_sql = "UPDATE products SET stock_quantity = '$new_stock' WHERE product_id = '$product_id'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating stock');
            }
            
            $trans_sql = "INSERT INTO inventory_transactions (product_id, transaction_type, quantity, notes, user_id) 
                         VALUES ('$product_id', 'out', '$quantity', '$notes', '$user_id')";
            
            if (!$conn->query($trans_sql)) {
                throw new Exception('Error recording transaction');
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock removed successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_transactions') {
        $product_id = isset($_POST['product_id']) ? sanitize($_POST['product_id']) : '';
        
        $sql = "SELECT t.*, p.product_name, u.full_name as user_name 
                FROM inventory_transactions t 
                LEFT JOIN products p ON t.product_id = p.product_id 
                LEFT JOIN users u ON t.user_id = u.user_id";
        
        if (!empty($product_id)) {
            $sql .= " WHERE t.product_id = '$product_id'";
        }
        
        $sql .= " ORDER BY t.transaction_date DESC LIMIT 50";
        
        $result = $conn->query($sql);
        $transactions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $transactions]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
}

$products = $conn->query("SELECT product_id, product_name, stock_quantity FROM products ORDER BY product_name");
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .status-low { background: #fee2e2; color: #dc2626; }
        .status-ok { background: #d1fae5; color: #059669; }
        .btn-icon { background: none; border: none; cursor: pointer; padding: 5px 8px; border-radius: 5px; }
        .btn-icon:hover { background: #f1f5f9; }
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
                <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="showAddStockModal()"><i class="fas fa-plus"></i> Add Stock</button>
                    <button class="btn btn-warning" onclick="showRemoveStockModal()"><i class="fas fa-minus"></i> Remove Stock</button>
                    <button class="btn btn-success" onclick="showNewProductModal()"><i class="fas fa-box"></i> New Product</button>
                </div>
            </div>
            
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchInventory" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterType" class="filter-select">
                    <option value="">All Types</option>
                    <option value="finished">Finished Products</option>
                    <option value="raw_material">Raw Materials</option>
                </select>
                <button class="btn btn-secondary" onclick="loadInventory()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>Product</th><th>Category</th><th>Type</th><th>Current Stock</th><th>Reorder Level</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="inventoryList"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header"><h3><i class="fas fa-history"></i> Recent Transactions</h3></div>
                <div class="card-body">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Quantity</th><th>Notes</th><th>User</th></tr></thead>
                        <tbody id="transactionsList"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals (simplified for brevity - similar structure) -->
    <div id="addStockModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Add Stock</h3><span class="close" onclick="closeAddStockModal()">&times;</span></div><div class="modal-body">...</div></div></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }
        
        function loadInventory() {
            const search = $('#searchInventory').val();
            const type = $('#filterType').val();
            $.ajax({
                url: 'inventory.php',
                type: 'POST',
                data: { action: 'get_inventory', search: search, type: type },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.data.forEach(function(p) {
                            const status = p.stock_quantity <= p.reorder_level ? 'Low Stock' : 'OK';
                            const statusClass = p.stock_quantity <= p.reorder_level ? 'status-low' : 'status-ok';
                            html += `<tr>
                                <td>${escapeHtml(p.product_name)}</td>
                                <td>${escapeHtml(p.category_name || '-')}</td>
                                <td>${p.product_type || 'finished'}</td>
                                <td><strong>${p.stock_quantity}</strong></td>
                                <td>${p.reorder_level}</td>
                                <td><span class="status-badge ${statusClass}">${status}</span></td>
                                <td>
                                    <button class="btn-icon" onclick="showAddStockModal(${p.product_id})"><i class="fas fa-plus-circle"></i></button>
                                    <button class="btn-icon" onclick="showRemoveStockModal(${p.product_id})"><i class="fas fa-minus-circle"></i></button>
                                </td>
                            </tr>`;
                        });
                        $('#inventoryList').html(html || '<tr><td colspan="7">No products found</td></tr>');
                    }
                }
            });
        }
        
        function loadTransactions() {
            $.ajax({
                url: 'inventory.php',
                type: 'POST',
                data: { action: 'get_transactions' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        let html = '';
                        response.data.forEach(function(t) {
                            const date = new Date(t.transaction_date).toLocaleString();
                            html += `<tr>
                                <td>${date}</td>
                                <td>${escapeHtml(t.product_name)}</td>
                                <td><span class="status-badge ${t.transaction_type === 'in' ? 'status-completed' : 'status-pending'}">${t.transaction_type === 'in' ? 'Stock In' : 'Stock Out'}</span></td>
                                <td>${t.quantity}</td>
                                <td>${escapeHtml(t.notes || '-')}</td>
                                <td>${escapeHtml(t.user_name || 'System')}</td>
                            </tr>`;
                        });
                        $('#transactionsList').html(html || '<tr><td colspan="6">No transactions</td></tr>');
                    }
                }
            });
        }
        
        function showAddStockModal(productId = null) {
            alert('Add stock modal - implement with product selection');
        }
        
        function showRemoveStockModal(productId = null) {
            alert('Remove stock modal - implement with product selection');
        }
        
        function showNewProductModal() {
            alert('New product modal - implement with form');
        }
        
        function closeAddStockModal() { $('#addStockModal').hide(); }
        function closeRemoveStockModal() { $('#removeStockModal').hide(); }
        function closeNewProductModal() { $('#newProductModal').hide(); }
        
        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        
        $(document).ready(function() {
            loadInventory();
            loadTransactions();
            $('#searchInventory').on('keyup', function() { loadInventory(); });
            $('#filterType').on('change', function() { loadInventory(); });
        });
    </script>
</body>
</html>