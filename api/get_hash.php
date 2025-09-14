<?php
header('Content-Type: application/json');

// Function to generate PHP password hash
function getPasswordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify password hash
function verifyPasswordHash($password, $hash) {
    return password_verify($password, $hash);
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // GET request - generate hash for provided password
        $password = $_GET['password'] ?? '';
        
        if (empty($password)) {
            throw new Exception('Password parameter is required');
        }
        
        $hash = getPasswordHash($password);
        
        echo json_encode([
            'hash' => $hash
        ]);
        
    } elseif ($method === 'POST') {
        // POST request - handle different actions
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'hash':
                // Generate hash
                $password = $input['password'] ?? '';
                
                if (empty($password)) {
                    throw new Exception('Password is required');
                }
                
                $hash = getPasswordHash($password);
                
                echo json_encode([
                    'hash' => $hash
                ]);
                break;
                
            case 'verify':
                // Verify password
                $password = $input['password'] ?? '';
                $hash = $input['hash'] ?? '';
                
                if (empty($password) || empty($hash)) {
                    throw new Exception('Password and hash are required');
                }
                
                $isValid = verifyPasswordHash($password, $hash);
                
                echo json_encode([
                    'valid' => $isValid
                ]);
                break;
                
            default:
                throw new Exception('Invalid action. Supported actions: hash, verify');
        }
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

?>
