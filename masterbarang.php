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
if (isset($_GET['msg'])) {
	switch ($_GET['msg']) {
		case 'created': $msg = '<div class="alert alert-success">Barang berhasil ditambahkan</div>'; break;
		case 'saved': $msg = '<div class="alert alert-success">Barang berhasil diupdate</div>'; break;
		case 'deleted': $msg = '<div class="alert alert-success">Barang berhasil dihapus</div>'; break;
	}
}

// Pagination, search, and sorting
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? max(1, min(100, (int)$_GET['perPage'])) : 10;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Sorting parameters
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'kodebarang';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort column
$allowedSorts = ['kodebarang', 'namabarang', 'namapabrik', 'namagolongan'];
if (!in_array($sortBy, $allowedSorts)) {
	$sortBy = 'kodebarang';
}

// Validate sort order
if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
	$sortOrder = 'asc';
}

$where = '';
$params = [];
if ($q !== '') {
	$where = "WHERE kodebarang LIKE ? OR namabarang LIKE ? OR namapabrik LIKE ? OR namagolongan LIKE ? OR satuan LIKE ? OR supplier LIKE ? OR kemasan LIKE ? OR nie LIKE ?";
	$like = "%$q%";
	$params = [$like, $like, $like, $like, $like, $like, $like, $like];
}

