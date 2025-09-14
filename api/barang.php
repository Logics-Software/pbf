<?php
// Start output buffering and clean any existing output
ob_start();
ob_clean();

// Suppress warnings temporarily
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/db.php';

// Clean any output before setting headers
ob_clean();

header('Content-Type: application/json');

// Restore error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = get_pdo_connection();
    
    $search = trim($_GET['search'] ?? '');
    
    $where = ['status = "aktif"'];
    $params = [];
    
    if ($search !== '') {
        $where[] = '(kodebarang LIKE ? OR namabarang LIKE ? OR supplier LIKE ? OR kemasan LIKE ? OR nie LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT kodebarang, namabarang, satuan, hargajual, discjual, stokakhir, foto, namapabrik, namagolongan, kandungan, supplier, kemasan, nie 
            FROM masterbarang 
            WHERE $whereClause 
            ORDER BY namabarang 
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $barang = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process foto field - keep all photos for multiple photo support
    foreach ($barang as &$item) {
        if ($item['foto']) {
            try {
                $photos = json_decode($item['foto'], true);
                if (is_array($photos) && !empty($photos)) {
                    // Keep all photos for multiple photo display
                    $item['foto'] = $photos;
                } else {
                    $item['foto'] = null;
                }
            } catch (Exception $e) {
                $item['foto'] = null;
            }
        } else {
            $item['foto'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $barang
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
