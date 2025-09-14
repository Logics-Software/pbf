<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('masterbarang')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
	header('Location: masterbarang.php?msg=error');
	exit;
}

// Check if barang exists
$stmt = $pdo->prepare('SELECT kodebarang, namabarang FROM masterbarang WHERE id = ?');
$stmt->execute([$id]);
$barang = $stmt->fetch();

if (!$barang) {
	header('Location: masterbarang.php?msg=notfound');
	exit;
}

// Delete the barang
$stmt = $pdo->prepare('DELETE FROM masterbarang WHERE id = ?');
$stmt->execute([$id]);

header('Location: masterbarang.php?msg=deleted');
exit;
