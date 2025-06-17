      
<!-- navigation.php - Modified for clip-path curves -->
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="brand">
            <img src="/FrugalFolio/images/logo.png" alt="Logo" class="logo-placeholder"> <?php // Verify path ?>
            <span class="title">FrugalFolio</span>
        </a>
        <?php // Close button removed for now, add later for overlay ?>
        <?php // <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar"><i class="fas fa-times"></i></button> ?>
    </div>

    <nav class="nav-links">
        <ul>
            <?php // Determine active page for styling ?>
            <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

            <li class="<?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">
                <a href="dashboard.php">
                    <i class="fas fa-th-large fa-fw"></i> <?php // Icon: Dashboard ?>
                    <span>Dashboard</span>
                </a>
                <?php if ($currentPage == 'dashboard.php'): ?>
                    <div class="curve-visualizer curve-top-bg"></div>
                    <div class="curve-visualizer curve-top-fg"></div>
                    <div class="curve-visualizer curve-bottom-bg"></div>
                    <div class="curve-visualizer curve-bottom-fg"></div>
                <?php endif; ?>
            </li>
            <li class="<?= ($currentPage == 'view_expenses.php' || $currentPage == 'add_expense_form.php' || $currentPage == 'edit_expense_form.php') ? 'active' : '' ?>">
                <a href="view_expenses.php">
                    <i class="fas fa-list-alt fa-fw"></i> <?php // Icon: List ?>
                    <span>View Expenses</span>
                </a>
                <?php if ($currentPage == 'view_expenses.php' || $currentPage == 'add_expense_form.php' || $currentPage == 'edit_expense_form.php'): ?>
                    <div class="curve-visualizer curve-top-bg"></div>
                    <div class="curve-visualizer curve-top-fg"></div>
                    <div class="curve-visualizer curve-bottom-bg"></div>
                    <div class="curve-visualizer curve-bottom-fg"></div>
                <?php endif; ?>
            </li>
            <li class="<?= ($currentPage == 'statistics.php') ? 'active' : '' ?>">
                <a href="statistics.php">
                    <i class="fas fa-chart-pie fa-fw"></i> <?php // Icon: Pie Chart ?>
                    <span>Statistics</span>
                </a>
                <?php if ($currentPage == 'statistics.php'): ?>
                    <div class="curve-visualizer curve-top-bg"></div>
                    <div class="curve-visualizer curve-top-fg"></div>
                    <div class="curve-visualizer curve-bottom-bg"></div>
                    <div class="curve-visualizer curve-bottom-fg"></div>
                <?php endif; ?>
            </li>
            <li class="<?= ($currentPage == 'calculator.php') ? 'active' : '' ?>">
                <a href="calculator.php">
                    <i class="fas fa-calculator fa-fw"></i> <?php // Icon: Calculator ?>
                    <span>Calculator</span>
                </a>
                <?php if ($currentPage == 'calculator.php'): ?>
                    <div class="curve-visualizer curve-top-bg"></div>
                    <div class="curve-visualizer curve-top-fg"></div>
                    <div class="curve-visualizer curve-bottom-bg"></div>
                    <div class="curve-visualizer curve-bottom-fg"></div>
                <?php endif; ?>            </li>
            <li class="<?= ($currentPage == 'receipt_scanner.php') ? 'active' : '' ?>">
                <a href="receipt_scanner.php">
                    <i class="fas fa-receipt fa-fw"></i>
                    <span>Receipt Scanner</span>
                </a>
                <?php if ($currentPage == 'receipt_scanner.php'): ?>
                    <div class="curve-visualizer curve-top-bg"></div>
                    <div class="curve-visualizer curve-top-fg"></div>
                    <div class="curve-visualizer curve-bottom-bg"></div>
                    <div class="curve-visualizer curve-bottom-fg"></div>
                <?php endif; ?>
            </li>
             <?php // Add more links later if needed ?>
        </ul>
    </nav>

     <?php // Optional Footer ?>
     <!--
    <div class="sidebar-footer">
        <p>User: Admin</p>
        <a href="logout.php">Logout</a>
    </div>
    -->
</aside>

    