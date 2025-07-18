<?php
require_once __DIR__ . '/../config/database.php';

class BienImage
{
    public static function getByBienId($bien_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM biens_images 
            WHERE bien_id = ?
            ORDER BY id
        ");
        $stmt->execute([$bien_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($bien_id, $image_path)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO biens_images (bien_id, image_path)
            VALUES (?, ?)
        ");
        $stmt->execute([$bien_id, $image_path]);
        return $pdo->lastInsertId();
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            DELETE FROM biens_images 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public static function deleteAllForBien($bien_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            DELETE FROM biens_images 
            WHERE bien_id = ?
        ");
        return $stmt->execute([$bien_id]);
    }
}