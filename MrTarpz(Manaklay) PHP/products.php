<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_products') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $category = isset($_POST['category']) ? sanitize($_POST['category']) : '';
        
        $sql = "SELECT p.*, c.category_name FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
        }
        if (!empty($category)) {
            $sql .= " AND p.category_id = '$category'";
        }
        $sql .= " ORDER BY p.product_id DESC";
        
        $result = $conn->query($sql);
        $products = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $products]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'add_product') {
        $category_id = !empty($_POST['category_id']) ? sanitize($_POST['category_id']) : 'NULL';
        $product_name = sanitize($_POST['product_name']);
        $description = sanitize($_POST['description']);
        $unit_price = sanitize($_POST['unit_price']);
        $cost_price = sanitize($_POST['cost_price']);
        $stock_quantity = sanitize($_POST['stock_quantity']);
        $reorder_level = sanitize($_POST['reorder_level']);
        $product_type = sanitize($_POST['product_type']);
        
        $sql = "INSERT INTO products (category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level, product_type) 
                VALUES ($category_id, '$product_name', '$description', '$unit_price', '$cost_price', '$stock_quantity', '$reorder_level', '$product_type')";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product added']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'update_product') {
        $product_id = sanitize($_POST['product_id']);
        $category_id = !empty($_POST['category_id']) ? sanitize($_POST['category_id']) : 'NULL';
        $product_name = sanitize($_POST['product_name']);
        $description = sanitize($_POST['description']);
        $unit_price = sanitize($_POST['unit_price']);
        $cost_price = sanitize($_POST['cost_price']);
        $stock_quantity = sanitize($_POST['stock_quantity']);
        $reorder_level = sanitize($_POST['reorder_level']);
        $product_type = sanitize($_POST['product_type']);
        
        $sql = "UPDATE products SET category_id=$category_id, product_name='$product_name', description='$description', 
                unit_price='$unit_price', cost_price='$cost_price', stock_quantity='$stock_quantity', 
                reorder_level='$reorder_level', product_type='$product_type' WHERE product_id='$product_id'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product updated']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_product') {
        $product_id = sanitize($_POST['product_id']);
        $check_sql = "SELECT COUNT(*) as count FROM order_items WHERE product_id = '$product_id'";
        $check_result = $conn->query($check_sql);
        $count = $check_result->fetch_assoc()['count'];
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete - product has orders']);
            exit;
        }
        
        $sql = "DELETE FROM products WHERE product_id = '$product_id'";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_categories') {
        $result = $conn->query("SELECT * FROM categories ORDER BY category_name");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $categories]);
        exit;
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            
            <div class="content-header">
                <h1><i class="fas fa-box"></i> Products Management</h1>
                <button class="btn btn-primary" onclick="showAddProductModal()"><i class="fas fa-plus"></i> Add Product</button>
            </div>
            
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchProduct" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterCategory" class="filter-select">
                    <option value="">All Categories</option>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <button class="btn btn-secondary" onclick="loadProducts()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>ID</th><th>Product Name</th><th>Category</th><th>Selling Price</th><th>Cost Price</th><th>Stock</th><th>Type</th><th>Actions</th></tr></thead>
                    <tbody id="productsList"><tr><td colspan="8" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }
        
        function loadProducts() {
            const search = $('#searchProduct').val();
            const category = $('#filterCategory').val();
            $.ajax({
                url: 'products.php',
                type: 'POST',
                data: { action: 'get_products', search: search, category: category },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.data.forEach(function(p) {
                            html += `<tr>
                                <td>${p.product_id}</td>
                                <td>${escapeHtml(p.product_name)}</td>
                                <td>${escapeHtml(p.category_name || '-')}</td>
                                <td>₱${parseFloat(p.unit_price).toFixed(2)}</td>
                                <td>₱${parseFloat(p.cost_price).toFixed(2)}</td>
                                <td>${p.stock_quantity}</td>
                                <td>${p.product_type || 'finished'}</td>
                                <td>
                                    <button class="btn-icon" onclick="editProduct(${p.product_id})"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon delete" onclick="deleteProduct(${p.product_id})"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>`;
                        });
                        $('#productsList').html(html || '<tr><td colspan="8">No products found</td></tr>');
                    }
                }
            });
        }
        
        function showAddProductModal() { alert('Add product form would open here'); }
        function editProduct(id) { alert('Edit product ' + id); }
        function deleteProduct(id) { if(confirm('Delete this product?')) { /* delete logic */ } }
        function escapeHtml(text) { if(!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        
        $(document).ready(function() {
            loadProducts();
            $('#searchProduct').on('keyup', function() { loadProducts(); });
            $('#filterCategory').on('change', function() { loadProducts(); });
        });
    </script>
</body>
</html>