<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cors.php';

class Customer
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM customers");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function findByCondition($column, $value)
    {
        global $pdo;



        $query = "SELECT * FROM customer WHERE $column = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$value]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;

        // Génération d'un ID client unique
        $customer_id = 'C' . date('YmdHis') . rand(1000, 9999);

        $stmt = $pdo->prepare("INSERT INTO customer (
            category_id,customer_id, customer_name, tp_type, contact_number, 
            customer_address, email, customer_TIN, customer_trade_number, 
            vat_customer_payer, reg_date, update_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");

        $stmt->execute([
            $data['category_id'],
            $customer_id,
            $data['customer_name'],
            $data['tp_type'] ?? '1',
            $data['contact_number'],
            $data['customer_address'],
            $data['email'] ?? '',
            $data['customer_TIN'] ?? '',
            $data['customer_trade_number'] ?? '',
            $data['vat_customer_payer'] ?? '0',
            date('Y-m-d'),
            $data['update_by'] ?? '1'
        ]);

        return self::find($pdo->lastInsertId());
    }

    public static function update($id, $data)
    {
        global $pdo;

        $fields = [];
        $params = [];

        $allowedFields = [
            'category_id',
            'customer_name',
            'tp_type',
            'contact_number',
            'customer_address',
            'email',
            'customer_TIN',
            'customer_trade_number',
            'vat_customer_payer'
        ];

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

        $sql = "UPDATE customer SET " . implode(', ', $fields) . ", date_updated = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM customer WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
