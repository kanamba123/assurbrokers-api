<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cors.php';

class Expert
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM experts");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO experts (user_id,secteur,pays,langues_parlees,description,disponible) VALUES (?, ?,?,?,?,?)");
        $stmt->execute([$data['user_id'], $data['secteur'], $data['pays'], $data['langues_parlees'], $data['description'], $data['disponible']]);
        return ['id' => $pdo->lastInsertId()] + $data;
    }
}
