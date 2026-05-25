// Inventory specific JavaScript
let currentProductId = null;

function showAddStockModal(productId = null) {
    currentProductId = productId;
    if (productId) {
        $('#add_product_id').val(productId);
    }
    $('#addStockModal').show();
}

function showRemoveStockModal(productId = null) {
    currentProductId = productId;
    if (productId) {
        $('#remove_product_id').val(productId);
        updateAvailableStock();
    }
    $('#removeStockModal').show();
}

function showNewProductModal() {
    $('#newProductForm')[0].reset();
    $('#newProductModal').show();
}

function updateAvailableStock() {
    const productId = $('#remove_product_id').val();
    const selectedOption = $('#remove_product_id option:selected');
    const availableStock = selectedOption.data('stock') || 0;
    const quantity = $('#remove_quantity').val();
    
    if (parseInt(quantity) > availableStock) {
        $('#stockWarning').show();
        $('#removeStockModal .btn-danger').prop('disabled', true);
    } else {
        $('#stockWarning').hide();
        $('#removeStockModal .btn-danger').prop('disabled', false);
    }
}

function addStock() {
    const productId = $('#add_product_id').val();
    const quantity = $('#add_quantity').val();
    const notes = $('#add_notes').val();
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    if (!quantity || quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: {
            action: 'add_stock',
            product_id: productId,
            quantity: quantity,
            notes: notes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Stock added successfully!');
                closeAddStockModal();
                loadInventory();
                loadTransactions();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function removeStock() {
    const productId = $('#remove_product_id').val();
    const quantity = $('#remove_quantity').val();
    const notes = $('#remove_notes').val();
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    if (!quantity || quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: {
            action: 'remove_stock',
            product_id: productId,
            quantity: quantity,
            notes: notes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Stock removed successfully!');
                closeRemoveStockModal();
                loadInventory();
                loadTransactions();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function saveNewProduct() {
    const formData = {
        action: 'add_product',
        category_id: $('#new_category_id').val(),
        product_name: $('#new_product_name').val(),
        description: $('#new_description').val(),
        unit_price: $('#new_unit_price').val(),
        cost_price: $('#new_cost_price').val(),
        stock_quantity: $('#new_stock_quantity').val(),
        reorder_level: $('#new_reorder_level').val(),
        product_type: $('#new_product_type').val()
    };
    
    if (!formData.product_name) {
        alert('Product name is required');
        return;
    }
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Product added successfully!');
                closeNewProductModal();
                loadInventory();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function closeAddStockModal() {
    $('#addStockModal').hide();
}

function closeRemoveStockModal() {
    $('#removeStockModal').hide();
}

function closeNewProductModal() {
    $('#newProductModal').hide();
}