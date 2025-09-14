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
if ($id <= 0) {
	header('Location: mastercustomer.php?msg=error');
	exit;
}

// Check if customer exists
$stmt = $pdo->prepare('SELECT kodecustomer, namacustomer FROM mastercustomer WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
	header('Location: mastercustomer.php?msg=error');
	exit;
}

// Delete the customer
$stmt = $pdo->prepare('DELETE FROM mastercustomer WHERE id = ?');
$stmt->execute([$id]);

header('Location: mastercustomer.php?msg=deleted');
exit;
?>
