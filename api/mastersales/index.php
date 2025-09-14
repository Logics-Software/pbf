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
		$kodesales = isset($_GET['kodesales']) ? trim((string)$_GET['kodesales']) : '';
		
		if ($id > 0) {
			$stmt = $pdo->prepare('SELECT * FROM mastersales WHERE id = ? LIMIT 1');
			$stmt->execute([$id]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'Sales tidak ditemukan']); }
			respond(200, ['success' => true, 'data' => $row]);
		}
		
		if ($kodesales !== '') {
			$stmt = $pdo->prepare('SELECT * FROM mastersales WHERE kodesales = ? LIMIT 1');
			$stmt->execute([$kodesales]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'Sales tidak ditemukan']); }
			respond(200, ['success' => true, 'data' => $row]);
		}
		
		$stmt = $pdo->query('SELECT * FROM mastersales ORDER BY kodesales ASC');
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
			$chk = $pdo->prepare('SELECT id FROM mastersales WHERE kodesales = ? LIMIT 1');
			$stmt = $pdo->prepare('INSERT INTO mastersales (kodesales,namasales,alamatsales,notelepon,status) VALUES (?,?,?,?,?)');
			
			foreach ($items as $idx => $row) {
				if (!is_array($row)) { throw new Exception('Item payload tidak valid pada index '.$idx); }
				
				$kodesales = trim((string)($row['kodesales'] ?? ''));
				$namasales = trim((string)($row['namasales'] ?? ''));
				$alamatsales = isset($row['alamatsales']) ? trim((string)$row['alamatsales']) : null;
				$notelepon = isset($row['notelepon']) ? trim((string)$row['notelepon']) : null;
				$status = isset($row['status']) ? (string)$row['status'] : 'aktif';

				if ($kodesales === '' || $namasales === '') {
					throw new Exception('Field wajib kosong pada index '.$idx.': kodesales, namasales');
				}
				if (!in_array($status, ['aktif','non_aktif'], true)) {
					throw new Exception('Status tidak valid pada index '.$idx);
				}

				$chk->execute([$kodesales]);
				if ($chk->fetch()) { throw new Exception('Kode sales sudah digunakan pada index '.$idx); }

				$stmt->execute([$kodesales,$namasales,$alamatsales,$notelepon,$status]);
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

		$exist = $pdo->prepare('SELECT * FROM mastersales WHERE id = ? LIMIT 1');
		$exist->execute([$id]);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'Sales tidak ditemukan']); }

		$fields = [];
		$params = [':id' => $id];
		
		if (isset($in['kodesales'])) { 
			$newKode = trim((string)$in['kodesales']); 
			if ($newKode === '') { respond(400,['success'=>false,'message'=>'Kode sales tidak boleh kosong']); }
			$chk = $pdo->prepare('SELECT id FROM mastersales WHERE kodesales = ? AND id <> ? LIMIT 1');
			$chk->execute([$newKode, $id]);
			if ($chk->fetch()) { respond(409,['success'=>false,'message'=>'Kode sales sudah digunakan']); }
			$fields[] = 'kodesales = :kodesales';
			$params[':kodesales'] = $newKode;
		}
		if (isset($in['namasales'])) { $fields[] = 'namasales = :namasales'; $params[':namasales'] = trim((string)$in['namasales']); }
		if (array_key_exists('alamatsales', $in)) { $fields[] = 'alamatsales = :alamatsales'; $params[':alamatsales'] = $in['alamatsales'] !== null ? trim((string)$in['alamatsales']) : null; }
		if (array_key_exists('notelepon', $in)) { $fields[] = 'notelepon = :notelepon'; $params[':notelepon'] = $in['notelepon'] !== null ? trim((string)$in['notelepon']) : null; }
		if (isset($in['status'])) { 
			$newStatus = (string)$in['status'];
			if (!in_array($newStatus, ['aktif','non_aktif'], true)) { respond(400,['success'=>false,'message'=>'Status tidak valid']); }
			$fields[] = 'status = :status';
			$params[':status'] = $newStatus;
		}
		
		if (!count($fields)) { respond(400, ['success' => false, 'message' => 'Tidak ada field yang diubah']); }

		$sql = 'UPDATE mastersales SET ' . implode(', ', $fields) . ' WHERE id = :id';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		respond(200, ['success' => true]);
	}

	if ($method === 'DELETE') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }
		
		$exist = $pdo->prepare('SELECT kodesales, namasales FROM mastersales WHERE id = ? LIMIT 1');
		$exist->execute([$id]);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'Sales tidak ditemukan']); }
		
		$stmt = $pdo->prepare('DELETE FROM mastersales WHERE id = ?');
		$stmt->execute([$id]);
		respond(200, ['success' => true, 'message' => 'Sales berhasil dihapus']);
	}

	respond(405, ['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
	respond(500, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
?>
