<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); } 
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null; 

// Get cart count for customer role
$cartCount = 0;
if ($user && $user['role'] === 'customer') {
    require_once __DIR__ . '/db.php';
    try {
        $pdo = get_pdo_connection();
        // Count items in shopping cart
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE customer_code = ?");
        $stmt->execute([$user['kodecustomer']]);
        $result = $stmt->fetch();
        $cartCount = $result['total'] ?: 0;
    } catch (Exception $e) {
        $cartCount = 0;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo defined('APP_NAME') ? APP_NAME : 'App'; ?></title>
	<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
	<link rel="shortcut icon" href="assets/img/favicon.svg" type="image/svg+xml">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<style>
		.btn-logout:hover {
			background-color: #dc3545 !important;
			border-color: #dc3545 !important;
			color: white !important;
		}
		
		.nav-dashboard:hover {
			color: #20c997 !important;
			font-weight: bold !important;
		}
		
		.cart-icon {
			position: relative;
			display: inline-block;
		}
		
		.cart-badge {
			position: absolute;
			top: -8px;
			right: -8px;
			background-color: #dc3545;
			color: white;
			border-radius: 50%;
			width: 20px;
			height: 20px;
			font-size: 0.75rem;
			font-weight: bold;
			display: flex;
			align-items: center;
			justify-content: center;
			line-height: 1;
			border: 2px solid #343a40;
			animation: pulse 2s infinite;
		}
		
		@keyframes pulse {
			0% { transform: scale(1); }
			50% { transform: scale(1.1); }
			100% { transform: scale(1); }
		}
		
		.cart-icon:hover .cart-badge {
			background-color: #c82333;
			transform: scale(1.1);
		}
		
		/* Cart Popup Styles */
		.cart-popup {
			position: absolute;
			top: 100%;
			right: 0;
			width: 350px;
			background: white;
			border: 1px solid #dee2e6;
			border-radius: 8px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
			z-index: 1050;
			opacity: 0;
			visibility: hidden;
			transform: translateY(-10px);
			transition: all 0.3s ease;
			margin-top: 10px;
		}
		
		.cart-icon:hover .cart-popup {
			opacity: 1;
			visibility: visible;
			transform: translateY(0);
		}
		
		.cart-popup-header {
			padding: 15px;
			border-bottom: 1px solid #dee2e6;
			background: #f8f9fa;
			border-radius: 8px 8px 0 0;
		}
		
		.cart-popup-header h6 {
			margin: 0;
			color: #495057;
			font-weight: 600;
		}
		
		.cart-popup-body {
			max-height: 300px;
			overflow-y: auto;
		}
		
		.cart-popup-item {
			padding: 12px 15px;
			border-bottom: 1px solid #f1f3f4;
			display: flex;
			align-items: center;
			gap: 10px;
			transition: background-color 0.2s ease;
			cursor: pointer;
		}
		
		.cart-popup-item:hover {
			background-color: #f8f9fa;
		}
		
		.cart-popup-item:last-child {
			border-bottom: none;
		}
		
		.cart-popup-item img {
			width: 40px;
			height: 40px;
			object-fit: cover;
			border-radius: 4px;
		}
		
		.cart-popup-item-info {
			flex: 1;
			min-width: 0;
		}
		
		.cart-popup-item-name {
			font-size: 0.9rem;
			font-weight: 500;
			color: #212529;
			margin: 0 0 4px 0;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		
		.cart-popup-item-details {
			font-size: 0.8rem;
			color: #6c757d;
			margin: 0;
		}
		
		.cart-popup-item-price {
			text-align: right;
			font-size: 0.9rem;
			font-weight: 600;
			color: #28a745;
		}
		
		.cart-popup-footer {
			padding: 15px;
			border-top: 1px solid #dee2e6;
			background: #f8f9fa;
			border-radius: 0 0 8px 8px;
		}
		
		.cart-popup-total {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
		}
		
		.cart-popup-total-label {
			font-weight: 600;
			color: #495057;
		}
		
		.cart-popup-total-amount {
			font-weight: 700;
			color: #28a745;
			font-size: 1.1rem;
		}
		
		.cart-popup-actions {
			display: flex;
			gap: 8px;
		}
		
		.cart-popup-btn {
			flex: 1;
			padding: 8px 12px;
			font-size: 0.85rem;
			border-radius: 4px;
			text-decoration: none;
			text-align: center;
			transition: all 0.2s ease;
		}
		
		.cart-popup-btn-primary {
			background: #007bff;
			color: white;
			border: 1px solid #007bff;
		}
		
		.cart-popup-btn-primary:hover {
			background: #0056b3;
			border-color: #0056b3;
			color: white;
		}
		
		.cart-popup-btn-secondary {
			background: #6c757d;
			color: white;
			border: 1px solid #6c757d;
		}
		
		.cart-popup-btn-secondary:hover {
			background: #545b62;
			border-color: #545b62;
			color: white;
		}
		
		.cart-popup-empty {
			padding: 30px 15px;
			text-align: center;
			color: #6c757d;
		}
		
		.cart-popup-empty i {
			font-size: 2rem;
			margin-bottom: 10px;
			opacity: 0.5;
		}
		
		/* Category Filter Icon Styles */
		.btn-category-filter {
			background-color: transparent !important;
			border: 1px solid #495057 !important;
			color: #f8f9fa !important;
			transition: all 0.3s ease;
		}
		
		.btn-category-filter:hover {
			background-color: #495057 !important;
			border-color: #495057 !important;
			color: white !important;
		}
		
		.btn-category-filter.popup-active {
			background-color: #007bff !important;
			border-color: #007bff !important;
			color: white !important;
		}
		
		@keyframes pulse {
			0% { transform: scale(1); }
			50% { transform: scale(1.2); }
			100% { transform: scale(1); }
		}
		
		/* Category Filter Popup */
		.category-filter-popup {
			position: fixed;
			top: 80px;
			left: 0;
			transform: translateY(-20px);
			width: 600px;
			max-width: 90vw;
			background: white;
			border: 1px solid #dee2e6;
			border-radius: 12px;
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
			z-index: 1050;
			opacity: 0;
			visibility: hidden;
			transition: all 0.3s ease;
			pointer-events: none;
			display: flex;
			min-height: 400px;
		}
		
		.category-filter-popup.show {
			opacity: 1;
			visibility: visible;
			transform: translateY(0);
			pointer-events: auto;
		}
		
		/* Menu Sidebar */
		.category-filter-menu {
			width: 170px;
			background: #f8f9fa;
			border-right: 1px solid #dee2e6;
			border-radius: 12px 0 0 12px;
			padding: 0;
		}
		
		.category-filter-menu-item {
			padding: 15px 16px;
			cursor: pointer;
			border-bottom: 1px solid #e9ecef;
			transition: all 0.2s ease;
			color: #495057;
			font-weight: 500;
			position: relative;
			font-size: 0.9rem;
		}
		
		.category-filter-menu-item:hover {
			background: #e9ecef;
			color: #007bff;
		}
		
		.category-filter-menu-item.active {
			background: #007bff;
			color: white;
		}
		
		.category-filter-menu-item.active::after {
			content: '';
			position: absolute;
			right: 0;
			top: 0;
			bottom: 0;
			width: 3px;
			background: #0056b3;
		}
		
		/* Sub Menu Content */
		.category-filter-content {
			flex: 1;
			padding: 20px;
			overflow-y: auto;
			max-height: 400px;
			min-width: 450px;
		}
		
		.category-filter-content h6 {
			margin: 0 0 15px 0;
			color: #495057;
			font-weight: 600;
			font-size: 1.1rem;
		}
		
		.category-filter-submenu {
			display: none;
		}
		
		.category-filter-submenu.active {
			display: block;
		}
		
		.category-filter-submenu-item {
			padding: 8px 12px;
			margin: 3px 0;
			background: transparent;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			transition: all 0.2s ease;
			color: #495057;
			font-size: 0.875rem;
		}
		
		.category-filter-submenu-item:hover {
			background: #e9ecef;
			color: #007bff;
		}
		
		.category-filter-submenu-item.selected {
			background: #007bff;
			color: white;
		}
		
		/* No data message */
		.category-filter-no-data {
			text-align: center;
			padding: 40px 20px;
			color: #6c757d;
			font-style: italic;
		}
		
		.category-filter-group {
			margin-bottom: 20px;
		}
		
		.category-filter-group:last-child {
			margin-bottom: 0;
		}
		
		.category-filter-label {
			font-size: 0.9rem;
			font-weight: 600;
			color: #495057;
			margin-bottom: 8px;
			display: block;
		}
		
		.category-filter-select {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #ced4da;
			border-radius: 6px;
			font-size: 0.9rem;
			background: white;
		}
		
		.category-filter-select:focus {
			border-color: #007bff;
			box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
			outline: none;
		}
		
		.category-filter-actions {
			display: flex;
			gap: 10px;
		}
		
		.category-filter-btn {
			flex: 1;
			padding: 8px 12px;
			border: none;
			border-radius: 6px;
			font-size: 0.85rem;
			font-weight: 500;
			cursor: pointer;
			transition: all 0.2s ease;
		}
		
		.category-filter-btn-primary {
			background: #007bff;
			color: white;
		}
		
		.category-filter-btn-primary:hover {
			background: #0056b3;
		}
		
		.category-filter-btn-secondary {
			background: #6c757d;
			color: white;
		}
		
		.category-filter-btn-secondary:hover {
			background: #545b62;
		}
		
		/* Mobile responsive */
		@media (max-width: 768px) {
			.category-filter-popup {
				top: 70px;
				left: 0;
				transform: translateY(-20px);
				width: calc(100vw - 30px);
				max-width: 350px;
			}
			
			.category-filter-popup.show {
				transform: translateY(0);
			}
			
			.category-filter-menu {
				width: 140px;
			}
			
			.category-filter-content {
				min-width: 200px;
				padding: 15px;
			}
			
			.category-filter-menu-item {
				padding: 12px 8px;
				font-size: 0.8rem;
			}
			
			/* Add extra right margin to mobile category filter icon */
			#categoryFilterIconMobile {
				margin-right: 1.5rem !important;
			}
		}
		
		.nav-separator {
			width: 1px;
			height: 30px;
			background-color: #6c757d;
			margin: 0 8px;
			align-self: center;
		}
		
		/* Hide separator on mobile */
		@media (max-width: 991.98px) {
			.nav-separator {
				display: none !important;
			}
		}
		
		/* Mobile cart positioning - completely separate from menu */
		@media (max-width: 991.98px) {
			.navbar {
				min-height: 70px;
				padding: 0.75rem 0;
			}
			
			.mobile-cart {
				position: fixed;
				right: 15px;
				top: 15px;
				z-index: 1060;
				color: #adb5bd !important;
				background-color: rgba(33, 37, 41, 0.9);
				padding: 8px 12px;
				border-radius: 50%;
				backdrop-filter: blur(10px);
				transition: all 0.3s ease;
			}
			
			.mobile-cart:hover {
				color: #ffffff !important;
				background-color: rgba(33, 37, 41, 1);
				transform: scale(1.1);
			}
		}
		
		/* Bottom Navigation Styles */
		.bottom-nav {
			position: fixed;
			bottom: 0;
			left: 0;
			right: 0;
			background-color: #fff;
			border-top: 1px solid #e9ecef;
			box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
			z-index: 1000;
			padding-bottom: env(safe-area-inset-bottom);
		}
		
		.bottom-nav-container {
			display: flex;
			justify-content: space-around;
			align-items: center;
			padding: 8px 0;
		}
		
		.bottom-nav-item {
			display: flex;
			flex-direction: column;
			align-items: center;
			text-decoration: none;
			color: #6c757d;
			padding: 8px 12px;
			border-radius: 8px;
			transition: all 0.3s ease;
			position: relative;
			min-width: 60px;
		}
		
		.bottom-nav-item i {
			font-size: 1.2rem;
			margin-bottom: 4px;
		}
		
		.bottom-nav-item span {
			font-size: 0.7rem;
			font-weight: 500;
		}
		
		.bottom-nav-item.active {
			color: #007bff;
			background-color: rgba(0, 123, 255, 0.1);
		}
		
		.bottom-nav-item:hover {
			color: #007bff;
			background-color: rgba(0, 123, 255, 0.05);
		}
		
		.bottom-nav-badge {
			position: absolute;
			top: 2px;
			right: 8px;
			background-color: #dc3545;
			color: white;
			border-radius: 50%;
			width: 18px;
			height: 18px;
			font-size: 0.6rem;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: bold;
		}
		
		/* Add bottom padding to body for customer to prevent content being hidden behind bottom nav */
		body.customer-role {
			padding-bottom: 80px;
		}
		
		/* Mobile Profile Submenu Styles */
		.mobile-profile-submenu {
			position: fixed;
			bottom: 80px;
			right: 15px;
			background: white;
			border: 1px solid #e9ecef;
			border-radius: 12px;
			box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
			z-index: 1050;
			opacity: 0;
			visibility: hidden;
			transform: translateY(10px);
			transition: all 0.3s ease;
			min-width: 200px;
			max-width: 250px;
		}
		
		.mobile-profile-submenu.show {
			opacity: 1;
			visibility: visible;
			transform: translateY(0);
		}
		
		.mobile-profile-submenu-header {
			padding: 15px;
			border-bottom: 1px solid #e9ecef;
			background: #f8f9fa;
			border-radius: 12px 12px 0 0;
		}
		
		.mobile-profile-submenu-header h6 {
			margin: 0;
			color: #495057;
			font-weight: 600;
			font-size: 0.9rem;
		}
		
		.mobile-profile-submenu-body {
			padding: 8px 0;
		}
		
		.mobile-profile-submenu-item {
			display: flex;
			align-items: center;
			padding: 12px 15px;
			color: #495057;
			text-decoration: none;
			transition: all 0.2s ease;
			border: none;
			background: none;
			width: 100%;
			text-align: left;
			font-size: 0.9rem;
		}
		
		.mobile-profile-submenu-item:hover {
			background: #f8f9fa;
			color: #007bff;
		}
		
		.mobile-profile-submenu-item i {
			width: 20px;
			margin-right: 10px;
			font-size: 0.9rem;
		}
		
		.mobile-profile-submenu-item.text-danger {
			color: #dc3545 !important;
		}
		
		.mobile-profile-submenu-item.text-danger:hover {
			background: #f8d7da;
			color: #721c24 !important;
		}
		
		.mobile-profile-submenu-divider {
			height: 1px;
			background: #e9ecef;
			margin: 8px 0;
		}
		
		/* Profile button active state */
		.bottom-nav-item.profile-active {
			color: #007bff;
			background-color: rgba(0, 123, 255, 0.1);
		}
		
		/* Account menu styling for Customer role - no border, same background as other items */
		#mobileProfileBtn {
			border: none !important;
			background-color: transparent !important;
		}
		
		#mobileProfileBtn:hover {
			border: none !important;
			background-color: rgba(0, 123, 255, 0.05) !important;
		}
		
		#mobileProfileBtn.active {
			border: none !important;
			background-color: rgba(0, 123, 255, 0.1) !important;
		}
		
		/* Mobile Navbar Styles for Non-Customer Roles */
		@media (max-width: 991.98px) {
			/* Ensure navbar is properly styled on mobile */
			.navbar {
				padding: 0.5rem 0;
			}
			
			.navbar-brand {
				font-size: 1.1rem;
			}
			
			.navbar-brand img {
				width: 40px;
				height: 40px;
			}
			
			/* Ensure proper spacing for mobile navbar */
			.navbar .container {
				padding-left: 15px;
				padding-right: 15px;
			}
			
			/* Mobile navbar toggler styling */
			.navbar-toggler {
				border: 1px solid rgba(255, 255, 255, 0.3);
				padding: 0.25rem 0.5rem;
			}
			
			.navbar-toggler:focus {
				box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
			}
			
			.navbar-toggler-icon {
				background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
			}
			
			/* Mobile navbar collapse */
			.navbar-collapse {
				background-color: rgba(33, 37, 41, 0.95);
				border-radius: 8px;
				margin-top: 10px;
				padding: 15px;
				backdrop-filter: blur(10px);
			}
			
			/* Mobile nav items */
			.navbar-nav .nav-item {
				margin: 5px 0;
			}
			
			.navbar-nav .nav-link {
				padding: 10px 15px;
				border-radius: 6px;
				transition: all 0.3s ease;
			}
			
			.navbar-nav .nav-link:hover {
				background-color: rgba(255, 255, 255, 0.1);
				color: #20c997 !important;
			}
			
			/* Mobile dropdown menus */
			.navbar-nav .dropdown-menu {
				background-color: rgba(33, 37, 41, 0.95);
				border: 1px solid rgba(255, 255, 255, 0.1);
				border-radius: 8px;
				margin-top: 5px;
				backdrop-filter: blur(10px);
				display: none !important;
				position: static !important;
				transform: none !important;
				box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
				width: 100%;
				min-width: auto;
				z-index: 1000;
			}
			
			.navbar-nav .dropdown-menu.show {
				display: block !important;
			}
			
			/* Ensure dropdown is visible when shown */
			.navbar-nav .dropdown-menu.show {
				opacity: 1;
				visibility: visible;
			}
			
			.navbar-nav .dropdown-item {
				color: rgba(255, 255, 255, 0.85);
				padding: 10px 15px;
				border-radius: 4px;
				margin: 2px 0;
				transition: all 0.3s ease;
			}
			
			.navbar-nav .dropdown-item:hover {
				background-color: rgba(255, 255, 255, 0.1);
				color: #20c997;
			}
			
			/* User dropdown in mobile */
			.navbar-nav .dropdown-menu-end {
				right: 0;
				left: auto;
			}
			
			/* Ensure user dropdown is visible on desktop */
			#userDropdown + .dropdown-menu {
				display: none;
				position: absolute;
				top: 100%;
				right: 0;
				left: auto;
				z-index: 1000;
				min-width: 200px;
				background-color: #fff;
				border: 1px solid rgba(0,0,0,.15);
				border-radius: 0.375rem;
				box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
			}
			
			#userDropdown + .dropdown-menu.show {
				display: block !important;
			}
			
			/* Ensure dropdown positioning works correctly */
			.navbar-nav .dropdown {
				position: relative;
			}
			
			/* Make sure dropdown items are visible */
			.navbar-nav .dropdown-item {
				white-space: nowrap;
				overflow: visible;
			}
		}
	</style>
