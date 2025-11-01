<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('masterbarang')) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$pdo = get_pdo_connection();

// Helper function to safely escape HTML, handling null values
function h($value) {
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

$data = [
	'kodebarang' => '',
	'namabarang' => '',
	'deskripsi' => '',
	'kandungan' => '',
	'supplier' => '',
	'kemasan' => '',
	'nie' => '',
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
	'kondisiharga' => 'baru',
	'stokakhir' => '0',
	'foto' => '',
	'status' => 'aktif',
];

if ($isEdit) {
	$stmt = $pdo->prepare('SELECT * FROM masterbarang WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	if (!$row) {
		header('Location: masterbarang.php?msg=error');
		exit;
	}
	$data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$data['kodebarang'] = trim($_POST['kodebarang'] ?? '');
	$data['namabarang'] = trim($_POST['namabarang'] ?? '');
	$data['deskripsi'] = trim($_POST['deskripsi'] ?? '');
	$data['kandungan'] = trim($_POST['kandungan'] ?? '');
	$data['supplier'] = trim($_POST['supplier'] ?? '');
	$data['kemasan'] = trim($_POST['kemasan'] ?? '');
	$data['nie'] = trim($_POST['nie'] ?? '');
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
	$data['kondisiharga'] = $_POST['kondisiharga'] ?? 'baru';
	$data['stokakhir'] = (int)($_POST['stokakhir'] ?? 0);
	$data['foto'] = trim($_POST['foto'] ?? '');
	$data['status'] = $_POST['status'] ?? 'aktif';

	if ($data['kodebarang'] === '' || $data['namabarang'] === '' || $data['satuan'] === '') {
		$error = 'Kode Barang, Nama Barang, dan Satuan wajib diisi';
	} else {
		// Check for duplicate kodebarang
		$checkSql = 'SELECT id FROM masterbarang WHERE kodebarang = ?';
		$checkParams = [$data['kodebarang']];
		if ($isEdit) {
			$checkSql .= ' AND id <> ?';
			$checkParams[] = $id;
		}
		$checkStmt = $pdo->prepare($checkSql);
		$checkStmt->execute($checkParams);
		if ($checkStmt->fetch()) {
			$error = 'Kode barang tersebut sudah digunakan';
		}
	}

	if (!$error) {
		if ($isEdit) {
			$sql = 'UPDATE masterbarang SET kodebarang=?, namabarang=?, deskripsi=?, kandungan=?, supplier=?, kemasan=?, nie=?, satuan=?, kodepabrik=?, namapabrik=?, kodegolongan=?, namagolongan=?, hpp=?, hargabeli=?, discbeli=?, hargajual=?, discjual=?, kondisiharga=?, stokakhir=?, foto=?, status=? WHERE id=?';
			$params = [$data['kodebarang'], $data['namabarang'], $data['deskripsi'] ?: null, $data['kandungan'] ?: null, $data['supplier'] ?: null, $data['kemasan'] ?: null, $data['nie'] ?: null, $data['satuan'], $data['kodepabrik'] ?: null, $data['namapabrik'] ?: null, $data['kodegolongan'] ?: null, $data['namagolongan'] ?: null, $data['hpp'], $data['hargabeli'], $data['discbeli'], $data['hargajual'], $data['discjual'], $data['kondisiharga'], $data['stokakhir'], $data['foto'] ?: null, $data['status'], $id];
		} else {
			$sql = 'INSERT INTO masterbarang (kodebarang,namabarang,deskripsi,kandungan,supplier,kemasan,nie,satuan,kodepabrik,namapabrik,kodegolongan,namagolongan,hpp,hargabeli,discbeli,hargajual,discjual,kondisiharga,stokakhir,foto,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
			$params = [$data['kodebarang'], $data['namabarang'], $data['deskripsi'] ?: null, $data['kandungan'] ?: null, $data['supplier'] ?: null, $data['kemasan'] ?: null, $data['nie'] ?: null, $data['satuan'], $data['kodepabrik'] ?: null, $data['namapabrik'] ?: null, $data['kodegolongan'] ?: null, $data['namagolongan'] ?: null, $data['hpp'], $data['hargabeli'], $data['discbeli'], $data['hargajual'], $data['discjual'], $data['kondisiharga'], $data['stokakhir'], $data['foto'] ?: null, $data['status']];
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		header('Location: masterbarang.php?msg=' . ($isEdit ? 'saved' : 'created'));
		exit;
	}
}

include __DIR__ . '/includes/header.php';
?>
<div class="flex-grow-1 mb-4">
	<div class="container" style="max-width: 1200px;">
		<h3 class="mb-3"><?php echo $isEdit ? 'Edit Barang' : 'Tambah Barang'; ?></h3>
		<?php if ($error): ?>
			<div class="alert alert-danger"><?php echo h($error); ?></div>
		<?php endif; ?>
		<div class="card">
			<div class="card-body">
				<form method="post" action="">
					<div class="row g-3">
						<div class="col-md-2">
							<label class="form-label">Kode Barang <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="kodebarang" required value="<?php echo h($data['kodebarang']); ?>">
						</div>
						<div class="col-md-7">
							<label class="form-label">Nama Barang <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="namabarang" required value="<?php echo h($data['namabarang']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Satuan <span class="text-danger">*</span></label>
							<input type="text" class="form-control" name="satuan" required value="<?php echo h($data['satuan']); ?>" placeholder="Contoh: Tablet, Kapsul, Botol">
						</div>
						<div class="col-md-6 d-none">
							<label class="form-label">Kode Pabrik</label>
							<input type="text" class="form-control" name="kodepabrik" value="<?php echo h($data['kodepabrik']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Nama Pabrik</label>
							<input type="text" class="form-control" name="namapabrik" value="<?php echo h($data['namapabrik']); ?>">
						</div>
						<div class="col-md-4 d-none">
							<label class="form-label">Kode Golongan</label>
							<input type="text" class="form-control" name="kodegolongan" value="<?php echo h($data['kodegolongan']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Nama Golongan</label>
							<input type="text" class="form-control" name="namagolongan" value="<?php echo h($data['namagolongan']); ?>">
						</div>
						<div class="col-md-6">
							<label class="form-label">Kemasan</label>
							<input type="text" name="kemasan" class="form-control" value="<?php echo h($data['kemasan']); ?>" placeholder="Jenis kemasan barang">
						</div>
						<div class="col-md-2">
							<label class="form-label">NIE (Nomor Izin Edar)</label>
							<input type="text" name="nie" class="form-control" value="<?php echo h($data['nie']); ?>" placeholder="Nomor Izin Edar">
						</div>
						<div class="col-md-7">
							<label class="form-label">Supplier/Pemasok</label>
							<input type="text" name="supplier" class="form-control" value="<?php echo h($data['supplier']); ?>" placeholder="Nama supplier/pemasok">
						</div>
						<div class="col-md-3">
							<label class="form-label">Status</label>
							<select name="status" class="form-select" required>
								<option value="aktif" <?php echo $data['status']==='aktif'?'selected':''; ?>>Aktif</option>
								<option value="non_aktif" <?php echo $data['status']==='non_aktif'?'selected':''; ?>>Non Aktif</option>
							</select>
						</div>
						<div class="col-12">
							<label class="form-label">Kandungan/Komposisi</label>
							<textarea name="kandungan" class="form-control" rows="3" placeholder="Masukkan kandungan/komposisi barang"><?php echo h($data['kandungan']); ?></textarea>
						</div>
						<div class="col-md-3">
							<label class="form-label">HPP</label>
							<div class="input-group">
								<span class="input-group-text">Rp</span>
								<input type="number" class="form-control" name="hpp" step="1" min="0" value="<?php echo h(number_format($data['hpp'] ?? 0, 0, '', '')); ?>">
							</div>
						</div>
						<div class="col-md-3 d-none">
							<label class="form-label">Harga Beli</label>
							<div class="input-group">
								<span class="input-group-text">Rp</span>
								<input type="number" class="form-control" name="hargabeli" step="1" min="0" value="<?php echo h(number_format($data['hargabeli'] ?? 0, 0, '', '')); ?>">
							</div>
						</div>
						<div class="col-md-2 d-none">
							<label class="form-label">Diskon Beli (%)</label>
							<div class="input-group">
								<input type="number" class="form-control" name="discbeli" step="0.01" min="0" max="100" value="<?php echo h($data['discbeli']); ?>">
								<span class="input-group-text">%</span>
							</div>
						</div>
						<div class="col-md-3">
							<label class="form-label">Harga Jual</label>
							<div class="input-group">
								<span class="input-group-text">Rp</span>
								<input type="number" class="form-control" name="hargajual" step="1" min="0" value="<?php echo h(number_format($data['hargajual'] ?? 0, 0, '', '')); ?>">
							</div>
						</div>
						<div class="col-md-2">
							<label class="form-label">Diskon Jual (%)</label>
							<div class="input-group">
								<input type="number" class="form-control" name="discjual" step="0.01" min="0" max="100" value="<?php echo h($data['discjual']); ?>">
								<span class="input-group-text">%</span>
							</div>
						</div>
						<div class="col-md-2">
							<label class="form-label">Kondisi Harga</label>
							<select name="kondisiharga" class="form-select" required>
								<?php 
								$kondisiOptions = [
									'baru' => 'Baru',
									'normal' => 'Normal',
									'promo' => 'Promo',
									'sale' => 'Sale',
									'spesial' => 'Spesial',
									'deals' => 'Deals'
								];
								foreach ($kondisiOptions as $value => $label): ?>
									<option value="<?php echo $value; ?>" <?php echo $data['kondisiharga']===$value?'selected':''; ?>><?php echo $label; ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-2">
							<label class="form-label">Stok Akhir</label>
							<input type="number" class="form-control" name="stokakhir" min="0" value="<?php echo h($data['stokakhir']); ?>">
						</div>
						<div class="col-12">
							<label class="form-label">Deskripsi/Spesifikasi Produk</label>
							<div id="deskripsi-editor" style="height: 200px; border: 1px solid #ced4da; border-radius: 0.375rem;"></div>
							<textarea name="deskripsi" id="deskripsi-textarea" style="display: none;"><?php echo h($data['deskripsi']); ?></textarea>
						</div>
						<div class="col-12">
							<label class="form-label">Gambar Barang</label>
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
							<input type="hidden" name="foto" id="fotoInput" value="<?php echo h($data['foto']); ?>">
							
							<!-- Photo Gallery -->
							<div id="photoGallery" class="mt-3" style="display: none;">
								<h6>Foto yang akan diupload:</h6>
								<div id="photoGrid" class="row g-2"></div>
								<div class="mt-2">
									<button type="button" class="btn btn-sm btn-outline-danger" id="clearAllPhotos">Hapus Semua</button>
								</div>
							</div>
							
							<div class="form-text">Upload multiple gambar barang (max 5MB per file) atau masukkan URL gambar</div>
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
    const fileInput = document.getElementById('fileInput');
    const fotoInput = document.getElementById('fotoInput');
    const urlInput = document.getElementById('urlInput');
    const addUrlPhotoBtn = document.getElementById('addUrlPhoto');
    const photoGallery = document.getElementById('photoGallery');
    const photoGrid = document.getElementById('photoGrid');
    const clearAllPhotosBtn = document.getElementById('clearAllPhotos');
    
    let photos = [];
    
    // Initialize with existing photos if editing
    if (fotoInput.value) {
        try {
            photos = JSON.parse(fotoInput.value);
            if (Array.isArray(photos)) {
                displayPhotos();
            } else {
                // Handle old single photo format
                photos = [fotoInput.value];
                displayPhotos();
            }
        } catch (e) {
            // Handle old single photo format
            photos = [fotoInput.value];
            displayPhotos();
        }
    }
    
    // Click to upload
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // File input change
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = '#007bff';
        uploadArea.style.backgroundColor = '#e3f2fd';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = '#dee2e6';
        uploadArea.style.backgroundColor = '#f8f9fa';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = '#dee2e6';
        uploadArea.style.backgroundColor = '#f8f9fa';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFiles(files);
        }
    });
    
    // Add URL photo
    addUrlPhotoBtn.addEventListener('click', function() {
        const url = urlInput.value.trim();
        if (url) {
            photos.push(url);
            urlInput.value = '';
            displayPhotos();
        }
    });
    
    // Clear all photos
    clearAllPhotosBtn.addEventListener('click', function() {
        photos = [];
        displayPhotos();
    });
    
    function handleFiles(files) {
        // Validate files before upload
        const maxSize = 5 * 1024 * 1024; // 5MB
        const validFiles = [];
        const errors = [];
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Check file type
            if (!file.type.startsWith('image/')) {
                errors.push(`File ${i + 1} (${file.name}): Invalid file type. Only images are allowed`);
                continue;
            }
            
            // Check file size
            if (file.size > maxSize) {
                errors.push(`File ${i + 1} (${file.name}): File too large. Maximum size is 5MB`);
                continue;
            }
            
            validFiles.push(file);
        }
        
        // Show validation errors if any
        if (errors.length > 0) {
            alert('File validation errors:\n' + errors.join('\n'));
        }
        
        // If no valid files, return
        if (validFiles.length === 0) {
            return;
        }
        
        const formData = new FormData();
        for (let i = 0; i < validFiles.length; i++) {
            formData.append('images[]', validFiles[i]);
        }
        
        fetch('upload_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.files.forEach(file => {
                    photos.push(file.path);
                });
                displayPhotos();
                
                if (data.errors && data.errors.length > 0) {
                    alert('Some files failed to upload:\n' + data.errors.join('\n'));
                }
            } else {
                alert('Upload failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Upload failed');
        });
    }
    
    function displayPhotos() {
        if (photos.length === 0) {
            photoGallery.style.display = 'none';
            fotoInput.value = '';
            return;
        }
        
        photoGallery.style.display = 'block';
        photoGrid.innerHTML = '';
        
        photos.forEach((photo, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-3 col-sm-4 col-6';
            
            col.innerHTML = `
                <div class="card">
                    <img src="${photo}" class="card-img-top" style="height: 120px; object-fit: cover;" alt="Photo ${index + 1}">
                    <div class="card-body p-2">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removePhoto(${index})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                            </svg>
                            Hapus
                        </button>
                    </div>
                </div>
            `;
            
            photoGrid.appendChild(col);
        });
        
        // Update hidden input with JSON array
        fotoInput.value = JSON.stringify(photos);
    }
    
    // Global function for removing photos
    window.removePhoto = function(index) {
        photos.splice(index, 1);
        displayPhotos();
    };
});
</script>

<!-- Quill Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill editor
    var quill = new Quill('#deskripsi-editor', {
        theme: 'snow',
        placeholder: 'Deskripsi detail produk untuk online shop...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                ['link'],
                ['clean']
            ]
        }
    });

    // Set initial content if editing
    var initialContent = document.getElementById('deskripsi-textarea').value;
    if (initialContent) {
        quill.root.innerHTML = initialContent;
    }

    // Update hidden textarea when content changes
    quill.on('text-change', function() {
        document.getElementById('deskripsi-textarea').value = quill.root.innerHTML;
    });

    // Update form submission to include editor content
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            document.getElementById('deskripsi-textarea').value = quill.root.innerHTML;
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
