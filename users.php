<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('users')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

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
							<a class="btn btn-sm btn-danger" href="users.php?delete=<?php echo (int)$r['id']; ?>" onclick="return confirm('Hapus user ini?');">Hapus</a>
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
<?php include __DIR__ . '/includes/footer.php'; ?>