</head>
<body class="d-flex flex-column min-vh-100 <?php echo ($user && $user['role'] === 'customer') ? 'customer-role' : ''; ?>">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 sticky-top">
	<div class="container">
		<a class="navbar-brand p-0 m-0 d-flex align-items-center <?php echo ($user && $user['role'] === 'customer') ? 'd-none d-lg-flex' : ''; ?>" href="dashboard.php"><img src="assets/img/logo-sayap.png" alt="Logo" width="48" height="48" class="me-0"></a>
		
		<?php if ($user && $user['role'] === 'customer'): ?>
			<!-- Category Filter Icon in Navbar -->
			<button class="btn btn-category-filter btn-sm ms-3 d-none d-lg-block" id="categoryFilterIcon" title="Filter Kategori" style="border-radius: 8px; padding: 8px 12px;">
				<i class="fas fa-layer-group me-1"></i>Kategori
			</button>
			<!-- Category Filter Icon for Mobile -->
			<button class="btn btn-category-filter btn-sm d-lg-none me-2" id="categoryFilterIconMobile" title="Filter Kategori" style="border-radius: 8px; padding: 6px 8px;">
				<i class="fas fa-layer-group"></i>
			</button>
			<!-- Search Form for Customer Mobile -->
			<div class="d-flex align-items-center d-lg-none flex-grow-1" style="margin-right: 60px;">
				<form class="d-flex w-100" method="GET" action="dashboard.php">
					<div class="input-group w-100" style="border: 1px solid #6c757d; border-radius: 0.375rem;">
						<input class="form-control form-control-sm" type="search" name="search" placeholder="Cari..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="border: none; box-shadow: none;">
						<button class="btn btn-outline-light btn-sm" type="submit" style="border: none; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#007bff'" onmouseout="this.style.backgroundColor='transparent'">
							<i class="fas fa-search"></i>
						</button>
					</div>
				</form>
			</div>
			<a class="nav-link cart-icon mobile-cart d-lg-none" href="cart.php" title="Keranjang Order">
				<i class="fas fa-shopping-cart"></i>
				<?php if ($cartCount > 0): ?>
					<span class="cart-badge"><?php echo $cartCount; ?></span>
				<?php endif; ?>
			</a>
		<?php endif; ?>
		
		<?php if ($user && $user['role'] !== 'customer'): ?>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<?php endif; ?>
		<div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav me-auto mb-2 mb-lg-0">
				<?php if ($user && $user['role'] !== 'customer'): ?>
					<li class="nav-item"><a class="nav-link nav-dashboard" href="dashboard.php">
						<i class="fas fa-tachometer-alt me-1"></i>Dashboard
					</a></li>
				<?php endif; ?>
				<?php if ($user): ?>
					<?php if (can_access('users')): ?>
						<li class="nav-item"><a class="nav-link" href="users.php">
							<i class="fas fa-user-cog me-1"></i>Users
						</a></li>
					<?php endif; ?>
					
					<!-- Master Dropdown Menu -->
					<?php if (can_access('masterbarang') || can_access('mastercustomer') || can_access('mastersales')): ?>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="masterDropdown" role="button" aria-expanded="false">
								<i class="fas fa-database me-1"></i>Master
							</a>
							<ul class="dropdown-menu" aria-labelledby="masterDropdown">
								<?php if (can_access('masterbarang')): ?>
									<li><a class="dropdown-item" href="masterbarang.php">
										<i class="fas fa-box me-2"></i>Master Barang
									</a></li>
								<?php endif; ?>
								<?php if (can_access('mastercustomer')): ?>
									<li><a class="dropdown-item" href="mastercustomer.php">
										<i class="fas fa-users me-2"></i>Master Customer
									</a></li>
								<?php endif; ?>
								<?php if (can_access('mastersales')): ?>
									<li><a class="dropdown-item" href="mastersales.php">
										<i class="fas fa-user-tie me-2"></i>Master Sales
									</a></li>
								<?php endif; ?>
							</ul>
						</li>
					<?php endif; ?>
					
					<!-- Transaksi Dropdown Menu -->
					<?php if (can_access('order') && $user['role'] !== 'customer'): ?>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="transaksiDropdown" role="button" aria-expanded="false">
								<i class="fas fa-exchange-alt me-1"></i>Transaksi
							</a>
							<ul class="dropdown-menu" aria-labelledby="transaksiDropdown">
								<li><a class="dropdown-item" href="order.php">
									<i class="fas fa-shopping-cart me-2"></i>Transaksi Order
								</a></li>
							</ul>
						</li>
					<?php endif; ?>
					<!-- Reports Dropdown Menu -->
					<?php if (can_access('reports') && $user['role'] !== 'customer'): ?>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" aria-expanded="false">
								<i class="fas fa-chart-bar me-1"></i>Reports
							</a>
							<ul class="dropdown-menu" aria-labelledby="reportsDropdown">
								<li><a class="dropdown-item" href="laporan_transaksi_order.php">
									<i class="fas fa-file-invoice me-2"></i>Laporan Transaksi Order
								</a></li>
								<li><a class="dropdown-item" href="reports.php">
									<i class="fas fa-list me-2"></i>Semua Reports
								</a></li>
							</ul>
						</li>
					<?php endif; ?>
				<?php endif; ?>
			</ul>
			
			<!-- Search Form for Customer -->
			<?php if ($user && $user['role'] === 'customer'): ?>
			<div class="d-flex align-items-center flex-grow-1 justify-content-center px-4 d-none d-md-flex">
				<form class="d-flex w-100" method="GET" action="dashboard.php">
					<div class="input-group w-100" style="border: 1px solid #6c757d; border-radius: 0.375rem;">
						<input class="form-control form-control-sm" type="search" name="search" placeholder="Cari produk..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="border: none; box-shadow: none;">
						<button class="btn btn-outline-light btn-sm" type="submit" style="border: none; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#007bff'" onmouseout="this.style.backgroundColor='transparent'">
							<i class="fas fa-search"></i>
						</button>
					</div>
				</form>
			</div>
			<?php endif; ?>
			<ul class="navbar-nav">
				<?php if ($user): ?>
					<?php if ($user['role'] === 'customer'): ?>
						<!-- Mobile Search Form -->
						<li class="nav-item d-md-none me-2">
							<form class="d-flex" method="GET" action="dashboard.php">
								<div class="input-group" style="border: 1px solid #6c757d; border-radius: 0.375rem;">
									<input class="form-control form-control-sm" type="search" name="search" placeholder="Cari..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="border: none; box-shadow: none; width: 120px;">
									<button class="btn btn-outline-light btn-sm" type="submit" style="border: none; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#007bff'" onmouseout="this.style.backgroundColor='transparent'">
										<i class="fas fa-search"></i>
									</button>
								</div>
							</form>
						</li>
						<li class="nav-item d-none d-lg-block me-3">
							<div class="cart-icon" title="Keranjang Order">
								<a class="nav-link" href="cart.php">
									<i class="fas fa-shopping-cart"></i>
									<?php if ($cartCount > 0): ?>
										<span class="cart-badge"><?php echo $cartCount; ?></span>
									<?php endif; ?>
								</a>
								
								<!-- Cart Popup -->
								<div class="cart-popup" id="cartPopup">
									<div class="cart-popup-header">
										<h6><i class="fas fa-shopping-cart me-2"></i>Keranjang Order</h6>
									</div>
									<div class="cart-popup-body" id="cartPopupBody">
										<!-- Cart items will be loaded here -->
									</div>
									<div class="cart-popup-footer" id="cartPopupFooter" style="display: none;">
										<div class="cart-popup-total">
											<span class="cart-popup-total-label">Total:</span>
											<span class="cart-popup-total-amount" id="cartPopupTotal">Rp 0</span>
										</div>
										<div class="cart-popup-actions">
											<a href="cart.php" class="cart-popup-btn cart-popup-btn-primary">Lihat Keranjang</a>
											<a href="order_form.php?from_cart=1" class="cart-popup-btn cart-popup-btn-secondary">Buat Order</a>
										</div>
									</div>
								</div>
							</div>
						</li>
						<li class="nav-item d-none d-lg-flex align-items-center me-3">
							<div class="nav-separator"></div>
						</li>
					<?php endif; ?>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fas fa-user me-1"></i>Halo, <?php echo htmlspecialchars($user['namalengkap']); ?>
						</a>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
							<?php if ($user['role'] === 'customer'): ?>
								<li><a class="dropdown-item" href="order_form.php">
									<i class="fas fa-shopping-cart me-2"></i>Buat Order
								</a></li>
								<li><a class="dropdown-item" href="order.php">
									<i class="fas fa-list me-2"></i>Lihat Order Saya
								</a></li>
								<li><a class="dropdown-item" href="laporan_transaksi_order.php">
									<i class="fas fa-chart-line me-2"></i>Riwayat Order Saya
								</a></li>
								<li><hr class="dropdown-divider"></li>
							<?php endif; ?>
							<li><a class="dropdown-item" href="profile.php">
								<i class="fas fa-user-edit me-2"></i>Edit Profil
							</a></li>
							<li><a class="dropdown-item" href="change_password.php">
								<i class="fas fa-key me-2"></i>Ubah Password
							</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item text-danger" href="logout.php">
								<i class="fas fa-sign-out-alt me-2"></i>Logout
							</a></li>
						</ul>
					</li>
				<?php else: ?>
					<li class="nav-item"><a class="btn btn-outline-light btn-sm" href="login.php">
						<i class="fas fa-sign-in-alt me-1"></i>Login
					</a></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
