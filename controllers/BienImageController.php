<?php
require_once __DIR__ . '/../models/BienImage.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class BienImageController
{
    public static function getByBien($bien_id)
    {
        $images = BienImage::getByBienId($bien_id);
        return json_response($images);
    }

    public static function store()
    {
        $currentUser = JwtMiddleware::getPayload();
        
        // Vérifier si le bien appartient à l'utilisateur
        // (implémentez cette logique selon vos besoins)
        
        if (empty($_FILES['image'])) {
            return json_response(["message" => "Aucun fichier image envoyé"], 400);
        }

        $bien_id = $_POST['bien_id'] ?? null;
        if (!$bien_id) {
            return json_response(["message" => "bien_id est requis"], 400);
        }

        // Gestion du téléchargement du fichier
        $uploadDir = __DIR__ . '/../uploads/biens/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'bien_' . $bien_id . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $image_path = '/uploads/biens/' . $filename;
            $image_id = BienImage::create($bien_id, $image_path);
            
            return json_response([
                "message" => "Image ajoutée avec succès",
                "data" => [
                    "id" => $image_id,
                    "image_path" => $image_path
                ]
            ], 201);
        } else {
            return json_response(["message" => "Erreur lors du téléchargement de l'image"], 500);
        }
    }

    public static function destroy($id)
    {
        // Optionnel: vérifier que l'image appartient à un bien de l'utilisateur
        
        if (BienImage::delete($id)) {
            return json_response(["message" => "Image supprimée"]);
        } else {
            return json_response(["message" => "Erreur lors de la suppression"], 400);
        }
    }
}