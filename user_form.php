<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('users')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

$data = [
	'username' => '',
	'password' => '',
	'namalengkap' => '',
	'alamat' => '',
	'role' => 'operator',
	'email' => '',
	'kodesales' => '',
	'kodecustomer' => '',
	'status' => 'aktif',
];

// Fetch customer data for dropdown
$customers = [];
try {
	$customerStmt = $pdo->query('SELECT kodecustomer, namacustomer FROM mastercustomer WHERE status = "aktif" ORDER BY namacustomer');
	$customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	// Handle error if mastercustomer table doesn't exist
	$customers = [];
}

// Fetch sales data for dropdown
$sales = [];
try {
	$salesStmt = $pdo->query('SELECT kodesales, namasales FROM mastersales WHERE status = "aktif" ORDER BY namasales');
	$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	// Handle error if mastersales table doesn't exist
	$sales = [];
}

if ($isEdit) {
	$stmt = $pdo->prepare('SELECT * FROM user WHERE id = ?');
	$stmt->execute([$id]);
	$existing = $stmt->fetch();
	if (!$existing) {
		$error = 'User tidak ditemukan';
	} else {
		$data = array_merge($data, $existing);
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$data['username'] = trim($_POST['username'] ?? '');
	$data['namalengkap'] = trim($_POST['namalengkap'] ?? '');
	$data['alamat'] = trim($_POST['alamat'] ?? '');
	$data['role'] = $_POST['role'] ?? 'operator';
	$data['email'] = trim($_POST['email'] ?? '');
	$data['kodesales'] = trim($_POST['kodesales'] ?? '');
	$data['kodecustomer'] = trim($_POST['kodecustomer'] ?? '');
	$data['status'] = $_POST['status'] ?? 'aktif';
	$password = (string)($_POST['password'] ?? '');
	
	// Clear irrelevant fields based on role
	if ($data['role'] === 'sales') {
		$data['kodecustomer'] = ''; // Clear customer code for sales role
	} elseif ($data['role'] === 'customer') {
		$data['kodesales'] = ''; // Clear sales code for customer role
	} else {
		// For admin, manajemen, operator roles, clear both
		$data['kodesales'] = '';
		$data['kodecustomer'] = '';
	}

	if ($data['username'] === '' || $data['namalengkap'] === '') {
		$error = 'Username dan Nama Lengkap wajib diisi';
	}

	if (!$error) {
		// Check for duplicate username
		$checkSql = $isEdit ? 'SELECT id FROM user WHERE username = ? AND id != ?' : 'SELECT id FROM user WHERE username = ?';
		$checkParams = $isEdit ? [$data['username'], $id] : [$data['username']];
		$checkStmt = $pdo->prepare($checkSql);
		$checkStmt->execute($checkParams);
		if ($checkStmt->fetch()) {
			$error = 'User tersebut sudah digunakan';
		}
	}

	if (!$error) {
		if ($isEdit) {
			if ($password !== '') {
				$hash = password_hash($password, PASSWORD_BCRYPT);
				$sql = 'UPDATE user SET username=?, password=?, namalengkap=?, alamat=?, role=?, email=?, kodesales=?, kodecustomer=?, status=? WHERE id=?';
				$params = [$data['username'], $hash, $data['namalengkap'], $data['alamat'], $data['role'], $data['email'], $data['kodesales'], $data['kodecustomer'], $data['status'], $id];
			} else {
				$sql = 'UPDATE user SET username=?, namalengkap=?, alamat=?, role=?, email=?, kodesales=?, kodecustomer=?, status=? WHERE id=?';
				$params = [$data['username'], $data['namalengkap'], $data['alamat'], $data['role'], $data['email'], $data['kodesales'], $data['kodecustomer'], $data['status'], $id];
			}
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			header('Location: users.php?msg=saved');
			exit;
		} else {
			if ($password === '') {
				$error = 'Password wajib diisi untuk user baru';
			} else {
				$hash = password_hash($password, PASSWORD_BCRYPT);
				$sql = 'INSERT INTO user (username,password,namalengkap,alamat,role,email,kodesales,kodecustomer,status) VALUES (?,?,?,?,?,?,?,?,?)';
				$params = [$data['username'], $hash, $data['namalengkap'], $data['alamat'], $data['role'], $data['email'], $data['kodesales'], $data['kodecustomer'], $data['status']];
				$stmt = $pdo->prepare($sql);
				$stmt->execute($params);
				header('Location: users.php?msg=created');
				exit;
			}
		}
	}
}

include __DIR__ . '/includes/header.php';
?>
<style>
/* Disabled field styling */
.form-control:disabled, .form-select:disabled {
    background-color: #f8f9fa !important;
    cursor: not-allowed !important;
    opacity: 0.6;
}
</style>
<div class="container" style="max-width: 720px;">
	<h3 class="mb-3"><?php echo $isEdit ? 'Edit User' : 'Tambah User'; ?></h3>
	<?php if ($error): ?>
		<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
	<?php endif; ?>
	<div class="card">
		<div class="card-body">
			<form method="post" action="">
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label">Username</label>
						<input type="text" class="form-control" name="username" required value="<?php echo htmlspecialchars($data['username']); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label"><?php echo $isEdit ? 'Password (isi jika ganti)' : 'Password'; ?></label>
						<input type="password" class="form-control" name="password" <?php echo $isEdit ? '' : 'required'; ?> >
					</div>
					<div class="col-md-6">
						<label class="form-label">Nama Lengkap</label>
						<input type="text" class="form-control" name="namalengkap" required value="<?php echo htmlspecialchars($data['namalengkap']); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label">Email</label>
						<input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($data['email']); ?>">
					</div>
					<div class="col-12">
						<label class="form-label">Alamat</label>
						<input type="text" class="form-control" name="alamat" value="<?php echo htmlspecialchars($data['alamat']); ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label">Role</label>
						<select name="role" class="form-select" required id="roleSelect">
							<?php 
							$roles = [
								'admin' => 'Administrator',
								'manajemen' => 'Manajemen',
								'operator' => 'Operator',
								'sales' => 'Sales',
								'customer' => 'Customer'
							];
							foreach ($roles as $role => $label): ?>
								<option value="<?php echo $role; ?>" <?php echo $data['role']===$role?'selected':''; ?>><?php echo $label; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label">Customer</label>
						<input type="text" name="kodecustomer" class="form-control" id="kodecustomerInput" 
							list="customerList" placeholder="-- Ketik nama customer --" 
							value="<?php echo htmlspecialchars($data['kodecustomer']); ?>">
						<datalist id="customerList">
							<?php foreach ($customers as $customer): ?>
								<option value="<?php echo htmlspecialchars($customer['kodecustomer']); ?>" 
									label="<?php echo htmlspecialchars($customer['namacustomer']); ?>"
									data-name="<?php echo htmlspecialchars($customer['namacustomer']); ?>">
							<?php endforeach; ?>
						</datalist>
						<small id="customerNameDisplay" class="text-muted" style="display: none; margin-left: 12px;"></small>
					</div>
					<div class="col-md-4">
						<label class="form-label">Sales</label>
						<input type="text" name="kodesales" class="form-control" id="kodesalesInput" 
							list="salesList" placeholder="-- Ketik nama sales --" 
							value="<?php echo htmlspecialchars($data['kodesales']); ?>">
						<datalist id="salesList">
							<?php foreach ($sales as $sale): ?>
								<option value="<?php echo htmlspecialchars($sale['kodesales']); ?>" 
									label="<?php echo htmlspecialchars($sale['namasales']); ?>"
									data-name="<?php echo htmlspecialchars($sale['namasales']); ?>">
							<?php endforeach; ?>
						</datalist>
						<small id="salesNameDisplay" class="text-muted" style="display: none; margin-left: 12px;"></small>
					</div>
					<div class="col-md-4">
						<label class="form-label">Status</label>
						<select name="status" class="form-select" required>
							<?php foreach (['aktif','non aktif'] as $st): ?>
								<option value="<?php echo $st; ?>" <?php echo $data['status']===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="mt-3 d-flex gap-2">
					<button type="submit" class="btn btn-primary">Simpan</button>
					<a href="users.php" class="btn btn-secondary">Batal</a>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
// Simple validation with search functionality
document.addEventListener('DOMContentLoaded', function() {
	var roleSelect = document.getElementById('roleSelect');
	var kodesalesInput = document.getElementById('kodesalesInput');
	var customerInput = document.getElementById('kodecustomerInput');
	var customerNameDisplay = document.getElementById('customerNameDisplay');
	var salesNameDisplay = document.getElementById('salesNameDisplay');
	
	// Customer data for name lookup
	var customerData = {};
	<?php foreach ($customers as $customer): ?>
	customerData['<?php echo htmlspecialchars($customer['kodecustomer']); ?>'] = '<?php echo htmlspecialchars($customer['namacustomer']); ?>';
	<?php endforeach; ?>
	
	// Sales data for name lookup
	var salesData = {};
	<?php foreach ($sales as $sale): ?>
	salesData['<?php echo htmlspecialchars($sale['kodesales']); ?>'] = '<?php echo htmlspecialchars($sale['namasales']); ?>';
	<?php endforeach; ?>
	
	function updateFieldStates() {
		var selectedRole = roleSelect.value;
		
		// Clear values when role changes
		if (selectedRole !== 'customer') {
			customerInput.value = '';
			customerNameDisplay.style.display = 'none';
		}
		if (selectedRole !== 'sales') {
			kodesalesInput.value = '';
			salesNameDisplay.style.display = 'none';
		}
		
		// Apply role-based restrictions
		if (selectedRole === 'sales') {
			// Sales role: enable kodesales, disable customer
			kodesalesInput.disabled = false;
			customerInput.disabled = true;
			customerNameDisplay.style.display = 'none';
		} else if (selectedRole === 'customer') {
			// Customer role: enable customer, disable kodesales
			customerInput.disabled = false;
			kodesalesInput.disabled = true;
			salesNameDisplay.style.display = 'none';
		} else {
			// Other roles (admin, manajemen, operator): disable both
			kodesalesInput.disabled = true;
			customerInput.disabled = true;
			customerNameDisplay.style.display = 'none';
			salesNameDisplay.style.display = 'none';
		}
	}
	
	function updateCustomerName() {
		var selectedCode = customerInput.value;
		if (selectedCode && customerData[selectedCode]) {
			customerNameDisplay.textContent = customerData[selectedCode];
			customerNameDisplay.style.display = 'block';
		} else {
			customerNameDisplay.style.display = 'none';
		}
	}
	
	function updateSalesName() {
		var selectedCode = kodesalesInput.value;
		if (selectedCode && salesData[selectedCode]) {
			salesNameDisplay.textContent = salesData[selectedCode];
			salesNameDisplay.style.display = 'block';
		} else {
			salesNameDisplay.style.display = 'none';
		}
	}
	
	// Run on page load
	updateFieldStates();
	updateCustomerName(); // Show name if customer is already selected
	updateSalesName(); // Show name if sales is already selected
	
	// Run when role changes
	roleSelect.addEventListener('change', updateFieldStates);
	
	// Run when customer input changes
	customerInput.addEventListener('input', updateCustomerName);
	customerInput.addEventListener('change', updateCustomerName);
	
	// Run when sales input changes
	kodesalesInput.addEventListener('input', updateSalesName);
	kodesalesInput.addEventListener('change', updateSalesName);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


