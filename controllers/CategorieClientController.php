<?php
require_once __DIR__ . '/../models/CategoryClient.php';
require_once __DIR__ . '/../helpers/response.php';

class CategorieClientController
{
    public static function index()
    {
        $categorieClient = CategoryClient::all();
        return json_response($categorieClient);
    }

    public static function show($id)
    {
        $categorieClient = CategoryClient::getById($id);
        if ($categorieClient) {
            return json_response($categorieClient);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Category Client non trouvé"]);
        }
    }

    public static function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (CategoryClient::create($data)) {
            return json_response(["message" => "Category Client ajouté avec succès", $data], 201);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur lors de l'ajout"]);
        }
    }

    public static function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (CategoryClient::update($id, $data)) {
            echo json_encode(["message" => "Category Client mis à jour"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur de mise à jour"]);
        }
    }

    public static function destroy($id)
    {
        if (CategoryClient::delete($id)) {
            echo json_encode(["message" => "Category Client supprimé"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur lors de la suppression"]);
        }
    }
}
