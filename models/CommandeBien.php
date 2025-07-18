<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class CommandeBien
{
    public static function all()
    {
        global $pdo;

        $query = "
        SELECT 
            commandes_biens.*,
            biens.titre AS bien_titre,
            biens.prix AS bien_prix,
            biens.localisation AS bien_localisation,
            users_acheteur.id AS acheteur_id,
            users_acheteur.name AS acheteur_name,
            users_vendeur.id AS vendeur_id,
            users_vendeur.name AS vendeur_name
        FROM 
            commandes_biens
        JOIN 
            biens ON commandes_biens.bien_id = biens.id
        JOIN 
            users AS users_acheteur ON commandes_biens.acheteur_id = users_acheteur.id
        JOIN 
            users AS users_vendeur ON biens.user_id = users_vendeur.id
        ORDER BY 
            commandes_biens.date_commande DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM commandes_biens 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($bien_id, $acheteur_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO commandes_biens (bien_id, acheteur_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$bien_id, $acheteur_id]);
        return $pdo->lastInsertId();
    }

    public static function updateStatut($id, $statut)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE commandes_biens 
            SET statut = ?
            WHERE id = ?
        ");
        return $stmt->execute([$statut, $id]);
    }

    public static function getByAcheteur($acheteur_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM commandes_biens 
            WHERE acheteur_id = ?
            ORDER BY date_commande DESC
        ");
        $stmt->execute([$acheteur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByVendeur($vendeur_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT cb.* FROM commandes_biens cb
            JOIN biens b ON cb.bien_id = b.id
            WHERE b.user_id = ?
            ORDER BY cb.date_commande DESC
        ");
        $stmt->execute([$vendeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}