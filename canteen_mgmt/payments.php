<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_payments') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        
        $sql = "SELECT p.*, o.order_id, o.total_amount, o.paid_amount, 
                COALESCE(c.full_name, 
                    CASE o.customer_type
                        WHEN 'walkin' THEN 'Walk-in Customer'
                        WHEN 'online' THEN 'Online Customer'
                        ELSE 'Customer'
                    END
                ) as customer_name
                FROM payments p 
                LEFT JOIN orders o ON p.order_id = o.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (p.payment_id LIKE '%$search%' OR o.order_id LIKE '%$search%' OR p.reference_number LIKE '%$search%')";
        }
        
        $sql .= " ORDER BY p.payment_date DESC";
        
        $result = $conn->query($sql);
        $payments = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $payments]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_unpaid_orders') {
        $sql = "SELECT o.*, 
                COALESCE(c.full_name, 
                    CASE o.customer_type
                        WHEN 'walkin' THEN 'Walk-in Customer'
                        WHEN 'online' THEN 'Online Customer'
                        ELSE 'Customer'
                    END
                ) as customer_name,
                (o.total_amount - COALESCE(o.paid_amount, 0)) as balance
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id 
                WHERE o.payment_status != 'paid' AND o.order_status != 'cancelled'
                ORDER BY o.order_date DESC";
        
        $result = $conn->query($sql);
        $orders = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $orders]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'record_payment') {
        $order_id = sanitize($_POST['order_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);
        $reference_number = !empty($_POST['reference_number']) ? "'" . sanitize($_POST['reference_number']) . "'" : 'NULL';
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            $order_sql = "SELECT total_amount, paid_amount, payment_status FROM orders WHERE order_id = '$order_id'";
            $order_result = $conn->query($order_sql);
            $order = $order_result->fetch_assoc();
            
            $current_paid = floatval($order['paid_amount'] ?? 0);
            $total = floatval($order['total_amount']);
            $new_paid = $current_paid + $amount;
            
            if ($new_paid > $total + 0.01) {
                throw new Exception('Payment amount exceeds order total');
            }
            
            $payment_sql = "INSERT INTO payments (order_id, amount, payment_method, reference_number, notes, user_id) 
                           VALUES ('$order_id', '$amount', '$payment_method', $reference_number, '$notes', '$user_id')";
            
            if (!$conn->query($payment_sql)) {
                throw new Exception('Error recording payment: ' . $conn->error);
            }
            
            $payment_status = 'partial';
            if (abs($new_paid - $total) < 0.01) {
                $payment_status = 'paid';
            }
            
            $update_sql = "UPDATE orders SET paid_amount = '$new_paid', payment_status = '$payment_status' WHERE order_id = '$order_id'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating order: ' . $conn->error);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_payment') {
        $payment_id = sanitize($_POST['payment_id']);
        
        $conn->begin_transaction();
        
        try {
            $payment_sql = "SELECT * FROM payments WHERE payment_id = '$payment_id'";
            $payment_result = $conn->query($payment_sql);
            $payment = $payment_result->fetch_assoc();
            
            $order_sql = "SELECT * FROM orders WHERE order_id = '{$payment['order_id']}'";
            $order_result = $conn->query($order_sql);
            $order = $order_result->fetch_assoc();
            
            $new_paid = floatval($order['paid_amount']) - floatval($payment['amount']);
            $payment_status = 'unpaid';
            if ($new_paid > 0) {
                $payment_status = 'partial';
            }
            
            $delete_sql = "DELETE FROM payments WHERE payment_id = '$payment_id'";
            if (!$conn->query($delete_sql)) {
                throw new Exception('Error deleting payment');
            }
            
            $update_sql = "UPDATE orders SET paid_amount = '$new_paid', payment_status = '$payment_status' WHERE order_id = '{$payment['order_id']}'";
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating order');
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    <title>Payments - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-progress { background: #dbeafe; color: #2563eb; }
        .btn-icon { background: none; border: none; cursor: pointer; padding: 5px 8px; border-radius: 5px; }
        .btn-icon:hover { background: #f1f5f9; }
        .btn-icon.delete:hover { background: #fee2e2; color: #dc2626; }
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
                <h1><i class="fas fa-money-bill-wave"></i> Payments Management</h1>
                <button class="btn btn-primary" onclick="showRecordPaymentModal()">
                    <i class="fas fa-plus"></i> Record Payment
                </button>
            </div>
            
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchPayment" placeholder="Search payments...">
                    <i class="fas fa-search"></i>
                </div>
                <button class="btn btn-secondary" onclick="loadPayments()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Date</th><th>Order #</th><th>Customer</th><th>Amount</th><th>Method</th><th>Reference</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="paymentsList">
                        <tr><td colspan="8" class="text-center">Loading payments...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Record Payment</h3>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="form-group">
                        <label>Select Order</label>
                        <select id="select_order" required onchange="updateOrderDetails()">
                            <option value="">Choose an order</option>
                        </select>
                    </div>
                    
                    <div id="orderDetails" style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: none;">
                        <p><strong>Customer:</strong> <span id="order_customer"></span></p>
                        <p><strong>Total:</strong> ₱<span id="order_total">0.00</span></p>
                        <p><strong>Paid:</strong> ₱<span id="order_paid">0.00</span></p>
                        <p><strong>Balance:</strong> ₱<span id="order_balance">0.00</span></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Amount (₱)</label>
                        <input type="number" id="amount" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select id="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Reference Number</label>
                        <input type="text" id="reference_number" placeholder="Receipt/Transaction #">
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="payment_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <button class="btn btn-success" onclick="recordPayment()">Record Payment</button>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            loadPayments();
            $('#searchPayment').on('keyup', function() { loadPayments(); });
        });
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
        
        function loadPayments() {
            const search = $('#searchPayment').val();
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: { action: 'get_payments', search: search },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.data.forEach(function(p) {
                            const date = new Date(p.payment_date).toLocaleString();
                            html += `<tr>
                                <td>#${p.payment_id}</td>
                                <td>${date}</td>
                                <td>#${p.order_id}</td>
                                <td>${escapeHtml(p.customer_name || 'N/A')}</td>
                                <td><strong>₱${parseFloat(p.amount).toFixed(2)}</strong></td>
                                <td><span class="status-badge status-completed">${p.payment_method}</span></td>
                                <td>${p.reference_number || '-'}</td>
                                <td>
                                    <button class="btn-icon delete" onclick="deletePayment(${p.payment_id})"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>`;
                        });
                        $('#paymentsList').html(html || '<tr><td colspan="8" class="text-center">No payments found</td></tr>');
                    }
                }
            });
        }
        
        function showRecordPaymentModal() {
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: { action: 'get_unpaid_orders' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Choose an order</option>';
                        response.data.forEach(function(order) {
                            options += `<option value="${order.order_id}" 
                                data-customer="${escapeHtml(order.customer_name)}"
                                data-total="${order.total_amount}"
                                data-paid="${order.paid_amount || 0}"
                                data-balance="${order.balance}">
                                #${order.order_id} - ${order.customer_name} (₱${parseFloat(order.balance).toFixed(2)})
                            </option>`;
                        });
                        $('#select_order').html(options);
                        $('#paymentForm')[0].reset();
                        $('#orderDetails').hide();
                        $('#paymentModal').show();
                    }
                }
            });
        }
        
        function updateOrderDetails() {
            const select = $('#select_order');
            const selected = select.find('option:selected');
            
            if (select.val()) {
                $('#order_customer').text(selected.data('customer'));
                $('#order_total').text(parseFloat(selected.data('total')).toFixed(2));
                $('#order_paid').text(parseFloat(selected.data('paid')).toFixed(2));
                const balance = parseFloat(selected.data('balance')).toFixed(2);
                $('#order_balance').text(balance);
                $('#amount').attr('max', balance);
                $('#orderDetails').show();
            } else {
                $('#orderDetails').hide();
            }
        }
        
        function recordPayment() {
            const orderId = $('#select_order').val();
            const amount = $('#amount').val();
            const method = $('#payment_method').val();
            const balance = parseFloat($('#order_balance').text());
            
            if (!orderId) { alert('Please select an order'); return; }
            if (!amount || amount <= 0) { alert('Please enter a valid amount'); return; }
            if (parseFloat(amount) > balance) { alert(`Amount cannot exceed balance of ₱${balance.toFixed(2)}`); return; }
            
            const btn = $('#paymentModal .btn-success');
            btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
            
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: {
                    action: 'record_payment',
                    order_id: orderId,
                    amount: amount,
                    payment_method: method,
                    reference_number: $('#reference_number').val(),
                    notes: $('#payment_notes').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Payment recorded successfully!');
                        closePaymentModal();
                        loadPayments();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                complete: function() {
                    btn.html('Record Payment').prop('disabled', false);
                }
            });
        }
        
        function deletePayment(paymentId) {
            if (confirm('Delete this payment?')) {
                $.ajax({
                    url: 'payments.php',
                    type: 'POST',
                    data: { action: 'delete_payment', payment_id: paymentId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Payment deleted');
                            loadPayments();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                });
            }
        }
        
        function closePaymentModal() {
            $('#paymentModal').hide();
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        window.onclick = function(event) {
            if ($(event.target).hasClass('modal')) {
                closePaymentModal();
            }
        }
    </script>
</body>
</html>