$total = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM masterbarang $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = (int)ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM masterbarang $where ORDER BY $sortBy $sortOrder LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$barang = $stmt->fetchAll();

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
			<h3>Master Barang</h3>
			<div class="d-flex gap-2">
				<button type="button" class="btn btn-outline-secondary" id="autoRefreshToggle" title="Toggle Auto Refresh">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
						<path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
						<path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
					</svg>
					<span id="autoRefreshText">Auto Refresh</span>
				</button>
				<a href="masterbarang_form.php" class="btn btn-primary">Tambah Barang</a>
			</div>
		</div>
		
		<?php echo $msg; ?>
		
		<!-- Auto Refresh Status -->
		<div id="autoRefreshStatus" class="alert alert-info d-none" role="alert">
			<div class="d-flex align-items-center">
				<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
				<span>Auto refresh aktif - Data akan diperbarui setiap 5 detik</span>
				<small class="ms-auto text-muted" id="refreshCountdown">5</small>
			</div>
		</div>
		
		<form class="row g-2 align-items-end mb-3" method="get" action="">
			<div class="col-md-6">
				<label class="form-label">Pencarian</label>
				<input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cari kode/nama barang/pabrik/golongan/satuan">
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
				<strong>Hasil pencarian:</strong> <?php echo $total; ?> barang ditemukan untuk "<em><?php echo htmlspecialchars($q); ?></em>"
				<a href="masterbarang.php" class="btn btn-sm btn-outline-secondary ms-2">Hapus filter</a>
			</div>
		<?php endif; ?>
		
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead class="table-dark-blue">
							<tr>
								<th class="sortable-header <?php echo $sortBy === 'kodebarang' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'kodebarang', 'order' => $sortBy === 'kodebarang' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Kode
										<span class="sort-icon">
											<?php if ($sortBy === 'kodebarang'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th class="sortable-header <?php echo $sortBy === 'namabarang' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'namabarang', 'order' => $sortBy === 'namabarang' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Nama Barang
										<span class="sort-icon">
											<?php if ($sortBy === 'namabarang'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th>Satuan</th>
								<th class="sortable-header <?php echo $sortBy === 'namapabrik' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'namapabrik', 'order' => $sortBy === 'namapabrik' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Pabrik
										<span class="sort-icon">
											<?php if ($sortBy === 'namapabrik'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th class="sortable-header <?php echo $sortBy === 'namagolongan' ? 'active' : ''; ?>">
									<a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'namagolongan', 'order' => $sortBy === 'namagolongan' && $sortOrder === 'asc' ? 'desc' : 'asc', 'page' => 1])); ?>" class="text-white text-decoration-none">
										Golongan
										<span class="sort-icon">
											<?php if ($sortBy === 'namagolongan'): ?>
												<?php echo $sortOrder === 'asc' ? '↑' : '↓'; ?>
											<?php else: ?>
												↕
											<?php endif; ?>
										</span>
									</a>
								</th>
								<th>Harga Jual</th>
								<th>Discount</th>
								<th>Stok</th>
								<th>Status</th>
								<th>Foto</th>
								<th width="120">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($barang)): ?>
								<tr>
									<td colspan="15" class="text-center text-muted">Belum ada data barang</td>
								</tr>
							<?php else: ?>
								<?php foreach ($barang as $row): ?>
									<tr>
										<td><strong><?php echo htmlspecialchars($row['kodebarang']); ?></strong></td>
										<td><?php echo htmlspecialchars($row['namabarang']); ?></td>
										<td><?php echo htmlspecialchars($row['satuan']); ?></td>
										<td>
											<?php if ($row['namapabrik']): ?>
												<?php echo htmlspecialchars($row['namapabrik']); ?>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($row['namagolongan']): ?>
												<?php echo htmlspecialchars($row['namagolongan']); ?>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td class="text-end">Rp <?php echo number_format($row['hargajual'], 0, ',', '.'); ?></td>
										<td class="text-end"><?php echo number_format($row['discjual'], 2, ',', '.'); ?> %</td>
										<td class="text-center">
											<span class="badge <?php echo $row['stokakhir'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
												<?php echo number_format($row['stokakhir'], 0, ',', '.'); ?>
											</span>
										</td>
										<td>
											<?php 
											$statusClass = $row['status'] === 'aktif' ? 'bg-success' : 'bg-danger';
											$statusText = $row['status'] === 'aktif' ? 'Aktif' : 'Non Aktif';
											?>
											<span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
										</td>
										<td>
											<?php 
											$photos = [];
											if ($row['foto']) {
												try {
													$photos = json_decode($row['foto'], true);
													if (!is_array($photos)) {
														$photos = [$row['foto']]; // Handle old single photo format
													}
												} catch (Exception $e) {
													$photos = [$row['foto']]; // Handle old single photo format
												}
											}
											?>
											<?php if (!empty($photos)): ?>
												<div class="d-flex align-items-center">
													<?php if (count($photos) === 1): ?>
														<img src="<?php echo htmlspecialchars($photos[0]); ?>" 
															 alt="Foto" 
															 class="img-thumbnail" 
															 style="width: 40px; height: 40px; object-fit: cover; cursor: pointer;" 
															 data-bs-toggle="modal" 
															 data-bs-target="#imageModal" 
															 data-image-src="<?php echo htmlspecialchars($photos[0]); ?>"
															 data-image-name="<?php echo htmlspecialchars($row['namabarang']); ?>"
															 data-photos='<?php echo htmlspecialchars(json_encode($photos)); ?>'
															 title="Klik untuk melihat foto">
													<?php else: ?>
														<div class="position-relative">
															<img src="<?php echo htmlspecialchars($photos[0]); ?>" 
																 alt="Foto" 
																 class="img-thumbnail" 
																 style="width: 40px; height: 40px; object-fit: cover; cursor: pointer;" 
																 data-bs-toggle="modal" 
																 data-bs-target="#imageModal" 
																 data-image-src="<?php echo htmlspecialchars($photos[0]); ?>"
																 data-image-name="<?php echo htmlspecialchars($row['namabarang']); ?>"
																 data-photos='<?php echo htmlspecialchars(json_encode($photos)); ?>'
																 title="Klik untuk melihat foto (<?php echo count($photos); ?> foto)">
															<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size: 0.6em;">
																<?php echo count($photos); ?>
															</span>
														</div>
													<?php endif; ?>
												</div>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td>
											<div class="btn-group btn-group-sm">
												<a href="masterbarang_form.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary" title="Edit">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
														<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L6.5 12.5a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5v-4a.5.5 0 0 1 .146-.354L12.146.146zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
													</svg>
												</a>
												<a href="masterbarang_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-danger" title="Hapus" onclick="return confirm('Yakin hapus barang ini?')">
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
		</div>
		
		<?php if ($pages > 1): ?>
		<nav class="mt-3">
			<ul class="pagination justify-content-center">
				<?php for ($i=1; $i <= $pages; $i++): $active = $i === $page; ?>
					<li class="page-item <?php echo $active ? 'active' : ''; ?>">
						<a class="page-link" href="?page=<?php echo $i; ?>&perPage=<?php echo (int)$perPage; ?>&q=<?php echo urlencode($q); ?>"><?php echo $i; ?></a>
					</li>
				<?php endfor; ?>
			</ul>
		</nav>
		<?php endif; ?>

	</div>
