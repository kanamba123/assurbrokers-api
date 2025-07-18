<?php
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../helpers/response.php';

class CategoryController
{
    public function index()
    {
        $category = Category::all();
        return json_response($category);
    }

    public function filter()
    {
        $type = $_GET['type'] ?? null;
        if (!$type) {
            return json_response(['error' => 'Paramètre type manquant'], 400);
        }

        $categories = Category::findByCondition('type', $type);
        return json_response($categories);
    }

    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return json_response(['error' => 'Catégorie non trouvée'], 404);
        }
        return json_response($category);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $requiredFields = ['nom', 'slug', 'description'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return json_response(['error' => "Champ manquant : $field"], 400);
            }
        }

        $category = Category::create($data);
        return json_response(['message' => 'Catégorie ajoutée', 'category' => $category]);
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!Category::find($id)) {
            return json_response(['error' => 'Catégorie non trouvée'], 404);
        }
        $category = Category::update($id, $data);
        return json_response(['message' => 'Catégorie mise à jour', 'category' => $category]);
    }

    public function destroy($id)
    {
        if (!Category::find($id)) {
            return json_response(['error' => 'Catégorie non trouvée'], 404);
        }
        Category::delete($id);
        return json_response(['message' => 'Catégorie supprimée']);
    }
}
