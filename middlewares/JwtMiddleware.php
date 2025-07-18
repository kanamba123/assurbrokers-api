<?php
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../helpers/response.php';

class JwtMiddleware {
    private static $payload;

    public static function handle() {
        try {
            $headers = getallheaders();
            
            // Debug: Enregistre les headers pour le débogage
            error_log('Headers received: ' . print_r($headers, true));
            
            if (!isset($headers['Authorization'])) {
                throw new Exception('Token manquant', 401);
            }

            $authHeader = $headers['Authorization'];
            
            // Vérifie le format du header
            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                throw new Exception('Format de token invalide. Utilisez: Bearer <token>', 401);
            }

            $token = $matches[1];
            self::$payload = JwtHandler::validateToken($token);
            
            if (!self::$payload) {
                throw new Exception('Token invalide ou expiré', 401);
            }

            // Stocke le payload dans une variable globale accessible
            $GLOBALS['jwt_payload'] = self::$payload;
            
            // Debug: Enregistre le payload décodé
            error_log('JWT Payload: ' . print_r(self::$payload, true));

        } catch (Exception $e) {
            error_log('JWT Error: ' . $e->getMessage());
            json_response([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ], $e->getCode());
            exit;
        }
    }

    public static function getPayload() {
        return self::$payload;
    }
}