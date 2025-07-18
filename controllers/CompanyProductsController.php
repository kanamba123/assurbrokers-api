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
            return json_response(['error' => 'Erreur lors de la création du produit'], 500);
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
}
