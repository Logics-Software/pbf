<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
if ($user['role'] !== 'customer') {
	header('Location: dashboard.php');
	exit;
}

require_once __DIR__ . '/includes/db.php';
$pdo = get_pdo_connection();

$kodebarang = isset($_GET['kodebarang']) ? trim($_GET['kodebarang']) : '';
if ($kodebarang === '') {
	header('Location: dashboard.php?msg=error');
	exit;
}

$stmt = $pdo->prepare('SELECT * FROM masterbarang WHERE kodebarang = ? AND status = "aktif"');
$stmt->execute([$kodebarang]);
$product = $stmt->fetch();

if (!$product) {
	header('Location: dashboard.php?msg=notfound');
	exit;
}

// Parse photos
$photos = [];
if ($product['foto']) {
	try {
		$photos = json_decode($product['foto'], true);
		if (!is_array($photos)) {
			$photos = [$product['foto']]; // Handle old single photo format
		}
	} catch (Exception $e) {
		$photos = [$product['foto']]; // Handle old single photo format
	}
}

// Calculate discount price
$discountPrice = $product['hargajual'] - ($product['hargajual'] * $product['discjual'] / 100);
$hasDiscount = $product['discjual'] > 0;

include __DIR__ . '/includes/header.php';
?>

<style>
.product-detail-container {
	max-width: 1200px;
	margin: 0 auto;
}

/* Image Slider Styles */
.image-slider-container {
	position: relative;
	background: #f8f9fa;
	border-radius: 12px;
	overflow: hidden;
	box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.main-slider {
	position: relative;
	height: 500px;
	overflow: hidden;
}

.slide {
	display: none;
	width: 100%;
	height: 100%;
	position: relative;
}

.slide.active {
	display: block;
}

.slide img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	transition: transform 0.3s ease;
}

.slide img:hover {
	transform: scale(1.05);
}

