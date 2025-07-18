<?php
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../helpers/response.php';

class ServiceController
{
    public function index()
    {
        $services = Service::all();
        return json_response($services);
    }

    public function show($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return json_response(['error' => 'Service non trouvé'], 404);
        }
        return json_response($service);
    }

    public function filter()
    {
        $value = $_GET['category_id'] ?? null;
        if (!$value) {
            return json_response(['error' => 'Paramètre category_id manquant'], 400);
        }

        $services = Service::findByCondition('category_id', $value);
        return json_response($services);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validation des données
        $errors = $this->validateServiceData($data);
        if (!empty($errors)) {
            return json_response(['errors' => $errors], 400);
        }

        try {
            $service = Service::create($data);
            return json_response([
                'message' => 'Service créé avec succès',
                'service' => $service
            ], 201);
        } catch (Exception $e) {
            return json_response([
                'error' => 'Erreur lors de la création du service',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return json_response(['error' => 'Service non trouvé'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Validation des données
        $errors = $this->validateServiceData($data, false);
        if (!empty($errors)) {
            return json_response(['errors' => $errors], 400);
        }

        try {
            $updatedService = Service::update($id, $data);
            return json_response([
                'message' => 'Service mis à jour avec succès',
                'service' => $updatedService
            ]);
        } catch (Exception $e) {
            return json_response([
                'error' => 'Erreur lors de la mise à jour du service',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return json_response(['error' => 'Service non trouvé'], 404);
        }

        try {
            Service::delete($id);
            return json_response(['message' => 'Service supprimé avec succès']);
        } catch (Exception $e) {
            return json_response([
                'error' => 'Erreur lors de la suppression du service',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valide les données du service
     * 
     * @param array $data Données à valider
     * @param bool $isCreate Mode création (vérifie tous les champs requis)
     * @return array Tableau des erreurs
     */
    private function validateServiceData($data, $isCreate = true)
    {
        $errors = [];
        
        $requiredFields = ['titre', 'description', 'contenu'];
        foreach ($requiredFields as $field) {
            if ($isCreate && !isset($data[$field])) {
                $errors[] = "Le champ $field est requis";
            } elseif (isset($data[$field]) && empty(trim($data[$field]))) {
                $errors[] = "Le champ $field ne peut pas être vide";
            }
        }

        // Validation supplémentaire pour le champ actif
        // if (isset($data['actif']) && !is_bool($data['actif'])) {
        //     $errors[] = "Le champ actif doit être un booléen";
        // }

        return $errors;
    }
}