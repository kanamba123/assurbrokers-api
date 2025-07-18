<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cors.php';

class InsuranceCompanies
{
    public static function all()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM insurance_companies");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM insurance_companies WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByCondition($column, $value)
    {
        global $pdo;
        $allowedColumns = [
            'name', 'NIF', 'Registre_com', 'email', 'phone', 'city', 'country', 'contact_person', 'is_active'
        ];

        if (!in_array($column, $allowedColumns)) {
            throw new InvalidArgumentException("Colonne non autorisée.");
        }

        $stmt = $pdo->prepare("SELECT * FROM insurance_companies WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO insurance_companies (
                name, NIF, Registre_com, email, phone, address, postal_code, city, country,
                contact_person, contact_email, contact_phone, commission_rate, payment_terms,
                contract_start_date, contract_end_date, is_active, notes, logo_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['NIF'] ?? null,
            $data['Registre_com'] ?? null,
            $data['email'],
            $data['phone'],
            $data['address'] ?? null,
            $data['postal_code'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? 'France',
            $data['contact_person'] ?? null,
            $data['contact_email'] ?? null,
            $data['contact_phone'] ?? null,
            $data['commission_rate'] ?? '15.00',
            $data['payment_terms'] ?? null,
            $data['contract_start_date'] ?? null,
            $data['contract_end_date'] ?? null,
            $data['is_active'] ?? 1,
            $data['notes'] ?? null,
            $data['logo_path'] ?? null
        ]);

        return self::find($pdo->lastInsertId());
    }

    public static function update($id, $data)
    {
        global $pdo;

        $fields = [];
        $params = [];

        $allowedFields = [
            'name', 'NIF', 'Registre_com', 'email', 'phone', 'address', 'postal_code',
            'city', 'country', 'contact_person', 'contact_email', 'contact_phone',
            'commission_rate', 'payment_terms', 'contract_start_date', 'contract_end_date',
            'is_active', 'notes', 'logo_path'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $sql = "UPDATE insurance_companies SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM insurance_companies WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
