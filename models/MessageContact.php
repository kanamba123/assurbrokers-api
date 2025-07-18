<?php
require_once __DIR__ . '/../config/database.php';

class MessageContact
{
    // ✅ Récupérer tous les messages de contact
    public static function all()
    {
        global $pdo;
        
        $stmt = $pdo->query("
            SELECT * 
            FROM messages_contact
            ORDER BY date_envoi DESC
        ");
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($msg) {
            return [
                'id' => $msg['id'],
                'nom' => $msg['nom'],
                'email' => $msg['email'],
                'message' => $msg['message'],
                'date_envoi' => $msg['date_envoi']
            ];
        }, $messages);
    }

    // ✅ Récupérer un message spécifique par ID
    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM messages_contact WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ✅ Créer un nouveau message
    public static function create($data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO messages_contact (nom, email, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['email'],
            $data['message']
        ]);

        return ['id' => $pdo->lastInsertId()] + $data;
    }

    // ✅ Supprimer un message
    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM messages_contact WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
