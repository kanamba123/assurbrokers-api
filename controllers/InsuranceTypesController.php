<?php
require_once __DIR__ . '/../models/InsuranceTypes.php';
require_once __DIR__ . '/../helpers/response.php';

class InsuranceTypesController
{
    public function index()
    {
        $types = InsuranceTypes::all();
        return json_response($types);
    }

    public function show($id)
    {
        $type = InsuranceTypes::find($id);
        if (!$type) {
            return json_response(['error' => 'Type d’assurance non trouvé'], 404);
        }

        return json_response($type);
    }

    public function filter()
    {
        $column = $_GET['column'] ?? null;
        $value = $_GET['value'] ?? null;

        if (!$column || !$value) {
            return json_response(['error' => 'Paramètres manquants'], 400);
        }

        $types = InsuranceTypes::findByCondition($column, $value);
        return json_response($types);
    }

    public function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name']) || empty($data['category_id'])) {
            return json_response(['error' => 'Champs obligatoires manquants (name, category_id)'], 400);
        }

        try {
            $type = InsuranceTypes::create($data);
            return json_response(['message' => 'Type d’assurance créé avec succès', 'type' => $type], 201);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la création'], 500);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!InsuranceTypes::find($id)) {
            return json_response(['error' => 'Type non trouvé'], 404);
        }

        try {
            $updated = InsuranceTypes::update($id, $data);

            if ($updated) {
                return json_response(['message' => 'Mise à jour réussie', 'type' => InsuranceTypes::find($id)]);
            }

            return json_response(['error' => 'Aucune modification effectuée'], 400);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy($id)
    {
        if (!InsuranceTypes::find($id)) {
            return json_response(['error' => 'Type non trouvé'], 404);
        }

        try {
            $deleted = InsuranceTypes::delete($id);

            if ($deleted) {
                return json_response(['message' => 'Suppression réussie']);
            }

            return json_response(['error' => 'Erreur lors de la suppression'], 500);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }
}
