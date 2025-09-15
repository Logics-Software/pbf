<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
require_login();
if (!can_access('users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $iduser = isset($_GET['iduser']) ? trim($_GET['iduser']) : '';
    
    if ($iduser === '') {
        echo json_encode(['success' => false, 'message' => 'ID user tidak boleh kosong']);
        exit;
    }
    
    try {
        // Check if user ID is used in order transactions
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM headerorder WHERE iduser = ?');
        $stmt->execute([$iduser]);
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
