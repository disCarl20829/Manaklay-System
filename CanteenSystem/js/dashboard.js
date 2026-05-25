// Dashboard specific JavaScript
$(document).ready(function() {
    // Initialize tooltips if any
    $('[data-tooltip]').each(function() {
        $(this).attr('title', $(this).data('tooltip'));
    });
    
    // Auto-refresh recent orders every 30 seconds
    if ($('#recent-orders').length) {
        setInterval(function() {
            $.ajax({
                url: 'orders.php',
                type: 'POST',
                data: { action: 'get_recent_orders' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        let html = '';
                        response.data.forEach(function(order) {
                            html += `<tr>
                                <td>#${order.order_id}</td>
                                <td>${escapeHtml(order.customer_name || 'Walk-in')}</td>
                                <td>₱${parseFloat(order.total_amount).toFixed(2)}</td>
                                <td><span class="status-badge status-${order.order_status}">${order.order_status}</span></td>
                            </tr>`;
                        });
                        $('#recent-orders').html(html || '<tr><td colspan="4">No recent orders</td></tr>');
                    }
                }
            });
        }, 30000);
    }
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

function showNotification(message, type = 'info') {
    // Simple notification - can be enhanced
    alert(message);
}