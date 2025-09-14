<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can_access('masterbarang')) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Forbidden']);
	exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Create images directory if it doesn't exist
$uploadDir = __DIR__ . '/images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if files were uploaded
if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

$files = $_FILES['images'];
$uploadedFiles = [];
$errors = [];

// Process each uploaded file
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "File " . ($i + 1) . ": Upload error";
        continue;
    }
    
    $file = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i]
    ];
    
    // Validate file type
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "File " . ($i + 1) . " (" . $file['name'] . "): Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed";
        continue;
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        $errors[] = "File " . ($i + 1) . " (" . $file['name'] . "): File too large. Maximum size is 5MB";
        continue;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'barang_' . uniqid() . '_' . time() . '_' . $i . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $uploadedFiles[] = [
            'filename' => $filename,
            'path' => 'images/' . $filename,
            'original_name' => $file['name']
        ];
    } else {
        $errors[] = "File " . ($i + 1) . " (" . $file['name'] . "): Failed to save file";
    }
}

// Return response
if (empty($uploadedFiles)) {
    echo json_encode([
        'success' => false,
        'message' => 'No files were uploaded successfully',
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
        'files' => $uploadedFiles,
        'errors' => $errors
    ]);
}
?>