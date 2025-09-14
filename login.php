<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
	header('Location: dashboard.php');
	exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = isset($_POST['username']) ? trim($_POST['username']) : '';
	$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
	if ($username === '' || $password === '') {
		$error = 'Username dan password wajib diisi';
	} else {
		$result = login($username, $password);
		if ($result['success']) {
			header('Location: dashboard.php');
			exit;
		} else {
			$error = $result['message'];
		}
	}
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* Mobile login positioning - move login section higher on mobile */
@media (max-width: 767.98px) {
    .login-container {
        padding-top: 2rem !important;
        align-items: flex-start !important;
    }
}

@media (max-width: 575.98px) {
    .login-container {
        padding-top: 1rem !important;
    }
}
</style>

<div class="flex-grow-1 d-flex align-items-center justify-content-center login-container">
	<div class="container" style="max-width: 420px;">
		<h3 class="mb-3 text-center">Login</h3>
		<?php if ($error): ?>
			<div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<div class="card shadow-sm">
			<div class="card-body">
				<form method="post" action="">
					<div class="mb-3">
						<label for="username" class="form-label">Username</label>
						<input type="text" class="form-control" id="username" name="username" required autofocus>
					</div>
					<div class="mb-3">
						<label for="password" class="form-label">Password</label>
						<div class="input-group">
							<input type="password" class="form-control" id="password" name="password" required>
							<button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan password" title="Tampilkan/Sembunyikan">
								<i class="fas fa-eye" id="eyeIcon"></i>
							</button>
						</div>
					</div>
					<button type="submit" class="btn btn-primary w-100">Masuk</button>
				</form>
				<script>
				(function(){
					var btn = document.getElementById('togglePassword');
					var input = document.getElementById('password');
					var eyeIcon = document.getElementById('eyeIcon');
					
					if (btn && input && eyeIcon) {
						btn.addEventListener('click', function(){
							var isHidden = input.type === 'password';
							input.type = isHidden ? 'text' : 'password';
							
							// Toggle icon class
							if (isHidden) {
								eyeIcon.className = 'fas fa-eye-slash';
								btn.setAttribute('aria-label', 'Sembunyikan password');
							} else {
								eyeIcon.className = 'fas fa-eye';
								btn.setAttribute('aria-label', 'Tampilkan password');
							}
						});
					}
				})();
				</script>
			</div>
		</div>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>


