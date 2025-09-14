<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();

// Check if user has access to reports
if (!can_access('reports')) {
    header('Location: dashboard.php');
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<style>
.report-card {
    transition: transform 0.2s;
    border-left: 4px solid #007bff;
}
.report-card:hover {
    transform: translateY(-2px);
}
.report-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}
</style>

<div class="flex-grow-1">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1">Reports</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="row g-4">
            <!-- Order Transaction Report -->
            <div class="col-lg-4 col-md-6">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <div class="report-icon bg-primary mx-auto mb-3">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h5 class="card-title">Laporan Transaksi Order</h5>
                        <p class="card-text text-muted">
                            Laporan lengkap transaksi order dengan berbagai filter dan pengelompokan data. 
                            Mendukung export PDF dan Excel.
                        </p>
                        <div class="mt-3">
                            <a href="laporan_transaksi_order.php" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Lihat Laporan
                            </a>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-check-circle text-success"></i> 
                                Global, Customer, Sales Grouping
                            </small><br>
                            <small class="text-muted">
                                <i class="fas fa-check-circle text-success"></i> 
                                Export PDF, Excel (.xls) & CSV
                            </small><br>
                            <small class="text-muted">
                                <i class="fas fa-check-circle text-success"></i> 
                                Filter Periode & Sales/Customer
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placeholder for future reports -->
            <div class="col-lg-4 col-md-6">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <div class="report-icon bg-success mx-auto mb-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="card-title">Laporan Penjualan</h5>
                        <p class="card-text text-muted">
                            Laporan analisis penjualan dengan grafik dan trend analysis.
                            Coming soon...
                        </p>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-clock"></i> Coming Soon
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock text-warning"></i> 
                                Dalam Pengembangan
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <div class="report-icon bg-info mx-auto mb-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">Laporan Customer</h5>
                        <p class="card-text text-muted">
                            Analisis data customer dan performa penjualan per customer.
                            Coming soon...
                        </p>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-clock"></i> Coming Soon
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock text-warning"></i> 
                                Dalam Pengembangan
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <div class="report-icon bg-warning mx-auto mb-3">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h5 class="card-title">Laporan Sales</h5>
                        <p class="card-text text-muted">
                            Performa sales dan target achievement dengan analisis detail.
                            Coming soon...
                        </p>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-clock"></i> Coming Soon
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock text-warning"></i> 
                                Dalam Pengembangan
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <div class="report-icon bg-danger mx-auto mb-3">
                            <i class="fas fa-box"></i>
                        </div>
                        <h5 class="card-title">Laporan Inventory</h5>
                        <p class="card-text text-muted">
                            Analisis stok barang, pergerakan inventory, dan reorder point.
                            Coming soon...
                        </p>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-clock"></i> Coming Soon
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock text-warning"></i> 
                                Dalam Pengembangan
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <div class="report-icon bg-secondary mx-auto mb-3">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h5 class="card-title">Laporan Keuangan</h5>
                        <p class="card-text text-muted">
                            Laporan keuangan lengkap dengan profit & loss analysis.
                            Coming soon...
                        </p>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-clock"></i> Coming Soon
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock text-warning"></i> 
                                Dalam Pengembangan
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Fitur Laporan Transaksi Order:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>3 Jenis Pengelompokan: Global, Customer, Sales</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Filter Periode: Bulanan atau Custom Range</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Filter Sales dan Customer</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Export ke PDF, Excel (.xls) dan CSV</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Summary Statistics</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Kolom Laporan:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-list text-primary me-2"></i>No Order</li>
                                    <li><i class="fas fa-list text-primary me-2"></i>Tanggal Order</li>
                                    <li><i class="fas fa-list text-primary me-2"></i>Nama Customer</li>
                                    <li><i class="fas fa-list text-primary me-2"></i>Nama Sales</li>
                                    <li><i class="fas fa-list text-primary me-2"></i>No Faktur</li>
                                    <li><i class="fas fa-list text-primary me-2"></i>Tanggal Faktur</li>
                                    <li><i class="fas fa-list text-primary me-2"></i>Total Faktur</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
