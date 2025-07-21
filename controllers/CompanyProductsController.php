<?php
require_once __DIR__ . '/../models/CompanyProducts.php';
require_once __DIR__ . '/../helpers/response.php';

class CompanyProductsController
{
    public function index()
    {
        $products = CompanyProducts::all();
        return json_response($products);
    }

    public static function getTemByPagination()
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 3;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $offset = ($page - 1) * $limit;

        // Récupérer les témoignages paginés
        $companyProducts = CompanyProducts::allProductCampaniesByPagination($limit, $offset);

        // Récupérer le nombre total de témoignages pour calculer hasMore
        global $pdo;
        $countQuery = "SELECT COUNT(*) FROM company_products";
        $totalStmt = $pdo->query($countQuery);
        $totalItems = (int) $totalStmt->fetchColumn();

        $hasMore = $totalItems > $page * $limit;

        // Retourner la structure attendue par React Query
        $response = [
            'items' => $companyProducts,
            'currentPage' => $page,
            'limit' => $limit,
            'totalItems' => $totalItems,
            'hasMore' => $hasMore,
        ];

        return json_response($companyProducts);
    }

    public function show($id)
    {
        $product = CompanyProducts::find($id);
        if (!$product) {
            return json_response(['error' => 'Produit non trouvé'], 404);
        }

        return json_response($product);
    }

    public function filter()
    {
        $column = $_GET['column'] ?? null;
        $value = $_GET['value'] ?? null;

        if (!$column || !$value) {
            return json_response(['error' => 'Paramètres manquants'], 400);
        }

        $products = CompanyProducts::findByCondition($column, $value);
        return json_response($products);
    }

    public function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $required = ['company_id', 'type_id', 'product_code', 'name', 'base_price', 'commission_rate'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_response(['error' => "Le champ $field est requis"], 400);
            }
        }

        try {
            $product = CompanyProducts::create($data);
            return json_response(['message' => 'Produit créé avec succès', 'product' => $product], 201);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la création du produit', $e], 500);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!CompanyProducts::find($id)) {
            return json_response(['error' => 'Produit non trouvé'], 404);
        }

        try {
            $updated = CompanyProducts::update($id, $data);

            if ($updated) {
                return json_response([
                    'message' => 'Produit mis à jour avec succès',
                    'product' => CompanyProducts::find($id)
                ]);
            }

            return json_response(['error' => 'Aucune modification effectuée'], 400);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy($id)
    {
        if (!CompanyProducts::find($id)) {
            return json_response(['error' => 'Produit non trouvé'], 404);
        }

        try {
            $deleted = CompanyProducts::delete($id);

            if ($deleted) {
                return json_response(['message' => 'Produit supprimé avec succès']);
            }

            return json_response(['error' => 'Erreur lors de la suppression'], 500);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }

    public static function uploadImage()
    {
        try {
            if (empty($_FILES['image'])) {
                return json_response(['message' => 'Aucune image fournie'], 400);
            }

            $uploadDir = __DIR__ . '/../public/uploads/company_products/';
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
                $imagePath = '/assurbrokers-api/public/uploads/company_products/' . $filename;
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
