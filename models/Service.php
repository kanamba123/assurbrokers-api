<?php
require_once __DIR__ . '/../config/database.php';

class Service
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM services");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findByCondition($column, $value)
    {
        global $pdo;



        $query = "SELECT * FROM services WHERE $column = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$value]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

     public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM services  WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }



    public static function create($data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO services (titre, description,contenu,image, actif,category_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['titre'],
            $data['description'],
            $data['contenu'],
            $data['image'],
            $data['actif'],
            $data['category_id'],
        ]);

        return ['id' => $pdo->lastInsertId()] + $data;
    }

    public static function update($id,$data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE services set titre=?, description=?,contenu=?,image=? WHERE id = ?
        ");
        $stmt->execute([
            $data['titre'],
            $data['description'],
            $data['contenu'],
            $data['image'],
            $id
        ]);

        return ['id' => $pdo->lastInsertId()] + $data;
    }


      public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM services  WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
