<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    
    $search = trim($_GET['search'] ?? '');
    
    $where = ['status = "aktif"'];
    $params = [];
    
    if ($search !== '') {
        $where[] = '(kodesales LIKE ? OR namasales LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT kodesales, namasales, alamatsales, notelepon 
            FROM mastersales 
            WHERE $whereClause 
            ORDER BY namasales 
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $sales
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
