<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

/*
 * Payments in this system are stored directly on the orders table:
 *   orders.paid_amount   — running total paid
 *   orders.payment_status — unpaid / partial / paid
 *   orders.payment_method — cash / gcash
 *
 * There is no separate `payments` table and no `customers` table.
 * This page shows all orders that have received at least a partial payment,
 * and allows recording or adjusting payments against any unpaid/partial order.
 */

if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // All payments (orders with paid_amount > 0)
    if ($_POST['action'] == 'get_payments') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';

        $sql = "SELECT o.order_id, o.order_date, o.total_amount, o.paid_amount,
                       o.payment_method, o.payment_status, o.order_status,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) AS item_count
                FROM orders o
                WHERE o.paid_amount > 0";

        if (!empty($search)) {
            $sql .= " AND o.order_id LIKE '%$search%'";
        }
        $sql .= " ORDER BY o.order_date DESC";

        $result = $conn->query($sql);
        $payments = [];
        if ($result) {
            while ($row = $result->fetch_assoc())
                $payments[] = $row;
            echo json_encode(['success' => true, 'data' => $payments]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // Orders that still have a balance (for the Record Payment form)
    if ($_POST['action'] == 'get_unpaid_orders') {
        $sql = "SELECT o.order_id, o.total_amount, o.paid_amount,
                       (o.total_amount - COALESCE(o.paid_amount, 0)) AS balance,
                       o.payment_method
                FROM orders o
                WHERE o.payment_status != 'paid'
                  AND o.order_status  != 'cancelled'
                ORDER BY o.order_date DESC";

        $result = $conn->query($sql);
        $orders = [];
        if ($result) {
            while ($row = $result->fetch_assoc())
                $orders[] = $row;
            echo json_encode(['success' => true, 'data' => $orders]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // Record a payment against an order
    if ($_POST['action'] == 'record_payment') {
        $order_id = intval($_POST['order_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
            exit;
        }

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
                throw new Exception('Payment exceeds the order total.');

            $status = ($new_paid >= $total - 0.01) ? 'paid' : 'partial';

            $upd = $conn->prepare(
                "UPDATE orders SET paid_amount = ?, payment_status = ?, payment_method = ? WHERE order_id = ?"
            );
            $upd->bind_param("dssi", $new_paid, $status, $payment_method, $order_id);
            if (!$upd->execute())
                throw new Exception('Error updating order.');

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Reverse a payment (zero out paid_amount for an order)
    if ($_POST['action'] == 'void_payment') {
        $order_id = intval($_POST['order_id']);

        $stmt = $conn->prepare(
            "UPDATE orders SET paid_amount = 0, payment_status = 'unpaid' WHERE order_id = ?"
        );
        $stmt->bind_param("i", $order_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payment voided.']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
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
    <title>Accounting & Inventory System - Payments</title>
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
            border-radius: 5px;
        }

        .btn-icon:hover {
            background: #f1f5f9;
        }

        .btn-icon.void:hover {
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
            background: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            overflow-y: auto;
            padding-top: 60px;
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
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .order-info-box {
            background: #f8fafc;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        .order-info-box p {
            margin: 4px 0;
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
                <h1><i class="fas fa-money-bill-wave"></i> Payments</h1>
                <button class="btn btn-primary" onclick="showRecordPaymentModal()">
                    <i class="fas fa-plus"></i> Record Payment
                </button>
            </div>

            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchPayment" placeholder="Search order #">
                    <i class="fas fa-search"></i>
                </div>
                <button class="btn btn-secondary" onclick="loadPayments()">
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
                            <th>Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsList">
                        <tr>
                            <td colspan="9" class="text-center">Loading payments...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 460px;">
            <div class="modal-header">
                <h3>Record Payment</h3>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Order</label>
                    <select id="select_order" required onchange="updateOrderDetails()">
                        <option value="">— choose an order —</option>
                    </select>
                </div>

                <div class="order-info-box" id="orderInfoBox">
                    <p><strong>Order #:</strong> <span id="info_order_id">—</span></p>
                    <p><strong>Total:</strong> ₱<span id="info_total">0.00</span></p>
                    <p><strong>Paid:</strong> ₱<span id="info_paid">0.00</span></p>
                    <p><strong>Balance:</strong> ₱<span id="info_balance">0.00</span></p>
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
                <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <button class="btn btn-success" onclick="recordPayment()">Record Payment</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            loadPayments();
            $('#searchPayment').on('keyup', loadPayments);
        });

        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }

        function loadPayments() {
            const search = $('#searchPayment').val();
            $.ajax({
                url: 'payments.php', type: 'POST',
                data: { action: 'get_payments', search },
                dataType: 'json',
                success: function (response) {
                    if (!response.success) return;
                    let html = '';
                    response.data.forEach(function (p) {
                        const date = new Date(p.order_date).toLocaleDateString();
                        const balance = parseFloat(p.total_amount) - parseFloat(p.paid_amount || 0);
                        html += `<tr>
                            <td><strong>#${p.order_id}</strong></td>
                            <td>${date}</td>
                            <td>${p.item_count}</td>
                            <td>₱${parseFloat(p.total_amount).toFixed(2)}</td>
                            <td>₱${parseFloat(p.paid_amount).toFixed(2)}</td>
                            <td>₱${balance.toFixed(2)}</td>
                            <td>${p.payment_method || 'cash'}</td>
                            <td><span class="status-badge status-${p.payment_status}">${p.payment_status}</span></td>
                            <td>
                                ${p.payment_status !== 'paid'
                                ? `<button class="btn-icon" title="Add payment" onclick="quickPay(${p.order_id}, ${balance.toFixed(2)})"><i class="fas fa-plus-circle"></i></button>`
                                : ''}
                                <button class="btn-icon void" title="Void payment" onclick="voidPayment(${p.order_id})"><i class="fas fa-undo"></i></button>
                            </td>
                        </tr>`;
                    });
                    $('#paymentsList').html(html || '<tr><td colspan="9" class="text-center">No payment records found.</td></tr>');
                }
            });
        }

        function showRecordPaymentModal() {
            $.ajax({
                url: 'payments.php', type: 'POST',
                data: { action: 'get_unpaid_orders' },
                dataType: 'json',
                success: function (response) {
                    if (!response.success) return;
                    let opts = '<option value="">— choose an order —</option>';
                    response.data.forEach(function (o) {
                        opts += `<option value="${o.order_id}"
                            data-total="${o.total_amount}"
                            data-paid="${o.paid_amount || 0}"
                            data-balance="${o.balance}">
                            Order #${o.order_id} — Balance ₱${parseFloat(o.balance).toFixed(2)}
                        </option>`;
                    });
                    $('#select_order').html(opts);
                    $('#pay_amount').val('');
                    $('#orderInfoBox').hide();
                    $('#paymentModal').show();
                }
            });
        }

        function updateOrderDetails() {
            const sel = $('#select_order');
            const opt = sel.find('option:selected');
            if (!sel.val()) { $('#orderInfoBox').hide(); return; }

            $('#info_order_id').text(sel.val());
            $('#info_total').text(parseFloat(opt.data('total')).toFixed(2));
            $('#info_paid').text(parseFloat(opt.data('paid')).toFixed(2));
            const bal = parseFloat(opt.data('balance')).toFixed(2);
            $('#info_balance').text(bal);
            $('#pay_amount').attr('max', bal);
            $('#orderInfoBox').show();
        }

        function recordPayment() {
            const order_id = $('#select_order').val();
            const amount = parseFloat($('#pay_amount').val());
            const balance = parseFloat($('#info_balance').text());

            if (!order_id) { alert('Please select an order.'); return; }
            if (!amount || amount <= 0) { alert('Enter a valid payment amount.'); return; }
            if (amount > balance + 0.01) { alert('Amount exceeds balance of ₱' + balance.toFixed(2)); return; }

            $.ajax({
                url: 'payments.php', type: 'POST',
                data: { action: 'record_payment', order_id, amount, payment_method: $('#pay_method').val() },
                dataType: 'json',
                success: function (res) {
                    alert(res.message);
                    if (res.success) { closePaymentModal(); loadPayments(); }
                }
            });
        }

        // Quick-pay button from the list row
        function quickPay(orderId, balance) {
            const amount = parseFloat(prompt(`Enter payment amount (Balance: ₱${balance.toFixed(2)}):`));
            if (!amount || amount <= 0) return;
            if (amount > balance + 0.01) { alert('Amount exceeds balance.'); return; }

            const method = confirm('GCash? Click OK for GCash, Cancel for Cash.') ? 'gcash' : 'cash';

            $.ajax({
                url: 'payments.php', type: 'POST',
                data: { action: 'record_payment', order_id: orderId, amount, payment_method: method },
                dataType: 'json',
                success: function (res) { alert(res.message); if (res.success) loadPayments(); }
            });
        }

        function voidPayment(orderId) {
            if (!confirm('Void all payments for Order #' + orderId + '? This sets paid amount back to ₱0.')) return;
            $.ajax({
                url: 'payments.php', type: 'POST',
                data: { action: 'void_payment', order_id: orderId },
                dataType: 'json',
                success: function (res) { alert(res.message); if (res.success) loadPayments(); }
            });
        }

        function closePaymentModal() { $('#paymentModal').hide(); }

        window.onclick = function (e) {
            if ($(e.target).hasClass('modal')) closePaymentModal();
        };
    </script>
</body>

</html>