<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
require_login();
if (!can_access('masterbarang')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $kodebarang = isset($_GET['kodebarang']) ? trim($_GET['kodebarang']) : '';
    
    if ($kodebarang === '') {
        echo json_encode(['success' => false, 'message' => 'Kode barang tidak boleh kosong']);
        exit;
    }
    
    try {
        // Check if barang code is used in detail order transactions
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM detailorder WHERE kodebarang = ?');
        $stmt->execute([$kodebarang]);
        $result = $stmt->fetch();
        $orderCount = (int)$result['count'];
        
        echo json_encode([
            'success' => true,
            'used_in_transaction' => $orderCount > 0,
            'order_count' => $orderCount
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
