<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

function current_user(): ?array {
	return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): void {
	if (!current_user()) {
		header('Location: login.php');
		exit;
	}
}

function require_roles(array $allowedRoles): void {
	require_login();
	$user = current_user();
	if (!$user || !in_array($user['role'], $allowedRoles, true)) {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

function is_admin(): bool {
	$user = current_user();
	return $user && $user['role'] === 'admin';
}

function has_role(string $role): bool {
	$user = current_user();
	return $user && $user['role'] === $role;
}

function can_access(string $resource): bool {
	$user = current_user();
	if (!$user) return false;
	
	// Admin has full access
	if ($user['role'] === 'admin') return true;
	
	// Define role-based permissions
	$permissions = [
		'admin' => ['*'], // Full access
		'manajemen' => ['dashboard', 'reports', 'profile'],
		'operator' => ['dashboard', 'users', 'masterbarang', 'mastercustomer', 'mastersales', 'order', 'reports', 'profile'],
		'sales' => ['dashboard', 'order', 'reports', 'profile'],
		'customer' => ['dashboard', 'order', 'reports', 'profile']
	];
	
	$userPermissions = $permissions[$user['role']] ?? [];
	return in_array('*', $userPermissions) || in_array($resource, $userPermissions);
}

function login(string $username, string $password): array {
	$pdo = get_pdo_connection();
	$sql = 'SELECT * FROM user WHERE username = ? LIMIT 1';
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$username]);
	$user = $stmt->fetch();
	if (!$user) {
		return ['success' => false, 'message' => 'Username atau password salah'];
	}
	if (!password_verify($password, $user['password'])) {
		return ['success' => false, 'message' => 'Username atau password salah'];
	}
	if (strtolower($user['status']) !== 'aktif') {
		return ['success' => false, 'message' => 'Akun non aktif. Hubungi administrator.'];
	}
	// Regenerate session ID to prevent fixation
	session_regenerate_id(true);
	$_SESSION['user'] = [
		'id' => $user['id'],
		'username' => $user['username'],
		'namalengkap' => $user['namalengkap'],
		'alamat' => $user['alamat'],
		'role' => $user['role'],
		'email' => $user['email'],
		'kodesales' => $user['kodesales'],
		'kodecustomer' => $user['kodecustomer'],
		'status' => $user['status'],
	];
	return ['success' => true, 'message' => 'Login berhasil'];
}

function logout(): void {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}
?>

