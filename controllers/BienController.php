<?php
require_once __DIR__ . '/../models/Bien.php';
require_once __DIR__ . '/../helpers/response.php';

class BienController
{
    public static function index()
    {
        $biens = Bien::all();
        return json_response($biens);
    }

    public static function show($id)
    {
        $bien = Bien::getById($id);
        if ($bien) {
            return json_response($bien);
        } else {
            return json_response(["message" => "Bien non trouvé ou désactivé"], 404);
        }
    }

    public static function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validation des données
        if (empty($data['titre']) || empty($data['description']) || empty($data['prix'])) {
            return json_response(["message" => "Titre, description et prix sont obligatoires"], 400);
        }

        $newBien = Bien::create($data);
        return json_response([
            "message" => "Bien créé avec succès",
            "data" => $newBien
        ], 201);
    }

    public static function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (Bien::update($id, $data)) {
            return json_response(["message" => "Bien mis à jour"]);
        } else {
            return json_response(["message" => "Erreur lors de la mise à jour"], 400);
        }
    }

    public static function destroy($id)
    {
        if (Bien::delete($id)) {
            return json_response(["message" => "Bien désactivé"]);
        } else {
            return json_response(["message" => "Erreur lors de la désactivation"], 400);
        }
    }

    public static function byUser()
    {
        $currentUser = JwtMiddleware::getPayload();
        $user_id = $currentUser['user_id'];

        $biens = Bien::getByUser($user_id);
        return json_response($biens);
    }
}