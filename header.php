<?php // header.php ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'FrugalFolio' ?></title>

    <!-- Base/Layout CSS (Load these first) -->
    <link rel="stylesheet" href="/Frugalfolio/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/Frugalfolio/css/layout.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/Frugalfolio/css/navigation.css?v=<?= time() ?>">    <link rel="stylesheet" href="/Frugalfolio/css/table.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/Frugalfolio/css/form.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/Frugalfolio/css/receipt_scanner_style.css?v=<?= time() ?>">

    <!-- Chart.js -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tesseract.js -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.3/dist/tesseract.min.js"></script>

    <?php
    if (isset($pageStylesheets) && is_array($pageStylesheets)) {
		foreach ($pageStylesheets as $sheet) {
			if (strpos($sheet, 'http://') === 0 || strpos($sheet, 'https://') === 0) {
				$cssPath = $sheet;
				 echo '<link rel="stylesheet" href="' . htmlspecialchars($cssPath) . '">' . "\n";
			} else {
				$cssPath = '/Frugalfolio' . (strpos($sheet, '/') === 0 ? '' : '/') . $sheet;
				 echo '<link rel="stylesheet" href="' . htmlspecialchars($cssPath) . '?v=' . time() . '">' . "\n";
			}
		}
	}
    ?>
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>

<div class="notification-page-overlay" id="notificationPageOverlay" style="display: none;"></div>

<div class="app-container">

    <?php include 'navigation.php'; // Sidebar HTML ?>

    <div class="main-content">

        <header class="top-bar">
            <div class="top-bar-left">
                <a href="dashboard.php" class="brand mobile-brand" id="mobileBrand">
                    <img src="/Frugalfolio/images/logo.png" alt="Logo" class="logo-placeholder">
                </a>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
					<i class="fas fa-bars"></i>
				</button>
                <h1 class="top-bar-page-title" id="topBarPageTitle">
                    <?php
                        $displayTitle = isset($pageTitle) ? str_replace(" - FrugalFolio", "", $pageTitle) : '';
                        echo htmlspecialchars($displayTitle);
                    ?>
                </h1>
            </div>

			<?php
                // Ensure session is started before accessing $_SESSION variables
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $notification_count = $_SESSION['price_notification_count'] ?? 0;
                $notifications = $_SESSION['price_notifications'] ?? [];
            ?>

            <div class="top-bar-right">
			    <div class="notification-area" style="position: relative;">
				    <button class="top-bar-icon-btn" id="notificationBellBtn" aria-label="Notifications">
					    <i class="fas fa-bell"></i>
					    <?php if ($notification_count > 0): ?><span class="badge notification-badge"><?= $notification_count ?></span><?php endif; ?>
				    </button>
				    <div class="notification-dropdown" id="notificationDropdown">
					    <h5>Notification (Recent Price Increases)</h5>
					    <?php if (!empty($notifications)): ?><ul><?php foreach ($notifications as $item): ?><li><strong><?= htmlspecialchars($item['item_name']) ?></strong>: <span style="text-decoration: line-through;">₱<?= number_format($item['old_price'], 2) ?></span> -> <strong>₱<?= number_format($item['new_price'], 2) ?></strong> <span style="color: red;">(+₱<?= number_format($item['difference'], 2) ?>)</span></li><?php endforeach; ?></ul><?php else: ?><p>No significant price increases found recently.</p><?php endif; ?>
				    </div>
			    </div>
				
				<div class="user-profile-dropdown-container">
					<div class="user-profile-area" id="userProfileTrigger" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="userDropdownMenu">
						<i class="fas fa-user-circle user-icon"></i>
						<span class="user-name">
							<?= isset($_SESSION['display_name']) ? htmlspecialchars($_SESSION['display_name']) : 'Admin' ?>
						</span>
						<i class="fas fa-caret-down" style="margin-left: 5px; font-size: 0.8em; opacity: 0.7;"></i>
					</div>
					<div class="user-dropdown-menu" id="userDropdownMenu" role="menu">
						<a href="logout.php" class="user-dropdown-item" role="menuitem">
							<i class="fas fa-sign-out-alt fa-fw"></i> Logout
						</a>
						<?php /* More items can be added here */ ?>
					</div>
				</div>
            </div>
        </header>

        <div class="content-below-topbar">