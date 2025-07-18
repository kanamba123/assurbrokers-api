<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class Temoignage
{
    public static function all()
    {
        global $pdo;

        $query = "
        SELECT 
            temoignages.*,
            users.id AS user_id,
            users.name AS user_name,
            users.email AS user_email,
            users.image_profile AS profile
        FROM 
            temoignages
        LEFT JOIN 
            users ON temoignages.user_id = users.id
        ORDER BY 
            temoignages.date_posted	 DESC
    ";

        $stmt = $pdo->prepare($query);
        $stmt->execute();

        $temoignages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($temoignage) {
            return [
                'id' => $temoignage['id'],
                'message' => $temoignage['message'],
                'note' => $temoignage['note'],
                'auteur' => $temoignage['auteur'],
                'role' => $temoignage['role'],
                'image' => $temoignage['image'],
                'date_posted' => $temoignage['date_posted'],
                'user' => $temoignage['user_id'] ? [
                    'id' => $temoignage['user_id'],
                    'name' => $temoignage['user_name'],
                    'email' => $temoignage['user_email'],
                    // 'profile' => $temoignage['profile']
                ] : null
            ];
        }, $temoignages);
    }


    public static function allTestimanyByPagination($limit = 5, $offset = 0)
    {
        global $pdo;

        $query = "
        SELECT 
            temoignages.*,
            users.id AS user_id,
            users.name AS user_name,
            users.email AS user_email,
            users.image_profile AS profile
        FROM 
            temoignages
        LEFT JOIN 
            users ON temoignages.user_id = users.id
        ORDER BY 
            temoignages.date_posted DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $temoignages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($temoignage) {
            return [
                'id' => $temoignage['id'],
                'message' => $temoignage['message'],
                'note' => $temoignage['note'],
                'auteur' => $temoignage['auteur'],
                'role' => $temoignage['role'],
                'image' => $temoignage['image'],
                'date_posted' => $temoignage['date_posted'],
                'user' => $temoignage['user_id'] ? [
                    'id' => $temoignage['user_id'],
                    'name' => $temoignage['user_name'],
                    'email' => $temoignage['user_email'],
                    'profile' => $temoignage['profile'],
                ] : null
            ];
        }, $temoignages);
    }


    public static function getById($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM temoignages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        $currentUser = JwtMiddleware::getPayload();

        $user_id = $currentUser['user_id'];
        $user_role = $currentUser['role'];

        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO temoignages (message,note,auteur,user_id,image,role)
            VALUES (?, ?, ?,?, ?,?)
        ");
        $stmt->execute([
            $data['message'],
            $data['note'],
            $data['auteur'],
            $user_id,
            $data['image'],
            $user_role
        ]);

        return ['id' => $pdo->lastInsertId()] + $data;
    }

    public static function update($id, $data)
    {
        $currentUser = JwtMiddleware::getPayload();

        $user_id = $currentUser['user_id'];
        $user_role = $currentUser['role'];

        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE temoignages 
            SET message = ?, note = ?, auteur = ?, user_id = ?, image = ?,role=? 
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['message'],
            $data['note'],
            $data['auteur'],
            $user_id,
            $data['image'],
            $user_role,
            $id,
        ]);
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM temoignages WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
