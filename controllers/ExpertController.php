<?php
require_once __DIR__ . '/../models/Expert.php';
require_once __DIR__ . '/../helpers/response.php';

class ExpertController
{
    public function index()
    {
        $clients = Expert::all();
        return json_response($clients);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['secteur']) ||!isset($data['pays']) ||  !isset($data['description'])) {
            return json_response(['error' => 'Champs manquants'], 400);
        }

        $client = Expert::create($data);
        return json_response(['message' => 'Expert ajouté', 'client' => $client]);
    }
}
