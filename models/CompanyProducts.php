<?php
require_once __DIR__ . '/../config/database.php';

class CompanyProducts
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM company_products");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function allProductCampaniesByPagination($limit = 5, $offset = 0)
    {
        global $pdo;

        $query = "
        SELECT 
            company_products.*
        FROM 
            company_products
        ORDER BY 
            company_products.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $productsCampanies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $productsCampanies;
    }

    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM company_products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByCondition($column, $value)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM company_products WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO company_products (
            company_id, type_id, product_code, name, description,
            base_price, commission_rate, is_active, terms_conditions, garanties,logo_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");

        $stmt->execute([
            $data['company_id'],
            $data['type_id'],
            $data['product_code'],
            $data['name'],
            $data['description'] ?? null,
            $data['base_price'],
            $data['commission_rate'],
            $data['is_active'] ?? 1,
            $data['terms_conditions'] ?? null,
            $data['garanties'] ?? null,
            $data['image_path'] ?? null,
        ]);

        return self::find($pdo->lastInsertId());
    }

    public static function update($id, $data)
    {
        global $pdo;

        $fields = [];
        $params = [];

        $allowedFields = [
            'company_id',
            'type_id',
            'product_code',
            'name',
            'description',
            'base_price',
            'commission_rate',
            'is_active',
            'terms_conditions',
            'garanties'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;

        $sql = "UPDATE company_products SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM company_products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
