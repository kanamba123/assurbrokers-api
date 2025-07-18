<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class Demande
{
    public static function all()
    { global $pdo;
        $currentUser = JwtMiddleware::getPayload();
        $client_id = $currentUser['user_id'];

        $query = "
    SELECT 
        demandes.*,
        customer.id AS user_id,
        customer.name AS customer_name,
        customer.email AS customer_email,
        experts.secteur AS secteur,
        experts.pays AS pays,
        livrables.id AS livrable_id,
        livrables.description AS livrable_description,
        livrables.chemin AS livrable_fichier,
        livrables.date_creation AS date_creation
    FROM 
        demandes
    LEFT JOIN 
        customer ON demandes.client_id = customer.id
    LEFT JOIN 
        experts ON demandes.expert_id = experts.id
    LEFT JOIN
        livrables ON demandes.id = livrables.demande_id
    WHERE demandes.client_id= '$client_id'  
    ORDER BY 
        demandes.date_demande DESC
    ";

        $stmt = $pdo->prepare($query);
        $stmt->execute();

        $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Structure les données pour une meilleure sortie
        return array_map(function ($demande) {
            return [
                'id' => $demande['id'],
                'titre' => $demande['titre'],
                'client_id' => $demande['client_id'],
                'description' => $demande['description'],
                'statut' => $demande['statut'],
                'date_demande' => $demande['date_demande'],
                'document_joint' => $demande['document_joint'],
                'validation_admin' => $demande['validation_admin'],
                'statut_expert' => $demande['statut_expert'],
                'livrable_path' => $demande['livrable_path'],
                'date_validation' => $demande['date_validation'],
                'has_livrable' => !empty($demande['livrable_id']),
                'customer' => $demande['user_id'] ? [
                    'id' => $demande['user_id'],
                    'name' => $demande['customer_name'],
                    'email' => $demande['customer_email']
                ] : null,
                'expert' => $demande['expert_id'] ? [
                    'secteur' => $demande['secteur'],
                    'pays' => $demande['pays']
                ] : null,
                'livrable' => !empty($demande['livrable_id']) ? [
                    'id' => $demande['livrable_id'],
                    'description' => $demande['livrable_description'],
                    'chemin' => $demande['livrable_fichier'],
                    'date_creation' => $demande['date_creation']
                ] : null
            ];
        }, $demandes);
    }

    public static function getById($id)
    {
        global $pdo;
        $query = "
            SELECT 
                demandes.*,
                customer.name AS customer_name,
                customer.email AS customer_email,
                experts.secteur AS secteur,
                experts.pays AS pays
            FROM 
                demandes
            LEFT JOIN 
                customer ON demandes.client_id = customer.id
            LEFT JOIN 
                experts ON demandes.expert_id = experts.id
            WHERE 
                demandes.id = ?
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($demande) {
            return [
                'id' => $demande['id'],
                'titre' => $demande['titre'],
                'description' => $demande['description'],
                'statut' => $demande['statut'],
                'date_demande' => $demande['date_demande'],
                'document_joint' => $demande['document_joint'],
                'validation_admin' => $demande['validation_admin'],
                'statut_expert' => $demande['statut_expert'],
                'livrable_path' => $demande['livrable_path'],
                'date_validation' => $demande['date_validation'],
                'customer' => $demande['client_id'] ? [
                    'id' => $demande['client_id'],
                    'name' => $demande['customer_name'],
                    'email' => $demande['customer_email']
                ] : null,
                'expert' => $demande['expert_id'] ? [
                    'id' => $demande['expert_id'],
                    'secteur' => $demande['secteur'],
                    'pays' => $demande['pays']
                ] : null
            ];
        }
        return null;
    }

    public static function create($data)
    {
        $currentUser = JwtMiddleware::getPayload();
        $client_id = $currentUser['user_id'];

        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO demandes 
                (client_id, expert_id, titre, description, statut, document_joint)
            VALUES 
                (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $client_id,
            $data['expert_id'],
            $data['titre'],
            $data['description'],
            $data['statut'] ?? 'envoyée',
            $data['document_joint'] ?? null
        ]);

        return ['id' => $pdo->lastInsertId()] + $data;
    }

    public static function update($id, $data)
    {
        global $pdo;
        $fields = [];
        $params = [];

        // Construire dynamiquement la requête en fonction des champs fournis
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $query = "UPDATE demandes SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM demandes WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
