<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    
    $search = trim($_GET['search'] ?? '');
    
    $where = ['status = "aktif"'];
    $params = [];
    
    if ($search !== '') {
        $where[] = '(kodecustomer LIKE ? OR namacustomer LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT kodecustomer, namacustomer, alamatcustomer, notelepon, contactperson, kodesales, namasales 
            FROM mastercustomer 
            WHERE $whereClause 
            ORDER BY namacustomer 
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $customers
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
