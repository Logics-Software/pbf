<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Check permission - only operator, admin, sales, and customer can access
require_roles(['operator', 'admin', 'sales', 'customer']);

$user = current_user();
include __DIR__ . '/includes/header.php';

// Get search parameters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? '');
$tanggal_sampai = trim($_GET['tanggal_sampai'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));

// Build query parameters
$params = [];
$where = [];

if ($search !== '') {
    $where[] = '(namacustomer LIKE ? OR namasales LIKE ? OR noorder LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}

if ($tanggal_mulai !== '') {
    $where[] = 'tanggalorder >= ?';
    $params[] = $tanggal_mulai;
}

if ($tanggal_sampai !== '') {
    $where[] = 'tanggalorder <= ?';
    $params[] = $tanggal_sampai;
}

// Filter by customer code if user role is customer
if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
    $where[] = 'kodecustomer = ?';
    $params[] = $user['kodecustomer'];
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
require_once __DIR__ . '/includes/db.php';
$pdo = get_pdo_connection();

$countSql = "SELECT COUNT(*) as total FROM headerorder $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];

// Get orders with pagination
$offset = ($page - 1) * $limit;
$sql = "SELECT * FROM headerorder $whereClause ORDER BY tanggalorder DESC, noorder DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalPages = ceil($total / $limit);

// Status options
$statusOptions = [
    '' => 'Semua Status',
    'idle' => 'Idle',
    'proses' => 'Proses',
    'faktur' => 'Faktur',
    'kirim' => 'Kirim',
    'terima' => 'Terima',
    'batal' => 'Batal'
];

// Status badges
$statusBadges = [
    'idle' => 'secondary',
    'proses' => 'warning',
    'faktur' => 'info',
    'kirim' => 'primary',
    'terima' => 'success',
    'batal' => 'danger'
];
?>

<div class="flex-grow-1">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Transaksi Order</h3>
            <a href="order_form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Order
            </a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'not_editable'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Order hanya dapat diedit jika statusnya "Idle". Order dengan status lain tidak dapat dimodifikasi.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Pencarian</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="No Order, Customer, atau Sales">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" 
                               value="<?php echo htmlspecialchars($tanggal_mulai); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="tanggal_sampai" class="form-label">Tanggal Sampai</label>
                        <input type="date" class="form-control" id="tanggal_sampai" name="tanggal_sampai" 
                               value="<?php echo htmlspecialchars($tanggal_sampai); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="limit" class="form-label">Per Halaman</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <?php if ($search || $status || $tanggal_mulai || $tanggal_sampai): ?>
                    <a href="order.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Reset Filter
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (count($orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark-green">
                                <tr>
                                    <th class="text-center" style="width: 10%;">No Order</th>
                                    <th class="text-center" style="width: 8%;">Tanggal</th>
                                    <th class="text-center" style="width: 20%;">Customer</th>
                                    <th class="text-center" style="width: 15%;">Sales</th>
                                    <th class="text-center" style="width: 12%;">Total Order</th>
                                    <th class="text-center" style="width: 8%;">Status</th>
                                    <th class="text-center" style="width: 10%;">No Faktur</th>
                                    <th class="text-center" style="width: 17%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="text-center">
                                            <strong><?php echo htmlspecialchars($order['noorder']); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?php echo date('d/m/Y', strtotime($order['tanggalorder'])); ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order['namacustomer']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($order['namasales']): ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($order['namasales']); ?></strong>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <strong>Rp <?php echo number_format($order['totalorder'], 0, ',', '.'); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $statusBadges[$order['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($order['nofaktur']): ?>
                                                <strong><?php echo htmlspecialchars($order['nofaktur']); ?></strong>
                                                <?php if ($order['tanggalfaktur']): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($order['tanggalfaktur'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if ($order['status'] === 'idle'): ?>
                                                    <a href="order_form.php?id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['noorder']); ?>')" 
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary" disabled title="Edit tidak tersedia untuk status <?php echo ucfirst($order['status']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary" disabled title="Delete tidak tersedia untuk status <?php echo ucfirst($order['status']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="order_view.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-outline-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada data order</h5>
                        <p class="text-muted">
                            <?php if ($search || $status || $tanggal_mulai || $tanggal_sampai): ?>
                                Tidak ada order yang sesuai dengan filter yang dipilih.
                            <?php else: ?>
                                Belum ada order yang dibuat. <a href="order_form.php">Klik di sini</a> untuk membuat order pertama.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Order pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                                <?php echo $totalPages; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

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
</style>

<script>
function deleteOrder(id, noorder) {
    if (confirm(`Apakah Anda yakin ingin menghapus order ${noorder}?`)) {
        fetch('api/order/index.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Order berhasil dihapus');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menghapus order');
        });
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
