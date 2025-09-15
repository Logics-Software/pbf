<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('users')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$msg = '';
if (isset($_GET['msg'])) {
	switch ($_GET['msg']) {
		case 'created': $msg = '<div class="alert alert-success">User berhasil ditambahkan</div>'; break;
		case 'saved': $msg = '<div class="alert alert-success">User berhasil diupdate</div>'; break;
		case 'deleted': $msg = '<div class="alert alert-success">User berhasil dihapus</div>'; break;
		case 'used_in_transaction': $msg = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Tidak bisa dihapus!</strong> Data user telah digunakan dalam transaksi order. Lakukan penonaktifkan jika data sudah tidak digunakan.</div>'; break;
		case 'error': $msg = '<div class="alert alert-danger">Terjadi kesalahan</div>'; break;
	}
}

// Delete (soft through status toggle? Here hard delete on request)
if (isset($_GET['delete'])) {
	$id = (int)$_GET['delete'];
	$stmt = $pdo->prepare('DELETE FROM user WHERE id = ?');
	$stmt->execute([$id]);
	header('Location: users.php?msg=deleted');
	exit;
}

// Pagination and search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? max(1, min(100, (int)$_GET['perPage'])) : 10;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = '';
$params = [];
if ($q !== '') {
	$where = "WHERE username LIKE ? OR namalengkap LIKE ? OR email LIKE ? OR role LIKE ?";
	$like = "%$q%";
	$params = [$like, $like, $like, $like];
}

$total = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = (int)ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM user $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="container">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h3 class="mb-0">Users</h3>
		<a href="user_form.php" class="btn btn-primary">Tambah User</a>
	</div>
	
	<?php echo $msg; ?>

	<form class="row g-2 align-items-end mb-3" method="get" action="">
		<div class="col-md-6">
			<label class="form-label">Pencarian</label>
			<input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cari username/nama/email/role">
		</div>
		<div class="col-md-3">
			<label class="form-label">Per halaman</label>
			<input type="number" class="form-control" name="perPage" min="1" max="100" value="<?php echo (int)$perPage; ?>">
		</div>
		<div class="col-md-3">
			<button class="btn btn-outline-primary w-100" type="submit">Terapkan</button>
		</div>
	</form>

	<div class="table-responsive">
		<table class="table table-striped table-bordered align-middle">
			<thead class="table-dark">
				<tr>
					<th>ID</th>
					<th>Username</th>
					<th>Nama Lengkap</th>
					<th>Role</th>
					<th>Email</th>
					<th>Status</th>
					<th style="width:160px">Aksi</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!$rows): ?>
					<tr><td colspan="7" class="text-center">Tidak ada data</td></tr>
				<?php else: foreach ($rows as $r): ?>
					<tr>
						<td><?php echo (int)$r['id']; ?></td>
						<td><?php echo htmlspecialchars($r['username']); ?></td>
						<td><?php echo htmlspecialchars($r['namalengkap']); ?></td>
						<td><?php echo htmlspecialchars($r['role']); ?></td>
						<td><?php echo htmlspecialchars($r['email']); ?></td>
						<td>
							<span class="badge <?php echo $r['status']==='aktif'?'bg-success':'bg-secondary'; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
						</td>
						<td>
							<a class="btn btn-sm btn-warning" href="user_form.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
							<button class="btn btn-sm btn-danger" onclick="showDeleteConfirm('<?php echo $r['id']; ?>', '<?php echo htmlspecialchars($r['namalengkap']); ?>', '<?php echo htmlspecialchars($r['username']); ?>')">Hapus</button>
						</td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ($pages > 1): ?>
	<nav>
		<ul class="pagination">
			<?php for ($i=1; $i <= $pages; $i++): $active = $i === $page; ?>
				<li class="page-item <?php echo $active ? 'active' : ''; ?>">
					<a class="page-link" href="?page=<?php echo $i; ?>&perPage=<?php echo (int)$perPage; ?>&q=<?php echo urlencode($q); ?>"><?php echo $i; ?></a>
				</li>
			<?php endfor; ?>
		</ul>
	</nav>
	<?php endif; ?>
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
				<h6 class="text-center mb-3">Apakah Anda yakin ingin menghapus user ini?</h6>
				<div class="alert alert-warning">
					<strong>Detail User:</strong><br>
					<strong>Username:</strong> <span id="deleteItemCode"></span><br>
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

function showDeleteConfirm(id, name, username) {
    // Check if user is used in transactions first
    fetch(`api/check_user_usage.php?iduser=${encodeURIComponent(id)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.used_in_transaction) {
                    // Show warning message instead of delete modal
                    showWarningMessage(name, username);
                } else {
                    // Safe to delete, show confirmation modal
                    deleteItemId = id;
                    document.getElementById('deleteItemCode').textContent = username;
                    document.getElementById('deleteItemName').textContent = name;
                    
                    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                    modal.show();
                }
            } else {
                alert('Terjadi kesalahan saat memeriksa data user: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memeriksa data user');
        });
}

function showWarningMessage(name, username) {
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
                        <strong>Detail User:</strong><br>
                        <strong>Nama:</strong> ${name}<br>
                        <strong>Username:</strong> ${username}
                    </div>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Alasan:</strong> Data user telah digunakan dalam transaksi order.<br>
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
        window.location.href = 'users.php?delete=' + deleteItemId;
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