</nav>

<?php if ($user && $user['role'] === 'customer'): ?>
<!-- Category Filter Popup -->

<div class="category-filter-popup" id="categoryFilterPopup">
	<!-- Menu Sidebar -->
	<div class="category-filter-menu">
		<div class="category-filter-menu-item active" data-category="pabrik">
			<i class="fas fa-industry me-2"></i>Pabrik
		</div>
		<div class="category-filter-menu-item" data-category="golongan">
			<i class="fas fa-tags me-2"></i>Golongan
		</div>
		<div class="category-filter-menu-item" data-category="kandungan">
			<i class="fas fa-flask me-2"></i>Kandungan
		</div>
		<div class="category-filter-menu-item" data-category="kemasan">
			<i class="fas fa-box me-2"></i>Kemasan
		</div>
	</div>
	
	<!-- Content Area -->
	<div class="category-filter-content">
		<!-- Pabrik Submenu -->
		<div class="category-filter-submenu active" id="submenu-pabrik">
			<div class="category-filter-submenu-items" id="pabrik-items">
				<div class="category-filter-no-data">Memuat data pabrik...</div>
			</div>
		</div>
		
		<!-- Golongan Submenu -->
		<div class="category-filter-submenu" id="submenu-golongan">
			<div class="category-filter-submenu-items" id="golongan-items">
				<div class="category-filter-no-data">Memuat data golongan...</div>
			</div>
		</div>
		
		<!-- Kandungan Submenu -->
		<div class="category-filter-submenu" id="submenu-kandungan">
			<div class="category-filter-submenu-items" id="kandungan-items">
				<div class="category-filter-no-data">Memuat data kandungan...</div>
			</div>
		</div>
		
		<!-- Kemasan Submenu -->
		<div class="category-filter-submenu" id="submenu-kemasan">
			<div class="category-filter-submenu-items" id="kemasan-items">
				<div class="category-filter-no-data">Memuat data kemasan...</div>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ($user && $user['role'] === 'customer'): ?>
