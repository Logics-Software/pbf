<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('mastercustomer')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

$data = [
	'kodecustomer' => '',
	'namacustomer' => '',
	'alamatcustomer' => '',
	'notelepon' => '',
	'contactperson' => '',
	'kodesales' => '',
	'namasales' => '',
	'status' => 'aktif',
];

// Fetch active sales data for dropdown
$salesStmt = $pdo->prepare('SELECT kodesales, namasales FROM mastersales WHERE status = ? ORDER BY namasales');
$salesStmt->execute(['aktif']);
$salesData = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($isEdit) {
	$stmt = $pdo->prepare('SELECT * FROM mastercustomer WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	if (!$row) {
		header('Location: mastercustomer.php?msg=error');
		exit;
	}
	$data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$data['kodecustomer'] = trim($_POST['kodecustomer'] ?? '');
	$data['namacustomer'] = trim($_POST['namacustomer'] ?? '');
	$data['alamatcustomer'] = trim($_POST['alamatcustomer'] ?? '');
	$data['notelepon'] = trim($_POST['notelepon'] ?? '');
	$data['contactperson'] = trim($_POST['contactperson'] ?? '');
	$data['kodesales'] = trim($_POST['kodesales'] ?? '');
	$data['namasales'] = trim($_POST['namasales'] ?? '');
	$data['status'] = $_POST['status'] ?? 'aktif';

	// Auto-populate namasales based on selected kodesales
	if ($data['kodesales']) {
		foreach ($salesData as $sales) {
			if ($sales['kodesales'] === $data['kodesales']) {
				$data['namasales'] = $sales['namasales'];
				break;
			}
		}
	}

	if ($data['kodecustomer'] === '' || $data['namacustomer'] === '') {
		$error = 'Kode Customer dan Nama Customer wajib diisi';
	} else {
		// Check for duplicate kodecustomer
		$checkSql = 'SELECT id FROM mastercustomer WHERE kodecustomer = ?';
		$checkParams = [$data['kodecustomer']];
		if ($isEdit) {
			$checkSql .= ' AND id <> ?';
			$checkParams[] = $id;
		}
		$checkStmt = $pdo->prepare($checkSql);
		$checkStmt->execute($checkParams);
		if ($checkStmt->fetch()) {
			$error = 'Kode customer tersebut sudah digunakan';
		}
	}

	if (!$error) {
		if ($isEdit) {
			$sql = 'UPDATE mastercustomer SET kodecustomer=?, namacustomer=?, alamatcustomer=?, notelepon=?, contactperson=?, kodesales=?, namasales=?, status=? WHERE id=?';
			$params = [$data['kodecustomer'], $data['namacustomer'], $data['alamatcustomer'] ?: null, $data['notelepon'] ?: null, $data['contactperson'] ?: null, $data['kodesales'] ?: null, $data['namasales'] ?: null, $data['status'], $id];
		} else {
			$sql = 'INSERT INTO mastercustomer (kodecustomer,namacustomer,alamatcustomer,notelepon,contactperson,kodesales,namasales,status) VALUES (?,?,?,?,?,?,?,?)';
			$params = [$data['kodecustomer'], $data['namacustomer'], $data['alamatcustomer'] ?: null, $data['notelepon'] ?: null, $data['contactperson'] ?: null, $data['kodesales'] ?: null, $data['namasales'] ?: null, $data['status']];
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		header('Location: mastercustomer.php?msg=' . ($isEdit ? 'saved' : 'created'));
		exit;
	}
}

include __DIR__ . '/includes/header.php';
?>
<div class="flex-grow-1">
	<div class="container" style="max-width: 800px;">
		<h3 class="mb-3"><?php echo $isEdit ? 'Edit Customer' : 'Tambah Customer'; ?></h3>
		<?php if ($error): ?>
			<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<div class="card">
			<div class="card-body">
				<form method="post" action="">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Kode Customer <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="kodecustomer" required value="<?php echo htmlspecialchars($data['kodecustomer']); ?>" placeholder="Contoh: CUST001">
						</div>
						<div class="col-md-6">
							<label class="form-label">Nama Customer <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="namacustomer" required value="<?php echo htmlspecialchars($data['namacustomer']); ?>" placeholder="Contoh: PT. Sehat Selalu">
						</div>
						<div class="col-12">
							<label class="form-label">Alamat Customer</label>
							<textarea class="form-control" name="alamatcustomer" rows="3" placeholder="Alamat lengkap customer"><?php echo htmlspecialchars($data['alamatcustomer']); ?></textarea>
						</div>
						<div class="col-md-6">
							<label class="form-label">No. Telepon</label>
							<input type="text" class="form-control" name="notelepon" value="<?php echo htmlspecialchars($data['notelepon']); ?>" placeholder="Contoh: 021-1234567">
						</div>
						<div class="col-md-6">
							<label class="form-label">Contact Person</label>
							<input type="text" class="form-control" name="contactperson" value="<?php echo htmlspecialchars($data['contactperson']); ?>" placeholder="Nama contact person">
						</div>
						<div class="col-md-6">
							<label class="form-label">Kode Sales</label>
							<select name="kodesales" class="form-select" id="kodesales">
								<option value="">-- Pilih Sales --</option>
								<?php foreach ($salesData as $sales): ?>
									<option value="<?php echo htmlspecialchars($sales['kodesales']); ?>" 
										<?php echo $data['kodesales'] === $sales['kodesales'] ? 'selected' : ''; ?>
										data-namasales="<?php echo htmlspecialchars($sales['namasales']); ?>">
										<?php echo htmlspecialchars($sales['kodesales'] . ' - ' . $sales['namasales']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-6" style="display: none;">
							<label class="form-label">Nama Sales</label>
							<input type="text" class="form-control" name="namasales" id="namasales" value="<?php echo htmlspecialchars($data['namasales']); ?>" readonly>
						</div>
						<div class="col-md-6">
							<label class="form-label">Status</label>
							<select name="status" class="form-select" required>
								<option value="aktif" <?php echo $data['status']==='aktif'?'selected':''; ?>>Aktif</option>
								<option value="non_aktif" <?php echo $data['status']==='non_aktif'?'selected':''; ?>>Non Aktif</option>
							</select>
						</div>
					</div>
					<div class="row mt-4">
						<div class="col-12">
							<button type="submit" class="btn btn-primary">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
									<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
									<path d="M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
								</svg>
								<?php echo $isEdit ? 'Update' : 'Simpan'; ?>
							</button>
							<a href="mastercustomer.php" class="btn btn-secondary ms-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
									<path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
								</svg>
								Batal
							</a>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const kodesalesSelect = document.getElementById('kodesales');
	const namasalesInput = document.getElementById('namasales');
	
	kodesalesSelect.addEventListener('change', function() {
		const selectedOption = this.options[this.selectedIndex];
		if (selectedOption.value) {
			namasalesInput.value = selectedOption.getAttribute('data-namasales');
		} else {
			namasalesInput.value = '';
		}
	});
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
