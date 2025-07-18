<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/utils/JwtHandler.php'; // Chemin vers votre classe JwtHandler

function getBearerToken() {
    $headers = getallheaders();
    
    // Support multiple ways to get the token
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (isset($requestHeaders['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $requestHeaders['Authorization'], $matches)) {
                return $matches[1];
            }
        }
    }
    
    return null;
}

function authenticateUser() {
    $token = getBearerToken();
  
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Token manquant', 'code' => 'missing_token']);
        exit;
    }

    $userData = JwtHandler::validateToken($token);
    
    if (!$userData) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalide ou expiré', 'code' => 'invalid_token']);
        exit;
    }

    return $userData;
}

// Fonction pour générer un token (exemple d'utilisation)
function generateAuthToken($userId, $userRole) {
    $payload = [
        'user_id' => $userId,
        'role' => $userRole,
        'iat' => time() // issued at
    ];
    
    return JwtHandler::generateToken($payload);
}
?>