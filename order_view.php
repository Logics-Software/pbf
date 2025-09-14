<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Check permission - only operator, admin, sales, and customer can access
require_roles(['operator', 'admin', 'sales', 'customer']);

$user = current_user();

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    header('Location: order.php');
    exit;
}

// Get database connection
require_once __DIR__ . '/includes/db.php';
$pdo = get_pdo_connection();

// Get order header data with customer filtering
$sql = "SELECT * FROM headerorder WHERE id = ?";
$params = [$orderId];

// Filter by customer code if user role is customer
if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
    $sql .= " AND kodecustomer = ?";
    $params[] = $user['kodecustomer'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$order = $stmt->fetch();

if (!$order) {
    header('Location: order.php');
    exit;
}

// Get order detail data
$stmt = $pdo->prepare("SELECT * FROM detailorder WHERE noorder = ? ORDER BY nourut ASC");
$stmt->execute([$order['noorder']]);
$details = $stmt->fetchAll();

// Status badges
$statusBadges = [
    'idle' => 'secondary',
    'proses' => 'warning',
    'faktur' => 'success',
    'batal' => 'danger'
];

include __DIR__ . '/includes/header.php';
?>

<style>
.table-dark-green {
    background-color: #14532d !important; /* Dark green */
    color: white !important;
}
.table-dark-green th {
    background-color: #14532d !important;
    color: white !important;
    border-color: #166534 !important;
}

.order-header-card {
    border-left: 4px solid #14532d;
}

.order-detail-card {
    border-left: 4px solid #1e3a8a;
}
</style>

<div class="flex-grow-1">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Detail Order</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="order.php">Order</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($order['noorder']); ?></li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <a href="order_form.php?id=<?php echo $order['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Order
                </a>
                <a href="order.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <!-- Order Header Information -->
        <div class="card order-header-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice"></i> Informasi Order
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="150"><strong>No. Order:</strong></td>
                                <td><?php echo htmlspecialchars($order['noorder']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tanggal Order:</strong></td>
                                <td><?php echo date('d/m/Y', strtotime($order['tanggalorder'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $statusBadges[$order['status']] ?? 'secondary'; ?> fs-6">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Order:</strong></td>
                                <td>
                                    <h5 class="text-success mb-0">
                                        Rp <?php echo number_format($order['totalorder'], 0, ',', '.'); ?>
                                    </h5>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="150"><strong>Customer:</strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['namacustomer']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['kodecustomer']); ?></small>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Sales:</strong></td>
                                <td>
                                    <?php if ($order['namasales']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['namasales']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['kodesales']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($order['nofaktur'])): ?>
                            <tr>
                                <td><strong>No. Faktur:</strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['nofaktur']); ?></strong>
                                    <?php if (!empty($order['tanggalfaktur'])): ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($order['tanggalfaktur'])); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($order['tanggalfaktur'])): ?>
                            <tr>
                                <td><strong>Tanggal Faktur:</strong></td>
                                <td><?php echo date('d/m/Y', strtotime($order['tanggalfaktur'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($order['namapengirim'])): ?>
                            <tr>
                                <td><strong>Nama Pengirim:</strong></td>
                                <td><?php echo htmlspecialchars($order['namapengirim']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Details -->
        <div class="card order-detail-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Detail Barang
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($details)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada detail barang</h5>
                        <p class="text-muted">Order ini belum memiliki detail barang.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark-green">
                                <tr>
                                    <th class="text-center" style="width: 5%;">No</th>
                                    <th class="text-center" style="width: 15%;">Kode Barang</th>
                                    <th style="width: 25%;">Nama Barang</th>
                                    <th class="text-center" style="width: 10%;">Satuan</th>
                                    <th class="text-center" style="width: 10%;">Jumlah</th>
                                    <th class="text-end" style="width: 15%;">Harga Satuan</th>
                                    <th class="text-center" style="width: 10%;">Discount</th>
                                    <th class="text-end" style="width: 15%;">Total Harga</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $grandTotal = 0;
                                foreach ($details as $index => $detail): 
                                    $grandTotal += $detail['totalharga'];
                                ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($detail['kodebarang']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($detail['namabarang']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($detail['satuan']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo number_format($detail['jumlah'], 0, ',', '.'); ?></span>
                                        </td>
                                        <td class="text-end">
                                            Rp <?php echo number_format($detail['hargasatuan'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($detail['discount'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo number_format($detail['discount'], 0, ',', '.'); ?>%</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <strong>Rp <?php echo number_format($detail['totalharga'], 0, ',', '.'); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="7" class="text-end"><strong>Grand Total:</strong></td>
                                    <td class="text-end">
                                        <h5 class="text-success mb-0">
                                            Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?>
                                        </h5>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="row mt-4 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle"></i> Informasi Tambahan
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Jumlah Item:</strong></td>
                                <td><?php echo count($details); ?> item</td>
                            </tr>
                            <tr>
                                <td><strong>Total Quantity:</strong></td>
                                <td><?php echo number_format(array_sum(array_column($details, 'jumlah')), 0, ',', '.'); ?> pcs</td>
                            </tr>
                            <tr>
                                <td><strong>Dibuat:</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Diupdate:</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-calculator"></i> Ringkasan Order
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td class="text-end">Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Total Order:</strong></td>
                                <td class="text-end">
                                    <h6 class="text-success mb-0">
                                        Rp <?php echo number_format($order['totalorder'], 0, ',', '.'); ?>
                                    </h6>
                                </td>
                            </tr>
                            <?php if ($grandTotal != $order['totalorder']): ?>
                            <tr class="table-warning">
                                <td><strong>Selisih:</strong></td>
                                <td class="text-end">
                                    <strong class="text-warning">
                                        Rp <?php echo number_format($order['totalorder'] - $grandTotal, 0, ',', '.'); ?>
                                    </strong>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
