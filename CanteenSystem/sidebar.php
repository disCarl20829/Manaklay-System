<div class="sidebar" style="overflow-x: hidden;">
    <div class="sidebar-header" style="width: 100%; text-align: center; padding: 15px; box-sizing: border-box; overflow: hidden;">
        <div class="sidebar-logo" style="width: 130px; height: 130px; margin: 0 auto 10px; display: flex; justify-content: center; align-items: center; overflow: hidden; border-radius: 50%;">
            <img src="./../resources/logo.jpg" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; display: block;">
        </div>
        <h3 style="margin: 0; color: #fff; font-size: 1.1rem;">Accounting & Inventory System</h3>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Products</a>
        <a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
        <a href="transactions.php"><i class="fas fa-shopping-cart"></i> Transactions</a>
        <a href="expenses.php"><i class="fas fa-chart-line"></i> Expenses</a>
        <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="sidebar-footer">
        <p><i class="fas fa-user"></i> <?php echo $_SESSION['full_name'] ?? 'User'; ?></p>
        <p><small><?php echo $_SESSION['role'] ?? 'staff'; ?></small></p>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let currentPage = window.location.pathname.split('/').pop();
    if (currentPage === '') currentPage = 'dashboard.php';
    
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    navLinks.forEach(link => {
        link.classList.remove('active');
        const linkHref = link.getAttribute('href');
        if (linkHref === currentPage) {
            link.classList.add('active');
        }
    });
});
</script>