<!-- Bottom Navigation for Customer -->
<nav class="bottom-nav d-lg-none">
	<div class="bottom-nav-container">
		<a href="dashboard.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
			<i class="fas fa-home"></i>
			<span>Home</span>
		</a>
		<a href="cart.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : ''; ?>">
			<i class="fas fa-shopping-cart"></i>
			<span>Keranjang</span>
			<?php if ($cartCount > 0): ?>
				<span class="bottom-nav-badge"><?php echo $cartCount; ?></span>
			<?php endif; ?>
		</a>
		<a href="order_form.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'order_form.php' ? 'active' : ''; ?>">
			<i class="fas fa-file-invoice"></i>
			<span>Order</span>
		</a>
		<a href="laporan_transaksi_order.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'laporan_transaksi_order.php' ? 'active' : ''; ?>">
			<i class="fas fa-history"></i>
			<span>Riwayat</span>
		</a>
		<button class="bottom-nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'change_password.php']) ? 'active' : ''; ?>" id="mobileProfileBtn" onclick="toggleMobileProfileSubmenu()">
			<i class="fas fa-user"></i>
			<span>Akun</span>
		</button>
	</div>
</nav>

<!-- Mobile Profile Submenu -->
<div class="mobile-profile-submenu" id="mobileProfileSubmenu">
	<div class="mobile-profile-submenu-header">
		<h6><i class="fas fa-user me-2"></i>Halo, <?php echo htmlspecialchars($user['namalengkap']); ?></h6>
	</div>
	<div class="mobile-profile-submenu-body">
		<?php if ($user['role'] === 'customer'): ?>
			<a href="order_form.php" class="mobile-profile-submenu-item">
				<i class="fas fa-shopping-cart"></i>
				Buat Order
			</a>
			<a href="order.php" class="mobile-profile-submenu-item">
				<i class="fas fa-list"></i>
				Lihat Order Saya
			</a>
			<a href="laporan_transaksi_order.php" class="mobile-profile-submenu-item">
				<i class="fas fa-chart-line"></i>
				Riwayat Order Saya
			</a>
			<div class="mobile-profile-submenu-divider"></div>
		<?php endif; ?>
		<a href="profile.php" class="mobile-profile-submenu-item">
			<i class="fas fa-user-edit"></i>
			Edit Profil
		</a>
		<a href="change_password.php" class="mobile-profile-submenu-item">
			<i class="fas fa-key"></i>
			Ubah Password
		</a>
		<div class="mobile-profile-submenu-divider"></div>
		<a href="logout.php" class="mobile-profile-submenu-item text-danger">
			<i class="fas fa-sign-out-alt"></i>
			Logout
		</a>
	</div>
