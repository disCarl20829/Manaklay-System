<?php
require_once './config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Low stock alert widget
    if ($_POST['action'] == 'get_low_stock') {
        $sql = "SELECT * FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5";
        $result = $conn->query($sql);
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc())
                $items[] = $row;
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // Main inventory list
    if ($_POST['action'] == 'get_inventory') {
        $search = isset($_POST['search']) ? $_POST['search'] : '';
        $category_id = isset($_POST['category_id']) ? $_POST['category_id'] : '';

        $sql = "SELECT p.*, c.category_name FROM products p
                   LEFT JOIN product_categories c ON p.category_id = c.category_id
                   WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
            $like = "%" . $search . "%";
            $params[] = $like;
            $params[] = $like;
            $types .= "ss";
        }
        if (!empty($category_id)) {
            $sql .= " AND p.category_id = ?";
            $params[] = intval($category_id);
            $types .= "i";
        }
        $sql .= " ORDER BY p.stock_quantity ASC";

        $stmt = $conn->prepare($sql);
        if (!empty($params))
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc())
                $items[] = $row;
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // Add stock (stock in)
    if ($_POST['action'] == 'add_stock') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'] ?? 1;

        if ($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT stock_quantity, unit_price FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row)
                throw new Exception('Product not found.');

            $new_stock = $row['stock_quantity'] + $quantity;
            $unit_price = $row['unit_price'];

            $upd = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
            $upd->bind_param("ii", $new_stock, $product_id);
            if (!$upd->execute())
                throw new Exception('Error updating stock.');

            // Log in product_transactions (positive quantity = stock in)
            $trans = $conn->prepare(
                "INSERT INTO product_transactions (product_id, quantity, unit_price, notes, user_id)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $trans->bind_param("iidsi", $product_id, $quantity, $unit_price, $notes, $user_id);
            if (!$trans->execute())
                throw new Exception('Error recording transaction.');

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock added successfully.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Remove stock (stock out)
    if ($_POST['action'] == 'remove_stock') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'] ?? 1;

        if ($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT stock_quantity, unit_price FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row)
                throw new Exception('Product not found.');
            if ($row['stock_quantity'] < $quantity) {
                throw new Exception('Insufficient stock. Available: ' . $row['stock_quantity']);
            }

            $new_stock = $row['stock_quantity'] - $quantity;
            $unit_price = $row['unit_price'];

            $upd = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
            $upd->bind_param("ii", $new_stock, $product_id);
            if (!$upd->execute())
                throw new Exception('Error updating stock.');

            // Log negative quantity = stock out
            $neg_qty = -$quantity;
            $trans = $conn->prepare(
                "INSERT INTO product_transactions (product_id, quantity, unit_price, notes, user_id)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $trans->bind_param("iidsi", $product_id, $neg_qty, $unit_price, $notes, $user_id);
            if (!$trans->execute())
                throw new Exception('Error recording transaction.');

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock removed successfully.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Create new product (quick-add from Inventory page)
    if ($_POST['action'] == 'create_product') {
        $product_name = sanitize($_POST['product_name']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : 'NULL';
        $stock_quantity = intval($_POST['stock_quantity']);
        $reorder_level = intval($_POST['reorder_level']);
        $description = sanitize($_POST['description']);
        $unit_price = floatval($_POST['unit_price'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);

        if (empty($product_name)) {
            echo json_encode(['success' => false, 'message' => 'Product name is required.']);
            exit;
        }

        $sql = "INSERT INTO products (product_name, category_id, stock_quantity, reorder_level, description, unit_price, cost_price)
                 VALUES ('$product_name', $category_id, '$stock_quantity', '$reorder_level', '$description', '$unit_price', '$cost_price')";

        if ($conn->query($sql)) {
            $product_id = $conn->insert_id;

            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $target_dir = "product_imgs/$product_id/";
                    if (!file_exists($target_dir))
                        mkdir($target_dir, 0777, true);
                    $target_file = $target_dir . "product." . $ext;

                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                        $conn->query("UPDATE products SET image = '$target_file' WHERE product_id = $product_id");
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'New product created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }

    // Transaction history (uses product_transactions table)
    if ($_POST['action'] == 'get_transactions') {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        $sql = "SELECT t.*, p.product_name, u.full_name AS user_name
                FROM product_transactions t
                LEFT JOIN products p ON t.product_id = p.product_id
                LEFT JOIN users u ON t.user_id = u.user_id";

        if ($product_id > 0) {
            $sql .= " WHERE t.product_id = $product_id";
        }
        $sql .= " ORDER BY t.transaction_date DESC LIMIT 50";

        $result = $conn->query($sql);
        $transactions = [];
        if ($result) {
            while ($row = $result->fetch_assoc())
                $transactions[] = $row;
            echo json_encode(['success' => true, 'data' => $transactions]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
}

// Dropdown data for forms
$productsArr = [];
$pq = $conn->query("SELECT product_id, product_name, stock_quantity FROM products ORDER BY product_name ASC");
if ($pq)
    while ($row = $pq->fetch_assoc())
        $productsArr[] = $row;

$categoriesArr = [];
$cq = $conn->query("SELECT * FROM product_categories ORDER BY category_name ASC");
if ($cq)
    while ($row = $cq->fetch_assoc())
        $categoriesArr[] = $row;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting & Inventory System - Inventory</title>
    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-low {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-ok {
            background: #d1fae5;
            color: #059669;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 5px;
            font-size: 1.1rem;
        }

        .btn-icon:hover {
            background: #f1f5f9;
        }

        .product-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }

        .product-thumb-placeholder {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            border-radius: 4px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.45);
            justify-content: center;
            align-items: flex-start;
        }

        .modal-content {
            background: #fefefe;
            margin: 40px auto;
            padding: 0;
            border: 1px solid #e2e8f0;
            width: 94%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: fadeIn .2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #1e293b;
        }

        .modal-header .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #94a3b8;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 14px;
            color: #334155;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

            <div class="content-header">
                <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="showAddStockModal()"><i class="fas fa-plus"></i> Add
                        Stock</button>
                    <button class="btn btn-warning" onclick="showRemoveStockModal()"><i class="fas fa-minus"></i> Remove
                        Stock</button>
                    <button class="btn btn-success" onclick="showNewProductModal()"><i class="fas fa-box"></i> New
                        Product</button>
                    <button class="btn btn-secondary" onclick="showTransactionsModal()"><i class="fas fa-history"></i>
                        History</button>
                </div>
            </div>

            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchInventory" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterCategory" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categoriesArr as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryList">
                        <tr>
                            <td colspan="6" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Product Modal -->
    <div id="newProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Product Record</h3>
                <span class="close" onclick="closeModal('newProductModal')">&times;</span>
            </div>
            <form id="newProductForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_product">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="product_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="product_image" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($categoriesArr as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cost Price (₱)</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Selling Price (₱)</label>
                        <input type="number" step="0.01" name="unit_price" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Initial Stock Level</label>
                        <input type="number" name="stock_quantity" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Reorder Alert Threshold</label>
                        <input type="number" name="reorder_level" class="form-control" value="10" min="0">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('newProductModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Stock Modal -->
    <div id="addStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Stock In Adjustment</h3>
                <span class="close" onclick="closeModal('addStockModal')">&times;</span>
            </div>
            <form id="addStockForm">
                <input type="hidden" name="action" value="add_stock">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Product *</label>
                        <select name="product_id" class="form-control" required>
                            <?php foreach ($productsArr as $p): ?>
                                <option value="<?php echo $p['product_id']; ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?> (Stock:
                                    <?php echo $p['stock_quantity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity to Add *</label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Notes / Remarks</label>
                        <textarea name="notes" class="form-control"
                            placeholder="e.g. Supplier delivery receipt #1234"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('addStockModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Inbound</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove Stock Modal -->
    <div id="removeStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Stock Out Adjustment</h3>
                <span class="close" onclick="closeModal('removeStockModal')">&times;</span>
            </div>
            <form id="removeStockForm">
                <input type="hidden" name="action" value="remove_stock">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Product *</label>
                        <select name="product_id" class="form-control" required>
                            <?php foreach ($productsArr as $p): ?>
                                <option value="<?php echo $p['product_id']; ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?> (Stock:
                                    <?php echo $p['stock_quantity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity to Deduct *</label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Notes / Remarks</label>
                        <textarea name="notes" class="form-control"
                            placeholder="e.g. Damaged or wasted items"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('removeStockModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Process Outbound</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction History Modal -->
    <div id="transactionsModal" class="modal">
        <div class="modal-content" style="max-width: 700px; margin: 40px auto;">
            <div class="modal-header">
                <h3>Stock Transaction History</h3>
                <span class="close" onclick="closeModal('transactionsModal')">&times;</span>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div style="padding: 15px; border-bottom: 1px solid #e2e8f0;">
                    <select id="filterTransProduct" class="form-control" style="max-width: 300px;">
                        <option value="">All Products</option>
                        <?php foreach ($productsArr as $p): ?>
                            <option value="<?php echo $p['product_id']; ?>">
                                <?php echo htmlspecialchars($p['product_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Qty Change</th>
                            <th>Unit Price</th>
                            <th>Notes</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsList">
                        <tr>
                            <td colspan="6" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('transactionsModal')">Close</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }
        function closeModal(id) { $('#' + id).hide(); }
        function showNewProductModal() { $('#newProductForm')[0].reset(); $('#newProductModal').show(); }
        function showAddStockModal() { $('#addStockForm')[0].reset(); $('#addStockModal').show(); }
        function showRemoveStockModal() { $('#removeStockForm')[0].reset(); $('#removeStockModal').show(); }
        function showTransactionsModal() { loadTransactions(); $('#transactionsModal').show(); }

        function loadInventory() {
            const search = $('#searchInventory').val();
            const category_id = $('#filterCategory').val();

            $.ajax({
                url: 'inventory.php', type: 'POST',
                data: { action: 'get_inventory', search, category_id },
                dataType: 'json',
                success: function (response) {
                    if (!response.success) return;
                    let html = '';
                    response.data.forEach(function (item) {
                        const low = parseInt(item.stock_quantity) <= parseInt(item.reorder_level);
                        const badge = low
                            ? '<span class="status-badge status-low">Low Stock</span>'
                            : '<span class="status-badge status-ok">Healthy</span>';
                        const imgTag = (item.image && item.image !== 'default.png')
                            ? `<img src="${item.image}?v=${Date.now()}" class="product-thumbnail" />`
                            : `<div class="product-thumb-placeholder"><i class="fas fa-image"></i></div>`;

                        html += `<tr>
                            <td>${imgTag}</td>
                            <td><strong>${item.product_name}</strong></td>
                            <td>${item.category_name || '—'}</td>
                            <td>${item.stock_quantity}</td>
                            <td>${item.reorder_level}</td>
                            <td>${badge}</td>
                        </tr>`;
                    });
                    $('#inventoryList').html(html || '<tr><td colspan="6" class="text-center">No products found.</td></tr>');
                }
            });
        }

        function loadTransactions() {
            const product_id = $('#filterTransProduct').val();
            $.ajax({
                url: 'inventory.php', type: 'POST',
                data: { action: 'get_transactions', product_id },
                dataType: 'json',
                success: function (response) {
                    if (!response.success) return;
                    let html = '';
                    response.data.forEach(function (t) {
                        const qty = parseInt(t.quantity);
                        const qtyHtml = qty >= 0
                            ? `<span style="color:#059669;">+${qty}</span>`
                            : `<span style="color:#dc2626;">${qty}</span>`;
                        const date = new Date(t.transaction_date).toLocaleString();
                        html += `<tr>
                            <td>${date}</td>
                            <td>${t.product_name || '—'}</td>
                            <td>${qtyHtml}</td>
                            <td>₱${parseFloat(t.unit_price || 0).toFixed(2)}</td>
                            <td>${t.notes || '—'}</td>
                            <td>${t.user_name || '—'}</td>
                        </tr>`;
                    });
                    $('#transactionsList').html(html || '<tr><td colspan="6" class="text-center">No transactions found.</td></tr>');
                }
            });
        }

        // AJAX form submissions
        $('#newProductForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'inventory.php', type: 'POST',
                data: new FormData(this), processData: false, contentType: false,
                dataType: 'json',
                success: function (res) {
                    alert(res.message);
                    if (res.success) { closeModal('newProductModal'); window.location.reload(); }
                }
            });
        });

        $('#addStockForm, #removeStockForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'inventory.php', type: 'POST',
                data: $(this).serialize(), dataType: 'json',
                success: function (res) {
                    alert(res.message);
                    if (res.success) window.location.reload();
                }
            });
        });

        $(document).ready(function () {
            loadInventory();
            $('#searchInventory').on('keyup', loadInventory);
            $('#filterCategory').on('change', loadInventory);
            $('#filterTransProduct').on('change', loadTransactions);
        });
    </script>
</body>

</html>