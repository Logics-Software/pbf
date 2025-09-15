<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();
$user = current_user();

// Get database connection
$pdo = get_pdo_connection();

// Get statistics based on user role
$stats = [];
$chartData = [];

if ($user['role'] === 'admin' || $user['role'] === 'operator') {
    // Get total counts for admin/operator
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user WHERE status = 'aktif'");
    $stats['users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM masterbarang WHERE status = 'aktif'");
    $stats['barang'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mastercustomer WHERE status = 'aktif'");
    $stats['customer'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mastersales WHERE status = 'aktif'");
    $stats['sales'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder");
    $stats['orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder WHERE status = 'idle'");
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder WHERE status = 'terima'");
    $stats['completed_orders'] = $stmt->fetch()['total'];
} elseif ($user['role'] === 'sales') {
    // Get sales-specific statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodesales = ?");
    $stmt->execute([$user['kodesales']]);
    $stats['my_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodesales = ? AND status = 'idle'");
    $stmt->execute([$user['kodesales']]);
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodesales = ? AND status = 'terima'");
    $stmt->execute([$user['kodesales']]);
    $stats['completed_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT SUM(totalorder) as total FROM headerorder WHERE kodesales = ? AND status = 'terima'");
    $stmt->execute([$user['kodesales']]);
    $stats['total_sales'] = $stmt->fetch()['total'] ?? 0;
    
    // Get customer count for this sales
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kodecustomer) as total FROM headerorder WHERE kodesales = ?");
    $stmt->execute([$user['kodesales']]);
    $stats['my_customers'] = $stmt->fetch()['total'];
} elseif ($user['role'] === 'customer') {
    // Get customer-specific statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodecustomer = ?");
    $stmt->execute([$user['kodecustomer']]);
    $stats['my_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodecustomer = ? AND status = 'idle'");
    $stmt->execute([$user['kodecustomer']]);
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodecustomer = ? AND status = 'terima'");
    $stmt->execute([$user['kodecustomer']]);
    $stats['completed_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT SUM(totalorder) as total FROM headerorder WHERE kodecustomer = ? AND status = 'terima'");
    $stmt->execute([$user['kodecustomer']]);
    $stats['total_purchases'] = $stmt->fetch()['total'] ?? 0;
    
    // Get pending orders for tracking
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE kodecustomer = ? AND status IN ('proses', 'faktur', 'kirim')");
    $stmt->execute([$user['kodecustomer']]);
    $stats['in_progress_orders'] = $stmt->fetch()['total'];
    
    // Get categories for filtering
    $categories = [];
    
    // Get distinct pabrik
    $stmt = $pdo->query("SELECT DISTINCT namapabrik FROM masterbarang WHERE status = 'aktif' AND namapabrik IS NOT NULL AND namapabrik != '' ORDER BY namapabrik");
    $categories['pabrik'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get distinct golongan
    $stmt = $pdo->query("SELECT DISTINCT namagolongan FROM masterbarang WHERE status = 'aktif' AND namagolongan IS NOT NULL AND namagolongan != '' ORDER BY namagolongan");
    $categories['golongan'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get distinct kemasan
    $stmt = $pdo->query("SELECT DISTINCT kemasan FROM masterbarang WHERE status = 'aktif' AND kemasan IS NOT NULL AND kemasan != '' ORDER BY kemasan");
    $categories['kemasan'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get distinct kandungan (split by comma)
    $stmt = $pdo->query("SELECT DISTINCT kandungan FROM masterbarang WHERE status = 'aktif' AND kandungan IS NOT NULL AND kandungan != ''");
    $kandunganRaw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $kandunganList = [];
    foreach ($kandunganRaw as $kandungan) {
        $items = array_map('trim', explode(',', $kandungan));
        foreach ($items as $item) {
            if (!empty($item) && !in_array($item, $kandunganList)) {
                $kandunganList[] = $item;
            }
        }
    }
    sort($kandunganList);
    $categories['kandungan'] = $kandunganList;

    // Get all products for lazy loading display with search functionality
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filterPabrik = isset($_GET['pabrik']) ? trim($_GET['pabrik']) : '';
    $filterGolongan = isset($_GET['golongan']) ? trim($_GET['golongan']) : '';
    $filterKandungan = isset($_GET['kandungan']) ? trim($_GET['kandungan']) : '';
    $filterKemasan = isset($_GET['kemasan']) ? trim($_GET['kemasan']) : '';
    $filterKondisiHarga = isset($_GET['kondisiharga']) ? trim($_GET['kondisiharga']) : '';
    $sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'terbaru';
    
    $whereConditions = ["status = 'aktif'", "stokakhir > 0"];
    $params = [];
    
    if (!empty($searchQuery)) {
        $whereConditions[] = "(namabarang LIKE ? OR kodebarang LIKE ? OR namapabrik LIKE ? OR namagolongan LIKE ? OR supplier LIKE ? OR kemasan LIKE ? OR nie LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($filterPabrik)) {
        $whereConditions[] = "namapabrik = ?";
        $params[] = $filterPabrik;
    }
    
    if (!empty($filterGolongan)) {
        $whereConditions[] = "namagolongan = ?";
        $params[] = $filterGolongan;
    }
    
    if (!empty($filterKandungan)) {
        $whereConditions[] = "kandungan LIKE ?";
        $params[] = '%' . $filterKandungan . '%';
    }
    
    if (!empty($filterKemasan)) {
        $whereConditions[] = "kemasan = ?";
        $params[] = $filterKemasan;
    }
    
    if (!empty($filterKondisiHarga)) {
        $whereConditions[] = "kondisiharga = ?";
        $params[] = $filterKondisiHarga;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Determine sorting order
    $orderBy = '';
    switch($sortBy) {
        case 'terlaris':
            $orderBy = 'ORDER BY (hargajual * stokakhir) DESC'; // Best selling based on value
            break;
        case 'termurah':
            $orderBy = 'ORDER BY hargajual ASC';
            break;
        case 'termahal':
            $orderBy = 'ORDER BY hargajual DESC';
            break;
        case 'terbaru':
        default:
            $orderBy = 'ORDER BY RAND()';
            break;
    }
    
    $sql = "SELECT kodebarang, namabarang, deskripsi, satuan, namapabrik, namagolongan, hargajual, discjual, kondisiharga, stokakhir, foto, supplier, kemasan, nie, kandungan, created_at FROM masterbarang WHERE $whereClause $orderBy";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Randomize product order for better variety
    shuffle($products);
    
    // Get sale products (max 12 items)
    $saleProducts = [];
    $totalSaleProducts = 0;
    
    // Count total sale products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM masterbarang WHERE status = 'aktif' AND stokakhir > 0 AND kondisiharga = 'sale'");
    $totalSaleProducts = $stmt->fetch()['total'];
    
    // Get all sale products for sliding
    if ($totalSaleProducts > 0) {
        $stmt = $pdo->query("SELECT kodebarang, namabarang, deskripsi, satuan, namapabrik, namagolongan, hargajual, discjual, kondisiharga, stokakhir, foto, supplier, kemasan, nie, kandungan FROM masterbarang WHERE status = 'aktif' AND stokakhir > 0 AND kondisiharga = 'sale' ORDER BY RAND()");
        $saleProducts = $stmt->fetchAll();
    }
    
    // Get promo products (max 6 items)
    $promoProducts = [];
    $totalPromoProducts = 0;
    
    // Count total promo products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM masterbarang WHERE status = 'aktif' AND stokakhir > 0 AND kondisiharga = 'promo'");
    $totalPromoProducts = $stmt->fetch()['total'];
    
    // Get all promo products for sliding
    if ($totalPromoProducts > 0) {
        $stmt = $pdo->query("SELECT kodebarang, namabarang, deskripsi, satuan, namapabrik, namagolongan, hargajual, discjual, kondisiharga, stokakhir, foto, supplier, kemasan, nie, kandungan FROM masterbarang WHERE status = 'aktif' AND stokakhir > 0 AND kondisiharga = 'promo' ORDER BY RAND()");
        $promoProducts = $stmt->fetchAll();
    }
    
    // Get baru products (max 5 items)
    $baruProducts = [];
    $totalBaruProducts = 0;
    
    // Count total baru products
    $stmt = $pdo->query("SELECT COUNT(*) FROM masterbarang WHERE status = 'aktif' AND stokakhir > 0 AND kondisiharga = 'baru'");
    $totalBaruProducts = (int)$stmt->fetchColumn();
    
    // Get all baru products
    if ($totalBaruProducts > 0) {
        $stmt = $pdo->query("SELECT kodebarang, namabarang, deskripsi, satuan, namapabrik, namagolongan, hargajual, discjual, kondisiharga, stokakhir, foto, supplier, kemasan, nie, kandungan FROM masterbarang WHERE status = 'aktif' AND stokakhir > 0 AND kondisiharga = 'baru' ORDER BY RAND() LIMIT 5");
        $baruProducts = $stmt->fetchAll();
    }
} elseif ($user['role'] === 'manajemen') {
    // Get management-specific statistics and chart data
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user WHERE status = 'aktif'");
    $stats['users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM masterbarang WHERE status = 'aktif'");
    $stats['barang'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mastercustomer WHERE status = 'aktif'");
    $stats['customer'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mastersales WHERE status = 'aktif'");
    $stats['sales'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder");
    $stats['orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder WHERE status = 'idle'");
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder WHERE status = 'terima'");
    $stats['completed_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM headerorder WHERE status = 'batal'");
    $stats['cancelled_orders'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT SUM(totalorder) as total FROM headerorder");
    $stats['total_all_orders'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(totalorder) as total FROM headerorder WHERE status = 'terima'");
    $stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Get YTD monthly data for charts
    $currentYear = date('Y');
    $months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    // Monthly order counts
    $chartData['labels'] = $monthNames;
    $chartData['orderCounts'] = [];
    $chartData['orderValues'] = [];
    $chartData['cancelledCounts'] = [];
    $chartData['customerCounts'] = [];
    
    foreach ($months as $month) {
        // Order counts per month
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE YEAR(tanggalorder) = ? AND MONTH(tanggalorder) = ?");
        $stmt->execute([$currentYear, $month]);
        $chartData['orderCounts'][] = $stmt->fetch()['total'];
        
        // Order values per month
        $stmt = $pdo->prepare("SELECT SUM(totalorder) as total FROM headerorder WHERE YEAR(tanggalorder) = ? AND MONTH(tanggalorder) = ? AND status = 'terima'");
        $stmt->execute([$currentYear, $month]);
        $chartData['orderValues'][] = $stmt->fetch()['total'] ?? 0;
        
        // Cancelled orders per month
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM headerorder WHERE YEAR(tanggalorder) = ? AND MONTH(tanggalorder) = ? AND status = 'batal'");
        $stmt->execute([$currentYear, $month]);
        $chartData['cancelledCounts'][] = $stmt->fetch()['total'];
        
        // New customers per month (first order date) - MariaDB compatible version
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT h1.kodecustomer) as total 
            FROM headerorder h1 
            WHERE YEAR(h1.tanggalorder) = ? 
            AND MONTH(h1.tanggalorder) = ? 
            AND h1.kodecustomer NOT IN (
                SELECT DISTINCT h2.kodecustomer 
                FROM headerorder h2 
                WHERE YEAR(h2.tanggalorder) < ? 
                OR (YEAR(h2.tanggalorder) = ? AND MONTH(h2.tanggalorder) < ?)
            )
        ");
        $stmt->execute([$currentYear, $month, $currentYear, $currentYear, $month]);
        $chartData['customerCounts'][] = $stmt->fetch()['total'];
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* Flash Sale Fire Icon Animation */
@keyframes fireFlicker {
    0% {
        transform: scale(1) rotate(-2deg);
        filter: brightness(1) drop-shadow(0 0 5px #ff4444);
    }
    25% {
        transform: scale(1.05) rotate(1deg);
        filter: brightness(1.2) drop-shadow(0 0 8px #ff6666);
    }
    50% {
        transform: scale(1.1) rotate(-1deg);
        filter: brightness(1.4) drop-shadow(0 0 10px #ff8888);
    }
    75% {
        transform: scale(1.05) rotate(2deg);
        filter: brightness(1.2) drop-shadow(0 0 8px #ff6666);
    }
    100% {
        transform: scale(1) rotate(-2deg);
        filter: brightness(1) drop-shadow(0 0 5px #ff4444);
    }
}

/* Flash Sale Navigation Buttons */
.flash-sale-nav-btn {
    opacity: 0 !important;
    visibility: hidden !important;
    transition: all 0.3s ease !important;
}

.flash-sale-nav-btn:hover {
    background: rgba(108, 117, 125, 0.9) !important;
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
}

.flash-sale-nav-btn:disabled {
    background: rgba(108, 117, 125, 0.5) !important;
    cursor: not-allowed !important;
    transform: translateY(-50%) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

.flash-sale-nav-btn:disabled:hover {
    background: rgba(108, 117, 125, 0.5) !important;
    transform: translateY(-50%) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

/* Show navigation buttons on Flash Sale section hover only when sliding is active */
#flashSaleContainer:hover .flash-sale-nav-btn {
    opacity: 1 !important;
    visibility: visible !important;
}

/* Flash Sale Card Responsive Sizing */
@media (max-width: 991.98px) {
    .flash-sale-slide > div {
        flex: 0 0 calc(33.333% - 10px) !important;
        max-width: calc(33.333% - 10px) !important;
    }
}

@media (max-width: 575.98px) {
    .flash-sale-slide > div {
        flex: 0 0 calc(50% - 7.5px) !important;
        max-width: calc(50% - 7.5px) !important;
    }
}

/* Promo Section Gift Icon Animation */
@keyframes giftBounce {
    0% {
        transform: scale(1) rotate(0deg);
        filter: brightness(1) drop-shadow(0 0 5px #ffc107);
    }
    25% {
        transform: scale(1.1) rotate(-5deg);
        filter: brightness(1.2) drop-shadow(0 0 8px #ffc107);
    }
    50% {
        transform: scale(1.2) rotate(5deg);
        filter: brightness(1.4) drop-shadow(0 0 10px #ffc107);
    }
    75% {
        transform: scale(1.1) rotate(-3deg);
        filter: brightness(1.2) drop-shadow(0 0 8px #ffc107);
    }
    100% {
        transform: scale(1) rotate(0deg);
        filter: brightness(1) drop-shadow(0 0 5px #ffc107);
    }
}

/* Promo Navigation Buttons */
.promo-nav-btn {
    opacity: 0 !important;
    visibility: hidden !important;
    transition: all 0.3s ease !important;
}

.promo-nav-btn:hover {
    background: rgba(108, 117, 125, 0.9) !important;
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
}

.promo-nav-btn:disabled {
    background: rgba(108, 117, 125, 0.5) !important;
    cursor: not-allowed !important;
    transform: translateY(-50%) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

.promo-nav-btn:disabled:hover {
    background: rgba(108, 117, 125, 0.5) !important;
    transform: translateY(-50%) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

/* Show navigation buttons on Promo section hover */
#promoContainer:hover .promo-nav-btn {
    opacity: 1 !important;
    visibility: visible !important;
}

/* Promo Card Responsive Sizing */
@media (max-width: 991.98px) {
    .promo-slide > div {
        flex: 0 0 calc(33.333% - 10px) !important;
        max-width: calc(33.333% - 10px) !important;
    }
}

@media (max-width: 575.98px) {
    .promo-slide > div {
        flex: 0 0 calc(50% - 7.5px) !important;
        max-width: calc(50% - 7.5px) !important;
    }
}

.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}
.customer-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: white;
}
.customer-card .card-body {
    padding: 1rem 0.75rem;
}
.customer-card .card-body h4 {
    margin-bottom: 0.25rem !important;
    font-size: 1.1rem;
}
.customer-card .card-body p {
    margin-bottom: 0 !important;
    font-size: 0.8rem;
}
.product-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e9ecef;
}
.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.product-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    background-color: #f8f9fa;
    border-radius: 8px 8px 0 0;
}
.product-image.lazy {
    opacity: 0;
    transition: opacity 0.3s;
}
.product-image.loaded {
    opacity: 1;
}
.product-price {
    font-size: 1rem;
    font-weight: 600;
    color: #28a745;
}
.product-price-original {
    font-size: 0.9rem;
    color: #6c757d;
    text-decoration: line-through;
}
.discount-badge {
	position: absolute;
	top: 10px;
	right: 10px;
	background: #ff6b6b;
	color: white;
	padding: 3px 6px;
	border-radius: 10px;
	font-size: 0.65rem;
	font-weight: 600;
	box-shadow: 0 2px 4px rgba(255, 107, 107, 0.3);
}
.promo-badge {
	position: absolute;
	top: 10px;
	left: 10px;
	background: #ffd93d;
	color: #212529;
	padding: 3px 6px;
	border-radius: 10px;
	font-size: 0.65rem;
	font-weight: 600;
	box-shadow: 0 2px 4px rgba(255, 217, 61, 0.3);
}

.sale-badge {
	position: absolute;
	top: 10px;
	left: 10px;
	background: #4caf50;
	color: white;
	padding: 3px 6px;
	border-radius: 10px;
	font-size: 0.65rem;
	font-weight: 600;
	box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
}

.spesial-badge {
	position: absolute;
	top: 10px;
	left: 10px;
	background: #ffeb3b;
	color: #212529;
	padding: 3px 6px;
	border-radius: 10px;
	font-size: 0.65rem;
	font-weight: 600;
	box-shadow: 0 2px 4px rgba(255, 235, 59, 0.3);
}

.deals-badge {
	position: absolute;
	top: 10px;
	left: 10px;
	background: #81c784;
	color: white;
	padding: 3px 6px;
	border-radius: 10px;
	font-size: 0.65rem;
	font-weight: 600;
	box-shadow: 0 2px 4px rgba(129, 199, 132, 0.3);
}
.baru-badge {
	position: absolute;
	top: 10px;
	left: 10px;
	background: #ff00ff;
	color: white;
	padding: 3px 6px;
	border-radius: 10px;
	font-size: 0.65rem;
	font-weight: 600;
	box-shadow: 0 2px 4px rgba(255, 0, 255, 0.3);
}

/* Animation for Produk Baru section star icon */
.baru-section-star {
	animation: baruStarPulse 2.5s ease-in-out infinite;
}

@keyframes baruStarPulse {
	0% {
		transform: scale(1) rotate(0deg);
		opacity: 1;
	}
	25% {
		transform: scale(1.1) rotate(5deg);
		opacity: 0.9;
	}
	50% {
		transform: scale(1.2) rotate(0deg);
		opacity: 0.8;
	}
	75% {
		transform: scale(1.1) rotate(-5deg);
		opacity: 0.9;
	}
	100% {
		transform: scale(1) rotate(0deg);
		opacity: 1;
	}
}

/* Shadow for Produk Baru section product images */
.baru-section .product-image {
	box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
	transition: box-shadow 0.3s ease;
}

.baru-section .product-image:hover {
	box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
}

/* Shadow for Flash Sale section product images */
.flash-sale-section .product-image {
	box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
	transition: box-shadow 0.3s ease;
}

.flash-sale-section .product-image:hover {
	box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
}

/* Shadow for Promo section product images */
.promo-section .product-image {
	box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
	transition: box-shadow 0.3s ease;
}

.promo-section .product-image:hover {
	box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
}
.stock-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(255,255,255,0.9);
    color: #495057;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.6rem;
    border: 1px solid rgba(0,0,0,0.1);
}
.product-title {
    font-size: 0.85rem;
    font-weight: 500;
    line-height: 1.2;
    height: 2.4rem;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
}
.product-unit {
    font-size: 0.8rem;
    color: #6c757d;
}
.product-description {
    font-size: 0.7rem;
    margin-bottom: 0.3rem;
    color: #495057;
    line-height: 1.3;
    font-style: italic;
}
.product-manufacturer {
    font-size: 0.7rem;
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 0.2rem;
}
.product-category {
    font-size: 0.7rem;
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 0.4rem;
}
/* Hide customer dashboard cards on mobile devices */
@media (max-width: 767.98px) {
    .customer-dashboard-cards {
        display: none !important;
    }
}

/* Optimize product cards for mobile (2 cards per row) */
@media (max-width: 575.98px) {
    .product-image {
        height: 180px;
    }
    .product-title {
        font-size: 0.9rem;
        height: 2.7rem;
    }
    .product-description {
        font-size: 0.75rem;
    }
    .product-manufacturer,
    .product-category {
        font-size: 0.75rem;
    }
    .product-price {
        font-size: 1.1rem;
    }
    .card-body {
        padding: 1rem !important;
    }
}

/* Banner Slider Styles */
.banner-slider {
    margin-bottom: 2rem;
}
.banner-slide {
    min-height: 200px;
    border-radius: 1rem !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}
.banner-slide:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
.carousel-control-prev,
.carousel-control-next {
    width: 50px;
    height: 50px;
    background-color: rgba(0,0,0,0.3);
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0;
    transition: all 0.3s ease;
}
.banner-slider:hover .carousel-control-prev,
.banner-slider:hover .carousel-control-next {
    opacity: 0.7;
}
.carousel-control-prev:hover,
.carousel-control-next:hover {
    opacity: 1 !important;
    background-color: rgba(0,0,0,0.5);
}
.carousel-control-prev {
    left: 20px;
}
.carousel-control-next {
    right: 20px;
}

/* Mobile responsive for banner */
@media (max-width: 767.98px) {
    .banner-slide {
        min-height: 150px;
        padding: 1.5rem !important;
    }
    .banner-slide h3 {
        font-size: 1.2rem;
    }
    .banner-slide p {
        font-size: 0.9rem;
    }
    .banner-slide i[style*="font-size: 4rem"] {
        font-size: 2.5rem !important;
    }
    .carousel-control-prev,
    .carousel-control-next {
        width: 40px;
        height: 40px;
    }
    .carousel-control-prev {
        left: 10px;
    }
    .carousel-control-next {
        right: 10px;
    }
}

/* Custom styling for clear all filters button */
.btn-clear-filters {
    background-color: #ffc0cb !important;
    border-color: #ffc0cb !important;
    color: #333 !important;
    transition: all 0.3s ease;
}

.btn-clear-filters:hover {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

/* Custom styling for selected category badges */
.badge-selected-category {
    background-color: #e3f2fd !important;
    border: 1px solid #bbdefb !important;
    color: #1976d2 !important;
    transition: all 0.3s ease;
    cursor: pointer;
}

.badge-selected-category:hover {
    background-color: #1976d2 !important;
    border-color: #1976d2 !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
}

.badge-selected-category .btn-close {
    filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(346deg) brightness(104%) contrast(97%);
    transition: filter 0.3s ease;
}

.badge-selected-category:hover .btn-close {
    filter: brightness(0) invert(1);
}

/* Hide product count badge on mobile */
@media (max-width: 768px) {
    .product-count-badge {
        display: none !important;
    }
}
</style>

<div class="flex-grow-1">
	<div class="container">
        <!-- Welcome Header -->
        <?php if ($user['role'] !== 'customer'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1">Dashboard</h3>
                <p class="text-muted mb-0">Selamat datang, <strong><?php echo htmlspecialchars($user['namalengkap']); ?></strong></p>
            </div>
            <div>
                <span class="badge bg-primary fs-6"><?php echo ucfirst($user['role']); ?></span>
			<?php if (is_admin()): ?>
				<span class="badge bg-warning ms-2">Full Access</span>
			<?php endif; ?>
		</div>
        </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'admin' || $user['role'] === 'operator'): ?>
            <!-- Admin/Operator Dashboard -->
            <div class="row g-3 mb-4">
                <!-- Statistics Cards -->
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-primary mx-auto mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['users']); ?></h4>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-box"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['barang']); ?></h4>
                            <p class="text-muted mb-0">Master Barang</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-info mx-auto mb-3">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['customer']); ?></h4>
                            <p class="text-muted mb-0">Master Customer</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-warning mx-auto mb-3">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['sales']); ?></h4>
                            <p class="text-muted mb-0">Master Sales</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-secondary mx-auto mb-3">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['orders']); ?></h4>
                            <p class="text-muted mb-0">Total Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-warning mx-auto mb-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['pending_orders']); ?></h4>
                            <p class="text-muted mb-0">Pending Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['completed_orders']); ?></h4>
                            <p class="text-muted mb-0">Completed Orders</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if (can_access('order')): ?>
                                    <a href="order_form.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Buat Order Baru
                                    </a>
                                <?php endif; ?>
                                <?php if (can_access('masterbarang')): ?>
                                    <a href="masterbarang_form.php" class="btn btn-success">
                                        <i class="fas fa-box"></i> Tambah Barang
                                    </a>
                                <?php endif; ?>
                                <?php if (can_access('mastercustomer')): ?>
                                    <a href="mastercustomer_form.php" class="btn btn-info">
                                        <i class="fas fa-user-plus"></i> Tambah Customer
                                    </a>
                                <?php endif; ?>
                                <?php if (can_access('users')): ?>
                                    <a href="user_form.php" class="btn btn-warning">
                                        <i class="fas fa-user-plus"></i> Tambah User
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Menu Utama</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <?php if (can_access('order')): ?>
                                    <div class="col-6">
                                        <a href="order.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-shopping-cart"></i><br>
                                            <small>Order</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (can_access('masterbarang')): ?>
                                    <div class="col-6">
                                        <a href="masterbarang.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-box"></i><br>
                                            <small>Barang</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (can_access('mastercustomer')): ?>
                                    <div class="col-6">
                                        <a href="mastercustomer.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-users"></i><br>
                                            <small>Customer</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (can_access('mastersales')): ?>
                                    <div class="col-6">
                                        <a href="mastersales.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-user-tie"></i><br>
                                            <small>Sales</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (can_access('users')): ?>
                                    <div class="col-6">
                                        <a href="users.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-users-cog"></i><br>
                                            <small>Users</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (can_access('reports')): ?>
                                    <div class="col-6">
                                        <a href="reports.php" class="btn btn-outline-dark w-100">
                                            <i class="fas fa-chart-bar"></i><br>
                                            <small>Reports</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($user['role'] === 'manajemen'): ?>
            <!-- Management Dashboard -->

            <!-- Charts Section -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Jumlah Order Bulanan</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="orderCountChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Nilai Order Bulanan</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="orderValueChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Order Batal Bulanan</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="cancelledOrderChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-area"></i> Customer Baru Bulanan</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="newCustomerChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <!-- Statistics Cards -->
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-primary mx-auto mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['users']); ?></h4>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-box"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['barang']); ?></h4>
                            <p class="text-muted mb-0">Master Barang</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-info mx-auto mb-3">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['customer']); ?></h4>
                            <p class="text-muted mb-0">Master Customer</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-warning mx-auto mb-3">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['sales']); ?></h4>
                            <p class="text-muted mb-0">Master Sales</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-secondary mx-auto mb-3">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['orders']); ?></h4>
                            <p class="text-muted mb-0">Total Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-warning mx-auto mb-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['pending_orders']); ?></h4>
                            <p class="text-muted mb-0">Pending Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['completed_orders']); ?></h4>
                            <p class="text-muted mb-0">Completed Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-danger mx-auto mb-3">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['cancelled_orders']); ?></h4>
                            <p class="text-muted mb-0">Cancelled Orders</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-info mx-auto mb-3">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <h4 class="mb-1">Rp <?php echo number_format($stats['total_all_orders'], 0, ',', '.'); ?></h4>
                            <p class="text-muted mb-0">Total Nilai Order</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h4 class="mb-1">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></h4>
                            <p class="text-muted mb-0">Total Revenue YTD</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($user['role'] === 'sales'): ?>
            <!-- Sales Dashboard -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-primary mx-auto mb-3">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['my_orders']); ?></h4>
                            <p class="text-muted mb-0">Total Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-warning mx-auto mb-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['pending_orders']); ?></h4>
                            <p class="text-muted mb-0">Pending Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['completed_orders']); ?></h4>
                            <p class="text-muted mb-0">Completed Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-info mx-auto mb-3">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($stats['my_customers']); ?></h4>
                            <p class="text-muted mb-0">My Customers</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-success mx-auto mb-3">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h4 class="mb-1">Rp <?php echo number_format($stats['total_sales'], 0, ',', '.'); ?></h4>
                            <p class="text-muted mb-0">Total Sales Value</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="order_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Buat Order Baru
                                </a>
                                <a href="order.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list"></i> Lihat Semua Orders
                                </a>
                                <a href="mastercustomer.php" class="btn btn-outline-info">
                                    <i class="fas fa-users"></i> Kelola Customer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($user['role'] === 'customer'): ?>
            <!-- Banner Slider Section -->
            <div class="banner-slider mb-4">
                <div id="bannerCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <div class="banner-slide bg-primary text-white p-4 rounded-3" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="mb-2"><i class="fas fa-shopping-bag me-2"></i>Selamat Datang di PBF</h3>
                                        <p class="mb-3">Temukan produk farmasi berkualitas dengan harga terbaik. Layanan cepat dan terpercaya untuk kebutuhan kesehatan Anda.</p>
                                        <a href="#products" class="btn btn-light btn-sm">
                                            <i class="fas fa-arrow-down me-1"></i>Lihat Produk
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="banner-slide bg-success text-white p-4 rounded-3" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="mb-2"><i class="fas fa-shipping-fast me-2"></i>Pengiriman Cepat</h3>
                                        <p class="mb-3">Layanan pengiriman cepat dan aman ke seluruh Indonesia. Pesan hari ini, terima besok!</p>
                                        <a href="order_form.php" class="btn btn-light btn-sm">
                                            <i class="fas fa-shopping-cart me-1"></i>Buat Order
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="banner-slide bg-warning text-dark p-4 rounded-3" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="mb-2"><i class="fas fa-percentage me-2"></i>Promo Spesial</h3>
                                        <p class="mb-3">Dapatkan diskon menarik untuk pembelian produk tertentu. Jangan lewatkan kesempatan ini!</p>
                                        <a href="#products" class="btn btn-dark btn-sm">
                                            <i class="fas fa-tags me-1"></i>Lihat Promo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>


            <!-- Sale Products Section -->
            <?php if (isset($saleProducts) && !empty($saleProducts) && empty($searchQuery) && empty($filterPabrik) && empty($filterGolongan) && empty($filterKandungan) && empty($filterKemasan) && empty($filterKondisiHarga)): ?>
            <div class="row g-2 mb-4 pt-2 pb-3 px-3 flash-sale-section" style="background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%); border-radius: 15px; border: 2px solid #d4edda;">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0"><i class="fas fa-fire text-danger me-2" style="animation: fireFlicker 1.5s ease-in-out infinite alternate;"></i>Flash Sale</h4>
                        <?php if ($totalSaleProducts > 6): ?>
                            <a href="dashboard.php?kondisiharga=sale" class="btn btn-success btn-sm">
                                <i class="fas fa-eye me-1"></i>Lihat Semua
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <div id="flashSaleContainer" style="overflow: hidden; position: relative;">
                        <?php if ($totalSaleProducts > 6): ?>
                        <!-- Left Arrow -->
                        <button class="flash-sale-nav-btn" id="prevFlashSale" onclick="slideFlashSale(-1)" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; background: rgba(108, 117, 125, 0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease; opacity: 0; visibility: hidden;">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <!-- Right Arrow -->
                        <button class="flash-sale-nav-btn" id="nextFlashSale" onclick="slideFlashSale(1)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); z-index: 10; background: rgba(108, 117, 125, 0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease; opacity: 0; visibility: hidden;">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                        
                        <div id="flashSaleSlider" style="display: flex; transition: transform 0.3s ease-in-out;">
                            <?php 
                            $chunks = array_chunk($saleProducts, 6);
                            foreach ($chunks as $chunkIndex => $chunk):
                                shuffle($chunk); // Randomize order within each chunk 
                            ?>
                            <div class="flash-sale-slide" style="min-width: 100%; display: flex; flex-wrap: wrap; gap: 15px;">
                                <?php foreach ($chunk as $product): 
                                    $fotos = json_decode($product['foto'], true);
                                    $mainImage = (!empty($fotos) && !empty($fotos[0]) && file_exists($fotos[0])) ? $fotos[0] : 'assets/img/no-image.svg';
                                    $discountPrice = $product['hargajual'] - ($product['hargajual'] * $product['discjual'] / 100);
                                    $hasDiscount = $product['discjual'] > 0;
                                ?>
                                <div style="flex: 0 0 calc(16.666% - 12.5px); max-width: calc(16.666% - 12.5px);">
                    <div class="card product-card h-100 p-2" style="cursor: pointer; font-size: 0.85rem;" onclick="window.location.href='customer_product_detail.php?kodebarang=<?php echo $product['kodebarang']; ?>'">
                        <div class="position-relative">
                            <img src="assets/img/no-image.svg" 
                                 data-src="<?php echo htmlspecialchars($mainImage); ?>" 
                                 alt="<?php echo htmlspecialchars($product['namabarang']); ?>"
                                 class="product-image lazy" style="height: 120px; object-fit: cover;">
                            
                            <?php if ($hasDiscount && !in_array($product['kondisiharga'], ['sale', 'promo'])): ?>
                                <span class="discount-badge">-<?php echo number_format($product['discjual'], 0); ?>%</span>
                            <?php endif; ?>
                            
                            <span class="stock-badge">Stok: <?php echo number_format($product['stokakhir']); ?></span>
                        </div>
                        <div class="card-body p-2">
                            <h6 class="product-title mb-1" style="font-size: 0.8rem; line-height: 1.2;"><?php echo htmlspecialchars($product['namabarang']); ?></h6>
                            <p class="product-info mb-1" style="font-size: 0.65rem; color: #6c757d; font-weight: 500;">
                                <?php 
                                $infoParts = [];
                                if (!empty($product['namapabrik'])) {
                                    $infoParts[] = htmlspecialchars($product['namapabrik']);
                                }
                                if (!empty($product['namagolongan'])) {
                                    $infoParts[] = htmlspecialchars($product['namagolongan']);
                                }
                                if (!empty($product['satuan'])) {
                                    $infoParts[] = htmlspecialchars($product['satuan']);
                                }
                                echo implode(' / ', $infoParts);
                                ?>
                            </p>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <?php if ($hasDiscount): ?>
                                        <div class="product-price" style="font-size: 0.85rem; color: #dc3545; font-weight: 600;">Rp <?php echo number_format($discountPrice, 0, ',', '.'); ?></div>
                                        <div class="product-price-original" style="font-size: 0.7rem; color: #6c757d; text-decoration: line-through;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                    <?php else: ?>
                                        <div class="product-price" style="font-size: 0.85rem; color: #dc3545; font-weight: 600;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; background-color: <?php echo ($product['kondisiharga'] === 'baru') ? '#dc3545' : '#28a745'; ?>; border-color: <?php echo ($product['kondisiharga'] === 'baru') ? '#dc3545' : '#28a745'; ?>; color: white;" onclick="event.stopPropagation(); addToCart('<?php echo $product['kodebarang']; ?>')">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Promo Products Section -->
            <?php if (isset($promoProducts) && !empty($promoProducts) && empty($searchQuery) && empty($filterPabrik) && empty($filterGolongan) && empty($filterKandungan) && empty($filterKemasan) && empty($filterKondisiHarga)): ?>
            <div class="row g-2 mb-4 pt-2 pb-3 px-3 promo-section" style="background: linear-gradient(135deg, #fff8dc 0%, #f5f5dc 100%); border-radius: 15px; border: 2px solid #f9e79f;">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0"><i class="fas fa-gift text-warning me-2" style="animation: giftBounce 2s ease-in-out infinite;"></i>Special Promo</h4>
                        <?php if ($totalPromoProducts > 6): ?>
                            <a href="dashboard.php?kondisiharga=promo" class="btn btn-warning btn-sm">
                                <i class="fas fa-eye me-1"></i>Lihat Semua
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <div id="promoContainer" style="overflow: hidden; position: relative;">
                        <?php if ($totalPromoProducts > 6): ?>
                        <!-- Left Arrow -->
                        <button class="promo-nav-btn" id="prevPromo" onclick="slidePromo(-1)" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; background: rgba(108, 117, 125, 0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease; opacity: 0; visibility: hidden;">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <!-- Right Arrow -->
                        <button class="promo-nav-btn" id="nextPromo" onclick="slidePromo(1)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); z-index: 10; background: rgba(108, 117, 125, 0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease; opacity: 0; visibility: hidden;">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                        
                        <div id="promoSlider" style="display: flex; transition: transform 0.3s ease-in-out;">
                            <?php 
                            $promoChunks = array_chunk($promoProducts, 6);
                            foreach ($promoChunks as $chunkIndex => $chunk):
                                shuffle($chunk); // Randomize order within each chunk 
                            ?>
                            <div class="promo-slide" style="min-width: 100%; display: flex; flex-wrap: wrap; gap: 15px;">
                                <?php foreach ($chunk as $product): 
                                    $fotos = json_decode($product['foto'], true);
                                    $mainImage = (!empty($fotos) && !empty($fotos[0]) && file_exists($fotos[0])) ? $fotos[0] : 'assets/img/no-image.svg';
                                    $discountPrice = $product['hargajual'] - ($product['hargajual'] * $product['discjual'] / 100);
                                    $hasDiscount = $product['discjual'] > 0;
                                ?>
                                <div style="flex: 0 0 calc(16.666% - 12.5px); max-width: calc(16.666% - 12.5px);">
                    <div class="card product-card h-100 p-2" style="cursor: pointer; font-size: 0.85rem;" onclick="window.location.href='customer_product_detail.php?kodebarang=<?php echo $product['kodebarang']; ?>'">
                        <div class="position-relative">
                            <img src="assets/img/no-image.svg" 
                                 data-src="<?php echo htmlspecialchars($mainImage); ?>" 
                                 alt="<?php echo htmlspecialchars($product['namabarang']); ?>"
                                 class="product-image lazy" style="height: 120px; object-fit: cover;">
                            
                            <?php if ($hasDiscount && !in_array($product['kondisiharga'], ['sale', 'promo'])): ?>
                                <span class="discount-badge">-<?php echo number_format($product['discjual'], 0); ?>%</span>
                            <?php endif; ?>
                            
                            <span class="stock-badge">Stok: <?php echo number_format($product['stokakhir']); ?></span>
                        </div>
                        <div class="card-body p-2">
                            <h6 class="product-title mb-1" style="font-size: 0.8rem; line-height: 1.2;"><?php echo htmlspecialchars($product['namabarang']); ?></h6>
                            <p class="product-info mb-1" style="font-size: 0.65rem; color: #6c757d; font-weight: 500;">
                                <?php 
                                $infoParts = [];
                                if (!empty($product['namapabrik'])) {
                                    $infoParts[] = htmlspecialchars($product['namapabrik']);
                                }
                                if (!empty($product['namagolongan'])) {
                                    $infoParts[] = htmlspecialchars($product['namagolongan']);
                                }
                                if (!empty($product['satuan'])) {
                                    $infoParts[] = htmlspecialchars($product['satuan']);
                                }
                                echo implode(' / ', $infoParts);
                                ?>
                            </p>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <?php if ($hasDiscount): ?>
                                        <div class="product-price" style="font-size: 0.85rem; color: #007bff; font-weight: 600;">Rp <?php echo number_format($discountPrice, 0, ',', '.'); ?></div>
                                        <div class="product-price-original" style="font-size: 0.7rem; color: #6c757d; text-decoration: line-through;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                    <?php else: ?>
                                        <div class="product-price" style="font-size: 0.85rem; color: #007bff; font-weight: 600;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; background-color: <?php echo ($product['kondisiharga'] === 'baru') ? '#dc3545' : '#f39c12'; ?>; border-color: <?php echo ($product['kondisiharga'] === 'baru') ? '#dc3545' : '#f39c12'; ?>; color: white;" onclick="event.stopPropagation(); addToCart('<?php echo $product['kodebarang']; ?>')">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Baru Products Section -->
            <?php if (isset($baruProducts) && !empty($baruProducts) && empty($searchQuery) && empty($filterPabrik) && empty($filterGolongan) && empty($filterKandungan) && empty($filterKemasan) && empty($filterKondisiHarga)): ?>
            <div class="row g-2 mb-4 pt-2 pb-3 px-3 baru-section" style="background: linear-gradient(135deg, #e6f3ff 0%, #f0f8ff 100%); border-radius: 15px; border: 2px solid #b0e0e6;">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <h4 class="mb-0 me-3" style="color: #1e3a8a; font-weight: 700;">
                                <i class="fas fa-star me-2 baru-section-star" style="color: #3b82f6;"></i>Produk Baru
                            </h4>
                        </div>
                        <?php if ($totalBaruProducts > 5): ?>
                            <a href="dashboard.php?kondisiharga=baru" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>Lihat Semua
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row g-3">
                        <!-- Large Card (First Product) -->
                        <?php if (!empty($baruProducts[0])): 
                            $product = $baruProducts[0];
                            $fotos = json_decode($product['foto'], true);
                            $mainImage = (!empty($fotos) && !empty($fotos[0]) && file_exists($fotos[0])) ? $fotos[0] : 'assets/img/no-image.svg';
                            $discountPrice = $product['hargajual'] - ($product['hargajual'] * $product['discjual'] / 100);
                            $hasDiscount = $product['discjual'] > 0;
                        ?>
                        <div class="col-lg-6 col-md-6">
                            <div class="card product-card h-100" style="cursor: pointer; border: none; padding: 15px;" onclick="window.location.href='customer_product_detail.php?kodebarang=<?php echo $product['kodebarang']; ?>'">
                                <div class="position-relative">
                                    <img src="assets/img/no-image.svg" 
                                         data-src="<?php echo htmlspecialchars($mainImage); ?>" 
                                         alt="<?php echo htmlspecialchars($product['namabarang']); ?>"
                                         class="product-image lazy" style="height: 320px; object-fit: cover;">
                                    
                                    <span class="stock-badge">Stok: <?php echo number_format($product['stokakhir']); ?></span>
                                </div>
                                <div class="card-body p-3">
                                    <h5 class="product-title mb-2" style="font-size: 1.3rem; line-height: 1.3; font-weight: 600;"><?php echo htmlspecialchars($product['namabarang']); ?></h5>
                                    <p class="product-info mb-2" style="font-size: 0.95rem; color: #6c757d; font-weight: 500;">
                                        <?php 
                                        $infoParts = [];
                                        if (!empty($product['namapabrik'])) {
                                            $infoParts[] = htmlspecialchars($product['namapabrik']);
                                        }
                                        if (!empty($product['namagolongan'])) {
                                            $infoParts[] = htmlspecialchars($product['namagolongan']);
                                        }
                                        if (!empty($product['satuan'])) {
                                            $infoParts[] = htmlspecialchars($product['satuan']);
                                        }
                                        echo implode(' / ', $infoParts);
                                        ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if ($hasDiscount): ?>
                                                <div class="product-price" style="font-size: 1.4rem; color: #dc3545; font-weight: 700;">Rp <?php echo number_format($discountPrice, 0, ',', '.'); ?></div>
                                                <div class="product-price-original" style="font-size: 1rem; color: #6c757d; text-decoration: line-through;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                            <?php else: ?>
                                                <div class="product-price" style="font-size: 1.4rem; color: #1e3a8a; font-weight: 700;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-sm" style="background-color: #dc3545; border-color: #dc3545; color: white;" onclick="event.stopPropagation(); addToCart('<?php echo $product['kodebarang']; ?>')">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Two Cards with 2 Rows Each (Products 2-5) -->
                        <div class="col-lg-6 col-md-6">
                            <div class="row g-2 h-100">
                                <?php for ($i = 1; $i < min(5, count($baruProducts)); $i += 2): ?>
                                <div class="col-12">
                                    <div class="row g-2">
                                        <?php for ($j = $i; $j < min($i + 2, min(5, count($baruProducts))); $j++): 
                                            $product = $baruProducts[$j];
                                            $fotos = json_decode($product['foto'], true);
                                            $mainImage = (!empty($fotos) && !empty($fotos[0]) && file_exists($fotos[0])) ? $fotos[0] : 'assets/img/no-image.svg';
                                            $discountPrice = $product['hargajual'] - ($product['hargajual'] * $product['discjual'] / 100);
                                            $hasDiscount = $product['discjual'] > 0;
                                        ?>
                                        <div class="col-6">
                                            <div class="card product-card h-100" style="cursor: pointer; border: none; padding: 10px;" onclick="window.location.href='customer_product_detail.php?kodebarang=<?php echo $product['kodebarang']; ?>'">
                                                <div class="position-relative">
                                                    <img src="assets/img/no-image.svg" 
                                                         data-src="<?php echo htmlspecialchars($mainImage); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['namabarang']); ?>"
                                                         class="product-image lazy" style="height: 120px; object-fit: cover;">
                                                    
                                                    <span class="stock-badge" style="font-size: 0.5rem; padding: 1px 3px;">Stok: <?php echo number_format($product['stokakhir']); ?></span>
                                                </div>
                                                <div class="card-body p-2">
                                                    <h6 class="product-title mb-1" style="font-size: 0.95rem; line-height: 1.2; font-weight: 600;"><?php echo htmlspecialchars($product['namabarang']); ?></h6>
                                                    <p class="product-info mb-1" style="font-size: 0.8rem; color: #6c757d; font-weight: 500;">
                                                        <?php 
                                                        $infoParts = [];
                                                        if (!empty($product['namapabrik'])) {
                                                            $infoParts[] = htmlspecialchars($product['namapabrik']);
                                                        }
                                                        if (!empty($product['namagolongan'])) {
                                                            $infoParts[] = htmlspecialchars($product['namagolongan']);
                                                        }
                                                        if (!empty($product['satuan'])) {
                                                            $infoParts[] = htmlspecialchars($product['satuan']);
                                                        }
                                                        echo implode(' / ', $infoParts);
                                                        ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <?php if ($hasDiscount): ?>
                                                                <div class="product-price" style="font-size: 1rem; color: #dc3545; font-weight: 700;">Rp <?php echo number_format($discountPrice, 0, ',', '.'); ?></div>
                                                                <div class="product-price-original" style="font-size: 0.8rem; color: #6c757d; text-decoration: line-through;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                                            <?php else: ?>
                                                                <div class="product-price" style="font-size: 1rem; color: #1e3a8a; font-weight: 700;">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button class="btn btn-sm" style="padding: 0.2rem 0.4rem; font-size: 0.6rem; background-color: #dc3545; border-color: #dc3545; color: white;" onclick="event.stopPropagation(); addToCart('<?php echo $product['kodebarang']; ?>')">
                                                            <i class="fas fa-cart-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Product Display Section -->
            <?php if (isset($products) && !empty($products)): ?>
            <div id="products" class="row g-3 mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <?php 
                        $hasFilters = !empty($searchQuery) || !empty($filterPabrik) || !empty($filterGolongan) || !empty($filterKandungan) || !empty($filterKemasan) || !empty($filterKondisiHarga);
                        if ($hasFilters): 
                        ?>
                            <h4 class="mb-0"><i class="fas fa-filter"></i> Hasil Filter</h4>
                        <?php else: ?>
                            <h4 class="mb-0"><i class="fas fa-shopping-bag"></i> Semua Produk</h4>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-primary fs-6 product-count-badge"><?php echo count($products); ?> Produk Tersedia</span>
                            <?php if ($hasFilters): ?>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0" style="font-size: 0.9rem; font-weight: 500; color: #007bff;"><i class="fas fa-sort-amount-down me-1"></i>Urutkan:</label>
                                <select class="form-select form-select-sm" style="width: auto; min-width: 120px; font-weight: bold;" onchange="applySorting(this.value)">
                                    <option value="terbaru" <?php echo $sortBy === 'terbaru' ? 'selected' : ''; ?>>Terbaru</option>
                                    <option value="terlaris" <?php echo $sortBy === 'terlaris' ? 'selected' : ''; ?>>Terlaris</option>
                                    <option value="termurah" <?php echo $sortBy === 'termurah' ? 'selected' : ''; ?>>Termurah</option>
                                    <option value="termahal" <?php echo $sortBy === 'termahal' ? 'selected' : ''; ?>>Termahal</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($hasFilters): ?>
                        <div class="mb-3">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <?php if (!empty($searchQuery)): ?>
                                    <span class="badge bg-info" style="font-size: 1.1em; padding: 0.4em;">Pencarian: "<?php echo htmlspecialchars($searchQuery); ?>"</span>
                                <?php endif; ?>
                                <?php if (!empty($filterPabrik)): ?>
                                    <span class="badge badge-selected-category d-flex align-items-center gap-1">
                                        Pabrik: <?php echo htmlspecialchars($filterPabrik); ?>
                                        <button type="button" class="btn-close" style="font-size: 1em; padding: 0.4em;" onclick="removeFilter('pabrik')" title="Hapus kategori pabrik"></button>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($filterGolongan)): ?>
                                    <span class="badge badge-selected-category d-flex align-items-center gap-1">
                                        Golongan: <?php echo htmlspecialchars($filterGolongan); ?>
                                        <button type="button" class="btn-close" style="font-size: 1em; padding: 0.4em;" onclick="removeFilter('golongan')" title="Hapus kategori golongan"></button>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($filterKandungan)): ?>
                                    <span class="badge badge-selected-category d-flex align-items-center gap-1">
                                        Kandungan: <?php echo htmlspecialchars($filterKandungan); ?>
                                        <button type="button" class="btn-close" style="font-size: 1em; padding: 0.4em;" onclick="removeFilter('kandungan')" title="Hapus kategori kandungan"></button>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($filterKemasan)): ?>
                                    <span class="badge badge-selected-category d-flex align-items-center gap-1">
                                        Kemasan: <?php echo htmlspecialchars($filterKemasan); ?>
                                        <button type="button" class="btn-close" style="font-size: 1em; padding: 0.4em;" onclick="removeFilter('kemasan')" title="Hapus kategori kemasan"></button>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($filterKondisiHarga)): ?>
                                    <span class="badge badge-selected-category d-flex align-items-center gap-1">
                                        Kondisi: <?php echo htmlspecialchars($filterKondisiHarga); ?>
                                        <button type="button" class="btn-close" style="font-size: 1em; padding: 0.4em;" onclick="removeFilter('kondisiharga')" title="Hapus filter kondisi harga"></button>
                                    </span>
                                <?php endif; ?>
                                <button type="button" class="btn btn-clear-filters btn-sm" onclick="clearAllFiltersAndSearch()">
                                    <i class="fas fa-times me-1"></i>Hapus Semua Filter
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php foreach ($products as $product): 
                    $fotos = json_decode($product['foto'], true);
                    $mainImage = (!empty($fotos) && !empty($fotos[0]) && file_exists($fotos[0])) ? $fotos[0] : 'assets/img/no-image.svg';
                    $discountPrice = $product['hargajual'] - ($product['hargajual'] * $product['discjual'] / 100);
                    $hasDiscount = $product['discjual'] > 0;
                ?>
                <div class="col-lg-2 col-md-3 col-sm-6 col-6">
                    <div class="card product-card h-100" style="cursor: pointer;" onclick="window.location.href='customer_product_detail.php?kodebarang=<?php echo $product['kodebarang']; ?>'">
                        <div class="position-relative">
                            <img src="assets/img/no-image.svg" 
                                 data-src="<?php echo htmlspecialchars($mainImage); ?>" 
                                 alt="<?php echo htmlspecialchars($product['namabarang']); ?>"
                                 class="product-image lazy">
                            
                            <?php if ($hasDiscount && !in_array($product['kondisiharga'], ['sale', 'promo'])): ?>
                                <span class="discount-badge">-<?php echo number_format($product['discjual'], 0); ?>%</span>
                            <?php endif; ?>
                            
                            <?php if ($product['kondisiharga'] && $product['kondisiharga'] !== 'normal'): ?>
                                <?php 
                                $kondisi = strtolower($product['kondisiharga']);
                                $badgeClass = '';
                                $badgeText = '';
                                
                                switch($kondisi) {
                                    case 'baru':
                                        $badgeClass = 'baru-badge';
                                        $badgeText = '<i class="fas fa-star me-1"></i>BARU';
                                        break;
                                    case 'promo':
                                        $badgeClass = 'promo-badge';
                                        $badgeText = 'PROMO';
                                        break;
                                    case 'sale':
                                        $badgeClass = 'sale-badge';
                                        $badgeText = 'Flash Sale';
                                        break;
                                    case 'spesial':
                                        $badgeClass = 'spesial-badge';
                                        $badgeText = 'SPESIAL';
                                        break;
                                    case 'deals':
                                        $badgeClass = 'deals-badge';
                                        $badgeText = 'DEALS';
                                        break;
                                }
                                ?>
                                <?php if ($badgeClass): ?>
                                    <span class="<?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <span class="stock-badge">Stok: <?php echo number_format($product['stokakhir']); ?></span>
                        </div>
                        <div class="card-body p-2">
                            <h6 class="product-title mb-2"><?php echo htmlspecialchars($product['namabarang']); ?></h6>
                            <p class="product-info mb-2" style="font-size: 0.7rem; color: #6c757d; font-weight: 500;">
                                <?php 
                                $infoParts = [];
                                if (!empty($product['namapabrik'])) {
                                    $infoParts[] = htmlspecialchars($product['namapabrik']);
                                }
                                if (!empty($product['namagolongan'])) {
                                    $infoParts[] = htmlspecialchars($product['namagolongan']);
                                }
                                if (!empty($product['satuan'])) {
                                    $infoParts[] = htmlspecialchars($product['satuan']);
                                }
                                echo implode(' / ', $infoParts);
                                ?>
                            </p>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <?php if ($hasDiscount): ?>
                                        <div class="product-price">Rp <?php echo number_format($discountPrice, 0, ',', '.'); ?></div>
                                        <div class="product-price-original">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                    <?php else: ?>
                                        <div class="product-price">Rp <?php echo number_format($product['hargajual'], 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm" style="background-color: <?php echo ($product['kondisiharga'] === 'baru') ? '#dc3545' : '#007bff'; ?>; border-color: <?php echo ($product['kondisiharga'] === 'baru') ? '#dc3545' : '#007bff'; ?>; color: white;" onclick="event.stopPropagation(); addToCart('<?php echo $product['kodebarang']; ?>')">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Default Dashboard for other roles Manajemen -->
		<div class="row g-3">
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body">
						<h5 class="card-title">Menu Utama</h5>
						<ul class="list-unstyled mb-0">
							<li><a href="dashboard.php">Dashboard</a></li>
							<?php if (can_access('users')): ?>
								<li><a href="users.php">Manajemen User</a></li>
							<?php endif; ?>
							<?php if (can_access('masterbarang')): ?>
								<li><a href="masterbarang.php">Master Barang</a></li>
							<?php endif; ?>
							<?php if (can_access('mastercustomer')): ?>
								<li><a href="mastercustomer.php">Master Customer</a></li>
							<?php endif; ?>
							<?php if (can_access('mastersales')): ?>
								<li><a href="mastersales.php">Master Sales</a></li>
							<?php endif; ?>
							<?php if (can_access('order')): ?>
								<li><a href="order.php">Transaksi Order</a></li>
							<?php endif; ?>
							<?php if (can_access('reports')): ?>
								<li><a href="reports.php">Reports</a></li>
							<?php endif; ?>
						</ul>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body">
						<h5 class="card-title">Info</h5>
						<p class="mb-2">Ini adalah dashboard untuk role: <strong><?php echo ucfirst($user['role']); ?></strong>.</p>
						<div class="text-center mt-3">
							<img src="assets/img/dashboard-illustration.svg" alt="Dashboard" class="img-fluid" style="max-height:200px;">
						</div>
					</div>
				</div>
			</div>
		</div>
        <?php endif; ?>
	</div>
</div>

<?php if ($user['role'] === 'manajemen'): ?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart data from PHP
const chartLabels = <?php echo json_encode($chartData['labels']); ?>;
const orderCounts = <?php echo json_encode($chartData['orderCounts']); ?>;
const orderValues = <?php echo json_encode($chartData['orderValues']); ?>;
const cancelledCounts = <?php echo json_encode($chartData['cancelledCounts']); ?>;
const customerCounts = <?php echo json_encode($chartData['customerCounts']); ?>;

// Chart configuration
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        }
    },
    scales: {
        y: {
            beginAtZero: true
        }
    }
};

// Order Count Chart (Line Chart)
const orderCountCtx = document.getElementById('orderCountChart').getContext('2d');
new Chart(orderCountCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Jumlah Order',
            data: orderCounts,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        ...chartOptions,
        plugins: {
            ...chartOptions.plugins,
            title: {
                display: true,
                text: 'Trend Jumlah Order Per Bulan'
            }
        }
    }
});

// Order Value Chart (Bar Chart)
const orderValueCtx = document.getElementById('orderValueChart').getContext('2d');
new Chart(orderValueCtx, {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Nilai Order (Rp)',
            data: orderValues,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        ...chartOptions,
        plugins: {
            ...chartOptions.plugins,
            title: {
                display: true,
                text: 'Revenue Per Bulan'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Cancelled Orders Chart (Doughnut Chart)
const cancelledOrderCtx = document.getElementById('cancelledOrderChart').getContext('2d');
new Chart(cancelledOrderCtx, {
    type: 'doughnut',
    data: {
        labels: chartLabels,
        datasets: [{
            data: cancelledCounts,
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40',
                '#FF6384',
                '#C9CBCF',
                '#4BC0C0',
                '#FF6384',
                '#36A2EB',
                '#FFCE56'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Distribusi Order Batal Per Bulan'
            }
        }
    }
});

// New Customers Chart (Area Chart)
const newCustomerCtx = document.getElementById('newCustomerChart').getContext('2d');
new Chart(newCustomerCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Customer Baru',
            data: customerCounts,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        ...chartOptions,
        plugins: {
            ...chartOptions.plugins,
            title: {
                display: true,
                text: 'Pertumbuhan Customer Baru Per Bulan'
            }
        }
    }
});
</script>
<?php endif; ?>

<?php if ($user['role'] === 'customer'): ?>
<script>
// Category filter functions
function applyFilters() {
    const pabrik = document.getElementById('filterPabrik') ? document.getElementById('filterPabrik').value : '';
    const golongan = document.getElementById('filterGolongan') ? document.getElementById('filterGolongan').value : '';
    const kandungan = document.getElementById('filterKandungan') ? document.getElementById('filterKandungan').value : '';
    const kemasan = document.getElementById('filterKemasan') ? document.getElementById('filterKemasan').value : '';
    
    const url = new URL(window.location);
    
    // Clear existing filter parameters
    url.searchParams.delete('pabrik');
    url.searchParams.delete('golongan');
    url.searchParams.delete('kandungan');
    url.searchParams.delete('kemasan');
    
    // Set new filter parameters only if they have values
    if (pabrik) url.searchParams.set('pabrik', pabrik);
    if (golongan) url.searchParams.set('golongan', golongan);
    if (kandungan) url.searchParams.set('kandungan', kandungan);
    if (kemasan) url.searchParams.set('kemasan', kemasan);
    
    // Preserve search query if exists
    const searchQuery = url.searchParams.get('search');
    if (searchQuery) {
        url.searchParams.set('search', searchQuery);
    }
    
    window.location.href = url.toString();
}

function clearFilters() {
    const url = new URL(window.location);
    url.searchParams.delete('pabrik');
    url.searchParams.delete('golongan');
    url.searchParams.delete('kandungan');
    url.searchParams.delete('kemasan');
    
    // Preserve search query if exists
    const searchQuery = url.searchParams.get('search');
    if (searchQuery) {
        url.searchParams.set('search', searchQuery);
    } else {
        url.search = '';
    }
    
    window.location.href = url.toString();
}

// Clear all filters and search, then refresh page
function clearAllFiltersAndSearch() {
    // Clear all search form inputs
    const searchInputs = document.querySelectorAll('input[name="search"]');
    searchInputs.forEach(input => {
        input.value = '';
    });
    
    // Clear all URL parameters and refresh
    window.location.href = 'dashboard.php';
}

// Apply sorting to filtered results
function applySorting(sortValue) {
    const url = new URL(window.location);
    
    // Preserve all existing parameters
    const currentParams = new URLSearchParams(window.location.search);
    
    // Set the new sort parameter
    if (sortValue && sortValue !== 'terbaru') {
        url.searchParams.set('sort', sortValue);
    } else {
        url.searchParams.delete('sort');
    }
    
    // Preserve all other existing parameters
    for (const [key, val] of currentParams.entries()) {
        if (key !== 'sort') {
            url.searchParams.set(key, val);
        }
    }
    
    // Redirect to sorted page
    window.location.href = url.toString();
}

// Remove individual filter
function removeFilter(category) {
    const url = new URL(window.location);
    
    // Remove the specific category filter
    url.searchParams.delete(category);
    
    // If no parameters left except search, redirect to clean URL
    const remainingParams = Array.from(url.searchParams.keys()).filter(key => key !== 'search');
    if (remainingParams.length === 0) {
        const searchQuery = url.searchParams.get('search');
        if (searchQuery) {
            window.location.href = `dashboard.php?search=${encodeURIComponent(searchQuery)}`;
        } else {
            window.location.href = 'dashboard.php';
        }
    } else {
        window.location.href = url.toString();
    }
}

// Advanced lazy loading for all products with performance optimization
document.addEventListener('DOMContentLoaded', function() {
    let lazyImages = document.querySelectorAll('.product-image.lazy');
    let imageObserver;
    
    // Function to initialize lazy loading
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        
                        // Add loading state with smooth transition
                        img.style.transition = 'opacity 0.3s ease';
                        img.style.opacity = '0.3';
                        
                        // Create new image to preload
                        const newImg = new Image();
                        newImg.onload = function() {
                            // Smooth transition to loaded image
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            img.classList.add('loaded');
                            img.style.opacity = '1';
                            imageObserver.unobserve(img);
                        };
                        newImg.onerror = function() {
                            // If image fails to load, keep the no-image placeholder
                            img.classList.remove('lazy');
                            img.classList.add('loaded');
                            img.style.opacity = '1';
                            imageObserver.unobserve(img);
                        };
                        newImg.src = img.dataset.src;
                    }
                });
            }, {
                // Load images when they're 100px away from viewport for smoother experience
                rootMargin: '100px 0px',
                threshold: 0.01
            });
            
            // Observe all lazy images
            lazyImages.forEach(img => {
                if (img.classList.contains('lazy')) {
                    imageObserver.observe(img);
                }
            });
        } else {
            // Fallback for browsers that don't support IntersectionObserver
            lazyImages.forEach(img => {
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                img.classList.add('loaded');
            });
        }
    }
    
    // Initialize lazy loading
    initLazyLoading();
    
    // Optional: Add scroll performance optimization
    let ticking = false;
    function updateLazyLoading() {
        if (!ticking) {
            requestAnimationFrame(() => {
                // Re-check for any new lazy images that might have been added
                const newLazyImages = document.querySelectorAll('.product-image.lazy');
                if (newLazyImages.length > 0 && imageObserver) {
                    newLazyImages.forEach(img => {
                        if (!img.dataset.observed) {
                            imageObserver.observe(img);
                            img.dataset.observed = 'true';
                        }
                    });
                }
                ticking = false;
            });
            ticking = true;
        }
    }
    
    // Listen for scroll events to handle dynamic content
    window.addEventListener('scroll', updateLazyLoading, { passive: true });
});

// Add to cart functionality
function addToCart(kodebarang) {
    // Add item to cart via API
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            kodebarang: kodebarang,
            quantity: 1
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

// Flash Sale Sliding Functionality
let currentFlashSaleSlide = 0;
const totalFlashSaleSlides = <?php echo isset($saleProducts) && !empty($saleProducts) ? ceil(count($saleProducts) / 6) : 0; ?>;

function slideFlashSale(direction) {
    if (totalFlashSaleSlides <= 1) return;
    
    const slider = document.getElementById('flashSaleSlider');
    const prevBtn = document.getElementById('prevFlashSale');
    const nextBtn = document.getElementById('nextFlashSale');
    
    currentFlashSaleSlide += direction;
    
    // Ensure we stay within bounds
    if (currentFlashSaleSlide < 0) {
        currentFlashSaleSlide = totalFlashSaleSlides - 1;
    } else if (currentFlashSaleSlide >= totalFlashSaleSlides) {
        currentFlashSaleSlide = 0;
    }
    
    // Update slider position
    slider.style.transform = `translateX(-${currentFlashSaleSlide * 100}%)`;
    
    // Update button states
    if (prevBtn && nextBtn) {
        prevBtn.disabled = false;
        nextBtn.disabled = false;
    }
}

// Initialize Flash Sale slider
document.addEventListener('DOMContentLoaded', function() {
    if (totalFlashSaleSlides > 1) {
        const prevBtn = document.getElementById('prevFlashSale');
        const nextBtn = document.getElementById('nextFlashSale');
        
        if (prevBtn && nextBtn) {
            prevBtn.disabled = currentFlashSaleSlide === 0;
            nextBtn.disabled = currentFlashSaleSlide === totalFlashSaleSlides - 1;
        }
    }
});

// Promo Sliding Functionality
let currentPromoSlide = 0;
const totalPromoSlides = <?php echo isset($promoProducts) && !empty($promoProducts) ? ceil(count($promoProducts) / 6) : 0; ?>;

function slidePromo(direction) {
    if (totalPromoSlides <= 1) return;
    
    const slider = document.getElementById('promoSlider');
    const prevBtn = document.getElementById('prevPromo');
    const nextBtn = document.getElementById('nextPromo');
    
    currentPromoSlide += direction;
    
    // Ensure we stay within bounds
    if (currentPromoSlide < 0) {
        currentPromoSlide = totalPromoSlides - 1;
    } else if (currentPromoSlide >= totalPromoSlides) {
        currentPromoSlide = 0;
    }
    
    // Update slider position
    slider.style.transform = `translateX(-${currentPromoSlide * 100}%)`;
    
    // Update button states
    if (prevBtn && nextBtn) {
        prevBtn.disabled = false;
        nextBtn.disabled = false;
    }
}

// Initialize Promo slider
document.addEventListener('DOMContentLoaded', function() {
    if (totalPromoSlides > 1) {
        const prevBtn = document.getElementById('prevPromo');
        const nextBtn = document.getElementById('nextPromo');
        
        if (prevBtn && nextBtn) {
            prevBtn.disabled = currentPromoSlide === 0;
            nextBtn.disabled = currentPromoSlide === totalPromoSlides - 1;
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
