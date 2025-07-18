<?php
require_once __DIR__ . '/../models/MessageContact.php';
require_once __DIR__ . '/../helpers/response.php';

class MessageContactController
{
    // 🔹 Liste tous les messages de contact
    public function index()
    {
        $messages = MessageContact::all();
        return json_response($messages);
    }

    // 🔹 Affiche un seul message de contact par ID
    public function show($id)
    {
        $message = MessageContact::find($id);
        if ($message) {
            return json_response($message);
        } else {
            return json_response(['error' => 'Message introuvable'], 404);
        }
    }

    // 🔹 Crée un nouveau message de contact
    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $required = ['nom', 'email', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_response(['error' => "Champ '$field' manquant"], 400);
            }
        }

        $message = MessageContact::create($data);
        return json_response(['message' => 'Message reçu', 'data' => $message], 201);
    }

    // 🔹 Supprime un message par ID
    public function destroy($id)
    {
        $deleted = MessageContact::delete($id);
        if ($deleted) {
            return json_response(['message' => 'Message supprimé']);
        } else {
            return json_response(['error' => 'Échec de la suppression'], 500);
        }
    }
}
