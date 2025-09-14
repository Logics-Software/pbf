<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$error = '';
$success = '';

// Get current user data
$pdo = get_pdo_connection();
$stmt = $pdo->prepare('SELECT id, username, namalengkap FROM user WHERE id = ?');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

if (!$userData) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if ($currentPassword === '') {
        $error = 'Password lama wajib diisi';
    } elseif ($newPassword === '') {
        $error = 'Password baru wajib diisi';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password baru minimal 6 karakter';
    } elseif (strlen($newPassword) > 50) {
        $error = 'Password baru maksimal 50 karakter';
    } elseif ($confirmPassword === '') {
        $error = 'Konfirmasi password wajib diisi';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Konfirmasi password tidak sama dengan password baru';
    } elseif ($currentPassword === $newPassword) {
        $error = 'Password baru harus berbeda dengan password lama';
    } else {
        // Verify current password
        $verifyStmt = $pdo->prepare('SELECT password FROM user WHERE id = ?');
        $verifyStmt->execute([$user['id']]);
        $userPassword = $verifyStmt->fetchColumn();
        
        if (!password_verify($currentPassword, $userPassword)) {
            $error = 'Password lama tidak benar';
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateStmt = $pdo->prepare('UPDATE user SET password = ? WHERE id = ?');
            if ($updateStmt->execute([$hashedPassword, $user['id']])) {
                $success = 'Password berhasil diubah';
                // Clear form
                $currentPassword = $newPassword = $confirmPassword = '';
            } else {
                $error = 'Terjadi kesalahan saat mengubah password';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
.password-card {
    border-left: 4px solid #dc3545;
}

.form-label.required::after {
    content: " *";
    color: #dc3545;
}

.password-strength {
    height: 4px;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.password-strength.weak {
    background-color: #dc3545;
    width: 25%;
}

.password-strength.fair {
    background-color: #ffc107;
    width: 50%;
}

.password-strength.good {
    background-color: #17a2b8;
    width: 75%;
}

.password-strength.strong {
    background-color: #28a745;
    width: 100%;
}

.password-requirements {
    font-size: 0.875rem;
}

.password-requirements .requirement {
    display: flex;
    align-items: center;
    margin-bottom: 0.25rem;
}

.password-requirements .requirement.met {
    color: #28a745;
}

.password-requirements .requirement.unmet {
    color: #6c757d;
}

.password-requirements .requirement i {
    margin-right: 0.5rem;
    width: 16px;
}
</style>

<div class="flex-grow-1">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Ubah Password</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="profile.php">Profil</a></li>
                        <li class="breadcrumb-item active">Ubah Password</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="profile.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Profil
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card password-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-key me-2"></i>Ubah Password
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

                        <form method="post" action="" id="changePasswordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label required">Password Lama</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" 
                                           value="<?php echo htmlspecialchars($currentPassword ?? ''); ?>" 
                                           required autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                        <i class="fas fa-eye" id="currentEyeIcon"></i>
                                    </button>
                                </div>
                                <div class="form-text">Masukkan password yang sedang digunakan</div>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label required">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           value="<?php echo htmlspecialchars($newPassword ?? ''); ?>" 
                                           required autocomplete="new-password" minlength="6" maxlength="50">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye" id="newEyeIcon"></i>
                                    </button>
                                </div>
                                
                                <!-- Password Strength Indicator -->
                                <div class="mt-2">
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>
                                
                                <!-- Password Requirements -->
                                <div class="password-requirements mt-2">
                                    <div class="requirement" id="req-length">
                                        <i class="fas fa-circle"></i>
                                        <span>Minimal 6 karakter</span>
                                    </div>
                                    <div class="requirement" id="req-uppercase">
                                        <i class="fas fa-circle"></i>
                                        <span>Mengandung huruf besar</span>
                                    </div>
                                    <div class="requirement" id="req-lowercase">
                                        <i class="fas fa-circle"></i>
                                        <span>Mengandung huruf kecil</span>
                                    </div>
                                    <div class="requirement" id="req-number">
                                        <i class="fas fa-circle"></i>
                                        <span>Mengandung angka</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label required">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           value="<?php echo htmlspecialchars($confirmPassword ?? ''); ?>" 
                                           required autocomplete="new-password" minlength="6" maxlength="50">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye" id="confirmEyeIcon"></i>
                                    </button>
                                </div>
                                <div class="form-text" id="confirmMessage"></div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger" id="submitBtn">
                                    <i class="fas fa-key me-1"></i>Ubah Password
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Tips -->
                <div class="card mt-3 mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Tips Keamanan Password
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Gunakan kombinasi huruf besar, huruf kecil, dan angka
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Hindari menggunakan informasi pribadi
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Jangan gunakan password yang sama dengan akun lain
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                Ganti password secara berkala
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password visibility toggles
function setupPasswordToggle(toggleId, inputId, iconId) {
    const toggle = document.getElementById(toggleId);
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (toggle && input && icon) {
        toggle.addEventListener('click', function() {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            
            if (isHidden) {
                icon.className = 'fas fa-eye-slash';
                toggle.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                icon.className = 'fas fa-eye';
                toggle.setAttribute('aria-label', 'Tampilkan password');
            }
        });
    }
}

// Password strength checker
function checkPasswordStrength(password) {
    let score = 0;
    const requirements = {
        length: password.length >= 6,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /\d/.test(password)
    };
    
    // Update requirement indicators
    Object.keys(requirements).forEach(req => {
        const element = document.getElementById('req-' + req);
        if (element) {
            if (requirements[req]) {
                element.classList.add('met');
                element.classList.remove('unmet');
                element.querySelector('i').className = 'fas fa-check';
                score++;
            } else {
                element.classList.add('unmet');
                element.classList.remove('met');
                element.querySelector('i').className = 'fas fa-circle';
            }
        }
    });
    
    // Update strength indicator
    const strengthBar = document.getElementById('passwordStrength');
    if (strengthBar) {
        strengthBar.className = 'password-strength';
        if (score === 0) {
            // No class added
        } else if (score === 1) {
            strengthBar.classList.add('weak');
        } else if (score === 2) {
            strengthBar.classList.add('fair');
        } else if (score === 3) {
            strengthBar.classList.add('good');
        } else if (score === 4) {
            strengthBar.classList.add('strong');
        }
    }
    
    return score;
}

// Confirm password checker
function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const message = document.getElementById('confirmMessage');
    
    if (confirmPassword === '') {
        message.textContent = '';
        message.className = 'form-text';
        return;
    }
    
    if (newPassword === confirmPassword) {
        message.textContent = 'Password cocok';
        message.className = 'form-text text-success';
    } else {
        message.textContent = 'Password tidak cocok';
        message.className = 'form-text text-danger';
    }
}

// Form validation
function validateForm() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const submitBtn = document.getElementById('submitBtn');
    
    const isValid = currentPassword !== '' && 
                   newPassword !== '' && 
                   confirmPassword !== '' && 
                   newPassword === confirmPassword && 
                   newPassword !== currentPassword &&
                   checkPasswordStrength(newPassword) >= 2;
    
    submitBtn.disabled = !isValid;
}

function resetForm() {
    if (confirm('Apakah Anda yakin ingin mengosongkan form?')) {
        document.getElementById('changePasswordForm').reset();
        document.getElementById('passwordStrength').className = 'password-strength';
        document.getElementById('confirmMessage').textContent = '';
        document.getElementById('confirmMessage').className = 'form-text';
        
        // Reset requirement indicators
        document.querySelectorAll('.requirement').forEach(req => {
            req.classList.remove('met', 'unmet');
            req.querySelector('i').className = 'fas fa-circle';
        });
        
        document.getElementById('submitBtn').disabled = true;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Setup password toggles
    setupPasswordToggle('toggleCurrentPassword', 'current_password', 'currentEyeIcon');
    setupPasswordToggle('toggleNewPassword', 'new_password', 'newEyeIcon');
    setupPasswordToggle('toggleConfirmPassword', 'confirm_password', 'confirmEyeIcon');
    
    // Setup event listeners
    document.getElementById('new_password').addEventListener('input', function() {
        checkPasswordStrength(this.value);
        validateForm();
    });
    
    document.getElementById('confirm_password').addEventListener('input', function() {
        checkPasswordMatch();
        validateForm();
    });
    
    document.getElementById('current_password').addEventListener('input', validateForm);
    
    // Initial validation
    validateForm();
    
    // Auto-hide alerts after 5 seconds
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