</div>

<!-- Image Modal with Zoom and Multiple Photos -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" style="width: 800px; max-width: 90vw;">
		<div class="modal-content" style="height: 600px; max-height: 90vh;">
			<div class="modal-header">
				<h5 class="modal-title" id="imageModalLabel">Foto Barang</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body text-center d-flex flex-column" style="height: calc(100% - 120px); padding: 1rem;">
				<!-- Photo Navigation -->
				<div id="photoNavigation" class="mb-3" style="display: none;">
					<div class="d-flex justify-content-between align-items-center">
						<button type="button" class="btn btn-outline-primary btn-sm" id="prevPhoto" title="Foto Sebelumnya">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
								<path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
							</svg>
							Sebelumnya
						</button>
						<span id="photoCounter" class="badge bg-primary"></span>
						<button type="button" class="btn btn-outline-primary btn-sm" id="nextPhoto" title="Foto Selanjutnya">
							Selanjutnya
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
								<path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
							</svg>
						</button>
					</div>
				</div>
				
				<!-- Image Container with Fixed Size -->
				<div class="image-container flex-grow-1 d-flex align-items-center justify-content-center" style="position: relative; overflow: hidden; border-radius: 8px; background: #f8f9fa; min-height: 400px;">
					<img id="modalImage" src="" alt="Foto Barang" style="max-width: 100%; max-height: 100%; transition: transform 0.3s ease; cursor: grab;" draggable="false">
				</div>
				
				<!-- Zoom Controls -->
				<div class="mt-3">
					<div class="btn-group" role="group">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="zoomOut" title="Zoom Out">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-zoom-out" viewBox="0 0 16 16">
							<path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11M13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
							<path d="M10.344 11.742q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1 6.5 6.5 0 0 1-1.398 1.4z"/>
							<path fill-rule="evenodd" d="M3 6.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5"/>
						</svg>
						</button>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="zoomIn" title="Zoom In">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-zoom-in" viewBox="0 0 16 16">
							<path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11M13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
							<path d="M10.344 11.742q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1 6.5 6.5 0 0 1-1.398 1.4z"/>
							<path fill-rule="evenodd" d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 0-1H6V3.5a.5.5 0 0 1 .5-.5"/>
						</svg>
						</button>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="resetZoom" title="Reset Zoom">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fullscreen" viewBox="0 0 16 16">
							<path d="M1.5 1a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0v-4A1.5 1.5 0 0 1 1.5 0h4a.5.5 0 0 1 0 1zM10 .5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 16 1.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5M.5 10a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 0 14.5v-4a.5.5 0 0 1 .5-.5m15 0a.5.5 0 0 1 .5.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5"/>
						</svg>						</button>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="fitToScreen" title="Fit to Screen">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fullscreen-exit" viewBox="0 0 16 16">
							<path d="M5.5 0a.5.5 0 0 1 .5.5v4A1.5 1.5 0 0 1 4.5 6h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5m5 0a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 10 4.5v-4a.5.5 0 0 1 .5-.5M0 10.5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 6 11.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5m10 1a1.5 1.5 0 0 1 1.5-1.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0z"/>
						</svg>
						</button>
					</div>
					<div class="mt-0">
						<small class="text-muted">
							<span id="zoomLevel">100%</span> | 
							<span id="swipeHint">Geser kiri/kanan untuk navigasi foto</span>
						</small>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('imageModalLabel');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const resetZoomBtn = document.getElementById('resetZoom');
    const fitToScreenBtn = document.getElementById('fitToScreen');
    const zoomLevel = document.getElementById('zoomLevel');
    const photoNavigation = document.getElementById('photoNavigation');
    const prevPhotoBtn = document.getElementById('prevPhoto');
    const nextPhotoBtn = document.getElementById('nextPhoto');
    const photoCounter = document.getElementById('photoCounter');
    
    let currentZoom = 1;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let translateX = 0;
    let translateY = 0;
    let currentPhotos = [];
    let currentPhotoIndex = 0;
    
    // Modal event listener
    imageModal.addEventListener('show.bs.modal', function(event) {
        const trigger = event.relatedTarget;
        const imageSrc = trigger.getAttribute('data-image-src');
        const imageName = trigger.getAttribute('data-image-name');
        const photosData = trigger.getAttribute('data-photos');
        
        try {
            currentPhotos = JSON.parse(photosData);
            if (!Array.isArray(currentPhotos)) {
                currentPhotos = [imageSrc];
            }
        } catch (e) {
            currentPhotos = [imageSrc];
        }
        
        currentPhotoIndex = currentPhotos.indexOf(imageSrc);
        if (currentPhotoIndex === -1) currentPhotoIndex = 0;
        
        modalImage.src = imageSrc;
        modalTitle.textContent = 'Foto: ' + imageName;
        
        // Show/hide navigation based on photo count
        if (currentPhotos.length > 1) {
            photoNavigation.style.display = 'block';
            updatePhotoCounter();
            updateNavigationButtons();
        } else {
            photoNavigation.style.display = 'none';
        }
        
        // Update swipe hint
        if (typeof updateSwipeHint === 'function') {
            updateSwipeHint();
        }
        
        // Reset zoom and position
        currentZoom = 1;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
        updateZoomLevel();
    });
    
    // Photo navigation
    prevPhotoBtn.addEventListener('click', function() {
        if (currentPhotoIndex > 0) {
            currentPhotoIndex--;
            modalImage.src = currentPhotos[currentPhotoIndex];
            updatePhotoCounter();
            updateNavigationButtons();
            // Reset zoom when changing photos
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateImageTransform();
            updateZoomLevel();
            updateSwipeHint();
        }
    });
    
    nextPhotoBtn.addEventListener('click', function() {
        if (currentPhotoIndex < currentPhotos.length - 1) {
            currentPhotoIndex++;
            modalImage.src = currentPhotos[currentPhotoIndex];
            updatePhotoCounter();
            updateNavigationButtons();
            // Reset zoom when changing photos
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateImageTransform();
            updateZoomLevel();
            updateSwipeHint();
        }
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (imageModal.classList.contains('show')) {
            if (e.key === 'ArrowLeft' && currentPhotoIndex > 0) {
                prevPhotoBtn.click();
            } else if (e.key === 'ArrowRight' && currentPhotoIndex < currentPhotos.length - 1) {
                nextPhotoBtn.click();
            }
        }
    });
    
    function updatePhotoCounter() {
        photoCounter.textContent = `${currentPhotoIndex + 1} / ${currentPhotos.length}`;
    }
    
    function updateNavigationButtons() {
        prevPhotoBtn.disabled = currentPhotoIndex === 0;
        nextPhotoBtn.disabled = currentPhotoIndex === currentPhotos.length - 1;
    }
    
    // Zoom controls
    zoomInBtn.addEventListener('click', function() {
        currentZoom = Math.min(currentZoom * 1.2, 5);
        updateImageTransform();
        updateZoomLevel();
    });
    
    zoomOutBtn.addEventListener('click', function() {
        currentZoom = Math.max(currentZoom / 1.2, 0.1);
        updateImageTransform();
        updateZoomLevel();
    });
    
    resetZoomBtn.addEventListener('click', function() {
        currentZoom = 1;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
        updateZoomLevel();
    });
    
    fitToScreenBtn.addEventListener('click', function() {
        const container = modalImage.parentElement;
        const containerRect = container.getBoundingClientRect();
        const imageRect = modalImage.getBoundingClientRect();
        
        const scaleX = (containerRect.width - 40) / imageRect.width;
        const scaleY = (containerRect.height - 40) / imageRect.height;
        currentZoom = Math.min(scaleX, scaleY, 1);
        
        translateX = 0;
        translateY = 0;
        updateImageTransform();
        updateZoomLevel();
    });
    
    // Mouse drag functionality
    modalImage.addEventListener('mousedown', function(e) {
        if (currentZoom > 1) {
            isDragging = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
            modalImage.style.cursor = 'grabbing';
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            updateImageTransform();
        }
    });
    
    document.addEventListener('mouseup', function() {
        if (isDragging) {
            isDragging = false;
            modalImage.style.cursor = currentZoom > 1 ? 'grab' : 'default';
        }
    });
    
    // Touch support for zoom and swipe navigation
    let touchStartX = 0;
    let touchStartY = 0;
    let touchStartTime = 0;
    let isSwipeGesture = false;
    
    modalImage.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            touchStartX = touch.clientX;
            touchStartY = touch.clientY;
            touchStartTime = Date.now();
            
            if (currentZoom > 1) {
                isDragging = true;
                startX = touch.clientX - translateX;
                startY = touch.clientY - translateY;
                isSwipeGesture = false;
            } else {
                isSwipeGesture = true;
                isDragging = false;
            }
        }
    });
    
    document.addEventListener('touchmove', function(e) {
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            const diffX = touchStartX - touch.clientX;
            const diffY = touchStartY - touch.clientY;
            
            if (isDragging && currentZoom > 1) {
                // Zoom drag mode
                e.preventDefault();
                translateX = touch.clientX - startX;
                translateY = touch.clientY - startY;
                updateImageTransform();
            } else if (isSwipeGesture && currentZoom === 1 && currentPhotos.length > 1) {
                // Swipe navigation mode
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 10) {
                    e.preventDefault();
                    // Visual feedback during swipe
                    const swipeThreshold = 50;
                    if (Math.abs(diffX) > swipeThreshold) {
                        modalImage.style.opacity = '0.7';
                    }
                }
            }
        }
    });
    
    document.addEventListener('touchend', function(e) {
        if (isDragging) {
            isDragging = false;
            if (currentZoom > 1) {
                modalImage.style.cursor = 'grab';
            }
        }
        
        if (isSwipeGesture && currentZoom === 1 && currentPhotos.length > 1) {
            const touchEndTime = Date.now();
            const touchDuration = touchEndTime - touchStartTime;
            const touch = e.changedTouches[0];
            const diffX = touchStartX - touch.clientX;
            const diffY = touchStartY - touch.clientY;
            
            // Reset opacity
            modalImage.style.opacity = '1';
            
            // Check if it's a valid swipe gesture
            if (touchDuration < 500 && Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0 && currentPhotoIndex < currentPhotos.length - 1) {
                    // Swipe left - next photo
                    nextPhotoBtn.click();
                } else if (diffX < 0 && currentPhotoIndex > 0) {
                    // Swipe right - previous photo
                    prevPhotoBtn.click();
                }
            }
        }
        
        isSwipeGesture = false;
    });
    
    // Mouse wheel zoom
    modalImage.addEventListener('wheel', function(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        currentZoom = Math.max(0.1, Math.min(5, currentZoom * delta));
        updateImageTransform();
        updateZoomLevel();
    });
    
    function updateImageTransform() {
        modalImage.style.transform = `scale(${currentZoom}) translate(${translateX}px, ${translateY}px)`;
        modalImage.style.cursor = currentZoom > 1 ? 'grab' : 'default';
    }
    
    function updateZoomLevel() {
        zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
        updateSwipeHint();
    }
    
    function updateSwipeHint() {
        const swipeHint = document.getElementById('swipeHint');
        if (currentZoom > 1) {
            swipeHint.textContent = 'Klik dan drag untuk memindahkan gambar';
        } else if (currentPhotos.length > 1) {
            swipeHint.textContent = 'Geser kiri/kanan untuk navigasi foto';
        } else {
            swipeHint.textContent = 'Zoom untuk melihat detail';
        }
    }
});