</div>
<?php endif; ?>

<script>
// Cart Popup functionality
document.addEventListener('DOMContentLoaded', function() {
    const cartIcon = document.querySelector('.cart-icon');
    const cartPopup = document.getElementById('cartPopup');
    const cartPopupBody = document.getElementById('cartPopupBody');
    const cartPopupFooter = document.getElementById('cartPopupFooter');
    const cartPopupTotal = document.getElementById('cartPopupTotal');
    
    if (cartIcon && cartPopup) {
        let cartData = [];
        let isPopupVisible = false;
        
        // Load cart data
        function loadCartData() {
            <?php if ($user && $user['role'] === 'customer'): ?>
            fetch('api/cart.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.items) {
                        cartData = data.data.items || [];
                        updateCartPopup();
                    } else {
                        cartData = [];
                        updateCartPopup();
                    }
                })
                .catch(error => {
                    console.error('Error loading cart data:', error);
                    cartData = [];
                    updateCartPopup();
                });
            <?php endif; ?>
        }
        
        // Update cart popup display
        function updateCartPopup() {
            if (cartData.length === 0) {
                cartPopupBody.innerHTML = `
                    <div class="cart-popup-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Keranjang order kosong</p>
                    </div>
                `;
                cartPopupFooter.style.display = 'none';
            } else {
                let totalPrice = 0;
                let itemsHtml = '';
                
                cartData.forEach(item => {
                    // Use pre-calculated values from API
                    const subtotal = parseFloat(item.subtotal) || 0;
                    totalPrice += subtotal;
                    
                    // Get product image
                    let productImage = 'assets/img/no-image.svg';
                    if (item.foto) {
                        try {
                            const photos = JSON.parse(item.foto);
                            if (photos && photos.length > 0 && photos[0]) {
                                productImage = photos[0];
                            }
                        } catch (e) {
                            // Use default image
                        }
                    }
                    
                    itemsHtml += `
                        <div class="cart-popup-item" onclick="openProductDetail('${item.kodebarang}')">
                            <img src="${productImage}" alt="${item.namabarang}" onerror="this.src='assets/img/no-image.svg'">
                            <div class="cart-popup-item-info">
                                <div class="cart-popup-item-name">${item.namabarang}</div>
                                <div class="cart-popup-item-details">${item.quantity}x ${item.satuan}</div>
                            </div>
                            <div class="cart-popup-item-price">
                                Rp ${subtotal.toLocaleString('id-ID')}
                            </div>
                        </div>
                    `;
                });
                
                cartPopupBody.innerHTML = itemsHtml;
                cartPopupTotal.textContent = `Rp ${totalPrice.toLocaleString('id-ID')}`;
                cartPopupFooter.style.display = 'block';
            }
        }
        
        // Open product detail in background
        window.openProductDetail = function(kodebarang) {
            // Open in new tab/window
            window.open(`customer_product_detail.php?kodebarang=${kodebarang}`, '_blank');
        };
        
        // Show popup on hover
        cartIcon.addEventListener('mouseenter', function() {
            isPopupVisible = true;
            loadCartData();
        });
        
        // Hide popup when mouse leaves
        cartIcon.addEventListener('mouseleave', function(e) {
            // Check if mouse is moving to popup
            if (!cartPopup.contains(e.relatedTarget)) {
                isPopupVisible = false;
            }
        });
        
        // Keep popup visible when hovering over it
        cartPopup.addEventListener('mouseenter', function() {
            isPopupVisible = true;
        });
        
        cartPopup.addEventListener('mouseleave', function() {
            isPopupVisible = false;
        });
        
        // Load initial cart data
        loadCartData();
    }
});

