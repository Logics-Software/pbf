<?php
/**
 * API untuk keranjang belanja
 * Endpoint: api/cart.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = current_user();
if ($user['role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$pdo = get_pdo_connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Add item to cart
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['kodebarang']) || !isset($input['quantity'])) {
                throw new Exception('Missing required parameters');
            }
            
            $kodebarang = trim($input['kodebarang']);
            $quantity = (int)$input['quantity'];
            
            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }
            
            // Check if product exists and is active
            $stmt = $pdo->prepare('SELECT kodebarang, namabarang, hargajual, discjual, stokakhir FROM masterbarang WHERE kodebarang = ? AND status = "aktif"');
            $stmt->execute([$kodebarang]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Product not found or inactive');
            }
            
            if ($product['stokakhir'] < $quantity) {
                throw new Exception('Insufficient stock');
            }
            
            // Check if item already exists in cart
            $stmt = $pdo->prepare('SELECT id, quantity FROM cart WHERE customer_code = ? AND kodebarang = ?');
            $stmt->execute([$user['kodecustomer'], $kodebarang]);
            $existingItem = $stmt->fetch();
            
            if ($existingItem) {
                // Update existing item
                $newQuantity = $existingItem['quantity'] + $quantity;
                
                if ($newQuantity > $product['stokakhir']) {
                    throw new Exception('Total quantity exceeds available stock');
                }
                
                $stmt = $pdo->prepare('UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$newQuantity, $existingItem['id']]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Item quantity updated in cart',
                    'data' => [
                        'kodebarang' => $kodebarang,
                        'quantity' => $newQuantity,
                        'action' => 'updated'
                    ]
                ]);
            } else {
                // Add new item
                $stmt = $pdo->prepare('INSERT INTO cart (customer_code, kodebarang, quantity) VALUES (?, ?, ?)');
                $stmt->execute([$user['kodecustomer'], $kodebarang, $quantity]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Item added to cart',
                    'data' => [
                        'kodebarang' => $kodebarang,
                        'quantity' => $quantity,
                        'action' => 'added'
                    ]
                ]);
            }
            break;
            
        case 'GET':
            // Get cart items
            $stmt = $pdo->prepare('
                SELECT c.*, m.namabarang, m.hargajual, m.discjual, m.stokakhir, m.foto, m.satuan
                FROM cart c
                JOIN masterbarang m ON c.kodebarang = m.kodebarang
                WHERE c.customer_code = ? AND m.status = "aktif"
                ORDER BY c.created_at DESC
            ');
            $stmt->execute([$user['kodecustomer']]);
            $cartItems = $stmt->fetchAll();
            
            // Calculate totals
            $totalItems = 0;
            $totalPrice = 0;
            
            foreach ($cartItems as &$item) {
                $discountPrice = $item['hargajual'] - ($item['hargajual'] * $item['discjual'] / 100);
                $item['discount_price'] = $discountPrice;
                $item['subtotal'] = $discountPrice * $item['quantity'];
                $totalItems += $item['quantity'];
                $totalPrice += $item['subtotal'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => $cartItems,
                    'summary' => [
                        'total_items' => $totalItems,
                        'total_price' => $totalPrice
                    ]
                ]
            ]);
            break;
            
        case 'PUT':
            // Update item quantity
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['kodebarang']) || !isset($input['quantity'])) {
                throw new Exception('Missing required parameters');
            }
            
            $kodebarang = trim($input['kodebarang']);
            $quantity = (int)$input['quantity'];
            
            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }
            
            // Check stock availability
            $stmt = $pdo->prepare('SELECT stokakhir FROM masterbarang WHERE kodebarang = ? AND status = "aktif"');
            $stmt->execute([$kodebarang]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Product not found or inactive');
            }
            
            if ($quantity > $product['stokakhir']) {
                throw new Exception('Insufficient stock');
            }
            
            // Update cart item
            $stmt = $pdo->prepare('UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_code = ? AND kodebarang = ?');
            $stmt->execute([$quantity, $user['kodecustomer'], $kodebarang]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Item not found in cart');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Item quantity updated',
                'data' => [
                    'kodebarang' => $kodebarang,
                    'quantity' => $quantity
                ]
            ]);
            break;
            
        case 'DELETE':
            // Remove item from cart
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['kodebarang'])) {
                throw new Exception('Missing required parameters');
            }
            
            $kodebarang = trim($input['kodebarang']);
            
            $stmt = $pdo->prepare('DELETE FROM cart WHERE customer_code = ? AND kodebarang = ?');
            $stmt->execute([$user['kodecustomer'], $kodebarang]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Item not found in cart');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Item removed from cart',
                'data' => [
                    'kodebarang' => $kodebarang
                ]
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
