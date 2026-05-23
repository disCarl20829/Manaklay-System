<?php
require_once './config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle customer CRUD here if needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            
            <div class="content-header">
                <h1><i class="fas fa-users"></i> Customers Management</h1>
                <button class="btn btn-primary" onclick="alert('Add customer feature coming soon')"><i class="fas fa-plus"></i> Add Customer</button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Orders</th><th>Actions</th></tr></thead>
                    <tbody id="customersList"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('open'); }
        
        $(document).ready(function() {
            $.ajax({
                url: './orders.php', // You can create a dedicated customer endpoint
                type: 'POST',
                data: { action: 'get_customers' },
                dataType: 'json',
                success: function(response) {
                    // Simplified for now
                    $('#customersList').html('<tr><td colspan="6">Customer management coming soon...</td></tr>');
                }
            });
        });
    </script>
</body>
</html>