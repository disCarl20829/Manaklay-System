<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            
            <div class="content-header">
                <h1><i class="fas fa-cog"></i> Settings</h1>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header"><h3>Shop Information</h3></div>
                <div class="card-body">
                    <form onsubmit="return false;">
                        <div class="form-group"><label>Shop Name</label><input type="text" value="Mr. Tarpz Printing Shop" class="form-control"></div>
                        <div class="form-group"><label>Address</label><input type="text" placeholder="Enter shop address" class="form-control"></div>
                        <div class="form-group"><label>Contact Number</label><input type="text" placeholder="Enter contact number" class="form-control"></div>
                        <div class="form-group"><label>Email</label><input type="email" placeholder="Enter email" class="form-control"></div>
                        <button class="btn btn-primary" onclick="alert('Settings saved (demo)')">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>