/* Slider Navigation */
.slider-nav {
	position: absolute;
	top: 50%;
	transform: translateY(-50%);
	background: rgba(255, 255, 255, 0.9);
	color: #007bff;
	border: none;
	width: 50px;
	height: 50px;
	border-radius: 50%;
	font-size: 18px;
	cursor: pointer;
	transition: all 0.3s ease;
	z-index: 10;
	opacity: 0;
	visibility: hidden;
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.slider-nav:hover {
	background: rgba(255, 255, 255, 1);
	color: #0056b3;
	transform: translateY(-50%) scale(1.1);
	box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

/* Show navigation buttons on hover of main image */
.main-slider:hover .slider-nav {
	opacity: 1;
	visibility: visible;
}

.slider-nav.prev {
	left: 20px;
}

.slider-nav.next {
	right: 20px;
}

.slider-nav:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

/* Thumbnail Navigation */
.thumbnail-nav {
	display: flex;
	gap: 10px;
	padding: 15px;
	background: white;
	overflow-x: auto;
	scrollbar-width: thin;
}

.thumbnail-nav::-webkit-scrollbar {
	height: 4px;
}

.thumbnail-nav::-webkit-scrollbar-track {
	background: #f1f1f1;
}

.thumbnail-nav::-webkit-scrollbar-thumb {
	background: #c1c1c1;
	border-radius: 2px;
}

.thumbnail {
	width: 80px;
	height: 80px;
	object-fit: cover;
	border-radius: 8px;
	cursor: pointer;
	border: 3px solid transparent;
	transition: all 0.3s ease;
	flex-shrink: 0;
}

.thumbnail:hover {
	border-color: #007bff;
	transform: scale(1.05);
}

.thumbnail.active {
	border-color: #007bff;
	box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

/* Product Info Styles */
.product-info {
	background: #fff;
	border-radius: 12px;
	padding: 30px;
	box-shadow: 0 4px 20px rgba(0,0,0,0.1);
	height: fit-content;
}

.product-title {
	font-size: 2.5rem;
	font-weight: 700;
	color: #2c3e50;
	margin-bottom: 10px;
	line-height: 1.2;
}

.product-code {
	font-size: 1.1rem;
	color: #6c757d;
	margin-bottom: 20px;
	font-weight: 500;
}

.price-section {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 25px;
	border-radius: 12px;
	margin-bottom: 30px;
	text-align: center;
}

.price-label {
	font-size: 1.1rem;
	margin-bottom: 10px;
	opacity: 0.9;
}

.price-value {
	font-size: 3rem;
	font-weight: 700;
	margin-bottom: 5px;
}

.price-discount {
	font-size: 1.2rem;
	opacity: 0.8;
}

.original-price {
	font-size: 1.5rem;
	text-decoration: line-through;
	opacity: 0.7;
	margin-bottom: 10px;
}

.discount-badge {
	background: #dc3545;
	color: white;
	padding: 8px 16px;
	border-radius: 20px;
	font-size: 1.1rem;
	font-weight: 600;
	display: inline-block;
	margin-bottom: 15px;
}

/* Product Condition Badge Styles */
.condition-badge {
	font-size: 0.9rem;
	font-weight: 600;
	padding: 8px 16px;
	border-radius: 20px;
	display: inline-block;
	margin-bottom: 15px;
	box-shadow: 0 2px 8px rgba(0,0,0,0.15);
	transition: all 0.3s ease;
}

.condition-badge:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.condition-badge.promo {
	background: linear-gradient(135deg, #17a2b8, #138496);
	color: white;
}

.condition-badge.sale {
	background: linear-gradient(135deg, #dc3545, #c82333);
	color: white;
}

.condition-badge.spesial {
	background: linear-gradient(135deg, #ffc107, #e0a800);
	color: #212529;
}

.condition-badge.deals {
	background: linear-gradient(135deg, #28a745, #1e7e34);
	color: white;
}

.product-details {
	margin-bottom: 30px;
}

.detail-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 0;
	border-bottom: 1px solid #e9ecef;
}

.detail-item:last-child {
	border-bottom: none;
}

.detail-label {
	font-weight: 600;
	color: #495057;
	font-size: 1.2rem;
}

.detail-value {
	color: #6c757d;
	font-size: 1.2rem;
}

.stock-badge {
	font-size: 1.2rem;
	padding: 8px 16px;
	border-radius: 20px;
	font-weight: 600;
}

.separator {
	height: 2px;
	background: linear-gradient(90deg, transparent, #dee2e6, transparent);
	margin: 40px 0;
}

.description-section {
	background: #f8f9fa;
	padding: 25px;
	border-radius: 12px;
	border-left: 4px solid #007bff;
}

.description-title {
	font-size: 1.5rem;
	font-weight: 600;
	color: #2c3e50;
	margin-bottom: 20px;
}

.description-content {
	font-size: 1.1rem;
	line-height: 1.6;
	color: #495057;
}

.no-description {
	color: #6c757d;
	font-style: italic;
}


/* No Image Placeholder */
.no-image-placeholder {
	width: 100%;
	height: 100%;
	background: #f8f9fa;
	border: 2px dashed #dee2e6;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #6c757d;
}

.no-image-placeholder svg {
	width: 64px;
	height: 64px;
	margin-bottom: 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
	.product-title {
		font-size: 2rem;
	}
	
	.price-value {
		font-size: 2.5rem;
	}
	
	.main-slider {
		height: 300px;
	}
	
	.slider-nav {
		width: 40px;
		height: 40px;
		font-size: 14px;
	}
	
	.slider-nav.prev {
		left: 10px;
	}
	
	.slider-nav.next {
		right: 10px;
	}
	
	.thumbnail {
		width: 60px;
		height: 60px;
	}
}

/* Slide Counter */
.slide-counter {
	position: absolute;
	bottom: 20px;
	right: 20px;
	background: rgba(0,0,0,0.7);
	color: white;
	padding: 8px 12px;
	border-radius: 20px;
	font-size: 0.9rem;
	z-index: 10;
}

/* Sticky Bottom Action Bar */
.sticky-bottom-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background: white;
	border-top: 1px solid #dee2e6;
	box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
	padding: 10px 0;
	z-index: 1000;
	backdrop-filter: blur(10px);
}

.product-thumbnail {
	width: 50px;
	height: 50px;
	border-radius: 8px;
	overflow: hidden;
	margin-right: 12px;
	flex-shrink: 0;
}

.thumbnail-img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.thumbnail-placeholder {
	width: 100%;
	height: 100%;
	background: #f8f9fa;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #6c757d;
	font-size: 20px;
}

.product-info-summary {
	flex: 1;
}

.product-name {
	font-size: 1.1rem;
	font-weight: 600;
	color: #2c3e50;
	margin-bottom: 0;
	line-height: 1.2;
}

/* Custom button styling for sticky bar */
.sticky-bottom-bar .btn {
	border-radius: 25px;
}

/* Quantity input and total price styling */
.quantity-section {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.quantity-section .form-label {
	font-size: 0.75rem;
	margin-bottom: 2px;
}

.quantity-section .input-group {
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	border-radius: 8px;
	overflow: hidden;
	border: none;
	background-color: #e9ecef;
}

.quantity-section .input-group .btn {
	border-radius: 0;
	font-size: 1rem;
	font-weight: bold;
	padding: 4px 8px;
	border: none;
	background-color: #dee2e6;
	color: #495057;
	transition: background-color 0.2s ease;
}

.quantity-section .input-group .btn:hover {
	background-color: #ced4da;
	color: #343a40;
}

.quantity-section .input-group .form-control {
	border-radius: 0;
	font-size: 0.8rem;
	font-weight: 600;
	border: none;
	background-color: #f8f9fa;
	color: #495057;
	/* Hide number input arrows */
	appearance: textfield;
	-moz-appearance: textfield;
}

.quantity-section .input-group .form-control::-webkit-outer-spin-button,
.quantity-section .input-group .form-control::-webkit-inner-spin-button {
	-webkit-appearance: none;
	margin: 0;
}

.total-price-section {
	display: flex;
	flex-direction: column;
	align-items: center;
	min-width: 80px;
}

.total-price-label {
	font-size: 0.7rem;
	margin-bottom: 2px;
}

.total-price-value {
	font-size: 0.9rem;
	color: #28a745;
}


/* Add bottom padding to main content to prevent overlap */
.product-detail-container {
	padding-bottom: 80px;
}

/* Responsive adjustments for sticky bar */
@media (max-width: 768px) {
	.sticky-bottom-bar {
		padding: 8px 0;
	}
	
	.product-thumbnail {
		width: 45px;
		height: 45px;
		margin-right: 10px;
	}
	
	.product-name {
		font-size: 1rem;
	}
	
	.sticky-bottom-bar .btn {
		padding: 16px 20px;
		font-size: 0.9rem;
	}
	
	.quantity-section .input-group {
		width: 100px !important;
	}
	
	.quantity-section .form-label {
		font-size: 0.7rem;
	}
	
	.total-price-section {
		min-width: 70px;
	}
	
	.total-price-value {
		font-size: 0.8rem;
	}
	
	.sticky-bottom-bar .gap-1 {
		gap: 0.25rem !important;
	}
}

@media (max-width: 576px) {
	.sticky-bottom-bar {
		padding: 6px 0;
	}
	
	.product-thumbnail {
		width: 40px;
		height: 40px;
		margin-right: 8px;
	}
	
	.product-name {
		font-size: 0.9rem;
	}
	
	.sticky-bottom-bar .col-md-6:first-child {
		margin-bottom: 8px;
	}
	
	.sticky-bottom-bar .d-flex.justify-content-end {
		justify-content: center !important;
	}
	
	.sticky-bottom-bar .btn {
		flex: 1;
		max-width: 100px;
		font-size: 0.75rem;
		padding: 12px 16px;
	}
}
</style>

<div class="flex-grow-1">
	<div class="container product-detail-container">
		<!-- Breadcrumb Navigation -->
		<nav aria-label="breadcrumb" class="mb-3">
			<!-- Desktop Breadcrumb -->
			<ol class="breadcrumb d-none d-md-flex">
				<li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
				<li class="breadcrumb-item"><a href="dashboard.php">Produk</a></li>
				<li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['namabarang']); ?></li>
			</ol>
			
			<!-- Mobile Back Button -->
			<div class="d-md-none">
				<a href="dashboard.php" class="btn btn-link p-0 text-decoration-none">
					<i class="fas fa-arrow-left" style="font-size: 1.2rem; color: #495057;"></i>
				</a>
			</div>
		</nav>

		<div class="row g-4">
			<!-- Product Images with Slider -->
			<div class="col-lg-6">
				<div class="image-slider-container">
					<?php if (!empty($photos)): ?>
						<div class="main-slider">
							<?php foreach ($photos as $index => $photo): ?>
								<div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
									<img src="<?php echo htmlspecialchars($photo); ?>" 
										 alt="<?php echo htmlspecialchars($product['namabarang']); ?> - Foto <?php echo $index + 1; ?>">
								</div>
							<?php endforeach; ?>
							
							<!-- Navigation Buttons -->
							<?php if (count($photos) > 1): ?>
								<button class="slider-nav prev" onclick="changeSlide(-1)">
									<i class="fas fa-chevron-left"></i>
								</button>
								<button class="slider-nav next" onclick="changeSlide(1)">
									<i class="fas fa-chevron-right"></i>
								</button>
							<?php endif; ?>
						</div>
						
						<?php if (count($photos) > 1): ?>
							<div class="thumbnail-nav">
								<?php foreach ($photos as $index => $photo): ?>
									<img src="<?php echo htmlspecialchars($photo); ?>" 
										 alt="Thumbnail <?php echo $index + 1; ?>" 
										 class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
										 onclick="goToSlide(<?php echo $index; ?>)">
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php else: ?>
						<div class="main-slider">
							<div class="slide active">
								<div class="no-image-placeholder">
									<div class="text-center">
										<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
											<path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
											<path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
										</svg>
										<p class="mb-0">Tidak ada gambar</p>
									</div>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Product Information -->
			<div class="col-lg-6">
				<div class="product-info">
					<!-- Price Section -->
					<div>
						<?php if ($hasDiscount): ?>
							<div style="text-align: left; padding-bottom: 10px;">
								<span style="display: inline-block; font-size: 2rem; font-weight: 600; margin-right: 15px; color: #007bff;">Rp <?php echo number_format($discountPrice, 0, ',', '.'); ?></span>
								<span style="display: inline-block; font-size: 1.2rem; text-decoration: line-through; margin-right: 15px; color:rgb(176, 182, 187);">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></span>
								<span style="display: inline-block; font-size: 1rem; color:rgb(224, 5, 27); background-color:rgb(216, 184, 187); border-radius: 5px; padding: 5px;"><?php echo number_format($product['discjual'], 0); ?>%</span>
							</div>
						<?php else: ?>
							<div style="text-align: left; padding-bottom: 10px;">
								<span style="display: inline-block; font-size: 2rem; font-weight: 600; margin-right: 15px; color: #007bff;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<h1 class="h4"><?php echo htmlspecialchars($product['namabarang']); ?></h1>
					
					<!-- Product Condition Badge -->
					<?php if ($product['kondisiharga']): ?>
						<div class="mb-3">
							<?php 
							$kondisi = strtolower($product['kondisiharga']);
							$badgeClass = '';
							$badgeText = '';
							
							switch($kondisi) {
								case 'promo':
									$badgeClass = 'bg-info';
									$badgeText = 'PROMO';
									break;
								case 'sale':
									$badgeClass = 'bg-danger';
									$badgeText = 'SALE';
									break;
								case 'spesial':
									$badgeClass = 'bg-warning';
									$badgeText = 'SPESIAL';
									break;
								case 'deals':
									$badgeClass = 'bg-success';
									$badgeText = 'DEALS';
									break;
								default:
									$badgeClass = 'bg-secondary';
									$badgeText = strtoupper($product['kondisiharga']);
							}
							?>
							<span class="condition-badge <?php echo $kondisi; ?>">
								<i class="fas fa-tag me-1"></i><?php echo $badgeText; ?>
							</span>
						</div>
					<?php endif; ?>
					
					<!-- Product Details -->
					<div class="product-details">
						<div class="detail-item border-bottom-0">
							<span class="detail-label">Satuan</span>
							<span class="detail-value"><?php echo htmlspecialchars($product['satuan']); ?></span>
						</div>
						<div class="detail-item border-bottom-0">
							<span class="detail-label">Pabrik</span>
							<span class="detail-value"><?php echo $product['namapabrik'] ? htmlspecialchars($product['namapabrik']) : '-'; ?></span>
						</div>
						<div class="detail-item border-bottom-0">
							<span class="detail-label">Golongan</span>
							<span class="detail-value"><?php echo $product['namagolongan'] ? htmlspecialchars($product['namagolongan']) : '-'; ?></span>
						</div>
						<?php if ($product['supplier']): ?>
						<div class="detail-item border-bottom-0">
							<span class="detail-label">Supplier</span>
							<span class="detail-value"><?php echo htmlspecialchars($product['supplier']); ?></span>
						</div>
						<?php endif; ?>
						<?php if ($product['kemasan']): ?>
						<div class="detail-item border-bottom-0">
							<span class="detail-label">Kemasan</span>
							<span class="detail-value"><?php echo htmlspecialchars($product['kemasan']); ?></span>
						</div>
						<?php endif; ?>
						<?php if ($product['nie']): ?>
						<div class="detail-item border-bottom-0">
							<span class="detail-label">NIE</span>
							<span class="detail-value"><?php echo htmlspecialchars($product['nie']); ?></span>
						</div>
						<?php endif; ?>
						<div class="detail-item border-bottom-0">
							<span class="detail-label">Stok</span>
							<span class="detail-value">
								<span class="stock-badge <?php echo $product['stokakhir'] > 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
									<?php echo number_format($product['stokakhir'], 0, ',', '.'); ?>
								</span>
							</span>
						</div>
					</div>
					
					<!-- Separator -->
					<div class="separator"></div>
					
					<!-- Description Section -->
					<div class="description-section">
						<h3 class="description-title">Deskripsi/Spesifikasi Produk</h3>
						<div class="description-content">
							<?php if ($product['deskripsi']): ?>
								<?php echo $product['deskripsi']; ?>
							<?php else: ?>
								<p class="no-description">Tidak ada deskripsi produk yang tersedia.</p>
							<?php endif; ?>
						</div>
					</div>
					
					<?php if ($product['kandungan']): ?>
					<!-- Separator -->
					<div class="separator"></div>
					
					<!-- Kandungan Section -->
					<div class="description-section">
						<h3 class="description-title">Kandungan/Komposisi</h3>
						<div class="description-content">
							<?php echo nl2br(htmlspecialchars($product['kandungan'])); ?>
						</div>
					</div>
					<?php endif; ?>
					
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Sticky Bottom Action Bar -->
<div class="sticky-bottom-bar">
	<div class="container">
		<div class="row align-items-center">
			<!-- Product Info (Left Side) -->
			<div class="col-md-6">
				<div class="d-flex align-items-center">
					<div class="product-thumbnail">
						<?php if (!empty($photos)): ?>
							<img src="<?php echo htmlspecialchars($photos[0]); ?>" 
								 alt="<?php echo htmlspecialchars($product['namabarang']); ?>"
								 class="thumbnail-img">
						<?php else: ?>
							<div class="thumbnail-placeholder">
								<i class="fas fa-image"></i>
							</div>
						<?php endif; ?>
					</div>
					<div class="product-info-summary">
						<h6 class="product-name"><?php echo htmlspecialchars($product['namabarang']); ?></h6>
					</div>
				</div>
			</div>
			
			<!-- Action Buttons (Right Side) -->
			<div class="col-md-6">
				<div class="d-flex justify-content-end align-items-center gap-2">
					<!-- Quantity Input and Total Price -->
					<div class="d-flex align-items-center gap-2">
						<div class="quantity-section">
							<div class="input-group input-group-sm" style="width: 120px;">
								<button class="btn btn-outline-secondary btn-sm" type="button" onclick="decreaseQuantity()">-</button>
								<input type="number" class="form-control text-center" id="quantityInput" value="1" min="1" max="<?php echo $product['stokakhir']; ?>" onchange="updateTotalPrice()">
								<button class="btn btn-outline-secondary btn-sm" type="button" onclick="increaseQuantity()">+</button>
							</div>
						</div>
						<div class="total-price-section">
							<div class="total-price-label small text-muted">Total Harga:</div>
							<div class="total-price-value fw-bold" id="totalPriceDisplay">
								Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?>
							</div>
						</div>
					</div>
					
					<!-- Action Buttons -->
					<div class="d-flex gap-1">
						<?php if ($product['stokakhir'] > 0): ?>
							<button class="btn btn-success" onclick="buyNowWithQuantity('<?php echo $product['kodebarang']; ?>')">
								<i class="fas fa-bolt me-1"></i>
								<span class="d-none d-md-inline">Order Sekarang</span>
								<span class="d-md-none">Order</span>
							</button>
							<button class="btn btn-primary" onclick="addToCartWithQuantity('<?php echo $product['kodebarang']; ?>')">
								<i class="fas fa-cart-plus me-1"></i>
								<span class="d-none d-md-inline">Tambah ke Keranjang</span>
								<span class="d-md-none">Keranjang</span>
							</button>
						<?php else: ?>
							<button class="btn btn-secondary" disabled>
								<i class="fas fa-times-circle me-1"></i>
								Stok Habis
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Image Slider Functionality
let currentSlideIndex = 0;
const slides = document.querySelectorAll('.slide');
const thumbnails = document.querySelectorAll('.thumbnail');
const totalSlides = slides.length;

function showSlide(index) {
	// Hide all slides
	slides.forEach(slide => slide.classList.remove('active'));
	thumbnails.forEach(thumb => thumb.classList.remove('active'));
	
	// Show current slide
	if (slides[index]) {
		slides[index].classList.add('active');
	}
	if (thumbnails[index]) {
		thumbnails[index].classList.add('active');
	}
	
	// Update counter
	document.getElementById('currentSlide').textContent = index + 1;
	
	// Update navigation buttons
	const prevBtn = document.querySelector('.slider-nav.prev');
	const nextBtn = document.querySelector('.slider-nav.next');
	
	if (prevBtn) prevBtn.disabled = index === 0;
	if (nextBtn) nextBtn.disabled = index === totalSlides - 1;
}

function changeSlide(direction) {
	currentSlideIndex += direction;
	
	// Loop around if needed
	if (currentSlideIndex >= totalSlides) {
		currentSlideIndex = 0;
	} else if (currentSlideIndex < 0) {
		currentSlideIndex = totalSlides - 1;
	}
	
	showSlide(currentSlideIndex);
}

function goToSlide(index) {
	currentSlideIndex = index;
	showSlide(currentSlideIndex);
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
	if (e.key === 'ArrowLeft') {
		changeSlide(-1);
	} else if (e.key === 'ArrowRight') {
		changeSlide(1);
	}
});

// Touch/swipe support for mobile
let touchStartX = 0;
let touchEndX = 0;

document.querySelector('.main-slider').addEventListener('touchstart', function(e) {
	touchStartX = e.changedTouches[0].screenX;
});

document.querySelector('.main-slider').addEventListener('touchend', function(e) {
	touchEndX = e.changedTouches[0].screenX;
	handleSwipe();
});

function handleSwipe() {
	const swipeThreshold = 50;
	const diff = touchStartX - touchEndX;
	
	if (Math.abs(diff) > swipeThreshold) {
		if (diff > 0) {
			// Swipe left - next slide
			changeSlide(1);
		} else {
			// Swipe right - previous slide
			changeSlide(-1);
		}
	}
}

// Auto-play slider (optional)
let autoPlayInterval;

function startAutoPlay() {
	if (totalSlides > 1) {
		autoPlayInterval = setInterval(() => {
			changeSlide(1);
		}, 5000); // Change slide every 5 seconds
	}
}

function stopAutoPlay() {
	if (autoPlayInterval) {
		clearInterval(autoPlayInterval);
	}
}

// Start auto-play when page loads
document.addEventListener('DOMContentLoaded', function() {
	// Initialize slider
	showSlide(0);
	
	// Start auto-play
	startAutoPlay();
	
	// Pause auto-play on hover
	const sliderContainer = document.querySelector('.image-slider-container');
	if (sliderContainer) {
		sliderContainer.addEventListener('mouseenter', stopAutoPlay);
		sliderContainer.addEventListener('mouseleave', startAutoPlay);
	}
});

// Add to cart functionality
function addToCart(kodebarang) {
	// Redirect to order form with pre-filled product
	window.location.href = `order_form.php?product=${kodebarang}`;
}

// Buy now functionality - direct order creation
function buyNow(kodebarang) {
	// Redirect to order form with pre-filled product and quantity 1
	window.location.href = `order_form.php?product=${kodebarang}&quantity=1&buy_now=1`;
}

// Buy now with quantity functionality
function buyNowWithQuantity(kodebarang) {
	const quantity = document.getElementById('quantityInput').value;
	const customerCode = '<?php echo $user['kodecustomer'] ?? ''; ?>';
	window.location.href = `order_form.php?product=${kodebarang}&quantity=${quantity}&buy_now=1&customer=${customerCode}`;
}

// Add to cart with quantity functionality
function addToCartWithQuantity(kodebarang) {
	const quantity = document.getElementById('quantityInput').value;
	
	// Add item to cart via API
	fetch('api/cart.php', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			kodebarang: kodebarang,
			quantity: parseInt(quantity)
		})
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			// Show success message
			showCartMessage('Item berhasil ditambahkan ke keranjang!', 'success');
			// Update cart count in header
			updateCartCount();
		} else {
			showCartMessage(data.message || 'Gagal menambahkan item ke keranjang', 'error');
		}
	})
	.catch(error => {
		console.error('Error:', error);
		showCartMessage('Terjadi kesalahan saat menambahkan item ke keranjang', 'error');
	});
}

// Show cart message
function showCartMessage(message, type) {
	// Create message element
	const messageDiv = document.createElement('div');
	messageDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
	messageDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
	messageDiv.innerHTML = `
		${message}
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	`;
	
	// Add to body
	document.body.appendChild(messageDiv);
	
	// Auto remove after 3 seconds
	setTimeout(() => {
		if (messageDiv.parentNode) {
			messageDiv.parentNode.removeChild(messageDiv);
		}
	}, 3000);
}

// Update cart count in header
function updateCartCount() {
	fetch('api/cart.php')
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				const cartBadges = document.querySelectorAll('.cart-badge');
				const totalItems = data.data.summary.total_items;
				
				cartBadges.forEach(badge => {
					if (totalItems > 0) {
						badge.textContent = totalItems;
						badge.style.display = 'inline';
					} else {
						badge.style.display = 'none';
					}
				});
			}
		})
		.catch(error => {
			console.error('Error updating cart count:', error);
		});
}

// Quantity management functions
function increaseQuantity() {
	const input = document.getElementById('quantityInput');
	const currentValue = parseInt(input.value);
	const maxValue = parseInt(input.getAttribute('max'));
	
	if (currentValue < maxValue) {
		input.value = currentValue + 1;
		updateTotalPrice();
	}
}

function decreaseQuantity() {
	const input = document.getElementById('quantityInput');
	const currentValue = parseInt(input.value);
	const minValue = parseInt(input.getAttribute('min'));
	
	if (currentValue > minValue) {
		input.value = currentValue - 1;
		updateTotalPrice();
	}
}

// Update total price based on quantity
function updateTotalPrice() {
	const quantity = parseInt(document.getElementById('quantityInput').value) || 1;
	const unitPrice = <?php echo $product['hargajual']; ?>;
	const discount = <?php echo $product['discjual']; ?>;
	
	// Calculate price with discount
	const discountAmount = (unitPrice * discount) / 100;
	const finalUnitPrice = unitPrice - discountAmount;
	
	// Calculate total
	const total = quantity * finalUnitPrice;
	
	// Update display
	document.getElementById('totalPriceDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');
}
</script>
