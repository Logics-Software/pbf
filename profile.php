<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$error = '';
$success = '';

// Get current user data
$pdo = get_pdo_connection();
$stmt = $pdo->prepare('SELECT id, username, namalengkap, alamat, email, role FROM user WHERE id = ?');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

if (!$userData) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only allow specific fields to be updated
    $namalengkap = trim($_POST['namalengkap'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Security: Only process allowed fields, ignore any other POST data
    $allowedFields = ['namalengkap', 'alamat', 'email'];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $allowedFields)) {
            unset($_POST[$key]);
        }
    }
    
    // Validation
    if ($namalengkap === '') {
        $error = 'Nama lengkap wajib diisi';
    } elseif (strlen($namalengkap) < 2) {
        $error = 'Nama lengkap minimal 2 karakter';
    } elseif (strlen($namalengkap) > 100) {
        $error = 'Nama lengkap maksimal 100 karakter';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($alamat) > 255) {
        $error = 'Alamat maksimal 255 karakter';
    } elseif ($email !== '' && strlen($email) > 100) {
        $error = 'Email maksimal 100 karakter';
    } else {
        // Check if email is already used by another user
        if ($email !== '') {
            $checkStmt = $pdo->prepare('SELECT id FROM user WHERE email = ? AND id != ?');
            $checkStmt->execute([$email, $user['id']]);
            if ($checkStmt->fetch()) {
                $error = 'Email sudah digunakan oleh user lain';
            }
        }
        
        if ($error === '') {
            // Update only allowed fields - namalengkap, alamat, email
            $updateStmt = $pdo->prepare('UPDATE user SET namalengkap = ?, alamat = ?, email = ? WHERE id = ?');
            if ($updateStmt->execute([$namalengkap, $alamat, $email, $user['id']])) {
                $success = 'Profil berhasil diperbarui';
                // Refresh user data
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch();
            } else {
                $error = 'Terjadi kesalahan saat memperbarui profil';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
.profile-card {
    border-left: 4px solid #198754;
}

.form-label.required::after {
    content: " *";
    color: #dc3545;
}
</style>

<div class="flex-grow-1">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Edit Profil</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Edit Profil</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Kembali
                </a>
            </div>
        </div>

        <!-- Profile Information Card -->
        <div class="row">
            <div class="col-md-8">
                <div class="card profile-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-edit me-2"></i>Informasi Profil
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <!-- Editable fields (can be modified) -->
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Informasi:</strong> Anda hanya dapat mengubah nama lengkap, alamat, dan email. 
                                Field lainnya tidak dapat dimodifikasi melalui halaman ini.
                            </div>

                            <!-- Field 1: Nama Lengkap (Required) -->
                            <div class="mb-3">
                                <label for="namalengkap" class="form-label required">Nama Lengkap</label>
                                <input type="text" class="form-control" id="namalengkap" name="namalengkap" 
                                       value="<?php echo htmlspecialchars($userData['namalengkap']); ?>" 
                                       required maxlength="100">
                                <div class="form-text">Masukkan nama lengkap Anda (wajib diisi)</div>
                            </div>

                            <!-- Field 2: Alamat (Optional) -->
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat</label>
                                <textarea class="form-control" id="alamat" name="alamat" rows="3" 
                                          maxlength="255"><?php echo htmlspecialchars($userData['alamat']); ?></textarea>
                                <div class="form-text">Masukkan alamat lengkap Anda (opsional)</div>
                            </div>

                            <!-- Field 3: Email (Optional) -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($userData['email']); ?>" 
                                       maxlength="100">
                                <div class="form-text">Masukkan alamat email Anda (opsional)</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i>Simpan Perubahan
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Informasi Akun
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?php echo htmlspecialchars($userData['username']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Role:</strong></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars(ucfirst($userData['role'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-success">Aktif</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Keamanan
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Kelola keamanan akun Anda dengan mengubah password secara berkala.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="change_password.php" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-key me-1"></i>Ubah Password
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-home me-1"></i>Kembali ke Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resetForm() {
    if (confirm('Apakah Anda yakin ingin mengembalikan form ke data asli?')) {
        // Reset form to original values
        document.getElementById('namalengkap').value = '<?php echo addslashes($userData['namalengkap']); ?>';
        document.getElementById('alamat').value = '<?php echo addslashes($userData['alamat']); ?>';
        document.getElementById('email').value = '<?php echo addslashes($userData['email']); ?>';
    }
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
