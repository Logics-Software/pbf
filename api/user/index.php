<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

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

try {
	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		$usernameQuery = isset($_GET['username']) ? trim((string)$_GET['username']) : '';
		if ($id > 0) {
			$stmt = $pdo->prepare('SELECT id, username, namalengkap, alamat, role, email, kodesales, kodecustomer, status FROM user WHERE id = ? LIMIT 1');
			$stmt->execute([$id]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'User tidak ditemukan']); }
			respond(200, ['success' => true, 'data' => $row]);
		}
		if ($usernameQuery !== '') {
			$stmt = $pdo->prepare('SELECT id, username, namalengkap, alamat, role, email, kodesales, kodecustomer, status FROM user WHERE username = ? LIMIT 1');
			$stmt->execute([$usernameQuery]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'User tidak ditemukan']); }
			respond(200, ['success' => true, 'data' => $row]);
		}
		$stmt = $pdo->query('SELECT id, username, namalengkap, alamat, role, email, kodesales, kodecustomer, status FROM user ORDER BY id DESC');
		$data = $stmt->fetchAll();
		respond(200, ['success' => true, 'data' => $data]);
	}

	if ($method === 'POST') {
		$in = body_json();
		$items = is_list_array($in) ? $in : [$in];
		if (!is_list_array($items)) { respond(400, ['success'=>false, 'message'=>'Payload tidak valid']); }
		if (count($items) === 0) { respond(400, ['success'=>false, 'message'=>'Payload kosong']); }

		$pdo->beginTransaction();
		try {
			$ids = [];
			$chk = $pdo->prepare('SELECT id FROM user WHERE username = ? LIMIT 1');
			$stmt = $pdo->prepare('INSERT INTO user (username,password,namalengkap,alamat,role,email,kodesales,kodecustomer,status) VALUES (?,?,?,?,?,?,?,?,?)');
			foreach ($items as $idx => $row) {
				if (!is_array($row)) { throw new Exception('Item payload tidak valid pada index '.$idx); }
				$username = trim((string)($row['username'] ?? ''));
				$password = (string)($row['password'] ?? '');
				$namalengkap = trim((string)($row['namalengkap'] ?? ''));
				$role = (string)($row['role'] ?? '');
				$alamat = array_key_exists('alamat',$row) ? (($row['alamat']===null)? null : trim((string)$row['alamat'])) : null;
				$email = array_key_exists('email',$row) ? (($row['email']===null)? null : trim((string)$row['email'])) : null;
				$kodesales = array_key_exists('kodesales',$row) ? (($row['kodesales']===null)? null : trim((string)$row['kodesales'])) : null;
				$kodecustomer = array_key_exists('kodecustomer',$row) ? (($row['kodecustomer']===null)? null : trim((string)$row['kodecustomer'])) : null;
				$status = isset($row['status']) ? (string)$row['status'] : 'aktif';

				if ($username === '' || $password === '' || $namalengkap === '' || $role === '') {
					throw new Exception('Field wajib kosong pada index '.$idx);
				}
				if (!in_array($role, ['operator','manajemen','sales','customer','admin'], true)) {
					throw new Exception('Role tidak valid pada index '.$idx);
				}
				if (!in_array($status, ['aktif','non aktif'], true)) {
					throw new Exception('Status tidak valid pada index '.$idx);
				}

				$chk->execute([$username]);
				if ($chk->fetch()) { throw new Exception('Username sudah digunakan pada index '.$idx); }

				$hash = password_hash($password, PASSWORD_BCRYPT);
				$stmt->execute([$username,$hash,$namalengkap,$alamat,$role,$email,$kodesales,$kodecustomer,$status]);
				$ids[] = (int)$pdo->lastInsertId();
			}
			$pdo->commit();
			if (count($items) === 1) { respond(201, ['success'=>true, 'id'=>$ids[0]]); }
			respond(201, ['success'=>true, 'ids'=>$ids, 'count'=>count($ids)]);
		} catch (Throwable $e) {
			if ($pdo->inTransaction()) { $pdo->rollBack(); }
			respond(400, ['success'=>false, 'message'=>$e->getMessage()]);
		}
	}

	if ($method === 'PUT' || $method === 'PATCH') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }

		$exist = $pdo->prepare('SELECT * FROM user WHERE id = ? LIMIT 1');
		$exist->execute([$id]);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'User tidak ditemukan']); }

		$fields = [];
		$params = [':id' => $id];
		if (isset($in['username'])) { $newU = trim((string)$in['username']); if ($newU==='') respond(400,['success'=>false,'message'=>'Username tidak boleh kosong']); $chk=$pdo->prepare('SELECT id FROM user WHERE username=? AND id<>? LIMIT 1'); $chk->execute([$newU,$id]); if($chk->fetch()) respond(409,['success'=>false,'message'=>'Username sudah digunakan']); $fields[]='username=:username'; $params[':username']=$newU; }
		if (isset($in['password'])) { $newP=(string)$in['password']; if ($newP!=='') { $fields[]='password=:password'; $params[':password'] = password_hash($newP, PASSWORD_BCRYPT); } }
		if (isset($in['namalengkap'])) { $fields[]='namalengkap=:namalengkap'; $params[':namalengkap']=trim((string)$in['namalengkap']); }
		if (array_key_exists('alamat',$in)) { $fields[]='alamat=:alamat'; $params[':alamat']=$in['alamat']!==null ? trim((string)$in['alamat']) : null; }
		if (isset($in['role'])) { $newR=(string)$in['role']; if(!in_array($newR,['operator','manajemen','sales','customer','admin'],true)) respond(400,['success'=>false,'message'=>'Role tidak valid']); $fields[]='role=:role'; $params[':role']=$newR; }
		if (array_key_exists('email',$in)) { $fields[]='email=:email'; $params[':email']=$in['email']!==null ? trim((string)$in['email']) : null; }
		if (array_key_exists('kodesales',$in)) { $fields[]='kodesales=:kodesales'; $params[':kodesales']=$in['kodesales']!==null ? trim((string)$in['kodesales']) : null; }
		if (array_key_exists('kodecustomer',$in)) { $fields[]='kodecustomer=:kodecustomer'; $params[':kodecustomer']=$in['kodecustomer']!==null ? trim((string)$in['kodecustomer']) : null; }
		if (isset($in['status'])) { $newS=(string)$in['status']; if(!in_array($newS,['aktif','non aktif'],true)) respond(400,['success'=>false,'message'=>'Status tidak valid']); $fields[]='status=:status'; $params[':status']=$newS; }
		if (!count($fields)) { respond(400, ['success' => false, 'message' => 'Tidak ada field yang diubah']); }

		$sql = 'UPDATE user SET ' . implode(', ', $fields) . ' WHERE id = :id';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		respond(200, ['success' => true]);
	}

	if ($method === 'DELETE') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }
		$stmt = $pdo->prepare('DELETE FROM user WHERE id = ?');
		$stmt->execute([$id]);
		respond(200, ['success' => true]);
	}

	respond(405, ['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
	respond(500, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}


