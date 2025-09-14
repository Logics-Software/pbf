<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('mastersales')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$msg = '';
if (isset($_GET['msg'])) {
	switch ($_GET['msg']) {
		case 'created': $msg = '<div class="alert alert-success">Sales berhasil ditambahkan</div>'; break;
		case 'saved': $msg = '<div class="alert alert-success">Sales berhasil diupdate</div>'; break;
		case 'deleted': $msg = '<div class="alert alert-success">Sales berhasil dihapus</div>'; break;
		case 'error': $msg = '<div class="alert alert-danger">Terjadi kesalahan</div>'; break;
	}
}

// Pagination and search
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['perPage'] ?? 10)));

$where = '';
$params = [];

if ($q !== '') {
	$where = 'WHERE (kodesales LIKE ? OR namasales LIKE ? OR alamatsales LIKE ? OR notelepon LIKE ?)';
	$searchTerm = '%' . $q . '%';
	$params = array_fill(0, 4, $searchTerm);
}

// Count total
$countSql = 'SELECT COUNT(*) FROM mastersales ' . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

// Get data
$offset = ($page - 1) * $perPage;
$sql = 'SELECT * FROM mastersales ' . $where . ' ORDER BY kodesales ASC LIMIT ? OFFSET ?';
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

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
</style>
<div class="flex-grow-1">
	<div class="container">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h3>Master Sales</h3>
			<a href="mastersales_form.php" class="btn btn-primary">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
					<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
				</svg>
				Tambah Sales
			</a>
		</div>
		
		<?php echo $msg; ?>
		
		<form class="row g-2 align-items-end mb-3" method="get" action="">
			<div class="col-md-6">
				<label class="form-label">Pencarian</label>
				<input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cari kode/nama sales/alamat/telepon">
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
				<strong>Hasil pencarian:</strong> <?php echo $total; ?> sales ditemukan untuk "<em><?php echo htmlspecialchars($q); ?></em>"
				<a href="mastersales.php" class="btn btn-sm btn-outline-secondary ms-2">Hapus filter</a>
			</div>
		<?php endif; ?>
		
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead class="table-dark-blue">
							<tr>
								<th>Kode</th>
								<th>Nama Sales</th>
								<th>Alamat</th>
								<th>No. Telepon</th>
								<th>Status</th>
								<th width="120">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($sales)): ?>
								<tr>
									<td colspan="6" class="text-center text-muted py-4">
										<?php if ($q !== ''): ?>
											Tidak ada sales yang ditemukan
										<?php else: ?>
											Belum ada data sales
										<?php endif; ?>
									</td>
								</tr>
							<?php else: ?>
								<?php foreach ($sales as $row): ?>
									<tr>
										<td><strong><?php echo htmlspecialchars($row['kodesales']); ?></strong></td>
										<td><?php echo htmlspecialchars($row['namasales']); ?></td>
										<td>
											<?php if ($row['alamatsales']): ?>
												<?php echo htmlspecialchars(substr($row['alamatsales'], 0, 50)); ?>
												<?php if (strlen($row['alamatsales']) > 50): ?>...<?php endif; ?>
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
											<?php 
											$statusClass = $row['status'] === 'aktif' ? 'bg-success' : 'bg-danger';
											$statusText = $row['status'] === 'aktif' ? 'Aktif' : 'Non Aktif';
											?>
											<span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
										</td>
										<td>
											<div class="btn-group btn-group-sm">
												<a href="mastersales_form.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary" title="Edit">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
														<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L6.5 12.5a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5v-4a.5.5 0 0 1 .146-.354L12.146.146zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
													</svg>
												</a>
												<a href="mastersales_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-danger" title="Hapus" onclick="return confirm('Yakin hapus sales ini?')">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
														<path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
														<path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
													</svg>
												</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
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

<?php include __DIR__ . '/includes/footer.php'; ?>