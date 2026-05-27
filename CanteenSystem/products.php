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
                LEFT JOIN product_categories c ON p.category_id = c.category_id
                WHERE 1=1";

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
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : 'NULL';
        $product_name = sanitize($_POST['product_name']);
        $description = sanitize($_POST['description']);
        $unit_price = floatval($_POST['unit_price']);
        $cost_price = floatval($_POST['cost_price']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $reorder_level = intval($_POST['reorder_level']);

        if (empty($product_name)) {
            echo json_encode(['success' => false, 'message' => 'Product name is required.']);
            exit;
        }

        $sql = "INSERT INTO products (category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level)
                VALUES ($category_id, '$product_name', '$description', '$unit_price', '$cost_price', '$stock_quantity', '$reorder_level')";

        if ($conn->query($sql)) {
            $product_id = $conn->insert_id;

            // Image stored in product_imgs/{product_id}/
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $target_dir = "./../product_imgs/$category_id/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $filename = "product." . $ext;
                    $target_file = $target_dir . $filename;

                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                        $conn->query("UPDATE products SET image = '$target_file' WHERE product_id = $product_id");
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Product added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    if ($_POST['action'] == 'update_product') {
        $product_id = intval($_POST['product_id']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : 'NULL';
        $product_name = sanitize($_POST['product_name']);
        $description = sanitize($_POST['description']);
        $unit_price = floatval($_POST['unit_price']);
        $cost_price = floatval($_POST['cost_price']);
        $reorder_level = intval($_POST['reorder_level']);

        $image_update_sql = "";
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $target_dir = "./../product_imgs/$category_id/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $filename = "product." . $ext;
                $target_file = $target_dir . "/" . $filename;

                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    $image_update_sql = ", image='$target_file'";
                }
            }
        }

        // Note: stock_quantity is managed through inventory (product_transactions), not direct edit
        $sql = "UPDATE products SET
                    category_id  = $category_id,
                    product_name = '$product_name',
                    description  = '$description',
                    unit_price   = '$unit_price',
                    cost_price   = '$cost_price',
                    reorder_level = '$reorder_level'
                    $image_update_sql
                WHERE product_id = '$product_id'";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product updated']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    if ($_POST['action'] == 'delete_product') {
        $product_id = intval($_POST['product_id']);

        $check_result = $conn->query("SELECT COUNT(*) as count FROM order_items WHERE product_id = '$product_id'");
        $count = $check_result->fetch_assoc()['count'];

        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete — product has existing order records.']);
            exit;
        }

        // Remove image folder if it exists
        $img_res = $conn->query("SELECT image FROM products WHERE product_id = '$product_id'");
        if ($img_res && $row = $img_res->fetch_assoc()) {
            if (!empty($row['image']) && file_exists($row['image'])) {
                unlink($row['image']);
            }
        }

        if ($conn->query("DELETE FROM products WHERE product_id = '$product_id'")) {
            echo json_encode(['success' => true, 'message' => 'Product deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    if ($_POST['action'] == 'get_categories') {
        $result = $conn->query("SELECT * FROM product_categories ORDER BY category_id DESC");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $categories]);
        exit;
    }
}