// Category Filter Popup functionality
document.addEventListener('DOMContentLoaded', function() {
    const categoryFilterIcon = document.getElementById('categoryFilterIcon');
    const categoryFilterIconMobile = document.getElementById('categoryFilterIconMobile');
    const categoryFilterPopup = document.getElementById('categoryFilterPopup');
    
    if ((categoryFilterIcon || categoryFilterIconMobile) && categoryFilterPopup) {
        let isPopupVisible = false;
        let hoverTimeout;
        
         // Function to position popup below icon
         function positionPopup(iconElement) {
             const rect = iconElement.getBoundingClientRect();
             const popup = categoryFilterPopup;
             
             // Position popup below the icon
             popup.style.left = rect.left + 'px';
             popup.style.top = (rect.bottom + 10) + 'px';
             
             // Adjust if popup goes off screen
             const popupRect = popup.getBoundingClientRect();
             if (popupRect.right > window.innerWidth) {
                 popup.style.left = (window.innerWidth - popupRect.width - 20) + 'px';
             }
             if (popupRect.left < 0) {
                 popup.style.left = '20px';
             }
         }
         
         // Show popup on hover (desktop) or click (mobile)
         if (categoryFilterIcon) {
             categoryFilterIcon.addEventListener('mouseenter', function() {
                 clearTimeout(hoverTimeout);
                 isPopupVisible = true;
                 categoryFilterIcon.classList.add('popup-active');
                 positionPopup(categoryFilterIcon);
                 categoryFilterPopup.classList.add('show');
                 loadCategoryData();
             });
            
             // Hide popup when mouse leaves icon
             categoryFilterIcon.addEventListener('mouseleave', function() {
                 hoverTimeout = setTimeout(() => {
                     isPopupVisible = false;
                     categoryFilterIcon.classList.remove('popup-active');
                     categoryFilterPopup.classList.remove('show');
                 }, 100); // Small delay to prevent flickering
             });
         }
         
         // Mobile click handler
         if (categoryFilterIconMobile) {
             categoryFilterIconMobile.addEventListener('click', function(e) {
                 e.preventDefault();
                 isPopupVisible = !isPopupVisible;
                 if (isPopupVisible) {
                     categoryFilterIconMobile.classList.add('popup-active');
                     positionPopup(categoryFilterIconMobile);
                     categoryFilterPopup.classList.add('show');
                     loadCategoryData();
                 } else {
                     categoryFilterIconMobile.classList.remove('popup-active');
                     categoryFilterPopup.classList.remove('show');
                 }
             });
         }
        
        // Keep popup visible when hovering over it
        categoryFilterPopup.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            isPopupVisible = true;
        });
        
        // Hide popup when mouse leaves popup
        categoryFilterPopup.addEventListener('mouseleave', function() {
            hoverTimeout = setTimeout(() => {
                isPopupVisible = false;
                categoryFilterPopup.classList.remove('show');
            }, 100);
        });
        
        // Reposition popup on window resize
        window.addEventListener('resize', function() {
            if (isPopupVisible) {
                const activeIcon = categoryFilterIcon?.classList.contains('popup-active') ? categoryFilterIcon : 
                                 categoryFilterIconMobile?.classList.contains('popup-active') ? categoryFilterIconMobile : null;
                if (activeIcon) {
                    positionPopup(activeIcon);
                }
            }
        });
        
        // Load category data from API
        function loadCategoryData() {
            // Get current filter values from URL
            const urlParams = new URLSearchParams(window.location.search);
            const currentPabrik = urlParams.get('pabrik') || '';
            const currentGolongan = urlParams.get('golongan') || '';
            const currentKandungan = urlParams.get('kandungan') || '';
            const currentKemasan = urlParams.get('kemasan') || '';
            
            // Load category options from API
            fetch('api/categories.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateCategorySubmenus(data.data);
                        
                        // Set current selected values
                        setSelectedItems({
                            pabrik: currentPabrik,
                            golongan: currentGolongan,
                            kandungan: currentKandungan,
                            kemasan: currentKemasan
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                });
        }
        
        // Populate category submenus
        function populateCategorySubmenus(categories) {
            // Populate Pabrik
            populateSubmenu('pabrik', categories.pabrik);
            
            // Populate Golongan
            populateSubmenu('golongan', categories.golongan);
            
            // Populate Kandungan
            populateSubmenu('kandungan', categories.kandungan);
            
            // Populate Kemasan
            populateSubmenu('kemasan', categories.kemasan);
        }
        
        // Populate individual submenu
        function populateSubmenu(category, items) {
            const container = document.getElementById(`${category}-items`);
            if (!container) return;
            
            if (items.length === 0) {
                container.innerHTML = '<div class="category-filter-no-data">Kategori tersebut tidak tersedia</div>';
                return;
            }
            
            container.innerHTML = '';
            items.forEach(item => {
                const itemElement = document.createElement('div');
                itemElement.className = 'category-filter-submenu-item';
                itemElement.textContent = item;
                itemElement.dataset.value = item;
                itemElement.onclick = () => selectSubmenuItem(category, item, itemElement);
                container.appendChild(itemElement);
            });
        }
        
        // Set selected items
        function setSelectedItems(selected) {
            Object.keys(selected).forEach(category => {
                if (selected[category]) {
                    const item = document.querySelector(`#${category}-items .category-filter-submenu-item[data-value="${selected[category]}"]`);
                    if (item) {
                        item.classList.add('selected');
                    }
                }
            });
        }
        
        // Select submenu item
        function selectSubmenuItem(category, value, element) {
            // Check if this item is already selected
            if (element.classList.contains('selected')) {
                // If already selected, remove the selection
                element.classList.remove('selected');
                applyFilter(category, ''); // Remove filter
            } else {
                // Remove previous selection in this category
                const container = document.getElementById(`${category}-items`);
                container.querySelectorAll('.category-filter-submenu-item').forEach(item => {
                    item.classList.remove('selected');
                });
                
                // Add selection to clicked item
                element.classList.add('selected');
                
                // Apply filter immediately
                applyFilter(category, value);
            }
        }
        
        // Apply individual filter
        function applyFilter(category, value) {
            const url = new URL(window.location);
            
            // Preserve all existing parameters
            const currentParams = new URLSearchParams(window.location.search);
            
            // Clear only the specific category being changed
            url.searchParams.delete(category);
            
            // Set the new value for the category if provided
            if (value) {
                url.searchParams.set(category, value);
            }
            
            // Preserve all other existing parameters
            for (const [key, val] of currentParams.entries()) {
                if (key !== category) {
                    url.searchParams.set(key, val);
                }
            }
            
            window.location.href = url.toString();
        }
        
        // Add menu item event listeners
        document.querySelectorAll('.category-filter-menu-item').forEach(item => {
            item.addEventListener('click', function() {
                const category = this.dataset.category;
                
                // Remove active class from all menu items
                document.querySelectorAll('.category-filter-menu-item').forEach(menuItem => {
                    menuItem.classList.remove('active');
                });
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Hide all submenus
                document.querySelectorAll('.category-filter-submenu').forEach(submenu => {
                    submenu.classList.remove('active');
                });
                
                // Show selected submenu
                document.getElementById(`submenu-${category}`).classList.add('active');
            });
        });
        
        // Apply filters from popup
        window.applyPopupFilters = function() {
            const pabrik = document.getElementById('popupFilterPabrik').value;
            const golongan = document.getElementById('popupFilterGolongan').value;
            const kandungan = document.getElementById('popupFilterKandungan').value;
            const kemasan = document.getElementById('popupFilterKemasan').value;
            
            // Build URL with filters
            const url = new URL(window.location);
            url.searchParams.delete('pabrik');
            url.searchParams.delete('golongan');
            url.searchParams.delete('kandungan');
            url.searchParams.delete('kemasan');
            
            if (pabrik) url.searchParams.set('pabrik', pabrik);
            if (golongan) url.searchParams.set('golongan', golongan);
            if (kandungan) url.searchParams.set('kandungan', kandungan);
            if (kemasan) url.searchParams.set('kemasan', kemasan);
            
            // Redirect to filtered page
            window.location.href = url.toString();
        };
        
        // Clear all filters
        window.clearPopupFilters = function() {
            const url = new URL(window.location);
            
            // Preserve search query
            const searchQuery = url.searchParams.get('search');
            
            // Clear all filter parameters
            url.searchParams.delete('pabrik');
            url.searchParams.delete('golongan');
            url.searchParams.delete('kandungan');
            url.searchParams.delete('kemasan');
            
            // Restore search query if it exists
            if (searchQuery) {
                url.searchParams.set('search', searchQuery);
            }
            
            // Redirect to unfiltered page
            window.location.href = url.toString();
        };
    }
});

