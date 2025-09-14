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
	// Special endpoint for stock update only
	if ($method === 'PATCH' && isset($_GET['action']) && $_GET['action'] === 'update-stock') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		$stokakhir = isset($in['stokakhir']) ? (int)$in['stokakhir'] : null;
		
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }
		if ($stokakhir === null) { respond(400, ['success' => false, 'message' => 'Parameter stokakhir wajib']); }
		if ($stokakhir < 0) { respond(400, ['success' => false, 'message' => 'Stok tidak boleh negatif']); }

		$exist = $pdo->prepare('SELECT id, kodebarang, namabarang FROM masterbarang WHERE id = ? LIMIT 1');
		$exist->execute([$id]);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'Barang tidak ditemukan']); }

		$stmt = $pdo->prepare('UPDATE masterbarang SET stokakhir = ? WHERE id = ?');
		$stmt->execute([$stokakhir, $id]);
		
		respond(200, [
			'success' => true, 
			'message' => 'Stok berhasil diupdate',
			'data' => [
				'id' => $id,
				'kodebarang' => $cur['kodebarang'],
				'namabarang' => $cur['namabarang'],
				'stokakhir' => $stokakhir
			]
		]);
	}

	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		$kodebarang = isset($_GET['kodebarang']) ? trim((string)$_GET['kodebarang']) : '';
		
		if ($id > 0) {
			$stmt = $pdo->prepare('SELECT * FROM masterbarang WHERE id = ? LIMIT 1');
			$stmt->execute([$id]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'Barang tidak ditemukan']); }
			respond(200, ['success' => true, 'data' => $row]);
		}
		
		if ($kodebarang !== '') {
			$stmt = $pdo->prepare('SELECT * FROM masterbarang WHERE kodebarang = ? LIMIT 1');
			$stmt->execute([$kodebarang]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'Barang tidak ditemukan']); }
			respond(200, ['success' => true, 'data' => $row]);
		}
		
		$stmt = $pdo->query('SELECT * FROM masterbarang ORDER BY kodebarang ASC');
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
			$chk = $pdo->prepare('SELECT id FROM masterbarang WHERE kodebarang = ? LIMIT 1');
			$stmt = $pdo->prepare('INSERT INTO masterbarang (kodebarang,namabarang,satuan,kodepabrik,namapabrik,kodegolongan,namagolongan,hpp,hargabeli,discbeli,hargajual,discjual,kondisiharga,stokakhir,foto,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
			
			foreach ($items as $idx => $row) {
				if (!is_array($row)) { throw new Exception('Item payload tidak valid pada index '.$idx); }
				
				$kodebarang = trim((string)($row['kodebarang'] ?? ''));
				$namabarang = trim((string)($row['namabarang'] ?? ''));
				$satuan = trim((string)($row['satuan'] ?? ''));
				$kodepabrik = isset($row['kodepabrik']) ? trim((string)$row['kodepabrik']) : null;
				$namapabrik = isset($row['namapabrik']) ? trim((string)$row['namapabrik']) : null;
				$kodegolongan = isset($row['kodegolongan']) ? trim((string)$row['kodegolongan']) : null;
				$namagolongan = isset($row['namagolongan']) ? trim((string)$row['namagolongan']) : null;
				$hpp = isset($row['hpp']) ? (float)$row['hpp'] : 0.00;
				$hargabeli = isset($row['hargabeli']) ? (float)$row['hargabeli'] : 0.00;
				$discbeli = isset($row['discbeli']) ? (float)$row['discbeli'] : 0.00;
				$hargajual = isset($row['hargajual']) ? (float)$row['hargajual'] : 0.00;
				$discjual = isset($row['discjual']) ? (float)$row['discjual'] : 0.00;
				$kondisiharga = isset($row['kondisiharga']) ? (string)$row['kondisiharga'] : 'normal';
				$stokakhir = isset($row['stokakhir']) ? (int)$row['stokakhir'] : 0;
				$status = isset($row['status']) ? (string)$row['status'] : 'aktif';
				$foto = null;
				if (isset($row['foto'])) {
					if (is_array($row['foto'])) {
						// Handle array of photos - convert to JSON
						$foto = json_encode($row['foto']);
					} elseif (is_string($row['foto']) && $row['foto'] !== '') {
						// Handle single photo - check if it's already JSON or a single path
						$fotoStr = trim($row['foto']);
						if (str_starts_with($fotoStr, '[') && str_ends_with($fotoStr, ']')) {
							// Already JSON format
							$foto = $fotoStr;
						} else {
							// Single photo path - convert to JSON array
							$foto = json_encode([$fotoStr]);
						}
					}
				}

				if ($kodebarang === '' || $namabarang === '' || $satuan === '') {
					throw new Exception('Field wajib kosong pada index '.$idx.': kodebarang, namabarang, satuan');
				}
				if (!in_array($kondisiharga, ['normal','promo','diskon'], true)) {
					throw new Exception('Kondisi harga tidak valid pada index '.$idx);
				}
				if (!in_array($status, ['aktif','non_aktif'], true)) {
					throw new Exception('Status tidak valid pada index '.$idx);
				}

				$chk->execute([$kodebarang]);
				if ($chk->fetch()) { throw new Exception('Kode barang sudah digunakan pada index '.$idx); }

				$stmt->execute([$kodebarang,$namabarang,$satuan,$kodepabrik,$namapabrik,$kodegolongan,$namagolongan,$hpp,$hargabeli,$discbeli,$hargajual,$discjual,$kondisiharga,$stokakhir,$foto,$status]);
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

		$exist = $pdo->prepare('SELECT * FROM masterbarang WHERE id = ? LIMIT 1');
		$exist->execute([$id]);
		$cur = $exist->fetch();
		if (!$cur) { respond(404, ['success' => false, 'message' => 'Barang tidak ditemukan']); }

		$fields = [];
		$params = [':id' => $id];
		
		if (isset($in['kodebarang'])) { 
			$newKode = trim((string)$in['kodebarang']); 
			if ($newKode === '') { respond(400,['success'=>false,'message'=>'Kode barang tidak boleh kosong']); }
			$chk = $pdo->prepare('SELECT id FROM masterbarang WHERE kodebarang = ? AND id <> ? LIMIT 1');
			$chk->execute([$newKode, $id]);
			if ($chk->fetch()) { respond(409,['success'=>false,'message'=>'Kode barang sudah digunakan']); }
			$fields[] = 'kodebarang = :kodebarang';
			$params[':kodebarang'] = $newKode;
		}
		if (isset($in['namabarang'])) { $fields[] = 'namabarang = :namabarang'; $params[':namabarang'] = trim((string)$in['namabarang']); }
		if (isset($in['satuan'])) { $fields[] = 'satuan = :satuan'; $params[':satuan'] = trim((string)$in['satuan']); }
		if (array_key_exists('kodepabrik', $in)) { $fields[] = 'kodepabrik = :kodepabrik'; $params[':kodepabrik'] = $in['kodepabrik'] !== null ? trim((string)$in['kodepabrik']) : null; }
		if (array_key_exists('namapabrik', $in)) { $fields[] = 'namapabrik = :namapabrik'; $params[':namapabrik'] = $in['namapabrik'] !== null ? trim((string)$in['namapabrik']) : null; }
		if (array_key_exists('kodegolongan', $in)) { $fields[] = 'kodegolongan = :kodegolongan'; $params[':kodegolongan'] = $in['kodegolongan'] !== null ? trim((string)$in['kodegolongan']) : null; }
		if (array_key_exists('namagolongan', $in)) { $fields[] = 'namagolongan = :namagolongan'; $params[':namagolongan'] = $in['namagolongan'] !== null ? trim((string)$in['namagolongan']) : null; }
		if (isset($in['hpp'])) { $fields[] = 'hpp = :hpp'; $params[':hpp'] = (float)$in['hpp']; }
		if (isset($in['hargabeli'])) { $fields[] = 'hargabeli = :hargabeli'; $params[':hargabeli'] = (float)$in['hargabeli']; }
		if (isset($in['discbeli'])) { $fields[] = 'discbeli = :discbeli'; $params[':discbeli'] = (float)$in['discbeli']; }
		if (isset($in['hargajual'])) { $fields[] = 'hargajual = :hargajual'; $params[':hargajual'] = (float)$in['hargajual']; }
		if (isset($in['discjual'])) { $fields[] = 'discjual = :discjual'; $params[':discjual'] = (float)$in['discjual']; }
		if (isset($in['kondisiharga'])) { 
			$newKondisi = (string)$in['kondisiharga'];
			if (!in_array($newKondisi, ['normal','promo','diskon'], true)) { respond(400,['success'=>false,'message'=>'Kondisi harga tidak valid']); }
			$fields[] = 'kondisiharga = :kondisiharga';
			$params[':kondisiharga'] = $newKondisi;
		}
		if (isset($in['stokakhir'])) { $fields[] = 'stokakhir = :stokakhir'; $params[':stokakhir'] = (int)$in['stokakhir']; }
		if (isset($in['status'])) { 
			$newStatus = (string)$in['status'];
			if (!in_array($newStatus, ['aktif','non_aktif'], true)) { respond(400,['success'=>false,'message'=>'Status tidak valid']); }
			$fields[] = 'status = :status';
			$params[':status'] = $newStatus;
		}
		if (array_key_exists('foto', $in)) { 
			$foto = null;
			if ($in['foto'] !== null) {
				if (is_array($in['foto'])) {
					// Handle array of photos - convert to JSON
					$foto = json_encode($in['foto']);
				} elseif (is_string($in['foto']) && $in['foto'] !== '') {
					// Handle single photo - check if it's already JSON or a single path
					$fotoStr = trim($in['foto']);
					if (str_starts_with($fotoStr, '[') && str_ends_with($fotoStr, ']')) {
						// Already JSON format
						$foto = $fotoStr;
					} else {
						// Single photo path - convert to JSON array
						$foto = json_encode([$fotoStr]);
					}
				}
			}
			$fields[] = 'foto = :foto'; 
			$params[':foto'] = $foto; 
		}
		
		if (!count($fields)) { respond(400, ['success' => false, 'message' => 'Tidak ada field yang diubah']); }

		$sql = 'UPDATE masterbarang SET ' . implode(', ', $fields) . ' WHERE id = :id';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		respond(200, ['success' => true]);
	}

	if ($method === 'DELETE') {
		$in = body_json();
		$id = (int)($in['id'] ?? 0);
		if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Parameter id wajib']); }
		$stmt = $pdo->prepare('DELETE FROM masterbarang WHERE id = ?');
		$stmt->execute([$id]);
		respond(200, ['success' => true]);
	}

	respond(405, ['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
	respond(500, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
