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

if ($id <= 0) {
	header('Location: mastersales.php?msg=error');
	exit;
}

// Check if sales exists
$stmt = $pdo->prepare('SELECT * FROM mastersales WHERE id = ?');
$stmt->execute([$id]);
$sales = $stmt->fetch();

if (!$sales) {
	header('Location: mastersales.php?msg=error');
	exit;
}

// Check if sales is being used in other tables
$checkStmt = $pdo->prepare('SELECT COUNT(*) FROM user WHERE kodesales = ?');
$checkStmt->execute([$sales['kodesales']]);
$userCount = $checkStmt->fetchColumn();

if ($userCount > 0) {
	header('Location: mastersales.php?msg=error&reason=in_use');
	exit;
}

// Delete the sales
$deleteStmt = $pdo->prepare('DELETE FROM mastersales WHERE id = ?');
$deleteStmt->execute([$id]);

header('Location: mastersales.php?msg=deleted');
exit;
?>
