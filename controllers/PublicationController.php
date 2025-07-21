<?php
require_once __DIR__ . '/../models/Publication.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../utils/FileUploader.php';

class PublicationController
{
    public static function index()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $perPage = $_GET['per_page'] ?? 10;
            
            $publications = Publication::all($perPage, ($page - 1) * $perPage);
            // return json_response([
            //     'data' => $publications,
            //     'meta' => [
            //         'page' => (int)$page,
            //         'per_page' => (int)$perPage
            //     ]
            // ]);
            return json_response($publications);
        } catch (Exception $e) {
            return json_response([
                'message' => 'Erreur lors de la récupération des publications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

      public static function getPublicationPub()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $perPage = $_GET['per_page'] ?? 10;
            
            $publications = Publication::all($perPage, ($page - 1) * $perPage);
            // return json_response([
            //     'data' => $publications,
            //     'meta' => [
            //         'page' => (int)$page,
            //         'per_page' => (int)$perPage
            //     ]
            // ]);
            return json_response($publications);
        } catch (Exception $e) {
            return json_response([
                'message' => 'Erreur lors de la récupération des publications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function show($id)
    {
        
        try {
            $publication = Publication::getById($id);
            if ($publication) {
                return json_response($publication);
            }
            return json_response(['message' => 'Publication non trouvée'], 404);
        } catch (Exception $e) {
            return json_response([
                'message' => 'Erreur lors de la récupération de la publication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function showBySlug($slug)
    {
        try {
            $publication = Publication::getBySlug($slug);
            if ($publication) {
                return json_response($publication);
            }
            return json_response(['message' => 'Publication non trouvée'], 404);
        } catch (Exception $e) {
            return json_response([
                'message' => 'Erreur lors de la récupération de la publication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function store()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation des données
            if (empty($data['titre']) || empty($data['contenu'])) {
                return json_response([
                    'message' => 'Titre et contenu sont obligatoires'
                ], 400);
            }

            // Gestion de l'image principale si nécessaire
            // (à adapter selon votre système de gestion de fichiers)
            
            $id = Publication::create($data);
            return json_response([
                'message' => 'Publication créée avec succès',
                'id' => $id
            ], 201);
        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 500;
            return json_response([
                'message' => 'Erreur lors de la création de la publication',
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }

    public static function update($id)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (Publication::update($id, $data)) {
                return json_response(['message' => 'Publication mise à jour']);
            }
            return json_response(['message' => 'Erreur lors de la mise à jour'], 400);
        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 500;
            return json_response([
                'message' => 'Erreur lors de la mise à jour de la publication',
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }

    public static function destroy($id)
    {
        try {
            if (Publication::delete($id)) {
                return json_response(['message' => 'Publication supprimée']);
            }
            return json_response(['message' => 'Erreur lors de la suppression'], 400);
        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 500;
            return json_response([
                'message' => 'Erreur lors de la suppression de la publication',
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }

public static function uploadImage()
{
    try {
        if (empty($_FILES['image'])) {
            return json_response(['message' => 'Aucune image fournie'], 400);
        }

        $uploadDir = __DIR__ . '/../public/uploads/publications/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Validation du type MIME
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fileInfo, $_FILES['image']['tmp_name']);
        finfo_close($fileInfo);

        if (!in_array($mime, $allowedTypes)) {
            return json_response(['message' => 'Type de fichier non autorisé'], 400);
        }

        // Génération du nom de fichier
        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'pub_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            // Construction de l'URL
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // Chemins sans échappement
            $imagePath = '/assurbrokers-api/public/uploads/publications/' . $filename;
            $imageUrl = $protocol . $host . $imagePath;

            // Retourne la réponse sans échappement JSON
            header('Content-Type: application/json');
            echo json_encode([
                'image_path' => $imagePath,
                'image_url' => $imageUrl,
                'filename' => $filename
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        return json_response(['message' => 'Erreur lors du téléchargement'], 500);
    } catch (Exception $e) {
        return json_response([
            'message' => 'Erreur lors du téléchargement de l\'image',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Fonction helper modifiée
function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
}