// Mobile Profile Submenu functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileProfileBtn = document.getElementById('mobileProfileBtn');
    const mobileProfileSubmenu = document.getElementById('mobileProfileSubmenu');
    
    if (mobileProfileBtn && mobileProfileSubmenu) {
        let isSubmenuVisible = false;
        
        // Toggle submenu function
        window.toggleMobileProfileSubmenu = function() {
            isSubmenuVisible = !isSubmenuVisible;
            
            if (isSubmenuVisible) {
                mobileProfileSubmenu.classList.add('show');
                mobileProfileBtn.classList.add('profile-active');
            } else {
                mobileProfileSubmenu.classList.remove('show');
                mobileProfileBtn.classList.remove('profile-active');
            }
        };
        
        // Close submenu when clicking outside
        document.addEventListener('click', function(event) {
            if (isSubmenuVisible && 
                !mobileProfileSubmenu.contains(event.target) && 
                !mobileProfileBtn.contains(event.target)) {
                isSubmenuVisible = false;
                mobileProfileSubmenu.classList.remove('show');
                mobileProfileBtn.classList.remove('profile-active');
            }
        });
        
        // Close submenu when clicking on submenu items
        mobileProfileSubmenu.addEventListener('click', function(event) {
            if (event.target.classList.contains('mobile-profile-submenu-item')) {
                // Small delay to allow navigation
                setTimeout(() => {
                    isSubmenuVisible = false;
                    mobileProfileSubmenu.classList.remove('show');
                    mobileProfileBtn.classList.remove('profile-active');
                }, 100);
            }
        });
        
        // Close submenu on window resize
        window.addEventListener('resize', function() {
            if (isSubmenuVisible) {
                isSubmenuVisible = false;
                mobileProfileSubmenu.classList.remove('show');
                mobileProfileBtn.classList.remove('profile-active');
            }
        });
    }
});

