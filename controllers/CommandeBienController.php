<?php
require_once __DIR__ . '/../models/CommandeBien.php';
require_once __DIR__ . '/../models/Bien.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class CommandeBienController
{
    public static function index()
    {
        $commandes = CommandeBien::all();
        return json_response($commandes);
    }

    public static function show($id)
    {
        $commande = CommandeBien::getById($id);
        if ($commande) {
            return json_response($commande);
        } else {
            return json_response(["message" => "Commande non trouvée"], 404);
        }
    }

    public static function store()
    {
        $currentUser = JwtMiddleware::getPayload();
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['bien_id'])) {
            return json_response(["message" => "bien_id est requis"], 400);
        }

        // Vérifier que le bien existe et est disponible
        $bien = Bien::getById($data['bien_id']);
        if (!$bien || $bien['statut'] !== 'disponible') {
            return json_response(["message" => "Le bien n'est pas disponible"], 400);
        }

        // Créer la commande
        $commande_id = CommandeBien::create($data['bien_id'], $currentUser['user_id']);

        // Mettre à jour le statut du bien
        Bien::update($data['bien_id'], ['statut' => 'réservé']);

        return json_response([
            "message" => "Commande créée avec succès",
            "commande_id" => $commande_id
        ], 201);
    }

    public static function updateStatut($id)
    {
        $currentUser = JwtMiddleware::getPayload();
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['statut'])) {
            return json_response(["message" => "statut est requis"], 400);
        }

        // Vérifier que l'utilisateur a le droit de modifier cette commande
        // (soit le vendeur, soit l'acheteur selon le statut)

        if (CommandeBien::updateStatut($id, $data['statut'])) {
            // Mettre à jour le statut du bien si nécessaire
            if ($data['statut'] === 'confirmée') {
                $commande = CommandeBien::getById($id);
                Bien::update($commande['bien_id'], ['statut' => 'vendu']);
            }

            return json_response(["message" => "Statut de la commande mis à jour"]);
        } else {
            return json_response(["message" => "Erreur lors de la mise à jour"], 400);
        }
    }

    public static function byAcheteur()
    {
        $currentUser = JwtMiddleware::getPayload();
        $commandes = CommandeBien::getByAcheteur($currentUser['user_id']);
        return json_response($commandes);
    }

    public static function byVendeur()
    {
        $currentUser = JwtMiddleware::getPayload();
        $commandes = CommandeBien::getByVendeur($currentUser['user_id']);
        return json_response($commandes);
    }
}