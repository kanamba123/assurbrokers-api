<?php
require_once __DIR__ . '/../models/Connexion.php';
require_once __DIR__ . '/../helpers/response.php';

class ConnexionController
{
    // Liste toutes les connexions (optionnel pagination)
    public function index()
    {
        try {
            $connexions = Connexion::all();
            return json_response(['connexions' => $connexions]);
        } catch (Exception $e) {
            error_log('Erreur ConnexionController index: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }

    // Supprime une connexion par son ID
    public function delete($id)
    {
        try {
            $deleted = Connexion::deleteById($id);
            if ($deleted) {
                return json_response(['message' => 'Connexion supprimée']);
            } else {
                return json_response(['error' => 'Connexion non trouvée'], 404);
            }
        } catch (Exception $e) {
            error_log('Erreur ConnexionController delete: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }

    // Nettoyer les connexions plus vieilles que $days jours (ex: 30)
    public function cleanOlderThan($days = 30)
    {
        try {
            $count = Connexion::deleteOlderThanDays($days);
            return json_response(['message' => "$count connexions supprimées"]);
        } catch (Exception $e) {
            error_log('Erreur ConnexionController cleanOlderThan: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }
}
