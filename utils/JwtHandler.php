<?php
require_once __DIR__ . '/../config/config.php';

class JwtHandler {
    public static function generateToken($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + JWT_EXPIRE;
        $payload = json_encode($payload);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validateToken($token) {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return false;
        }

        list($header, $payload, $signatureProvided) = $tokenParts;

        $headerDecoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $header));
        $payloadDecoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload));

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($headerDecoded));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadDecoded));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if ($base64UrlSignature === $signatureProvided) {
            $payloadData = json_decode($payloadDecoded, true);
            if (isset($payloadData['exp']) && $payloadData['exp'] >= time()) {
                return $payloadData;
            }
        }

        return false;
    }
}