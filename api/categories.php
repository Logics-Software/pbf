<?php
/**
 * API endpoint untuk mendapatkan data kategori filter
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $categories = [
        'pabrik' => [],
        'golongan' => [],
        'kandungan' => [],
        'kemasan' => []
    ];
    
    // Get distinct pabrik
    $stmt = $pdo->query("SELECT DISTINCT namapabrik FROM masterbarang WHERE status = 'aktif' AND namapabrik IS NOT NULL AND namapabrik != '' ORDER BY namapabrik");
    $categories['pabrik'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get distinct golongan
    $stmt = $pdo->query("SELECT DISTINCT namagolongan FROM masterbarang WHERE status = 'aktif' AND namagolongan IS NOT NULL AND namagolongan != '' ORDER BY namagolongan");
    $categories['golongan'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get distinct kandungan (split by comma)
    $stmt = $pdo->query("SELECT DISTINCT kandungan FROM masterbarang WHERE status = 'aktif' AND kandungan IS NOT NULL AND kandungan != ''");
    $kandunganData = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $kandunganSet = [];
    foreach ($kandunganData as $kandungan) {
        $items = array_map('trim', explode(',', $kandungan));
        foreach ($items as $item) {
            if (!empty($item)) {
                $kandunganSet[$item] = true;
            }
        }
    }
    $categories['kandungan'] = array_keys($kandunganSet);
    sort($categories['kandungan']);
    
    // Get distinct kemasan
    $stmt = $pdo->query("SELECT DISTINCT kemasan FROM masterbarang WHERE status = 'aktif' AND kemasan IS NOT NULL AND kemasan != '' ORDER BY kemasan");
    $categories['kemasan'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
