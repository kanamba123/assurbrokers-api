<?php
require_once __DIR__ . '/../models/ReseauxSociaux.php';
require_once __DIR__ . '/../helpers/response.php';

class ReseauxSociauxController
{
    public function index()
    {
        $reseauxSociaux = ReseauxSociaux::all();
        return json_response($reseauxSociaux);
    }

    public function filter()
    {
        $type = $_GET['type'] ?? null;
        if (!$type) {
            return json_response(['error' => 'Paramètre type manquant'], 400);
        }

        $categories = ReseauxSociaux::findByCondition('type', $type);
        return json_response($categories);
    }

    public function show($id)
    {
        $reseauxSociaux = ReseauxSociaux::find($id);
        if (!$reseauxSociaux) {
            return json_response(['error' => 'Reseaux Sociaux non trouvée'], 404);
        }
        return json_response($reseauxSociaux);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $requiredFields = ['nom', 'url', 'icone'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return json_response(['error' => "Champ manquant : $field"], 400);
            }
        }

        $reseauxSociaux = ReseauxSociaux::create($data);
        return json_response(['message' => 'Reseaux Sociaux ajoutée', 'reseauxSociaux' => $reseauxSociaux]);
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!ReseauxSociaux::find($id)) {
            return json_response(['error' => 'Reseaux Sociaux non trouvée'], 404);
        }
        $reseauxSociaux = ReseauxSociaux::update($id, $data);
        return json_response(['message' => 'Reseaux Sociaux mise à jour', 'reseauxSociaux' => $reseauxSociaux]);
    }

    public function destroy($id)
    {
        if (!ReseauxSociaux::find($id)) {
            return json_response(['error' => 'Reseaux Sociaux non trouvée'], 404);
        }
        ReseauxSociaux::delete($id);
        return json_response(['message' => 'Reseaux Sociaux supprimée']);
    }
}
