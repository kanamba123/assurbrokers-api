<?php
require_once __DIR__ . '/../config/database.php';

class CategoryClient
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM categorieclient");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM categorieclient WHERE 	id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO categorieclient (nom, description, image_uri)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['description'],
            $data['image_uri'],
        ]);

        return ['Id_Categorie_Client' => $pdo->lastInsertId()] + $data;
    }

    public static function update($id, $data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE categorieclient 
            SET nom = ?, description = ?, image_uri = ?
            WHERE Id_Categorie_Client = ?
        ");
        return $stmt->execute([
            $data['nom'],
            $data['description'],
            $data['image_uri'],
            $id,
        ]);
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM categorieclient WHERE Id_Categorie_Client = ?");
        return $stmt->execute([$id]);
    }
}