// Auto Refresh Functionality - Simple Version
document.addEventListener('DOMContentLoaded', function() {
    let autoRefreshEnabled = false;
    let refreshInterval = null;
    let countdownInterval = null;
    let countdown = 5;
    
    const autoRefreshToggle = document.getElementById('autoRefreshToggle');
    const autoRefreshText = document.getElementById('autoRefreshText');
    const autoRefreshStatus = document.getElementById('autoRefreshStatus');
    const refreshCountdown = document.getElementById('refreshCountdown');
    
    console.log('Auto refresh elements found:', {
        toggle: !!autoRefreshToggle,
        text: !!autoRefreshText,
        status: !!autoRefreshStatus,
        countdown: !!refreshCountdown
    });
    
    // Start countdown
    function startCountdown() {
        console.log('Starting countdown');
        countdown = 5;
        if (refreshCountdown) refreshCountdown.textContent = countdown;
        
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        
        countdownInterval = setInterval(function() {
            countdown--;
            if (refreshCountdown) refreshCountdown.textContent = countdown;
            console.log('Countdown:', countdown);
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                if (refreshCountdown) refreshCountdown.textContent = '5';
            }
        }, 1000);
    }
    
    // Stop countdown
    function stopCountdown() {
        console.log('Stopping countdown');
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        if (refreshCountdown) refreshCountdown.textContent = '5';
    }
    
    // Refresh page data
    function refreshData() {
        console.log('Refreshing data...');
        
        // Show loading indicator
        if (autoRefreshStatus) {
            autoRefreshStatus.classList.remove('alert-info');
            autoRefreshStatus.classList.add('alert-warning');
            const span = autoRefreshStatus.querySelector('span');
            if (span) span.textContent = 'Memperbarui data...';
        }
        
        // Reload the page
        window.location.reload();
    }
    
    // Start auto refresh
    function startAutoRefresh() {
        console.log('Starting auto refresh');
        
        if (refreshInterval) {
            console.log('Auto refresh already running');
            return;
        }
        
        autoRefreshEnabled = true;
        
        if (autoRefreshToggle) {
            autoRefreshToggle.classList.remove('btn-outline-secondary');
            autoRefreshToggle.classList.add('btn-success');
        }
        
        if (autoRefreshText) {
            autoRefreshText.textContent = 'Auto Refresh ON';
        }
        
        if (autoRefreshStatus) {
            autoRefreshStatus.classList.remove('d-none');
        }
        
        console.log('Auto refresh enabled, setting up interval');
        
        // Simple auto refresh every 5 seconds
        refreshInterval = setInterval(function() {
            console.log('Auto refresh tick - starting refresh process');
            startCountdown();
            setTimeout(refreshData, 5000);
        }, 5000);
        
        console.log('Auto refresh started successfully');
    }
    
    // Stop auto refresh
    function stopAutoRefresh() {
        console.log('Stopping auto refresh');
        
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
        stopCountdown();
        
        autoRefreshEnabled = false;
        
        if (autoRefreshToggle) {
            autoRefreshToggle.classList.remove('btn-success');
            autoRefreshToggle.classList.add('btn-outline-secondary');
        }
        
        if (autoRefreshText) {
            autoRefreshText.textContent = 'Auto Refresh';
        }
        
        if (autoRefreshStatus) {
            autoRefreshStatus.classList.add('d-none');
        }
        
        console.log('Auto refresh stopped');
    }
    
    // Toggle auto refresh
    if (autoRefreshToggle) {
        autoRefreshToggle.addEventListener('click', function() {
            console.log('Toggle button clicked, current state:', autoRefreshEnabled);
            if (autoRefreshEnabled) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
            // Save preference when toggled
            localStorage.setItem('masterbarang_autoRefresh', autoRefreshEnabled.toString());
            console.log('Preference saved:', autoRefreshEnabled);
        });
    }
    
    // Check for auto refresh preference in localStorage
    const savedPreference = localStorage.getItem('masterbarang_autoRefresh');
    console.log('Saved preference:', savedPreference);
    if (savedPreference === 'true') {
        console.log('Restoring auto refresh from saved preference');
        startAutoRefresh();
    }
    
    console.log('Auto refresh functionality initialized');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>