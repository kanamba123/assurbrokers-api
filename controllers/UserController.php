<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/response.php';

class UserController
{
    public function index()
    {
        return json_response(User::all());
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return json_response(['error' => 'Utilisateur non trouvé'], 404);
        }
        return json_response($user);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $required = [ 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_response(['error' => "Le champ $field est obligatoire"], 400);
            }
        }

        try {
            $user = User::create($data);
            return json_response([
                'message' => 'Utilisateur créé avec succès',
                'user' => $user
            ], 201);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur de création de l’utilisateur', 'detail' => $e->getMessage()], 500);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!User::find($id)) {
            return json_response(['error' => 'Utilisateur non trouvé'], 404);
        }

        try {
            $success = User::update($id, $data);
            if ($success) {
                return json_response([
                    'message' => 'Utilisateur mis à jour avec succès',
                    'user' => User::find($id)
                ]);
            } else {
                return json_response(['error' => 'Aucune modification effectuée'], 400);
            }
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur lors de la mise à jour', 'detail' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        if (!User::find($id)) {
            return json_response(['error' => 'Utilisateur non trouvé'], 404);
        }

        try {
            $success = User::delete($id);
            if ($success) {
                return json_response(['message' => 'Utilisateur supprimé avec succès']);
            }
            return json_response(['error' => 'Échec de la suppression'], 500);
        } catch (Exception $e) {
            return json_response(['error' => 'Erreur de suppression', 'detail' => $e->getMessage()], 500);
        }
    }
}
