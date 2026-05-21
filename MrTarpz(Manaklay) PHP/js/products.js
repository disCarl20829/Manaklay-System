// Products specific JavaScript
let editingProductId = null;

function showAddProductModal() {
    editingProductId = null;
    $('#modalTitle').text('Add New Product');
    $('#productForm')[0].reset();
    $('#action').val('add_product');
    loadCategories();
    $('#productModal').show();
}

function editProduct(productId) {
    editingProductId = productId;
    $('#modalTitle').text('Edit Product');
    $('#action').val('update_product');
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: { action: 'get_product', product_id: productId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const p = response.data;
                $('#productId').val(p.product_id);
                $('#category_id').val(p.category_id);
                $('#product_name').val(p.product_name);
                $('#description').val(p.description);
                $('#unit_price').val(p.unit_price);
                $('#cost_price').val(p.cost_price);
                $('#stock_quantity').val(p.stock_quantity);
                $('#reorder_level').val(p.reorder_level);
                $('#product_type').val(p.product_type || 'finished');
                $('#productModal').show();
            }
        }
    });
}

function loadCategories() {
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: { action: 'get_categories' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">Select Category</option>';
                response.data.forEach(function(cat) {
                    options += `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`;
                });
                $('#category_id').html(options);
            }
        }
    });
}

function saveProduct() {
    const formData = {
        action: $('#action').val(),
        product_id: $('#productId').val(),
        category_id: $('#category_id').val(),
        product_name: $('#product_name').val(),
        description: $('#description').val(),
        unit_price: $('#unit_price').val(),
        cost_price: $('#cost_price').val(),
        stock_quantity: $('#stock_quantity').val(),
        reorder_level: $('#reorder_level').val(),
        product_type: $('#product_type').val()
    };
    
    if (!formData.product_name) {
        alert('Product name is required');
        return;
    }
    
    if (!formData.unit_price || formData.unit_price <= 0) {
        alert('Valid selling price is required');
        return;
    }
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                closeModal();
                loadProducts();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product? This cannot be undone.')) {
        $.ajax({
            url: 'products.php',
            type: 'POST',
            data: { action: 'delete_product', product_id: productId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Product deleted');
                    loadProducts();
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    }
}

function closeModal() {
    $('#productModal').hide();
}

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
                displayProducts(response.data);
            } else {
                $('#productsList').html('<tr><td colspan="8" class="text-center">Error loading products</td></tr>');
            }
        }
    });
}

function displayProducts(products) {
    let html = '';
    
    if (products && products.length > 0) {
        products.forEach(function(p) {
            html += `<tr>
                <td>${p.product_id}</td>
                <td><strong>${escapeHtml(p.product_name)}</strong></td>
                <td>${escapeHtml(p.category_name || '-')}</td>
                <td>₱${parseFloat(p.unit_price).toFixed(2)}</td>
                <td>₱${parseFloat(p.cost_price).toFixed(2)}</td>
                <td>
                    <span class="${p.stock_quantity <= p.reorder_level ? 'text-danger' : ''}">
                        ${p.stock_quantity}
                    </span>
                </td>
                <td>${p.product_type || 'finished'}</td>
                <td class="actions">
                    <button class="btn-icon" onclick="editProduct(${p.product_id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" onclick="deleteProduct(${p.product_id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn-icon" onclick="window.location.href='inventory.php?add_stock=${p.product_id}'" title="Add Stock">
                        <i class="fas fa-plus-circle"></i>
                    </button>
                </td>
            </tr>`;
        });
    } else {
        html = '<tr><td colspan="8" class="empty-table">No products found</td></tr>';
    }
    
    $('#productsList').html(html);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

$(document).ready(function() {
    loadProducts();
    loadCategories();
    
    $('#searchProduct').on('keyup', function() {
        loadProducts();
    });
    
    $('#filterCategory').on('change', function() {
        loadProducts();
    });
});