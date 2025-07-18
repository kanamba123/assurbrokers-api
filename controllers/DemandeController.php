<?php
require_once __DIR__ . '/../models/Demande.php';
require_once __DIR__ . '/../helpers/response.php';

class DemandeController
{
    public static function index()
    {
        $demandes = Demande::all();
        return json_response($demandes);
    }

    public static function show($id)
    {
        $demande = Demande::getById($id);
        if ($demande) {
            echo json_encode($demande);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Témoignage non trouvé"]);
        }
    }

    public static function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (Demande::create($data)) {
            return json_response(["message" => "Demande ajouté avec succès", $data], 201);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur lors de l'ajout"]);
        }
    }

    public static function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (Demande::update($id, $data)) {
            echo json_encode(["message" => "Demande mis à jour"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur de mise à jour"]);
        }
    }

    public static function destroy($id)
    {
        if (Demande::delete($id)) {
            echo json_encode(["message" => "Demande supprimé"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur lors de la suppression"]);
        }
    }
}
