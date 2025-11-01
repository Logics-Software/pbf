<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

try {
    require_login();
    $user = current_user();
    
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    
    // Check if user has access to reports
    if (!can_access('reports')) {
        header('Location: dashboard.php');
        exit;
    }
    
    $pdo = get_pdo_connection();
} catch (Exception $e) {
    error_log("Laporan Order Auth Error: " . $e->getMessage());
    if (ini_get('display_errors')) {
        die("Authentication Error: " . htmlspecialchars($e->getMessage()));
    } else {
        die("Terjadi kesalahan autentikasi. Silakan hubungi administrator.");
    }
}

// Get filter parameters
$reportType = $_GET['type'] ?? 'global'; // global, customer, sales
$periodType = $_GET['period'] ?? 'monthly'; // monthly, custom
$month = $_GET['month'] ?? date('Y-m');
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$salesFilter = $_GET['sales'] ?? '';
$customerFilter = $_GET['customer'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$exportFormat = $_GET['export'] ?? '';

// Get sales and customer lists for filters based on user role
$salesList = [];
$customerList = [];

if ($user['role'] === 'sales') {
    // For sales role: force filter to their own sales code, ignore all other sales
    if (!empty($user['kodesales'])) {
        $salesFilter = $user['kodesales']; // Force to user's sales code
        
        // Get customers who have made orders with this sales
        $stmt = $pdo->prepare("SELECT DISTINCT h.kodecustomer, h.namacustomer 
                              FROM headerorder h 
                              WHERE h.kodesales = ? 
                              ORDER BY h.namacustomer");
        $stmt->execute([$user['kodesales']]);
        $customerList = $stmt->fetchAll();
    } else {
        // Sales user without kodesales - show no data
        $salesFilter = 'INVALID_SALES_CODE';
        $customerList = [];
    }
} elseif ($user['role'] === 'customer') {
    // For customer role: force filter to their own customer code, ignore all other filters
    if (!empty($user['kodecustomer'])) {
        $customerFilter = $user['kodecustomer']; // Force to user's customer code
    } else {
        // Customer user without kodecustomer - show no data
        $customerFilter = 'INVALID_CUSTOMER_CODE';
    }
    $salesFilter = ''; // Ignore sales filter completely
} else {
    // For admin/operator/manajemen: show all sales and customers
    $stmt = $pdo->query("SELECT kodesales, namasales FROM mastersales WHERE status = 'aktif' ORDER BY namasales");
    $salesList = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT kodecustomer, namacustomer FROM mastercustomer WHERE status = 'aktif' ORDER BY namacustomer");
    $customerList = $stmt->fetchAll();
}

// Build query based on filters
$whereConditions = [];
$params = [];

// Date filtering
if ($periodType === 'monthly') {
    $whereConditions[] = "DATE_FORMAT(h.tanggalorder, '%Y-%m') = ?";
    $params[] = $month;
} else {
    if ($startDate) {
        $whereConditions[] = "h.tanggalorder >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $whereConditions[] = "h.tanggalorder <= ?";
        $params[] = $endDate;
    }
}

// Sales filter
if ($salesFilter && $salesFilter !== 'INVALID_SALES_CODE') {
    $whereConditions[] = "h.kodesales = ?";
    $params[] = $salesFilter;
} elseif ($salesFilter === 'INVALID_SALES_CODE') {
    // Sales user without valid kodesales - return empty result
    $whereConditions[] = "1 = 0"; // Always false condition
}

// Customer filter
if ($customerFilter && $customerFilter !== 'INVALID_CUSTOMER_CODE') {
    $whereConditions[] = "h.kodecustomer = ?";
    $params[] = $customerFilter;
} elseif ($customerFilter === 'INVALID_CUSTOMER_CODE') {
    // Customer user without valid kodecustomer - return empty result
    $whereConditions[] = "1 = 0"; // Always false condition
}

if ($statusFilter) {
    $whereConditions[] = "h.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Base query
$baseQuery = "
    SELECT 
        h.noorder,
        h.tanggalorder,
        h.namacustomer,
        h.namasales,
        h.nofaktur,
        h.tanggalfaktur,
        h.totalorder as totalfaktur,
        h.status
    FROM headerorder h
    $whereClause
    ORDER BY h.tanggalorder DESC, h.noorder DESC
";

// Execute query
try {
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    // Log error in production, show user-friendly message
    error_log("Laporan Order Error: " . $e->getMessage());
    $orders = [];
    // Optionally show error to user in development
    if (ini_get('display_errors')) {
        die("Database Error: " . htmlspecialchars($e->getMessage()));
    } else {
        die("Terjadi kesalahan saat memuat data. Silakan hubungi administrator.");
    }
}

// Group data based on report type
$groupedData = [];
$summary = [
    'total_orders' => count($orders),
    'total_value' => 0,
    'completed_orders' => 0,
    'completed_value' => 0
];

// Calculate total value safely
if (!empty($orders)) {
    $totals = array_column($orders, 'totalfaktur');
    $summary['total_value'] = !empty($totals) ? array_sum($totals) : 0;
}

foreach ($orders as $order) {
    if ($order['status'] === 'terima') {
        $summary['completed_orders']++;
        $summary['completed_value'] += $order['totalfaktur'];
    }
    
    if ($reportType === 'customer') {
        $key = $order['namacustomer'];
    } elseif ($reportType === 'sales') {
        $key = $order['namasales'];
    } else {
        $key = 'all';
    }
    
    if (!isset($groupedData[$key])) {
        $groupedData[$key] = [
            'name' => $key,
            'orders' => [],
            'total_orders' => 0,
            'total_value' => 0,
            'completed_orders' => 0,
            'completed_value' => 0
        ];
    }
    
    $groupedData[$key]['orders'][] = $order;
    $groupedData[$key]['total_orders']++;
    $groupedData[$key]['total_value'] += $order['totalfaktur'];
    
    if ($order['status'] === 'terima') {
        $groupedData[$key]['completed_orders']++;
        $groupedData[$key]['completed_value'] += $order['totalfaktur'];
    }
}

// Handle exports
if ($exportFormat === 'pdf') {
    require_once __DIR__ . '/export_pdf.php';
    exit;
} elseif ($exportFormat === 'excel') {
    require_once __DIR__ . '/export_excel.php';
    exit;
} elseif ($exportFormat === 'xlsx') {
    require_once __DIR__ . '/export_xlsx.php';
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<style>
.report-card {
    border-left: 4px solid #007bff;
}
.filter-card {
    border-left: 4px solid #28a745;
}
.summary-card {
    border-left: 4px solid #ffc107;
}
</style>

<div class="flex-grow-1">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1">Laporan Transaksi Order</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Laporan Transaksi Order</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card filter-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Laporan</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Jenis Laporan</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="global" <?php echo $reportType === 'global' ? 'selected' : ''; ?>>Global</option>
                                <option value="customer" <?php echo $reportType === 'customer' ? 'selected' : ''; ?>>Group by Customer</option>
                                <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Group by Sales</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Periode</label>
                            <select name="period" class="form-select" onchange="togglePeriodInputs()">
                                <option value="monthly" <?php echo $periodType === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                                <option value="custom" <?php echo $periodType === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="monthly-input">
                            <label class="form-label">Bulan</label>
                            <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3" id="custom-inputs" style="display: none;">
                            <label class="form-label">Tanggal Awal</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-md-3" id="custom-inputs2" style="display: none;">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <?php if ($user['role'] === 'admin' || $user['role'] === 'operator' || $user['role'] === 'manajemen'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Filter Sales</label>
                            <select name="sales" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Sales</option>
                                <?php foreach ($salesList as $sales): ?>
                                    <option value="<?php echo htmlspecialchars($sales['kodesales']); ?>" <?php echo $salesFilter === $sales['kodesales'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sales['namasales']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter Customer</label>
                            <select name="customer" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Customer</option>
                                <?php foreach ($customerList as $customer): ?>
                                    <option value="<?php echo htmlspecialchars($customer['kodecustomer']); ?>" <?php echo $customerFilter === $customer['kodecustomer'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['namacustomer']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="idle" <?php echo $statusFilter === 'idle' ? 'selected' : ''; ?>>Idle</option>
                                <option value="proses" <?php echo $statusFilter === 'proses' ? 'selected' : ''; ?>>Proses</option>
                                <option value="faktur" <?php echo $statusFilter === 'faktur' ? 'selected' : ''; ?>>Faktur</option>
                                <option value="kirim" <?php echo $statusFilter === 'kirim' ? 'selected' : ''; ?>>Kirim</option>
                                <option value="terima" <?php echo $statusFilter === 'terima' ? 'selected' : ''; ?>>Terima</option>
                                <option value="batal" <?php echo $statusFilter === 'batal' ? 'selected' : ''; ?>>Batal</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                        <?php elseif ($user['role'] === 'sales'): ?>
                        <!-- For sales role: customer and status filter shown -->
                        <div class="col-md-3">
                            <label class="form-label">Filter Customer</label>
                            <select name="customer" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Customer</option>
                                <?php foreach ($customerList as $customer): ?>
                                    <option value="<?php echo htmlspecialchars($customer['kodecustomer']); ?>" <?php echo $customerFilter === $customer['kodecustomer'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['namacustomer']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="idle" <?php echo $statusFilter === 'idle' ? 'selected' : ''; ?>>Idle</option>
                                <option value="proses" <?php echo $statusFilter === 'proses' ? 'selected' : ''; ?>>Proses</option>
                                <option value="faktur" <?php echo $statusFilter === 'faktur' ? 'selected' : ''; ?>>Faktur</option>
                                <option value="kirim" <?php echo $statusFilter === 'kirim' ? 'selected' : ''; ?>>Kirim</option>
                                <option value="terima" <?php echo $statusFilter === 'terima' ? 'selected' : ''; ?>>Terima</option>
                                <option value="batal" <?php echo $statusFilter === 'batal' ? 'selected' : ''; ?>>Batal</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- For customer role: only status filter shown -->
                        <div class="col-md-3">
                            <label class="form-label">Filter Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="idle" <?php echo $statusFilter === 'idle' ? 'selected' : ''; ?>>Idle</option>
                                <option value="proses" <?php echo $statusFilter === 'proses' ? 'selected' : ''; ?>>Proses</option>
                                <option value="faktur" <?php echo $statusFilter === 'faktur' ? 'selected' : ''; ?>>Faktur</option>
                                <option value="kirim" <?php echo $statusFilter === 'kirim' ? 'selected' : ''; ?>>Kirim</option>
                                <option value="terima" <?php echo $statusFilter === 'terima' ? 'selected' : ''; ?>>Terima</option>
                                <option value="batal" <?php echo $statusFilter === 'batal' ? 'selected' : ''; ?>>Batal</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card summary-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-primary mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h4 class="mb-1"><?php echo number_format($summary['total_orders']); ?></h4>
                        <p class="text-muted mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card summary-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-success mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="mb-1"><?php echo number_format($summary['completed_orders']); ?></h4>
                        <p class="text-muted mb-0">Completed Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card summary-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-info mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="mb-1">Rp <?php echo number_format($summary['total_value'], 0, ',', '.'); ?></h4>
                        <p class="text-muted mb-0">Total Value</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card summary-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-warning mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h4 class="mb-1">Rp <?php echo number_format($summary['completed_value'], 0, ',', '.'); ?></h4>
                        <p class="text-muted mb-0">Completed Value</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title mb-3"><i class="fas fa-download"></i> Export Laporan</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" class="btn btn-danger">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'xlsx'])); ?>" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Export Excel (.xls)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-outline-success">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                <strong>PDF:</strong> Format HTML untuk print-to-PDF | 
                                <strong>Excel (.xls):</strong> Format Excel kompatibel | 
                                <strong>CSV:</strong> Format teks untuk kompatibilitas luas
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Data -->
        <div class="card report-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-table"></i> 
                    <?php 
                    $title = 'Laporan Transaksi Order';
                    if ($reportType === 'customer') $title .= ' - Group by Customer';
                    elseif ($reportType === 'sales') $title .= ' - Group by Sales';
                    echo $title;
                    ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($groupedData)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada data</h5>
                        <p class="text-muted">Tidak ada transaksi order yang sesuai dengan filter yang dipilih.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedData as $group): ?>
                        <?php if ($reportType !== 'global'): ?>
                            <div class="border-bottom p-3 bg-light">
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                    <span class="badge bg-primary ms-2"><?php echo $group['total_orders']; ?> Orders</span>
                                    <span class="badge bg-success ms-1">Rp <?php echo number_format($group['total_value'], 0, ',', '.'); ?></span>
                                </h6>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No Order</th>
                                        <th>Tanggal Order</th>
                                        <th>Customer</th>
                                        <th>Sales</th>
                                        <th>No Faktur</th>
                                        <th>Tanggal Faktur</th>
                                        <th>Total Faktur</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group['orders'] as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['noorder']); ?></strong></td>
                                            <td><?php echo date('d/m/Y', strtotime($order['tanggalorder'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['namacustomer']); ?></td>
                                            <td><?php echo htmlspecialchars($order['namasales']); ?></td>
                                            <td>
                                                <?php if ($order['nofaktur']): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($order['nofaktur']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($order['tanggalfaktur']): ?>
                                                    <?php echo date('d/m/Y', strtotime($order['tanggalfaktur'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong>Rp <?php echo number_format($order['totalfaktur'], 0, ',', '.'); ?></strong></td>
                                            <td>
                                                <?php
                                                $statusBadges = [
                                                    'idle' => 'secondary',
                                                    'proses' => 'warning',
                                                    'faktur' => 'info',
                                                    'kirim' => 'primary',
                                                    'terima' => 'success',
                                                    'batal' => 'danger'
                                                ];
                                                $badgeClass = $statusBadges[$order['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($order['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function togglePeriodInputs() {
    const periodType = document.querySelector('select[name="period"]').value;
    const monthlyInput = document.getElementById('monthly-input');
    const customInputs = document.getElementById('custom-inputs');
    const customInputs2 = document.getElementById('custom-inputs2');
    
    if (periodType === 'monthly') {
        monthlyInput.style.display = 'block';
        customInputs.style.display = 'none';
        customInputs2.style.display = 'none';
    } else {
        monthlyInput.style.display = 'none';
        customInputs.style.display = 'block';
        customInputs2.style.display = 'block';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePeriodInputs();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
