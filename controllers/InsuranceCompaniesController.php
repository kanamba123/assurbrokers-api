<?php
require_once __DIR__ . '/../models/InsuranceCompanies.php';
require_once __DIR__ . '/../helpers/response.php';

class InsuranceCompaniesController
{
    public function index()
    {
        $companies = InsuranceCompanies::all();
        return json_response($companies);
    }

    public function mich(){
         $companies = InsuranceCompanies::all();
        return json_response($companies);
    }

    public function filter()
    {
        $value = $_GET['name'] ?? null;
        if (!$value) {
            return json_response(['error' => 'Paramètre name manquant'], 400);
        }

        try {
            $results = InsuranceCompanies::findByCondition('name', $value);
            return json_response($results);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la recherche'], 500);
        }
    }

    public function show($id)
    {
        $company = InsuranceCompanies::find($id);
        
        if (!$company) {
            return json_response(['error' => 'Compagnie non trouvée'], 404);
        }
        
        return json_response($company);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Champs obligatoires
        $requiredFields = ['name', 'email', 'phone'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return json_response(['error' => "Le champ $field est obligatoire"], 400);
            }
        }

        try {
            $company = InsuranceCompanies::create($data);
            return json_response([
                'message' => 'Compagnie créée avec succès',
                'company' => $company
            ], 201);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la création de la compagnie'], 500);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!InsuranceCompanies::find($id)) {
            return json_response(['error' => 'Compagnie non trouvée'], 404);
        }

        try {
            $success = InsuranceCompanies::update($id, $data);
            
            if ($success) {
                return json_response([
                    'message' => 'Compagnie mise à jour avec succès',
                    'company' => InsuranceCompanies::find($id)
                ]);
            }

            return json_response(['error' => 'Aucune modification effectuée'], 400);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la mise à jour de la compagnie'], 500);
        }
    }

    public function destroy($id)
    {
        if (!InsuranceCompanies::find($id)) {
            return json_response(['error' => 'Compagnie non trouvée'], 404);
        }

        try {
            $success = InsuranceCompanies::delete($id);

            if ($success) {
                return json_response(['message' => 'Compagnie supprimée avec succès']);
            }

            return json_response(['error' => 'Échec de la suppression de la compagnie'], 500);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la suppression'], 500);
        }
    }
}
