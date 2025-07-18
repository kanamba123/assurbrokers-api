<?php
require_once __DIR__ . '/../config/database.php';

class InsuranceTypes
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM insurance_types");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM insurance_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByCondition($column, $value)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM insurance_types WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO insurance_types (
            name, description, category_id, company_id, is_active, garanties
        ) VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'],
            $data['company_id'] ?? null,
            $data['is_active'] ?? 1,
            $data['garanties'] ?? null,
        ]);

        return self::find($pdo->lastInsertId());
    }

    public static function update($id, $data)
    {
        global $pdo;

        $fields = [];
        $params = [];

        $allowedFields = ['name', 'description', 'category_id', 'company_id', 'is_active', 'garanties'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;

        $sql = "UPDATE insurance_types SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM insurance_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
