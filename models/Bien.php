<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class Bien
{
    public static function all()
    {
        global $pdo;

        $query = "
        SELECT 
            biens.*,
            users.id AS user_id,
            users.name AS user_name,
            users.email AS user_email,
            categories.id AS categorie_id,
            categories.nom AS categorie_nom
        FROM 
            biens
        LEFT JOIN 
            users ON biens.user_id = users.id
        LEFT JOIN
            categories ON biens.categorie_id = categories.id
        WHERE
            biens.actif = TRUE
        ORDER BY 
            biens.date_ajout DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute();

        $biens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($bien) {
            return [
                'id' => $bien['id'],
                'titre' => $bien['titre'],
                'description' => $bien['description'],
                'prix' => $bien['prix'],
                'localisation' => $bien['localisation'],
                'surface' => $bien['surface'],
                'statut' => $bien['statut'],
                'date_ajout' => $bien['date_ajout'],
                'user' => $bien['user_id'] ? [
                    'id' => $bien['user_id'],
                    'name' => $bien['user_name'],
                    'email' => $bien['user_email']
                ] : null,
                'categorie' => $bien['categorie_id'] ? [
                    'id' => $bien['categorie_id'],
                    'nom' => $bien['categorie_nom']
                ] : null
            ];
        }, $biens);
    }

    public static function getById($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT 
                biens.*,
                users.id AS user_id,
                users.name AS user_name,
                users.email AS user_email,
                categories.id AS categorie_id,
                categories.nom AS categorie_nom
            FROM 
                biens
            LEFT JOIN 
                users ON biens.user_id = users.id
            LEFT JOIN
                categories ON biens.categorie_id = categories.id
            WHERE 
                biens.id = ? AND biens.actif = TRUE
        ");
        $stmt->execute([$id]);
        $bien = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bien) {
            $bien['images'] = BienImage::getByBienId($id);
        }

        return $bien;
    }
    public static function create($data)
    {
        $currentUser = JwtMiddleware::getPayload();
        $user_id = $currentUser['user_id'];

        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO biens 
            (titre, description, prix, categorie_id, user_id, localisation, surface, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['titre'],
            $data['description'],
            $data['prix'],
            $data['categorie_id'],
            $user_id,
            $data['localisation'],
            $data['surface'],
            $data['statut'] ?? 'disponible'
        ]);

        return ['id' => $pdo->lastInsertId()] + $data;
    }

    public static function update($id, $data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE biens 
            SET 
                titre = ?, 
                description = ?, 
                prix = ?,
                categorie_id = ?,
                localisation = ?,
                surface = ?,
                statut = ?
            WHERE id = ? AND actif = TRUE
        ");
        return $stmt->execute([
            $data['titre'],
            $data['description'],
            $data['prix'],
            $data['categorie_id'],
            $data['localisation'],
            $data['surface'],
            $data['statut'] ?? 'disponible',
            $id
        ]);
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE biens 
            SET actif = FALSE 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public static function getByUser($user_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM biens 
            WHERE user_id = ? AND actif = TRUE
            ORDER BY date_ajout DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