$categories = $conn->query("SELECT * FROM product_categories ORDER BY category_id DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting & Inventory System - Products</title>
    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 5px;
        }

        .btn-icon:hover {
            background: #f1f5f9;
        }

        .btn-icon.delete:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-inner {
            background: #fff;
            width: 95%;
            max-width: 550px;
            border-radius: 8px;
            overflow: auto;
            max-height: 90vh;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
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
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.4rem;
            font-size: 14px;
            color: #334155;
        }

        .form-control {
            padding: 0.65rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

            <div class="content-header">
                <h1><i class="fas fa-box"></i> Products Management</h1>
                <button class="btn btn-primary" onclick="showAddProductModal()">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>

            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchProduct" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterCategory" class="filter-select">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['category_id']; ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button class="btn btn-secondary" onclick="loadProducts()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Selling Price</th>
                            <th>Cost Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsList">
                        <tr>
                            <td colspan="8" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add / Edit Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-inner">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Product</h3>
                <span class="close" onclick="closeProductModal()">&times;</span>
            </div>
            <form id="productForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add_product">
                <input type="hidden" name="product_id" id="formProductId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="product_name" id="prodName" class="form-control" required
                            placeholder="e.g., Piattos">
                    </div>
                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="product_image" id="prodImage" class="form-control" accept="image/*">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" id="prodCategory" class="form-control">
                                <option value="">No Category</option>
                                <?php
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $cat['category_id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="prodDescription" rows="2" class="form-control"
                            placeholder="Product details..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cost Price (₱) *</label>
                            <input type="number" step="0.01" name="cost_price" id="prodCost" class="form-control"
                                required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Selling Price (₱) *</label>
                            <input type="number" step="0.01" name="unit_price" id="prodPrice" class="form-control"
                                required placeholder="0.00">
                        </div>
                    </div>
                    <!-- Stock fields: visible on add, hidden on edit (managed via Inventory) -->
                    <div class="form-row" id="stockFields">
                        <div class="form-group">
                            <label>Initial Stock *</label>
                            <input type="number" name="stock_quantity" id="prodStock" class="form-control" value="0"
                                min="0">
                        </div>
                        <div class="form-group">
                            <label>Reorder Level</label>
                            <input type="number" name="reorder_level" id="prodReorder" class="form-control" value="10"
                                min="0">
                        </div>
                    </div>
                    <!-- On edit, reorder_level still editable -->
                    <div class="form-group" id="reorderEditField" style="display:none;">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" id="prodReorderEdit" class="form-control" value="10"
                            min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }

        function loadProducts() {
            const search = $('#searchProduct').val();
            const category = $('#filterCategory').val();
            $.ajax({
                url: 'products.php', type: 'POST',
                data: { action: 'get_products', search, category },
                dataType: 'json',
                success: function (response) {
                    if (!response.success) return;
                    let html = '';
                    response.data.forEach(function (p) {
                        const imgTag = p.image && p.image !== 'default.png'
                            ? `<img src="${p.image}?v=${Date.now()}" class="product-thumbnail" />`
                            : `<div class="product-thumb-placeholder"><i class="fas fa-image"></i></div>`;

                        html += `<tr>
                            <td>${p.product_id}</td>
                            <td>${imgTag}</td>
                            <td><strong>${escapeHtml(p.product_name)}</strong></td>
                            <td>${escapeHtml(p.category_name || '—')}</td>
                            <td>₱${parseFloat(p.unit_price).toFixed(2)}</td>
                            <td>₱${parseFloat(p.cost_price).toFixed(2)}</td>
                            <td>${p.stock_quantity}</td>
                            <td>
                                <button class="btn-icon" title="Edit"
                                    onclick="editProduct(${p.product_id},'${escapeHtml(p.product_name)}','${p.category_id || ''}','${escapeHtml(p.description)}','${p.cost_price}','${p.unit_price}','${p.reorder_level}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete" title="Delete"
                                    onclick="deleteProduct(${p.product_id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                    $('#productsList').html(html || '<tr><td colspan="8" class="text-center">No products found.</td></tr>');
                }
            });
        }

        function showAddProductModal() {
            $('#productForm')[0].reset();
            $('#formAction').val('add_product');
            $('#formProductId').val('');
            $('#modalTitle').text('Add New Product');
            $('#stockFields').show();
            $('#reorderEditField').hide();
            // make sure the add-mode reorder input is wired (name attr)
            $('#prodReorder').attr('name', 'reorder_level');
            $('#prodReorderEdit').removeAttr('name');
            $('#productModal').css('display', 'flex');
        }

        function editProduct(id, name, category, desc, cost, price, reorder) {
            $('#productForm')[0].reset();
            $('#formAction').val('update_product');
            $('#formProductId').val(id);
            $('#modalTitle').text('Edit Product #' + id);

            $('#prodName').val(name);
            $('#prodCategory').val(category);
            $('#prodDescription').val(desc);
            $('#prodCost').val(cost);
            $('#prodPrice').val(price);

            // Stock is managed via Inventory page; hide initial stock fields
            $('#stockFields').hide();
            $('#prodStock').removeAttr('required');
            // Show standalone reorder edit
            $('#reorderEditField').show();
            $('#prodReorderEdit').val(reorder).attr('name', 'reorder_level');
            $('#prodReorder').removeAttr('name');

            $('#productModal').css('display', 'flex');
        }

        function closeProductModal() {
            $('#productModal').css('display', 'none');
        }

        function deleteProduct(id) {
            if (!confirm('Delete product #' + id + '? This cannot be undone.')) return;
            $.ajax({
                url: 'products.php', type: 'POST',
                data: { action: 'delete_product', product_id: id },
                dataType: 'json',
                success: function (response) {
                    alert(response.message);
                    if (response.success) loadProducts();
                }
            });
        }

        $('#productForm').on('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            $.ajax({
                url: 'products.php', type: 'POST',
                data: fd, processData: false, contentType: false,
                dataType: 'json',
                success: function (response) {
                    alert(response.message);
                    if (response.success) { closeProductModal(); loadProducts(); }
                },
                error: function () { alert('Could not connect to server.'); }
            });
        });

        $(document).ready(function () {
            loadProducts();
            $('#searchProduct').on('keyup', loadProducts);
            $('#filterCategory').on('change', loadProducts);
        });
    </script>
</body>

</html>