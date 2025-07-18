<?php
require_once __DIR__ . '/../config/Database.php';

class RefreshToken {
    // Enregistre un token en BDD
    public static function store($token, $userId, $expiresAt) {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO refresh_tokens (token, user_id, expires_at) VALUES (?, ?, ?)");
        return $stmt->execute([$token, $userId, date('Y-m-d H:i:s', $expiresAt)]);
    }

    // Récupère un token par son token string
    public static function find($token) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM refresh_tokens WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Supprime un token par son token string
    public static function delete($token) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM refresh_tokens WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    // Supprime les tokens expirés
    public static function deleteExpired() {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM refresh_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    // Récupère tous les tokens
    public static function all() {
        global $pdo;
        $stmt = $pdo->query("SELECT rt.*, u.email, u.name FROM refresh_tokens rt LEFT JOIN users u ON rt.user_id = u.id ORDER BY expires_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
