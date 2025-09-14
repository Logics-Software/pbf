<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('masterbarang')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = get_pdo_connection();

$msg = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'bulk_update':
                    if (isset($_POST['selected_items']) && isset($_POST['new_kondisi'])) {
                        $selectedItems = $_POST['selected_items'];
                        $newKondisi = $_POST['new_kondisi'];
                        
                        if (empty($selectedItems)) {
                            throw new Exception('Pilih minimal satu barang untuk diupdate');
                        }
                        
                        $placeholders = str_repeat('?,', count($selectedItems) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE masterbarang SET kondisiharga = ? WHERE kodebarang IN ($placeholders)");
                        $params = array_merge([$newKondisi], $selectedItems);
                        $stmt->execute($params);
                        
                        $msg = '<div class="alert alert-success">Berhasil mengupdate ' . count($selectedItems) . ' barang dengan kondisi harga: ' . ucfirst($newKondisi) . '</div>';
                    }
                    break;
                    
                case 'update_single':
                    if (isset($_POST['kodebarang']) && isset($_POST['kondisi_harga'])) {
                        $kodebarang = $_POST['kodebarang'];
                        $kondisiHarga = $_POST['kondisi_harga'];
                        
                        $stmt = $pdo->prepare("UPDATE masterbarang SET kondisiharga = ? WHERE kodebarang = ?");
                        $stmt->execute([$kondisiHarga, $kodebarang]);
                        
                        $msg = '<div class="alert alert-success">Kondisi harga barang ' . htmlspecialchars($kodebarang) . ' berhasil diupdate menjadi: ' . ucfirst($kondisiHarga) . '</div>';
                    }
                    break;
                    
                case 'filter_update':
                    if (isset($_POST['filter_kondisi']) && isset($_POST['new_kondisi'])) {
                        $filterKondisi = $_POST['filter_kondisi'];
                        $newKondisi = $_POST['new_kondisi'];
                        
                        $whereClause = '';
                        $params = [$newKondisi];
                        
                        if ($filterKondisi !== 'all') {
                            $whereClause = ' WHERE kondisiharga = ?';
                            $params[] = $filterKondisi;
                        }
                        
                        $stmt = $pdo->prepare("UPDATE masterbarang SET kondisiharga = ?" . $whereClause);
                        $stmt->execute($params);
                        
                        $affectedRows = $stmt->rowCount();
                        $msg = '<div class="alert alert-success">Berhasil mengupdate ' . $affectedRows . ' barang dengan kondisi harga: ' . ucfirst($newKondisi) . '</div>';
                    }
                    break;
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Get filter parameters
$filterKondisi = isset($_GET['filter_kondisi']) ? $_GET['filter_kondisi'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

// Build query
$whereConditions = [];
$params = [];

if ($filterKondisi && $filterKondisi !== 'all') {
    $whereConditions[] = 'kondisiharga = ?';
    $params[] = $filterKondisi;
}

if ($search) {
    $whereConditions[] = '(kodebarang LIKE ? OR namabarang LIKE ? OR namapabrik LIKE ?)';
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count
$countQuery = "SELECT COUNT(*) FROM masterbarang $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalItems = $stmt->fetchColumn();

// Get paginated data
$offset = ($page - 1) * $perPage;
$dataQuery = "SELECT kodebarang, namabarang, namapabrik, namagolongan, hargajual, discjual, kondisiharga, stokakhir, status 
              FROM masterbarang $whereClause 
              ORDER BY kodebarang ASC 
              LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$barang = $stmt->fetchAll();

// Get statistics
$statsQuery = "SELECT kondisiharga, COUNT(*) as count FROM masterbarang GROUP BY kondisiharga ORDER BY count DESC";
$stmt = $pdo->query($statsQuery);
$stats = $stmt->fetchAll();

$totalPages = ceil($totalItems / $perPage);

// Available price conditions
$kondisiOptions = [
    'baru' => 'Baru',
    'normal' => 'Normal', 
    'promo' => 'Promo',
    'sale' => 'Sale',
    'spesial' => 'Spesial',
    'deals' => 'Deals'
];

include __DIR__ . '/includes/header.php';
?>

<style>
.kondisi-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.stats-card {
    transition: transform 0.2s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.bulk-actions {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.filter-section {
    background: #e9ecef;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.table-responsive {
    border-radius: 0.375rem;
    overflow: hidden;
}

.action-buttons {
    white-space: nowrap;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-tags me-2"></i>Kelola Kondisi Harga Barang</h2>
                    <p class="text-muted mb-0">Update kondisi harga barang secara individual atau bulk</p>
                </div>
                <a href="masterbarang.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Master Barang
                </a>
            </div>

            <?php echo $msg; ?>
            <?php echo $error; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5><i class="fas fa-chart-bar me-2"></i>Statistik Kondisi Harga</h5>
                </div>
                <?php foreach ($stats as $stat): ?>
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center p-3">
                            <h6 class="card-title mb-1"><?php echo $kondisiOptions[$stat['kondisiharga']] ?? ucfirst($stat['kondisiharga']); ?></h6>
                            <h4 class="text-primary mb-0"><?php echo number_format($stat['count']); ?></h4>
                            <small class="text-muted">barang</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="filter_kondisi" class="form-label">Filter Kondisi Harga</label>
                        <select name="filter_kondisi" id="filter_kondisi" class="form-select">
                            <option value="all" <?php echo $filterKondisi === 'all' ? 'selected' : ''; ?>>Semua Kondisi</option>
                            <?php foreach ($kondisiOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $filterKondisi === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cari Barang</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Kode, Nama, atau Pabrik..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="masterbarang_kondisi_harga.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <h6><i class="fas fa-tasks me-2"></i>Bulk Actions</h6>
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_update">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="new_kondisi" class="form-label">Update Kondisi Harga ke:</label>
                            <select name="new_kondisi" id="new_kondisi" class="form-select" required>
                                <option value="">Pilih Kondisi Baru</option>
                                <?php foreach ($kondisiOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-success" onclick="selectAll()">
                                    <i class="fas fa-check-square me-1"></i>Pilih Semua
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="deselectAll()">
                                    <i class="fas fa-square me-1"></i>Batal Pilih
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-warning" onclick="return confirmBulkUpdate()">
                                    <i class="fas fa-sync me-1"></i>Update Terpilih
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filter Update Section -->
            <div class="bulk-actions">
                <h6><i class="fas fa-filter me-2"></i>Update Berdasarkan Filter</h6>
                <form method="POST" onsubmit="return confirmFilterUpdate()">
                    <input type="hidden" name="action" value="filter_update">
                    <input type="hidden" name="filter_kondisi" value="<?php echo htmlspecialchars($filterKondisi); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="filter_new_kondisi" class="form-label">Update semua barang yang terfilter ke:</label>
                            <select name="new_kondisi" id="filter_new_kondisi" class="form-select" required>
                                <option value="">Pilih Kondisi Baru</option>
                                <?php foreach ($kondisiOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Update Semua (<?php echo number_format($totalItems); ?> barang)
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Data Table -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Data Barang 
                        <span class="badge bg-primary"><?php echo number_format($totalItems); ?> total</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                    </th>
                                    <th>Kode Barang</th>
                                    <th>Nama Barang</th>
                                    <th>Pabrik</th>
                                    <th>Golongan</th>
                                    <th>Harga Jual</th>
                                    <th>Discount</th>
                                    <th>Kondisi Harga</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($barang)): ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                        Tidak ada data barang
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($barang as $row): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($row['kodebarang']); ?>" 
                                               class="item-checkbox" form="bulkForm">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['kodebarang']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['namabarang']); ?></td>
                                    <td><?php echo htmlspecialchars($row['namapabrik'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['namagolongan'] ?: '-'); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($row['hargajual'], 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($row['discjual'], 2, ',', '.'); ?>%</td>
                                    <td>
                                        <?php 
                                        $kondisiHargaClass = '';
                                        $kondisiHargaText = '';
                                        switch($row['kondisiharga'] ?? 'baru') {
                                            case 'baru': $kondisiHargaClass = 'bg-primary'; $kondisiHargaText = 'Baru'; break;
                                            case 'normal': $kondisiHargaClass = 'bg-secondary'; $kondisiHargaText = 'Normal'; break;
                                            case 'promo': $kondisiHargaClass = 'bg-info'; $kondisiHargaText = 'Promo'; break;
                                            case 'sale': $kondisiHargaClass = 'bg-danger'; $kondisiHargaText = 'Sale'; break;
                                            case 'spesial': $kondisiHargaClass = 'bg-warning'; $kondisiHargaText = 'Spesial'; break;
                                            case 'deals': $kondisiHargaClass = 'bg-success'; $kondisiHargaText = 'Deals'; break;
                                            default: $kondisiHargaClass = 'bg-primary'; $kondisiHargaText = 'Baru'; break;
                                        }
                                        ?>
                                        <span class="badge kondisi-badge <?php echo $kondisiHargaClass; ?>"><?php echo $kondisiHargaText; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $row['stokakhir'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo number_format($row['stokakhir'], 0, ',', '.'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $row['status'] === 'aktif' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $row['status'] === 'aktif' ? 'Aktif' : 'Non Aktif'; ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="showQuickUpdate('<?php echo htmlspecialchars($row['kodebarang']); ?>', '<?php echo $row['kondisiharga']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
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
</div>

<!-- Quick Update Modal -->
<div class="modal fade" id="quickUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Kondisi Harga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="quickUpdateForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_single">
                    <input type="hidden" name="kodebarang" id="modal_kodebarang">
                    
                    <div class="mb-3">
                        <label class="form-label">Kode Barang:</label>
                        <input type="text" class="form-control" id="modal_kodebarang_display" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_kondisi_harga" class="form-label">Kondisi Harga Baru:</label>
                        <select name="kondisi_harga" id="modal_kondisi_harga" class="form-select" required>
                            <?php foreach ($kondisiOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAll() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    selectAllCheckbox.checked = true;
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectAllCheckbox.checked = false;
}

function confirmBulkUpdate() {
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    const newKondisi = document.getElementById('new_kondisi').value;
    
    if (selectedItems.length === 0) {
        alert('Pilih minimal satu barang untuk diupdate');
        return false;
    }
    
    if (!newKondisi) {
        alert('Pilih kondisi harga baru');
        return false;
    }
    
    return confirm(`Apakah Anda yakin ingin mengupdate ${selectedItems.length} barang dengan kondisi harga: ${newKondisi}?`);
}

function confirmFilterUpdate() {
    const newKondisi = document.getElementById('filter_new_kondisi').value;
    const totalItems = <?php echo $totalItems; ?>;
    
    if (!newKondisi) {
        alert('Pilih kondisi harga baru');
        return false;
    }
    
    return confirm(`Apakah Anda yakin ingin mengupdate ${totalItems} barang yang terfilter dengan kondisi harga: ${newKondisi}?`);
}

function showQuickUpdate(kodebarang, currentKondisi) {
    document.getElementById('modal_kodebarang').value = kodebarang;
    document.getElementById('modal_kodebarang_display').value = kodebarang;
    document.getElementById('modal_kondisi_harga').value = currentKondisi;
    
    const modal = new bootstrap.Modal(document.getElementById('quickUpdateModal'));
    modal.show();
}

// Update select all checkbox when individual checkboxes change
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            selectAllCheckbox.checked = checkedBoxes.length === checkboxes.length;
            selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
