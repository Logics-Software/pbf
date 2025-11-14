<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

function respond(int $code, array $data): void {
	http_response_code($code);
	echo json_encode($data);
	exit;
}

// Read raw body once for all consumers
$__RAW_BODY = file_get_contents('php://input');
function body_json(): array { global $__RAW_BODY; $j = json_decode($__RAW_BODY ?: '', true); return is_array($j) ? $j : []; }
function is_list_array(array $a): bool { if ($a === []) return true; $i = 0; foreach (array_keys($a) as $k) { if ($k !== $i) return false; $i++; } return true; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = get_pdo_connection();

// Check authentication and role - only operator, admin, sales, and customer can access
require_roles(['operator', 'admin', 'sales', 'customer']);
$user = current_user();

try {
	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		$noorder = isset($_GET['noorder']) ? trim((string)$_GET['noorder']) : '';
		
		// Get single order with details
		if ($id > 0) {
			$sql = 'SELECT * FROM headerorder WHERE id = ?';
			$params = [$id];
			
			// Filter by customer code if user role is customer
			if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
				$sql .= ' AND kodecustomer = ?';
				$params[] = $user['kodecustomer'];
			}
			
			$sql .= ' LIMIT 1';
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$header = $stmt->fetch();
			if (!$header) { respond(404, ['success' => false, 'message' => 'Order tidak ditemukan']); }
			
			// Get order details
			$stmt = $pdo->prepare('SELECT * FROM detailorder WHERE noorder = ? ORDER BY nourut ASC');
			$stmt->execute([$header['noorder']]);
			$details = $stmt->fetchAll();
			
			$header['details'] = $details;
			respond(200, ['success' => true, 'data' => $header]);
		}
		
		// Get order by noorder
		if ($noorder !== '') {
			$sql = 'SELECT * FROM headerorder WHERE noorder = ?';
			$params = [$noorder];
			
			// Filter by customer code if user role is customer
			if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
				$sql .= ' AND kodecustomer = ?';
				$params[] = $user['kodecustomer'];
			}
			
			$sql .= ' LIMIT 1';
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$header = $stmt->fetch();
			if (!$header) { respond(404, ['success' => false, 'message' => 'Order tidak ditemukan']); }
			
			// Get order details
			$stmt = $pdo->prepare('SELECT * FROM detailorder WHERE noorder = ? ORDER BY nourut ASC');
			$stmt->execute([$header['noorder']]);
			$details = $stmt->fetchAll();
			
			$header['details'] = $details;
			respond(200, ['success' => true, 'data' => $header]);
		}
		
		// Get list of orders with pagination and search
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
		$offset = ($page - 1) * $limit;
		
		$search = trim($_GET['search'] ?? '');
		$status = trim($_GET['status'] ?? '');
		$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? '');
		$tanggal_sampai = trim($_GET['tanggal_sampai'] ?? '');
		
		$where = [];
		$params = [];
		
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
		
		// Filter by sales code if user role is sales
		if ($user['role'] === 'sales' && !empty($user['kodesales'])) {
			$where[] = 'kodesales = ?';
			$params[] = $user['kodesales'];
		}
		
		$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
		
		// Get total count
		$countSql = "SELECT COUNT(*) as total FROM headerorder $whereClause";
		$countStmt = $pdo->prepare($countSql);
		$countStmt->execute($params);
		$total = $countStmt->fetch()['total'];
		
		// Get orders
		$sql = "SELECT * FROM headerorder $whereClause ORDER BY tanggalorder DESC, noorder DESC LIMIT $limit OFFSET $offset";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$orders = $stmt->fetchAll();
		
		respond(200, [
			'success' => true, 
			'data' => $orders,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total' => (int)$total,
				'pages' => ceil($total / $limit)
			]
		]);
	}

	if ($method === 'POST') {
		$in = body_json();
		
		// Validate required fields
		$noorder = trim($in['noorder'] ?? '');
		$tanggalorder = trim($in['tanggalorder'] ?? '');
		$kodecustomer = trim($in['kodecustomer'] ?? '');
		$namacustomer = trim($in['namacustomer'] ?? '');
		$kodesales = trim($in['kodesales'] ?? '');
		$details = $in['details'] ?? [];
		
		if ($noorder === '' || $tanggalorder === '' || $kodecustomer === '' || $namacustomer === '') {
			respond(400, ['success' => false, 'message' => 'Field wajib: noorder, tanggalorder, kodecustomer, namacustomer']);
		}
		
		if (!is_array($details) || count($details) === 0) {
			respond(400, ['success' => false, 'message' => 'Detail order tidak boleh kosong']);
		}
		
		// Validate customer exists in master customer
		if ($kodecustomer !== '') {
			$customerCheck = $pdo->prepare('SELECT id FROM mastercustomer WHERE kodecustomer = ? AND status = ? LIMIT 1');
			$customerCheck->execute([trim($kodecustomer), 'aktif']);
			if (!$customerCheck->fetch()) {
				respond(400, ['success' => false, 'message' => 'Customer tidak ditemukan dalam master customer atau status tidak aktif']);
			}
		}
		
		// Validate sales exists in master sales
		if ($kodesales !== '') {
			$salesCheck = $pdo->prepare('SELECT id FROM mastersales WHERE kodesales = ? AND status = ? LIMIT 1');
			$salesCheck->execute([trim($kodesales), 'aktif']);
			if (!$salesCheck->fetch()) {
				respond(400, ['success' => false, 'message' => 'Sales tidak ditemukan dalam master sales atau status tidak aktif']);
			}
		}
		
		// Check if noorder already exists
		$chk = $pdo->prepare('SELECT id FROM headerorder WHERE noorder = ? LIMIT 1');
		$chk->execute([$noorder]);
		if ($chk->fetch()) {
			respond(409, ['success' => false, 'message' => 'Nomor order sudah digunakan']);
		}
		
		$pdo->beginTransaction();
		try {
			// Calculate total order
			$totalorder = 0;
			foreach ($details as $detail) {
				$jumlah = (int)($detail['jumlah'] ?? 0);
				$hargasatuan = (int)($detail['hargasatuan'] ?? 0);
				$discount = (float)($detail['discount'] ?? 0);
				$totalharga = (int)(($jumlah * $hargasatuan) * (1 - $discount / 100));
				$totalorder += $totalharga;
			}
			
			// Insert header order
			$stmt = $pdo->prepare('INSERT INTO headerorder (noorder, tanggalorder, kodecustomer, namacustomer, kodesales, namasales, totalorder, status, iduser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$stmt->execute([
				$noorder,
				$tanggalorder,
				$kodecustomer,
				$namacustomer,
				$in['kodesales'] ?? null,
				$in['namasales'] ?? null,
				$totalorder,
				$in['status'] ?? 'idle',
				$user['id']
			]);
			
			// Insert detail orders
			$stmt = $pdo->prepare('INSERT INTO detailorder (noorder, kodebarang, namabarang, satuan, jumlah, hargasatuan, discount, totalharga, nourut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$nourut = 1;
			foreach ($details as $detail) {
				$jumlah = (int)($detail['jumlah'] ?? 0);
				$hargasatuan = (int)($detail['hargasatuan'] ?? 0);
				$discount = (float)($detail['discount'] ?? 0);
				$totalharga = (int)(($jumlah * $hargasatuan) * (1 - $discount / 100));
				
				$stmt->execute([
					$noorder,
					$detail['kodebarang'],
					$detail['namabarang'],
					$detail['satuan'],
					$jumlah,
					$hargasatuan,
					$discount,
					$totalharga,
					$nourut++
				]);
			}
			
			// Clear cart if order is created from cart (only for customer role)
			$fromCart = isset($in['from_cart']) && $in['from_cart'] === true;
			if ($fromCart && $user['role'] === 'customer' && !empty($user['kodecustomer'])) {
				$stmt = $pdo->prepare('DELETE FROM cart WHERE customer_code = ?');
				$stmt->execute([$user['kodecustomer']]);
			}
			
			$pdo->commit();
			respond(201, ['success' => true, 'message' => 'Order berhasil dibuat', 'noorder' => $noorder]);
			
		} catch (Throwable $e) {
			if ($pdo->inTransaction()) { $pdo->rollBack(); }
			respond(400, ['success' => false, 'message' => $e->getMessage()]);
		}
	}

	if ($method === 'PUT' || $method === 'PATCH') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }

		$sql = 'SELECT * FROM headerorder WHERE id = ?';
		$params = [$id];
		
		// Filter by customer code if user role is customer
		if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
			$sql .= ' AND kodecustomer = ?';
			$params[] = $user['kodecustomer'];
		}
		
		$sql .= ' LIMIT 1';
		$exist = $pdo->prepare($sql);
		$exist->execute($params);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'Order tidak ditemukan']); }

		// Check if order can be edited (only idle status)
		if ($cur['status'] !== 'idle') {
			respond(403, ['success' => false, 'message' => 'Order hanya dapat diedit jika statusnya "Idle"']);
		}

		// Validate customer exists in master customer (if provided)
		if (isset($in['kodecustomer']) && trim($in['kodecustomer']) !== '') {
			$customerCheck = $pdo->prepare('SELECT id FROM mastercustomer WHERE kodecustomer = ? AND status = ? LIMIT 1');
			$customerCheck->execute([trim($in['kodecustomer']), 'aktif']);
			if (!$customerCheck->fetch()) {
				respond(400, ['success' => false, 'message' => 'Customer tidak ditemukan dalam master customer atau status tidak aktif']);
			}
		}
		
		// Validate sales exists in master sales (if provided)
		if (isset($in['kodesales']) && trim($in['kodesales']) !== '') {
			$salesCheck = $pdo->prepare('SELECT id FROM mastersales WHERE kodesales = ? AND status = ? LIMIT 1');
			$salesCheck->execute([trim($in['kodesales']), 'aktif']);
			if (!$salesCheck->fetch()) {
				respond(400, ['success' => false, 'message' => 'Sales tidak ditemukan dalam master sales atau status tidak aktif']);
			}
		}

		$pdo->beginTransaction();
		try {
			$fields = [];
			$params = [':id' => $id];
			
			// Update header fields
			if (isset($in['tanggalorder'])) { $fields[] = 'tanggalorder = :tanggalorder'; $params[':tanggalorder'] = trim((string)$in['tanggalorder']); }
			if (isset($in['kodecustomer'])) { $fields[] = 'kodecustomer = :kodecustomer'; $params[':kodecustomer'] = trim((string)$in['kodecustomer']); }
			if (isset($in['namacustomer'])) { $fields[] = 'namacustomer = :namacustomer'; $params[':namacustomer'] = trim((string)$in['namacustomer']); }
			if (array_key_exists('kodesales', $in)) { $fields[] = 'kodesales = :kodesales'; $params[':kodesales'] = $in['kodesales'] !== null ? trim((string)$in['kodesales']) : null; }
			if (array_key_exists('namasales', $in)) { $fields[] = 'namasales = :namasales'; $params[':namasales'] = $in['namasales'] !== null ? trim((string)$in['namasales']) : null; }
			if (isset($in['status'])) { 
				$newStatus = (string)$in['status'];
				if (!in_array($newStatus, ['idle','proses','faktur','kirim','terima','batal'], true)) { 
					respond(400,['success'=>false,'message'=>'Status tidak valid']); 
				}
				$fields[] = 'status = :status';
				$params[':status'] = $newStatus;
			}
			if (array_key_exists('nofaktur', $in)) { $fields[] = 'nofaktur = :nofaktur'; $params[':nofaktur'] = $in['nofaktur'] !== null ? trim((string)$in['nofaktur']) : null; }
			if (array_key_exists('tanggalfaktur', $in)) { $fields[] = 'tanggalfaktur = :tanggalfaktur'; $params[':tanggalfaktur'] = $in['tanggalfaktur'] !== null ? trim((string)$in['tanggalfaktur']) : null; }
			if (array_key_exists('namapengirim', $in)) { $fields[] = 'namapengirim = :namapengirim'; $params[':namapengirim'] = $in['namapengirim'] !== null ? trim((string)$in['namapengirim']) : null; }
			
			// Update details if provided
			if (isset($in['details']) && is_array($in['details'])) {
				// Delete existing details
				$stmt = $pdo->prepare('DELETE FROM detailorder WHERE noorder = ?');
				$stmt->execute([$cur['noorder']]);
				
				// Insert new details
				$stmt = $pdo->prepare('INSERT INTO detailorder (noorder, kodebarang, namabarang, satuan, jumlah, hargasatuan, discount, totalharga, nourut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
				$nourut = 1;
				$totalorder = 0;
				
				foreach ($in['details'] as $detail) {
					$jumlah = (int)($detail['jumlah'] ?? 0);
					$hargasatuan = (int)($detail['hargasatuan'] ?? 0);
					$discount = (float)($detail['discount'] ?? 0);
					$totalharga = (int)(($jumlah * $hargasatuan) * (1 - $discount / 100));
					$totalorder += $totalharga;
					
					$stmt->execute([
						$cur['noorder'],
						$detail['kodebarang'],
						$detail['namabarang'],
						$detail['satuan'],
						$jumlah,
						$hargasatuan,
						$discount,
						$totalharga,
						$nourut++
					]);
				}
				
				// Update total order
				$fields[] = 'totalorder = :totalorder';
				$params[':totalorder'] = $totalorder;
			}
			
			if (count($fields) > 0) {
				$sql = 'UPDATE headerorder SET ' . implode(', ', $fields) . ' WHERE id = :id';
				$stmt = $pdo->prepare($sql);
				$stmt->execute($params);
			}
			
			$pdo->commit();
			respond(200, ['success' => true, 'message' => 'Order berhasil diupdate']);
			
		} catch (Throwable $e) {
			if ($pdo->inTransaction()) { $pdo->rollBack(); }
			respond(400, ['success' => false, 'message' => $e->getMessage()]);
		}
	}

	if ($method === 'DELETE') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }
		
		$sql = 'SELECT noorder, namacustomer, status FROM headerorder WHERE id = ?';
		$params = [$id];
		
		// Filter by customer code if user role is customer
		if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
			$sql .= ' AND kodecustomer = ?';
			$params[] = $user['kodecustomer'];
		}
		
		$sql .= ' LIMIT 1';
		$exist = $pdo->prepare($sql);
		$exist->execute($params);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'Order tidak ditemukan']); }

		// Check if order can be deleted (only idle status)
		if ($cur['status'] !== 'idle') {
			respond(403, ['success' => false, 'message' => 'Order hanya dapat dihapus jika statusnya "Idle"']);
		}
		
		$pdo->beginTransaction();
		try {
			// Delete details first (foreign key constraint)
			$stmt = $pdo->prepare('DELETE FROM detailorder WHERE noorder = ?');
			$stmt->execute([$cur['noorder']]);
			
			// Delete header
			$stmt = $pdo->prepare('DELETE FROM headerorder WHERE id = ?');
			$stmt->execute([$id]);
			
			$pdo->commit();
			respond(200, ['success' => true, 'message' => 'Order berhasil dihapus']);
			
		} catch (Throwable $e) {
			if ($pdo->inTransaction()) { $pdo->rollBack(); }
			respond(400, ['success' => false, 'message' => $e->getMessage()]);
		}
	}

	respond(405, ['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
	respond(500, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
?>
