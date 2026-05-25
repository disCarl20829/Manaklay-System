<?php
require_once './config.php';
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
        $search = isset($_POST['search']) ? $_POST['search'] : '';
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        $category_id = isset($_POST['category_id']) ? $_POST['category_id'] : '';
        
        $sql = "SELECT p.*, c.category_name FROM products p 
                LEFT JOIN product_categories c ON p.category_id = c.category_id 
                WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
            $searchParam = "%" . $search . "%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }
        
        if (!empty($type)) {
            $sql .= " AND p.product_type = ?";
            $params[] = $type;
            $types .= "s";
        }

        if (!empty($category_id)) {
            $sql .= " AND p.category_id = ?";
            $params[] = intval($category_id);
            $types .= "i";
        }
        
        $sql .= " ORDER BY p.stock_quantity ASC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
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
        $quantity = intval($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'] ?? 1; 
        
        if($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stock_sql = "SELECT stock_quantity FROM products WHERE product_id = ?";
            $stmt = $conn->prepare($stock_sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stock_result = $stmt->get_result();

            if (!$stock_result || $stock_result->num_rows == 0) {
                throw new Exception('Product not found.');
            }
            $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
            
            $new_stock = $current_stock + $quantity;
            $update_sql = "UPDATE products SET stock_quantity = ? WHERE product_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_stock, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Error updating stock');
            }
            
            $trans_sql = "INSERT INTO inventory_transactions (product_id, transaction_type, quantity, notes, user_id) VALUES (?, 'in', ?, ?, ?)";
            $trans_stmt = $conn->prepare($trans_sql);
            $trans_stmt->bind_param("iisi", $product_id, $quantity, $notes, $user_id);
            
            if (!$trans_stmt->execute()) {
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
        $quantity = intval($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'] ?? 1;
        
        if($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stock_sql = "SELECT stock_quantity FROM products WHERE product_id = ?";
            $stmt = $conn->prepare($stock_sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stock_result = $stmt->get_result();

            if (!$stock_result || $stock_result->num_rows == 0) {
                throw new Exception('Product not found.');
            }
            $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
            
            if ($current_stock < $quantity) {
                throw new Exception('Insufficient stock. Available: ' . $current_stock);
            }
            
            $new_stock = $current_stock - $quantity;
            $update_sql = "UPDATE products SET stock_quantity = ? WHERE product_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_stock, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Error updating stock');
            }
            
            $trans_sql = "INSERT INTO inventory_transactions (product_id, transaction_type, quantity, notes, user_id) VALUES (?, 'out', ?, ?, ?)";
            $trans_stmt = $conn->prepare($trans_sql);
            $trans_stmt->bind_param("iisi", $product_id, $quantity, $notes, $user_id);
            
            if (!$trans_stmt->execute()) {
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
    
    if ($_POST['action'] == 'create_product') {
        $product_name = sanitize($_POST['product_name']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $product_type = sanitize($_POST['product_type']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $reorder_level = intval($_POST['reorder_level']);
        $description = sanitize($_POST['description']);
        
        if(empty($product_name)) {
            echo json_encode(['success' => false, 'message' => 'Product Name is required.']);
            exit;
        }

        $sql = "INSERT INTO products (product_name, category_id, product_type, stock_quantity, reorder_level, description) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisiis", $product_name, $category_id, $product_type, $stock_quantity, $reorder_level, $description);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'New product created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
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

// Fetch lists for forms
$productsArr = [];
$productsQuery = $conn->query("SELECT product_id, product_name, stock_quantity, category_id FROM products ORDER BY product_name ASC");
if($productsQuery) {
    while($row = $productsQuery->fetch_assoc()) { $productsArr[] = $row; }
}

$categoriesArr = [];
$categoriesQuery = $conn->query("SELECT * FROM product_categories ORDER BY category_name ASC");
if($categoriesQuery) {
    while($row = $categoriesQuery->fetch_assoc()) { $categoriesArr[] = $row; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .status-low { background: #fee2e2; color: #dc2626; }
        .status-ok { background: #d1fae5; color: #059669; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-pending { background: #ffedd5; color: #ea580c; }
        .btn-icon { background: none; border: none; cursor: pointer; padding: 5px 8px; border-radius: 5px; font-size: 1.1rem; }
        .btn-icon:hover { background: #f1f5f9; }
        .btn-icon.text-success { color: #10b981; }
        .btn-icon.text-danger { color: #ef4444; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); padding-top: 60px; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #e2e8f0; width: 100%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); overflow: hidden; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.2rem; color: #1e293b; }
        .modal-header .close { font-size: 24px; font-weight: bold; cursor: pointer; color: #94a3b8; }
        .modal-header .close:hover { color: #475569; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: #334155; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border 0.15s ease; }
        .form-control:focus { outline: none; border-color: #3b82f6; }
        textarea.form-control { resize: vertical; min-height: 70px; }
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
                
                <select id="filterCategory" class="filter-select" onchange="loadInventory()">
                    <option value="">All Categories</option>
                    <?php foreach($categoriesArr as $c): ?>
                        <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                    <?php endforeach; ?>
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
    
    <div id="addStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle text-success"></i> Add Stock (Restock In)</h3>
                <span class="close" onclick="closeAddStockModal()">&times;</span>
            </div>
            <form id="formAddStock">
                <input type="hidden" name="action" value="add_stock">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="addStockProduct">Select Product</label>
                        <select name="product_id" id="addStockProduct" class="form-control" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach($productsArr as $p): ?>
                                <option value="<?php echo $p['product_id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Current: <?php echo $p['stock_quantity']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="addStockQty">Quantity to Add</label>
                        <input type="number" name="quantity" id="addStockQty" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="addStockNotes">Transaction Notes / Details</label>
                        <textarea name="notes" id="addStockNotes" class="form-control" placeholder="Supplier invoice, tracking number, restock log..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddStockModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <div id="removeStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-minus-circle text-danger"></i> Remove Stock (Stock Out)</h3>
                <span class="close" onclick="closeRemoveStockModal()">&times;</span>
            </div>
            <form id="formRemoveStock">
                <input type="hidden" name="action" value="remove_stock">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="removeStockProduct">Select Product</label>
                        <select name="product_id" id="removeStockProduct" class="form-control" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach($productsArr as $p): ?>
                                <option value="<?php echo $p['product_id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Current: <?php echo $p['stock_quantity']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="removeStockQty">Quantity to Remove</label>
                        <input type="number" name="quantity" id="removeStockQty" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="removeStockNotes">Reason for Removal</label>
                        <textarea name="notes" id="removeStockNotes" class="form-control" placeholder="Damaged items, production dispatch, expired goods..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRemoveStockModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Pull-out</button>
                </div>
            </form>
        </div>
    </div>

    <div id="newProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-box text-primary"></i> Create New Base Product</h3>
                <span class="close" onclick="closeNewProductModal()">&times;</span>
            </div>
            <form id="formNewProduct">
                <input type="hidden" name="action" value="create_product">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newProdName">Product Name *</label>
                        <input type="text" name="product_name" id="newProdName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="newProdCategory">Category</label>
                        <select name="category_id" id="newProdCategory" class="form-control">
                            <option value="">None / Unspecified</option>
                            <?php foreach($categoriesArr as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="newProdType">Product Type</label>
                        <select name="product_type" id="newProdType" class="form-control">
                            <option value="finished">Finished Product</option>
                            <option value="raw_material">Raw Material</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="newProdStock">Initial Stock Opening Value</label>
                        <input type="number" name="stock_quantity" id="newProdStock" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label for="newProdReorder">Low-Stock Alert Limit (Reorder Threshold)</label>
                        <input type="number" name="reorder_level" id="newProdReorder" class="form-control" value="10" min="0">
                    </div>
                    <div class="form-group">
                        <label for="newProdDesc">Description</label>
                        <textarea name="description" id="newProdDesc" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeNewProductModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }
        
        function loadInventory() {
            const search = $('#searchInventory').val();
            const type = $('#filterType').val();
            const categoryId = $('#filterCategory').val();
            $.ajax({
                url: 'inventory.php',
                type: 'POST',
                data: { action: 'get_inventory', search: search, type: type, category_id: categoryId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.data.forEach(function(p) {
                            const status = p.stock_quantity <= p.reorder_level ? 'Low Stock' : 'OK';
                            const statusClass = p.stock_quantity <= p.reorder_level ? 'status-low' : 'status-ok';
                            html += `<tr>
                                <td>${escapeHtml(p.product_name)}</td>
                                <td>${escapeHtml(p.category_name || 'Unspecified')}</td>
                                <td>${p.product_type || 'finished'}</td>
                                <td><strong>${p.stock_quantity}</strong></td>
                                <td>${p.reorder_level}</td>
                                <td><span class="status-badge ${statusClass}">${status}</span></td>
                                <td>
                                    <button class="btn-icon text-success" onclick="showAddStockModal(${p.product_id})" title="Add Stock"><i class="fas fa-plus-circle"></i></button>
                                    <button class="btn-icon text-danger" onclick="showRemoveStockModal(${p.product_id})" title="Remove Stock"><i class="fas fa-minus-circle"></i></button>
                                </td>
                            </tr>`;
                        });
                        $('#inventoryList').html(html || '<tr><td colspan="7" class="text-center">No products found</td></tr>');
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
                        $('#transactionsList').html(html || '<tr><td colspan="6" class="text-center">No transactions recorded yet</td></tr>');
                    }
                }
            });
        }
        
        function showAddStockModal(productId = null) {
            $('#formAddStock')[0].reset();
            if(productId) { $('#addStockProduct').val(productId); }
            $('#addStockModal').show();
        }
        function closeAddStockModal() { $('#addStockModal').hide(); }
        
        function showRemoveStockModal(productId = null) {
            $('#formRemoveStock')[0].reset();
            if(productId) { $('#removeStockProduct').val(productId); }
            $('#removeStockModal').show();
        }
        function closeRemoveStockModal() { $('#removeStockModal').hide(); }
        
        function showNewProductModal() {
            $('#formNewProduct')[0].reset();
            $('#newProductModal').show();
        }
        function closeNewProductModal() { $('#newProductModal').hide(); }
        
        function escapeHtml(text) { 
            if (!text) return ''; 
            const div = document.createElement('div'); 
            div.textContent = text; 
            return div.innerHTML; 
        }
        
        $(document).ready(function() {
            loadInventory();
            loadTransactions();
            
            $('#searchInventory').on('keyup', function() { loadInventory(); });
            $('#filterType').on('change', function() { loadInventory(); });

            window.onclick = function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            }

            $('#formAddStock').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'inventory.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        alert(res.message);
                        if(res.success) {
                            closeAddStockModal();
                            loadInventory();
                            loadTransactions();
                        }
                    }
                });
            });

            $('#formRemoveStock').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'inventory.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        alert(res.message);
                        if(res.success) {
                            closeRemoveStockModal();
                            loadInventory();
                            loadTransactions();
                        }
                    }
                });
            });

            $('#formNewProduct').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'inventory.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        alert(res.message);
                        if(res.success) {
                            closeNewProductModal();
                            window.location.reload(); 
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>