<?php
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../helpers/response.php';

class CustomerController
{
    public function index()
    {
        $clients = Customer::all();
        return json_response($clients);
    }

      public function filter()
    {
        $value = $_GET['category_id'] ?? null;
        if (!$value) {
            return json_response(['error' => 'Paramètre category_id manquant'], 400);
        }

        $categories = Customer::findByCondition('category_id', $value);
       

        return json_response($categories);
    }

    public function show($id)
    {
        $client = Customer::find($id);
        
        if (!$client) {
            return json_response(['error' => 'Client non trouvé'], 404);
        }
        
        return json_response($client);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validation des champs obligatoires
        $requiredFields = ['customer_name', 'contact_number', 'customer_address'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return json_response(['error' => "Le champ $field est obligatoire"], 400);
            }
        }

        try {
            $client = Customer::create($data);
            return json_response([
                'message' => 'Client créé avec succès',
                'client' => $client
            ], 201);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la création du client',$e], 500);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!Customer::find($id)) {
            return json_response(['error' => 'Client non trouvé'], 404);
        }
        
        try {
            $success = Customer::update($id, $data);
            
            if ($success) {
                return json_response([
                    'message' => 'Client mis à jour avec succès',
                    'client' => Customer::find($id)
                ]);
            }
            
            return json_response(['error' => 'Aucune modification effectuée'], 400);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la mise à jour du client'], 500);
        }
    }

    public function destroy($id)
    {
        if (!Customer::find($id)) {
            return json_response(['error' => 'Client non trouvé'], 404);
        }
        
        try {
            $success = Customer::delete($id);
            
            if ($success) {
                return json_response(['message' => 'Client supprimé avec succès']);
            }
            
            return json_response(['error' => 'Échec de la suppression du client'], 500);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la suppression du client'], 500);
        }
    }
}