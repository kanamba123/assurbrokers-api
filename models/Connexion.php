<?php

class Connexion {
    public static function log($userId) {
        global $pdo;

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        $sql = "INSERT INTO connexions (user_id, ip, user_agent) VALUES (:user_id, :ip, :user_agent)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':ip' => $ip,
            ':user_agent' => $userAgent
        ]);
    }

    public static function all() {
    global $pdo;
    $stmt = $pdo->query("SELECT c.id, c.user_id, c.ip, c.user_agent, c.date_connexion,c.date_deconnexion, u.email, u.name 
                         FROM connexions c 
                         LEFT JOIN users u ON c.user_id = u.id 
                         ORDER BY c.date_connexion DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public static function deleteById($id) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM connexions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public static function deleteOlderThanDays($days) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM connexions WHERE date_connexion < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
