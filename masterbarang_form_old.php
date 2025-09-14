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
$isEdit = $id > 0;
$error = '';

$data = [
	'kodebarang' => '',
	'namabarang' => '',
	'satuan' => '',
	'kodepabrik' => '',
	'namapabrik' => '',
	'kodegolongan' => '',
	'namagolongan' => '',
	'hpp' => '0',
	'hargabeli' => '0',
	'discbeli' => '0',
	'hargajual' => '0',
	'discjual' => '0',
	'kondisiharga' => 'normal',
	'stokakhir' => '0',
	'foto' => '',
];

if ($isEdit) {
	$stmt = $pdo->prepare('SELECT * FROM masterbarang WHERE id = ?');
	$stmt->execute([$id]);
	$existing = $stmt->fetch();
	if (!$existing) {
		$error = 'Barang tidak ditemukan';
	} else {
		$data = array_merge($data, $existing);
		$data['hpp'] = number_format($data['hpp'], 2, '.', '');
		$data['hargabeli'] = number_format($data['hargabeli'], 2, '.', '');
		$data['discbeli'] = number_format($data['discbeli'], 2, '.', '');
		$data['hargajual'] = number_format($data['hargajual'], 2, '.', '');
		$data['discjual'] = number_format($data['discjual'], 2, '.', '');
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$data['kodebarang'] = trim($_POST['kodebarang'] ?? '');
	$data['namabarang'] = trim($_POST['namabarang'] ?? '');
	$data['satuan'] = trim($_POST['satuan'] ?? '');
	$data['kodepabrik'] = trim($_POST['kodepabrik'] ?? '');
	$data['namapabrik'] = trim($_POST['namapabrik'] ?? '');
	$data['kodegolongan'] = trim($_POST['kodegolongan'] ?? '');
	$data['namagolongan'] = trim($_POST['namagolongan'] ?? '');
	$data['hpp'] = (float)($_POST['hpp'] ?? 0);
	$data['hargabeli'] = (float)($_POST['hargabeli'] ?? 0);
	$data['discbeli'] = (float)($_POST['discbeli'] ?? 0);
	$data['hargajual'] = (float)($_POST['hargajual'] ?? 0);
	$data['discjual'] = (float)($_POST['discjual'] ?? 0);
	$data['kondisiharga'] = $_POST['kondisiharga'] ?? 'normal';
	$data['stokakhir'] = (int)($_POST['stokakhir'] ?? 0);
	$data['foto'] = trim($_POST['foto'] ?? '');

	if ($data['kodebarang'] === '' || $data['namabarang'] === '' || $data['satuan'] === '') {
		$error = 'Kode Barang, Nama Barang, dan Satuan wajib diisi';
	}

	if (!$error) {
		// Check for duplicate kodebarang
		$checkSql = $isEdit ? 'SELECT id FROM masterbarang WHERE kodebarang = ? AND id != ?' : 'SELECT id FROM masterbarang WHERE kodebarang = ?';
		$checkParams = $isEdit ? [$data['kodebarang'], $id] : [$data['kodebarang']];
		$checkStmt = $pdo->prepare($checkSql);
		$checkStmt->execute($checkParams);
		if ($checkStmt->fetch()) {
			$error = 'Kode barang tersebut sudah digunakan';
		}
	}

	if (!$error) {
		if ($isEdit) {
			$sql = 'UPDATE masterbarang SET kodebarang=?, namabarang=?, satuan=?, kodepabrik=?, namapabrik=?, kodegolongan=?, namagolongan=?, hpp=?, hargabeli=?, discbeli=?, hargajual=?, discjual=?, kondisiharga=?, stokakhir=?, foto=? WHERE id=?';
			$params = [$data['kodebarang'], $data['namabarang'], $data['satuan'], $data['kodepabrik'] ?: null, $data['namapabrik'] ?: null, $data['kodegolongan'] ?: null, $data['namagolongan'] ?: null, $data['hpp'], $data['hargabeli'], $data['discbeli'], $data['hargajual'], $data['discjual'], $data['kondisiharga'], $data['stokakhir'], $data['foto'] ?: null, $id];
		} else {
			$sql = 'INSERT INTO masterbarang (kodebarang,namabarang,satuan,kodepabrik,namapabrik,kodegolongan,namagolongan,hpp,hargabeli,discbeli,hargajual,discjual,kondisiharga,stokakhir,foto) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
			$params = [$data['kodebarang'], $data['namabarang'], $data['satuan'], $data['kodepabrik'] ?: null, $data['namapabrik'] ?: null, $data['kodegolongan'] ?: null, $data['namagolongan'] ?: null, $data['hpp'], $data['hargabeli'], $data['discbeli'], $data['hargajual'], $data['discjual'], $data['kondisiharga'], $data['stokakhir'], $data['foto'] ?: null];
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		header('Location: masterbarang.php?msg=' . ($isEdit ? 'saved' : 'created'));
		exit;
	}
}

include __DIR__ . '/includes/header.php';
?>
<div class="flex-grow-1">
	<div class="container" style="max-width: 800px;">
		<h3 class="mb-3"><?php echo $isEdit ? 'Edit Barang' : 'Tambah Barang'; ?></h3>
		<?php if ($error): ?>
			<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<div class="card">
			<div class="card-body">
				<form method="post" action="">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Kode Barang <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="kodebarang" required value="<?php echo htmlspecialchars($data['kodebarang']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Nama Barang <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="namabarang" required value="<?php echo htmlspecialchars($data['namabarang']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Satuan <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="satuan" required value="<?php echo htmlspecialchars($data['satuan']); ?>" placeholder="Contoh: Tablet, Kapsul, Botol">
						</div>
						<div class="col-md-6">
							<label class="form-label">Kondisi Harga</label>
							<select name="kondisiharga" class="form-select" required>
								<?php foreach (['normal','promo','diskon'] as $kondisi): ?>
									<option value="<?php echo $kondisi; ?>" <?php echo $data['kondisiharga']===$kondisi?'selected':''; ?>><?php echo ucfirst($kondisi); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label">Stok Akhir</label>
							<input type="number" class="form-control" name="stokakhir" min="0" value="<?php echo htmlspecialchars($data['stokakhir']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Kode Pabrik</label>
							<input type="text" class="form-control" name="kodepabrik" value="<?php echo htmlspecialchars($data['kodepabrik']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Nama Pabrik</label>
							<input type="text" class="form-control" name="namapabrik" value="<?php echo htmlspecialchars($data['namapabrik']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Kode Golongan</label>
							<input type="text" class="form-control" name="kodegolongan" value="<?php echo htmlspecialchars($data['kodegolongan']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Nama Golongan</label>
							<input type="text" class="form-control" name="namagolongan" value="<?php echo htmlspecialchars($data['namagolongan']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">HPP (Harga Pokok Penjualan)</label>
							<div class="input-group">
								<span class="input-group-text">Rp</span>
								<input type="number" class="form-control" name="hpp" step="0.01" min="0" value="<?php echo htmlspecialchars($data['hpp']); ?>">
							</div>
						</div>
						<div class="col-md-6">
							<label class="form-label">Harga Beli</label>
							<div class="input-group">
								<span class="input-group-text">Rp</span>
								<input type="number" class="form-control" name="hargabeli" step="0.01" min="0" value="<?php echo htmlspecialchars($data['hargabeli']); ?>">
							</div>
						</div>
						<div class="col-md-6">
							<label class="form-label">Diskon Beli (%)</label>
							<div class="input-group">
								<input type="number" class="form-control" name="discbeli" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($data['discbeli']); ?>">
								<span class="input-group-text">%</span>
							</div>
						</div>
						<div class="col-md-6">
							<label class="form-label">Harga Jual</label>
							<div class="input-group">
								<span class="input-group-text">Rp</span>
								<input type="number" class="form-control" name="hargajual" step="0.01" min="0" value="<?php echo htmlspecialchars($data['hargajual']); ?>">
							</div>
						</div>
						<div class="col-md-6">
							<label class="form-label">Diskon Jual (%)</label>
							<div class="input-group">
								<input type="number" class="form-control" name="discjual" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($data['discjual']); ?>">
								<span class="input-group-text">%</span>
							</div>
						</div>
						<div class="col-12">
							<label class="form-label">Foto/Gambar Barang (Multiple)</label>
							<div class="upload-area" id="uploadArea" style="border: 2px dashed #dee2e6; border-radius: 8px; padding: 40px; text-align: center; background: #f8f9fa; cursor: pointer; transition: all 0.3s ease;">
								<div id="uploadContent">
									<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16" class="mb-3">
										<path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
										<path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
									</svg>
									<p class="mb-2"><strong>Klik atau drag & drop gambar di sini</strong></p>
									<p class="text-muted small mb-0">JPG, PNG, GIF, WebP (max 5MB per file)</p>
									<p class="text-muted small mb-0">Bisa upload multiple gambar sekaligus</p>
								</div>
							</div>
							<input type="file" id="fileInput" multiple accept="image/*" style="display: none;">
							<input type="hidden" name="foto" id="fotoInput" value="<?php echo htmlspecialchars($data['foto']); ?>">
							
							<!-- Photo Gallery -->
							<div id="photoGallery" class="mt-3" style="display: none;">
								<h6>Foto yang akan diupload:</h6>
								<div id="photoGrid" class="row g-2"></div>
								<div class="mt-2">
									<button type="button" class="btn btn-sm btn-outline-danger" id="clearAllPhotos">Hapus Semua</button>
								</div>
							</div>
							
							<div class="form-text">Upload multiple gambar barang atau masukkan URL gambar</div>
							<div class="mt-2">
								<input type="url" class="form-control" id="urlInput" placeholder="Masukkan URL gambar (https://example.com/image.jpg)">
								<button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="addUrlPhoto">Tambah URL</button>
							</div>
						</div>
					</div>
					<div class="mt-3 d-flex gap-2">
						<button type="submit" class="btn btn-primary">Simpan</button>
						<a href="masterbarang.php" class="btn btn-secondary">Batal</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const uploadContent = document.getElementById('uploadContent');
    const uploadPreview = document.getElementById('uploadPreview');
    const previewImage = document.getElementById('previewImage');
    const fotoInput = document.getElementById('fotoInput');
    const urlInput = document.getElementById('urlInput');
    const toggleUrlBtn = document.getElementById('toggleUrl');
    const removeImageBtn = document.getElementById('removeImage');
    
    let isUrlMode = false;
    
    // Initialize with existing image if editing
    if (fotoInput.value) {
        showPreview(fotoInput.value);
    }
    
    // Toggle between upload and URL mode
    toggleUrlBtn.addEventListener('click', function() {
        isUrlMode = !isUrlMode;
        if (isUrlMode) {
            urlInput.style.display = 'block';
            uploadArea.style.display = 'none';
            toggleUrlBtn.textContent = 'Gunakan Upload';
            urlInput.value = fotoInput.value;
        } else {
            urlInput.style.display = 'none';
            uploadArea.style.display = 'block';
            toggleUrlBtn.textContent = 'Gunakan URL';
            fotoInput.value = urlInput.value;
        }
    });
    
    // URL input change
    urlInput.addEventListener('input', function() {
        fotoInput.value = this.value;
        if (this.value) {
            showPreview(this.value);
        } else {
            hidePreview();
        }
    });
    
    // Remove image
    removeImageBtn.addEventListener('click', function() {
        hidePreview();
        fotoInput.value = '';
        urlInput.value = '';
    });
    
    // Drag and drop events
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#007bff';
        this.style.backgroundColor = '#e3f2fd';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#dee2e6';
        this.style.backgroundColor = '#f8f9fa';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#dee2e6';
        this.style.backgroundColor = '#f8f9fa';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFile(files[0]);
        }
    });
    
    // Click to upload
    uploadArea.addEventListener('click', function() {
        if (!isUrlMode) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = function(e) {
                if (e.target.files.length > 0) {
                    uploadFile(e.target.files[0]);
                }
            };
            input.click();
        }
    });
    
    function uploadFile(file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Tipe file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.');
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File terlalu besar. Maksimal 5MB.');
            return;
        }
        
        // Show loading
        uploadContent.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Mengupload...</p>';
        
        // Upload file
        const formData = new FormData();
        formData.append('image', file);
        
        fetch('upload_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fotoInput.value = data.path;
                showPreview(data.path);
            } else {
                alert('Error: ' + data.message);
                resetUploadArea();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengupload file.');
            resetUploadArea();
        });
    }
    
    function showPreview(imagePath) {
        previewImage.src = imagePath;
        uploadContent.style.display = 'none';
        uploadPreview.style.display = 'block';
    }
    
    function hidePreview() {
        uploadContent.style.display = 'block';
        uploadPreview.style.display = 'none';
        resetUploadArea();
    }
    
    function resetUploadArea() {
        uploadContent.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16" class="mb-3">
                <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
            </svg>
            <p class="mb-2"><strong>Klik atau drag & drop gambar di sini</strong></p>
            <p class="text-muted small mb-0">JPG, PNG, GIF, WebP (max 5MB)</p>
        `;
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
