<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('mastercustomer')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$msg = '';
if (isset($_GET['msg'])) {
	switch ($_GET['msg']) {
		case 'created': $msg = '<div class="alert alert-success">Customer berhasil ditambahkan</div>'; break;
		case 'saved': $msg = '<div class="alert alert-success">Customer berhasil diupdate</div>'; break;
		case 'deleted': $msg = '<div class="alert alert-success">Customer berhasil dihapus</div>'; break;
		case 'used_in_transaction': $msg = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Tidak bisa dihapus!</strong> Data customer telah digunakan dalam transaksi. Lakukan penonaktifkan jika data sudah tidak digunakan.</div>'; break;
		case 'error': $msg = '<div class="alert alert-danger">Terjadi kesalahan</div>'; break;
	}
}

// Pagination, search, and sorting
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['perPage'] ?? 10)));

// Sorting parameters
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'kodecustomer';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort column
$allowedSorts = ['kodecustomer', 'namacustomer', 'alamatcustomer', 'namasales'];
if (!in_array($sortBy, $allowedSorts)) {
	$sortBy = 'kodecustomer';
}

// Validate sort order
if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
	$sortOrder = 'asc';
}

$where = '';
$params = [];

if ($q !== '') {
	$where = 'WHERE (kodecustomer LIKE ? OR namacustomer LIKE ? OR alamatcustomer LIKE ? OR notelepon LIKE ? OR contactperson LIKE ? OR kodesales LIKE ? OR namasales LIKE ?)';
	$searchTerm = '%' . $q . '%';
	$params = array_fill(0, 7, $searchTerm);
}

// Count total
$countSql = 'SELECT COUNT(*) FROM mastercustomer ' . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

// Get data
$offset = ($page - 1) * $perPage;
$sql = 'SELECT * FROM mastercustomer ' . $where . ' ORDER BY ' . $sortBy . ' ' . $sortOrder . ' LIMIT ? OFFSET ?';
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customer = $stmt->fetchAll();

$totalPages = ceil($total / $perPage);

include __DIR__ . '/includes/header.php';
?>
<style>
.table-dark-blue {
	background-color: #1e3a8a !important; /* Dark blue */
	color: white !important;
}
.table-dark-blue th {
	background-color: #1e3a8a !important;
	color: white !important;
	border-color: #1e40af !important;
}

/* Sortable header styling */
.sortable-header {
	cursor: pointer;
	user-select: none;
	position: relative;
	transition: background-color 0.2s ease;
}

.sortable-header:hover {
	background-color: #1e40af !important;
}

.sortable-header .sort-icon {
	margin-left: 5px;
	font-size: 0.8em;
	opacity: 0.7;
}

