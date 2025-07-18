<?php
require_once __DIR__ . '/../models/RefreshToken.php';

class RefreshTokenHandler {
    public static function generateRefreshToken($userId) {
        $refreshToken = bin2hex(random_bytes(64));
        $expiresAt = time() + (60 * 60 * 24 * 7); // 7 jours

        RefreshToken::store($refreshToken, $userId, $expiresAt);

        return $refreshToken;
    }

    public static function validateRefreshToken($refreshToken) {
        $record = RefreshToken::find($refreshToken);
        if (!$record || strtotime($record['expires_at']) < time()) {
            if ($record) {
                RefreshToken::delete($refreshToken);
            }
            return false;
        }
        return $record['user_id'];
    }

    public static function revokeRefreshToken($refreshToken) {
        RefreshToken::delete($refreshToken);
    }
}
