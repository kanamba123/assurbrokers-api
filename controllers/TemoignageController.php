<?php
require_once __DIR__ . '/../models/Temoignage.php';
require_once __DIR__ . '/../helpers/response.php';

class TemoignageController
{
    public static function index()
    {
        $temoignages = Temoignage::all();
        return json_response($temoignages);
    }

   public static function getTemByPagination()
{
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Récupérer les témoignages paginés
    $temoignages = Temoignage::allTestimanyByPagination($limit, $offset);

    // Récupérer le nombre total de témoignages pour calculer hasMore
    global $pdo;
    $countQuery = "SELECT COUNT(*) FROM temoignages";
    $totalStmt = $pdo->query($countQuery);
    $totalItems = (int) $totalStmt->fetchColumn();

    $hasMore = $totalItems > $page * $limit;

    // Retourner la structure attendue par React Query
    $response = [
        'items' => $temoignages,
        'currentPage' => $page,
        'limit' => $limit,
        'totalItems' => $totalItems,
        'hasMore' => $hasMore,
    ];

    return json_response($response);
}


    public static function show($id)
    {
        $temoignage = Temoignage::getById($id);
        if ($temoignage) {
            echo json_encode($temoignage);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Témoignage non trouvé"]);
        }
    }

    public static function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (Temoignage::create($data)) {
            return json_response(["message" => "Témoignage ajouté avec succès", $data], 201);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur lors de l'ajout"]);
        }
    }

    public static function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (Temoignage::update($id, $data)) {
            return json_response(["message" => "Témoignage mis à jour"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Erreur de mise à jour"]);
        }
    }

    public static function destroy($id)
    {
        if (!Temoignage::getById($id)) {
            return json_response(['error' => 'Reseaux Sociaux non trouvée'], 404);
        }
        Temoignage::delete($id);
        return json_response(['message' => 'Temoignage supprimée']);
    }


    public static function uploadImage()
    {
        try {
            if (empty($_FILES['image'])) {
                return json_response(['message' => 'Aucune image fournie'], 400);
            }

            $uploadDir = __DIR__ . '/../public/uploads/temoignages/';
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
            $filename = 'tem_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                // Construction de l'URL
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

                // Chemins sans échappement
                $imagePath = '/gisaanalytica-api/public/uploads/temoignages/' . $filename;
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
}
