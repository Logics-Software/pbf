<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('mastersales')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

$data = [
	'kodesales' => '',
	'namasales' => '',
	'alamatsales' => '',
	'notelepon' => '',
	'status' => 'aktif',
];

if ($isEdit) {
	$stmt = $pdo->prepare('SELECT * FROM mastersales WHERE id = ?');
	$stmt->execute([$id]);
	$existing = $stmt->fetch();
	if (!$existing) {
		$error = 'Sales tidak ditemukan';
	} else {
		$data = array_merge($data, $existing);
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$data['kodesales'] = trim($_POST['kodesales'] ?? '');
	$data['namasales'] = trim($_POST['namasales'] ?? '');
	$data['alamatsales'] = trim($_POST['alamatsales'] ?? '');
	$data['notelepon'] = trim($_POST['notelepon'] ?? '');
	$data['status'] = $_POST['status'] ?? 'aktif';

	if ($data['kodesales'] === '' || $data['namasales'] === '') {
		$error = 'Kode Sales dan Nama Sales wajib diisi';
	}

	if (!$error) {
		// Check for duplicate kodesales
		$checkSql = $isEdit ? 'SELECT id FROM mastersales WHERE kodesales = ? AND id != ?' : 'SELECT id FROM mastersales WHERE kodesales = ?';
		$checkParams = $isEdit ? [$data['kodesales'], $id] : [$data['kodesales']];
		$checkStmt = $pdo->prepare($checkSql);
		$checkStmt->execute($checkParams);
		if ($checkStmt->fetch()) {
			$error = 'Kode Sales tersebut sudah digunakan';
		}
	}

	if (!$error) {
		if ($isEdit) {
			$sql = 'UPDATE mastersales SET kodesales=?, namasales=?, alamatsales=?, notelepon=?, status=? WHERE id=?';
			$params = [$data['kodesales'], $data['namasales'], $data['alamatsales'], $data['notelepon'], $data['status'], $id];
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			header('Location: mastersales.php?msg=saved');
			exit;
		} else {
			$sql = 'INSERT INTO mastersales (kodesales,namasales,alamatsales,notelepon,status) VALUES (?,?,?,?,?)';
			$params = [$data['kodesales'], $data['namasales'], $data['alamatsales'], $data['notelepon'], $data['status']];
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			header('Location: mastersales.php?msg=created');
			exit;
		}
	}
}

include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 720px;">
	<h3 class="mb-3"><?php echo $isEdit ? 'Edit Sales' : 'Tambah Sales'; ?></h3>
	<?php if ($error): ?>
		<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
	<?php endif; ?>
	<div class="card">
		<div class="card-body">
			<form method="post" action="">
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label">Kode Sales</label>
						<input type="text" class="form-control" name="kodesales" required value="<?php echo htmlspecialchars($data['kodesales']); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label">Nama Sales</label>
						<input type="text" class="form-control" name="namasales" required value="<?php echo htmlspecialchars($data['namasales']); ?>">
					</div>
					<div class="col-12">
						<label class="form-label">Alamat</label>
						<textarea class="form-control" name="alamatsales" rows="3"><?php echo htmlspecialchars($data['alamatsales']); ?></textarea>
					</div>
					<div class="col-md-6">
						<label class="form-label">No. Telepon</label>
						<input type="text" class="form-control" name="notelepon" value="<?php echo htmlspecialchars($data['notelepon']); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label">Status</label>
						<select name="status" class="form-select" required>
							<?php foreach (['aktif','non_aktif'] as $st): ?>
								<option value="<?php echo $st; ?>" <?php echo $data['status']===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="mt-3 d-flex gap-2">
					<button type="submit" class="btn btn-primary">Simpan</button>
					<a href="mastersales.php" class="btn btn-secondary">Batal</a>
				</div>
			</form>
		</div>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
