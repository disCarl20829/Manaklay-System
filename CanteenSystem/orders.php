<?php
require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isLoggedIn()) {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    redirect('index.php');
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Recent orders for dashboard widget
    if ($_POST['action'] == 'get_recent_orders') {
        $result = $conn->query("SELECT * FROM orders ORDER BY order_date DESC LIMIT 5");
        $orders = [];
        if ($result) {
            while ($row = $result->fetch_assoc())
                $orders[] = $row;
            echo json_encode(['success' => true, 'data' => $orders]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // Full orders list with optional filters
    if ($_POST['action'] == 'get_orders') {
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';

        $sql = "SELECT o.*,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) AS item_count
                   FROM orders o WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($search)) {
            $sql .= " AND o.order_id LIKE ?";
            $params[] = "%" . $search . "%";
            $types .= "s";
        }
        if (!empty($status)) {
            $sql .= " AND o.order_status = ?";
            $params[] = $status;
            $types .= "s";
        }
        $sql .= " ORDER BY o.order_date DESC";

        $stmt = $conn->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        $orders = [];

        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $orders
        ]);
        exit;
    }

    // Single order detail with items
    if ($_POST['action'] == 'get_order_details') {
        $order_id = intval($_POST['order_id']);

        $stmt = $conn->prepare(
            "SELECT o.*, u.full_name AS staff_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.user_id
             WHERE o.order_id = ?"
        );
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        $stmt2 = $conn->prepare(
            "SELECT oi.*, p.product_name
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = ?"
        );
        $stmt2->bind_param("i", $order_id);
        $stmt2->execute();

        $result2 = $stmt2->get_result();

        $items = [];

        while ($item = $result2->fetch_assoc()) {
            $items[] = $item;
        }

        echo json_encode(['success' => true, 'data' => ['order' => $order, 'items' => $items]]);
        exit;
    }

    // Create a new order
    if ($_POST['action'] == 'add_order') {
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
        $user_id = $_SESSION['user_id'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO orders (notes, user_id, order_status, payment_status, payment_method)
                 VALUES (?, ?, 'pending', 'unpaid', ?)"
            );
            $stmt->bind_param("sis", $notes, $user_id, $payment_method);
            if (!$stmt->execute())
                throw new Exception('Error creating order.');

            $order_id = $conn->insert_id;
            $total_amount = 0;

            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $item_stmt = $conn->prepare(
                    "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, specifications)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($_POST['items'] as $item) {
                    $product_id = intval($item['product_id']);
                    $quantity = intval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $subtotal = $quantity * $unit_price;
                    $specifications = isset($item['specifications']) ? trim($item['specifications']) : '';

                    $item_stmt->bind_param("iiidds", $order_id, $product_id, $quantity, $unit_price, $subtotal, $specifications);
                    if (!$item_stmt->execute())
                        throw new Exception('Error adding order item.');
                    $total_amount += $subtotal;
                }
            }

            $upd = $conn->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
            $upd->bind_param("di", $total_amount, $order_id);
            if (!$upd->execute())
                throw new Exception('Error setting order total.');

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Order created successfully', 'order_id' => $order_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Record payment directly on an order (orders.paid_amount + payment_status)
    if ($_POST['action'] == 'record_payment') {
        $order_id = intval($_POST['order_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT total_amount, paid_amount FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            if (!$order)
                throw new Exception('Order not found.');

            $new_paid = floatval($order['paid_amount']) + $amount;
            $total = floatval($order['total_amount']);

            if ($new_paid > $total + 0.01)
                throw new Exception('Payment exceeds order total.');

            $status = ($new_paid >= $total - 0.01) ? 'paid' : 'partial';

            $upd = $conn->prepare(
                "UPDATE orders SET paid_amount = ?, payment_status = ?, payment_method = ? WHERE order_id = ?"
            );
            $upd->bind_param("dssi", $new_paid, $status, $payment_method, $order_id);
            if (!$upd->execute())
                throw new Exception('Error updating payment.');

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Payment recorded.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Update order status
    if ($_POST['action'] == 'update_order_status') {
        $order_id = intval($_POST['order_id']);
        $status = trim($_POST['status']);

        $allowed = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
        $stmt->bind_param("si", $status, $order_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating status.']);
        }
        exit;
    }
}

$products_query = $conn->query(
    "SELECT product_id, product_name, unit_price FROM products WHERE stock_quantity > 0 ORDER BY product_name"
);
$products_array = [];
while ($prod = $products_query->fetch_assoc())
    $products_array[] = $prod;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting & Inventory System - Orders</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-items-container {
            max-height: 380px;
            overflow-y: auto;
            margin-bottom: 16px;
        }

        .order-item-row {
            background: #f8fafc;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 10px;
            position: relative;
        }

        .remove-item {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
        }

        .remove-item:hover {
            background: #dc2626;
            color: #fff;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-completed,
        .status-delivered {
            background: #d1fae5;
            color: #059669;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-paid {
            background: #d1fae5;
            color: #059669;
        }

        .status-partial {
            background: #fef3c7;
            color: #d97706;
        }

        .status-unpaid {
            background: #fee2e2;
            color: #dc2626;
        }

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

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            overflow-y: auto;
            padding-top: 40px;
        }

        .modal-content {
            background: #fff;
            margin: 0 auto 40px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }

        .modal-header .close {
            font-size: 22px;
            cursor: pointer;
            color: #94a3b8;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

            <div class="content-header">
                <h1><i class="fas fa-shopping-cart"></i> Orders Management</h1>
                <button class="btn btn-primary" onclick="showAddOrderModal()">
                    <i class="fas fa-plus"></i> New Order
                </button>
            </div>

            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchOrder" placeholder="Search order #">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterStatus" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button class="btn btn-secondary" onclick="loadOrders()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersList">
                        <tr>
                            <td colspan="9" class="text-center">Loading orders...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content" style="max-width: 780px;">
            <div class="modal-header">
                <h3>Create New Order</h3>
                <span class="close" onclick="closeOrderModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select id="new_payment_method">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" id="new_notes" placeholder="Optional remarks">
                    </div>
                </div>

                <h4 style="margin: 0 0 10px;">Order Items</h4>
                <div id="orderItems" class="order-items-container"></div>
                <button type="button" class="btn btn-secondary" onclick="addOrderItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>

                <div
                    style="margin-top: 16px; padding: 14px; background: #f8fafc; border-radius: 8px; text-align: right;">
                    <strong>Total: ₱<span id="orderTotal">0.00</span></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitOrderBtn" onclick="submitOrder()">Create
                    Order</button>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 750px;">
            <div class="modal-header">
                <h3>Order Details</h3>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div id="orderDetails" class="modal-body"></div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="payOrderModal" class="modal">
        <div class="modal-content" style="max-width: 420px;">
            <div class="modal-header">
                <h3>Record Payment</h3>
                <span class="close" onclick="$('#payOrderModal').hide()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pay_order_id">
                <div class="form-group">
                    <label>Balance Due: ₱<span id="pay_balance">0.00</span></label>
                </div>
                <div class="form-group">
                    <label>Amount (₱) *</label>
                    <input type="number" id="pay_amount" step="0.01" min="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select id="pay_method">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="$('#payOrderModal').hide()">Cancel</button>
                <button class="btn btn-success" onclick="submitPayment()">Record Payment</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const productsList = <?php echo json_encode($products_array); ?>;

        $(document).ready(function () {
            loadOrders();
            $('#searchOrder').on('keyup', loadOrders);
            $('#filterStatus').on('change', loadOrders);
        });

        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }

        function loadOrders() {
            $.ajax({
                url: 'orders.php', type: 'POST',
                data: { action: 'get_orders', search: $('#searchOrder').val(), status: $('#filterStatus').val() },
                dataType: 'json',
                success: function (response) {
                    if (response.success) displayOrders(response.data);
                    else $('#ordersList').html('<tr><td colspan="9" class="text-center">' + response.message + '</td></tr>');
                }
            });
        }

        function displayOrders(orders) {
            let html = '';
            if (orders && orders.length > 0) {
                orders.forEach(function (o) {
                    const date = new Date(o.order_date).toLocaleDateString();
                    const balance = parseFloat(o.total_amount) - parseFloat(o.paid_amount || 0);
                    html += `<tr>
                        <td><strong>#${o.order_id}</strong></td>
                        <td>${date}</td>
                        <td>${o.item_count || 0}</td>
                        <td>₱${parseFloat(o.total_amount).toFixed(2)}</td>
                        <td>₱${parseFloat(o.paid_amount || 0).toFixed(2)}</td>
                        <td>₱${balance.toFixed(2)}</td>
                        <td><span class="status-badge status-${o.order_status}">${o.order_status.replace('_', ' ')}</span></td>
                        <td><span class="status-badge status-${o.payment_status || 'unpaid'}">${o.payment_status || 'unpaid'}</span></td>
                        <td>
                            <button class="btn-icon" title="View" onclick="viewOrder(${o.order_id})"><i class="fas fa-eye"></i></button>
                            <button class="btn-icon" title="Pay"  onclick="openPayModal(${o.order_id}, ${balance.toFixed(2)})"><i class="fas fa-money-bill-wave"></i></button>
                            <button class="btn-icon" title="Status" onclick="updateOrderStatus(${o.order_id})"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>`;
                });
            } else {
                html = '<tr><td colspan="9" class="text-center">No orders found.</td></tr>';
            }
            $('#ordersList').html(html);
        }

        function showAddOrderModal() {
            $('#orderItems').empty();
            $('#orderTotal').text('0.00');
            $('#new_notes').val('');
            addOrderItem();
            $('#orderModal').show();
        }
        function closeOrderModal() { $('#orderModal').hide(); }
        function closeDetailsModal() { $('#orderDetailsModal').hide(); }

        function addOrderItem() {
            const uid = Date.now() + Math.floor(Math.random() * 100);
            const opts = productsList.map(p =>
                `<option value="${p.product_id}" data-price="${p.unit_price}">${escapeHtml(p.product_name)} — ₱${parseFloat(p.unit_price).toFixed(2)}</option>`
            ).join('');

            $('#orderItems').append(`
                <div class="order-item-row" id="item-${uid}">
                    <button type="button" class="remove-item" onclick="removeOrderItem('${uid}')">×</button>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Product</label>
                            <select class="product-select" onchange="updateItemPrice('${uid}')">
                                <option value="">Select product</option>${opts}
                            </select>
                        </div>
                        <div class="form-group" style="max-width:100px;">
                            <label>Qty</label>
                            <input type="number" class="quantity-input" min="1" value="1" oninput="updateItemSubtotal('${uid}')">
                        </div>
                        <div class="form-group" style="max-width:110px;">
                            <label>Unit Price</label>
                            <input type="number" class="price-input" step="0.01" readonly>
                        </div>
                        <div class="form-group" style="max-width:110px;">
                            <label>Subtotal</label>
                            <input type="number" class="subtotal-input" step="0.01" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Specifications</label>
                        <input type="text" class="specs-input" placeholder="e.g. size, variant...">
                    </div>
                </div>
            `);
        }

        function removeOrderItem(uid) { $(`#item-${uid}`).remove(); updateOrderTotal(); }

        function updateItemPrice(uid) {
            const row = $(`#item-${uid}`);
            const price = parseFloat(row.find('.product-select option:selected').data('price')) || 0;
            row.find('.price-input').val(price.toFixed(2));
            updateItemSubtotal(uid);
        }

        function updateItemSubtotal(uid) {
            const row = $(`#item-${uid}`);
            const qty = parseInt(row.find('.quantity-input').val()) || 0;
            const price = parseFloat(row.find('.price-input').val()) || 0;
            row.find('.subtotal-input').val((qty * price).toFixed(2));
            updateOrderTotal();
        }

        function updateOrderTotal() {
            let total = 0;
            $('.subtotal-input').each(function () { total += parseFloat($(this).val()) || 0; });
            $('#orderTotal').text(total.toFixed(2));
        }

        function submitOrder() {
            let items = [];
            let isValid = true;

            $('.order-item-row').each(function () {
                const product_id = $(this).find('.product-select').val();
                if (!product_id) { isValid = false; return false; }
                items.push({
                    product_id,
                    quantity: parseInt($(this).find('.quantity-input').val()),
                    unit_price: parseFloat($(this).find('.price-input').val()),
                    specifications: $(this).find('.specs-input').val()
                });
            });

            if (!isValid || items.length === 0) {
                alert('Please select a product for every item row.');
                return;
            }

            const btn = $('#submitOrderBtn').prop('disabled', true).text('Creating...');

            $.ajax({
                url: 'orders.php', type: 'POST',
                data: {
                    action: 'add_order',
                    notes: $('#new_notes').val(),
                    payment_method: $('#new_payment_method').val(),
                    items
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) { alert('Order #' + res.order_id + ' created!'); closeOrderModal(); loadOrders(); }
                    else alert('Error: ' + res.message);
                },
                error: function () { alert('Network error.'); },
                complete: function () { $('#submitOrderBtn').prop('disabled', false).text('Create Order'); }
            });
        }

        function viewOrder(orderId) {
            $.ajax({
                url: 'orders.php', type: 'POST',
                data: { action: 'get_order_details', order_id: orderId },
                dataType: 'json',
                success: function (res) {
                    if (res.success) { displayOrderDetails(res.data); $('#orderDetailsModal').show(); }
                    else alert('Error: ' + res.message);
                }
            });
        }

        function displayOrderDetails(data) {
            const o = data.order;
            const items = data.items;
            const balance = parseFloat(o.total_amount) - parseFloat(o.paid_amount || 0);

            const itemsHtml = items.map(item => `
                <tr>
                    <td>${escapeHtml(item.product_name)}</td>
                    <td>${item.quantity}</td>
                    <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                    <td>${escapeHtml(item.specifications || '—')}</td>
                </tr>
            `).join('');

            $('#orderDetails').html(`
                <div style="background:#f8fafc;padding:14px;border-radius:8px;margin-bottom:18px;">
                    <p><strong>Order #:</strong> ${o.order_id}</p>
                    <p><strong>Date:</strong> ${new Date(o.order_date).toLocaleString()}</p>
                    <p><strong>Staff:</strong> ${escapeHtml(o.staff_name || '—')}</p>
                    <p><strong>Status:</strong> <span class="status-badge status-${o.order_status}">${o.order_status.replace('_', ' ')}</span></p>
                    <p><strong>Payment:</strong> <span class="status-badge status-${o.payment_status || 'unpaid'}">${o.payment_status || 'unpaid'}</span> — ${o.payment_method || 'cash'}</p>
                    <p><strong>Notes:</strong> ${escapeHtml(o.notes || '—')}</p>
                </div>
                <h4>Items</h4>
                <table class="table" style="margin-bottom:0;">
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th><th>Specs</th></tr></thead>
                    <tbody>
                        ${itemsHtml}
                        <tr style="font-weight:bold;background:#f1f5f9;">
                            <td colspan="3" style="text-align:right;">Total</td>
                            <td>₱${parseFloat(o.total_amount).toFixed(2)}</td><td></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align:right;">Paid</td>
                            <td>₱${parseFloat(o.paid_amount || 0).toFixed(2)}</td><td></td>
                        </tr>
                        <tr style="font-weight:bold;">
                            <td colspan="3" style="text-align:right;">Balance</td>
                            <td>₱${balance.toFixed(2)}</td><td></td>
                        </tr>
                    </tbody>
                </table>
            `);
        }

        function openPayModal(orderId, balance) {
            if (balance <= 0) { alert('This order is already fully paid.'); return; }
            $('#pay_order_id').val(orderId);
            $('#pay_balance').text(balance.toFixed(2));
            $('#pay_amount').val('').attr('max', balance);
            $('#payOrderModal').show();
        }

        function submitPayment() {
            const amount = parseFloat($('#pay_amount').val());
            const balance = parseFloat($('#pay_balance').text());
            if (!amount || amount <= 0) { alert('Enter a valid amount.'); return; }
            if (amount > balance + 0.01) { alert('Amount exceeds balance.'); return; }

            $.ajax({
                url: 'orders.php', type: 'POST',
                data: {
                    action: 'record_payment',
                    order_id: $('#pay_order_id').val(),
                    amount, payment_method: $('#pay_method').val()
                },
                dataType: 'json',
                success: function (res) {
                    alert(res.message);
                    if (res.success) { $('#payOrderModal').hide(); loadOrders(); }
                }
            });
        }

        function updateOrderStatus(orderId) {
            const status = prompt('Enter new status:\npending | in_progress | completed | delivered | cancelled');
            if (!status) return;
            $.ajax({
                url: 'orders.php', type: 'POST',
                data: { action: 'update_order_status', order_id: orderId, status: status.trim().toLowerCase() },
                dataType: 'json',
                success: function (res) { alert(res.message); if (res.success) loadOrders(); }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
    </script>
</body>

</html>