.sortable-header.active .sort-icon {
	opacity: 1;
}
</style>
<div class="flex-grow-1">
	<div class="container">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h3>Master Customer</h3>
			<a href="mastercustomer_form.php" class="btn btn-primary">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
					<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
				</svg>
				Tambah Customer
			</a>
		</div>
		
		<?php echo $msg; ?>
		
		<form class="row g-2 align-items-end mb-3" method="get" action="">
			<div class="col-md-6">
				<label class="form-label">Pencarian</label>
				<input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cari kode/nama customer/alamat/telepon/contact/sales">
			</div>
			<div class="col-md-3">
				<label class="form-label">Per halaman</label>
				<input type="number" class="form-control" name="perPage" min="1" max="100" value="<?php echo (int)$perPage; ?>">
			</div>
			<div class="col-md-3">
				<button class="btn btn-outline-primary w-100" type="submit">Terapkan</button>
			</div>
		</form>
		
		<?php if ($q !== ''): ?>
			<div class="alert alert-info">
				<strong>Hasil pencarian:</strong> <?php echo $total; ?> customer ditemukan untuk "<em><?php echo htmlspecialchars($q); ?></em>"
				<a href="mastercustomer.php" class="btn btn-sm btn-outline-secondary ms-2">Hapus filter</a>
			</div>
		<?php endif; ?>
		
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead class="table-dark-blue">
							<tr>
								<th class="sortable-header <?php echo $sortBy === 'kodecustomer' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'kodecustomer', 'order' => $sortBy === 'kodecustomer' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Kode
										<span class="sort-icon">
											<?php if ($sortBy === 'kodecustomer'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th class="sortable-header <?php echo $sortBy === 'namacustomer' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'namacustomer', 'order' => $sortBy === 'namacustomer' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Nama Customer
										<span class="sort-icon">
											<?php if ($sortBy === 'namacustomer'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th class="sortable-header <?php echo $sortBy === 'alamatcustomer' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'alamatcustomer', 'order' => $sortBy === 'alamatcustomer' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Alamat
										<span class="sort-icon">
											<?php if ($sortBy === 'alamatcustomer'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th>No. Telepon</th>
								<th>Contact Person</th>
								<th class="sortable-header <?php echo $sortBy === 'namasales' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'namasales', 'order' => $sortBy === 'namasales' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Nama Sales
										<span class="sort-icon">
											<?php if ($sortBy === 'namasales'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th>Status</th>
								<th width="120">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($customer)): ?>
								<tr>
									<td colspan="9" class="text-center text-muted py-4">
										<?php if ($q !== ''): ?>
											Tidak ada customer yang ditemukan
										<?php else: ?>
											Belum ada data customer
										<?php endif; ?>
									</td>
								</tr>
							<?php else: ?>
								<?php foreach ($customer as $row): ?>
									<tr>
										<td><strong><?php echo htmlspecialchars($row['kodecustomer']); ?></strong></td>
										<td><?php echo htmlspecialchars($row['namacustomer']); ?></td>
										<td>
											<?php if ($row['alamatcustomer']): ?>
												<?php echo htmlspecialchars(substr($row['alamatcustomer'], 0, 50)); ?>
												<?php if (strlen($row['alamatcustomer']) > 50): ?>...<?php endif; ?>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($row['notelepon']): ?>
												<?php echo htmlspecialchars($row['notelepon']); ?>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($row['contactperson']): ?>
												<?php echo htmlspecialchars($row['contactperson']); ?>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($row['namasales']): ?>
												<?php echo htmlspecialchars($row['namasales']); ?>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php 
											$statusClass = $row['status'] === 'aktif' ? 'bg-success' : 'bg-danger';
											$statusText = $row['status'] === 'aktif' ? 'Aktif' : 'Non Aktif';
											?>
											<span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
										</td>
										<td>
											<div class="btn-group btn-group-sm">
												<a href="mastercustomer_form.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary" title="Edit">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
														<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L6.5 12.5a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5v-4a.5.5 0 0 1 .146-.354L12.146.146zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
													</svg>
												</a>
												<button class="btn btn-outline-danger" title="Hapus" onclick="showDeleteConfirm('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['namacustomer']); ?>', '<?php echo htmlspecialchars($row['kodecustomer']); ?>')">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
														<path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
														<path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
													</svg>
												</button>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		
		<?php if ($totalPages > 1): ?>
		<nav aria-label="Pagination">
			<ul class="pagination justify-content-center">
				<?php if ($page > 1): ?>
					<li class="page-item">
						<a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
					</li>
				<?php endif; ?>
				
				<?php
				$start = max(1, $page - 2);
				$end = min($totalPages, $page + 2);
				for ($i = $start; $i <= $end; $i++):
				?>
					<li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
						<a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
					</li>
				<?php endfor; ?>
				
				<?php if ($page < $totalPages): ?>
					<li class="page-item">
						<a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
					</li>
				<?php endif; ?>
			</ul>
		</nav>
		<?php endif; ?>
	</div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-danger text-white">
				<h5 class="modal-title" id="deleteConfirmModalLabel">
					<i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
				</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="text-center mb-3">
					<i class="fas fa-trash-alt text-danger" style="font-size: 3rem;"></i>
				</div>
				<h6 class="text-center mb-3">Apakah Anda yakin ingin menghapus customer ini?</h6>
				<div class="alert alert-warning">
					<strong>Detail Customer:</strong><br>
					<strong>Kode:</strong> <span id="deleteItemCode"></span><br>
					<strong>Nama:</strong> <span id="deleteItemName"></span>
				</div>
				<div class="alert alert-danger">
					<i class="fas fa-exclamation-circle me-2"></i>
					<strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan!
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
					<i class="fas fa-times me-1"></i>Batal
				</button>
				<button type="button" class="btn btn-danger" id="confirmDeleteBtn">
					<i class="fas fa-trash me-1"></i>Ya, Hapus
				</button>
			</div>
		</div>
	</div>
</div>

<script>
// Delete Confirmation Modal Functionality
let deleteItemId = null;

function showDeleteConfirm(id, name, code) {
    // Check if customer is used in transactions first
    fetch(`api/check_customer_usage.php?kodecustomer=${encodeURIComponent(code)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.used_in_transaction) {
                    // Show warning message instead of delete modal
                    showWarningMessage(name, code);
                } else {
                    // Safe to delete, show confirmation modal
                    deleteItemId = id;
                    document.getElementById('deleteItemCode').textContent = code;
                    document.getElementById('deleteItemName').textContent = name;
                    
                    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                    modal.show();
                }
            } else {
                alert('Terjadi kesalahan saat memeriksa data customer: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memeriksa data customer');
        });
}

function showWarningMessage(name, code) {
    // Create centered warning modal
    const modalDiv = document.createElement('div');
    modalDiv.className = 'modal fade show';
    modalDiv.style.cssText = 'display: block; background-color: rgba(0,0,0,0.5); z-index: 9999;';
    modalDiv.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Peringatan
                    </h5>
                    <button type="button" class="btn-close" onclick="closeWarningModal()" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-ban text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3">Tidak bisa dihapus!</h6>
                    <div class="alert alert-warning">
                        <strong>Detail Customer:</strong><br>
                        <strong>Nama:</strong> ${name}<br>
                        <strong>Kode:</strong> ${code}
                    </div>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Alasan:</strong> Data customer telah digunakan dalam transaksi.<br>
                        Lakukan penonaktifkan jika data sudah tidak digunakan.
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-warning" onclick="closeWarningModal()">
                        <i class="fas fa-check me-1"></i>Mengerti
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Add to body
    document.body.appendChild(modalDiv);
    
    // Auto remove after 10 seconds
    setTimeout(() => {
        closeWarningModal();
    }, 10000);
}

function closeWarningModal() {
    const modal = document.querySelector('.modal.show');
    if (modal) {
        modal.remove();
    }
}

// Handle delete confirmation
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteItemId) {
        // Redirect to delete page
        window.location.href = 'mastercustomer_delete.php?id=' + deleteItemId;
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
