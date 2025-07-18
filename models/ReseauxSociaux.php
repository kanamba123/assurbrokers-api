<?php
require_once __DIR__ . '/../config/database.php';

class ReseauxSociaux
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM reseaux_sociaux ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Trouve les catégories par type avec une approche plus flexible
    public static function findByCondition($column, $value)
    {
        global $pdo;
        
        if (!in_array($column, ['nom', 'url', 'icone'])) {
            throw new InvalidArgumentException("Colonne de recherche non autorisée");
        }
        
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        // return array_map(function($item) {
        //     return new self($item);
        // }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM reseaux_sociaux  WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO reseaux_sociaux  (nom, url , icone )
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['url'],
            $data['icone'],
        ]);
        return ['id' => $pdo->lastInsertId()] + $data;
    }

    public static function update($id, $data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE reseaux_sociaux 
            SET nom = ?, url = ?, icone = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['nom'],
            $data['url'],
            $data['icone'],
            $id
        ]);
        return self::find($id);
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM reseaux_sociaux  WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