// Mobile Navbar Enhancement for Non-Customer Roles
document.addEventListener('DOMContentLoaded', function() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        // Handle dropdown clicks in mobile navbar
        const dropdownToggles = document.querySelectorAll('.navbar-nav .dropdown-toggle');
        
        // User dropdown is now handled by Bootstrap with data-bs-toggle="dropdown"
        
        dropdownToggles.forEach((toggle) => {
            // Skip user dropdown as it's handled by Bootstrap
            if (toggle.id === 'userDropdown') {
                return;
            }
            
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Find the dropdown menu (next sibling ul element)
                const dropdownMenu = this.nextElementSibling;
                
                if (!dropdownMenu) {
                    console.error('Dropdown menu not found for:', this);
                    return;
                }
                
                // Close other open dropdowns
                const otherDropdowns = document.querySelectorAll('.navbar-nav .dropdown-menu.show');
                otherDropdowns.forEach(dropdown => {
                    if (dropdown !== dropdownMenu) {
                        dropdown.classList.remove('show');
                        const toggle = dropdown.previousElementSibling;
                        if (toggle) {
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
                
                // Toggle current dropdown
                const isOpen = dropdownMenu.classList.contains('show');
                
                if (isOpen) {
                    dropdownMenu.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    dropdownMenu.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            });
        });
        
        // Fallback: If no dropdowns were found, try again after a short delay
        if (dropdownToggles.length === 0) {
            setTimeout(() => {
                const retryDropdownToggles = document.querySelectorAll('.navbar-nav .dropdown-toggle');
                
                retryDropdownToggles.forEach((toggle) => {
                    // Skip user dropdown as it's handled by Bootstrap
                    if (toggle.id === 'userDropdown') {
                        return;
                    }
                    
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const dropdownMenu = this.nextElementSibling;
                        
                        if (!dropdownMenu) {
                            console.error('Dropdown menu not found for:', this);
                            return;
                        }
                        
                        const otherDropdowns = document.querySelectorAll('.navbar-nav .dropdown-menu.show');
                        otherDropdowns.forEach(dropdown => {
                            if (dropdown !== dropdownMenu) {
                                dropdown.classList.remove('show');
                                const toggle = dropdown.previousElementSibling;
                                if (toggle) {
                                    toggle.setAttribute('aria-expanded', 'false');
                                }
                            }
                        });
                        
                        const isOpen = dropdownMenu.classList.contains('show');
                        
                        if (isOpen) {
                            dropdownMenu.classList.remove('show');
                            this.setAttribute('aria-expanded', 'false');
                        } else {
                            dropdownMenu.classList.add('show');
                            this.setAttribute('aria-expanded', 'true');
                        }
                    });
                });
            }, 100);
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                const openDropdowns = document.querySelectorAll('.navbar-nav .dropdown-menu.show');
                openDropdowns.forEach(dropdown => {
                    dropdown.classList.remove('show');
                    dropdown.previousElementSibling.setAttribute('aria-expanded', 'false');
                });
            }
        });
        
        // Close navbar when clicking on regular nav links (not dropdown toggles)
        const regularNavLinks = document.querySelectorAll('.navbar-nav .nav-link:not(.dropdown-toggle)');
        regularNavLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Check if navbar is collapsed (mobile view)
                if (navbarCollapse.classList.contains('show')) {
                    // Close the navbar
                    const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                        toggle: false
                    });
                    bsCollapse.hide();
                }
            });
        });
        
        // Close navbar when clicking outside (mobile)
        document.addEventListener('click', function(event) {
            if (navbarCollapse.classList.contains('show') && 
                !navbarCollapse.contains(event.target) && 
                !navbarToggler.contains(event.target)) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });
        
        // Close navbar on window resize to desktop size
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991.98 && navbarCollapse.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });
    }
});
</script>


