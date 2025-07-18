<?php
require_once __DIR__ . '/../models/RefreshToken.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../models/User.php'; // Pour récupérer l'utilisateur
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../utils/RefreshTokenHandler.php';

class RefreshTokenController
{
    // Liste tous les refresh tokens
    public function index()
    {
        try {
            $tokens = RefreshToken::all();
            return json_response(['refresh_tokens' => $tokens]);
        } catch (Exception $e) {
            error_log('Erreur RefreshTokenController index: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }

    // Supprime un refresh token par son token
    public function delete($token)
    {
        try {
            $deleted = RefreshToken::delete($token);
            if ($deleted) {
                return json_response(['message' => 'Refresh token supprimé']);
            } else {
                return json_response(['error' => 'Refresh token non trouvé'], 404);
            }
        } catch (Exception $e) {
            error_log('Erreur RefreshTokenController delete: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }

    // Nettoyer les refresh tokens expirés
    public function cleanExpired()
    {
        try {
            $count = RefreshToken::deleteExpired();
            return json_response(['message' => "$count refresh tokens supprimés"]);
        } catch (Exception $e) {
            error_log('Erreur RefreshTokenController cleanExpired: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }


    public function refreshToken()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $refreshToken = $data['refresh_token'] ?? null;

            if (!$refreshToken) {
                return json_response(['error' => 'Refresh token manquant'], 400);
            }

            // Valider le refresh token et récupérer user_id
            $userId = RefreshTokenHandler::validateRefreshToken($refreshToken);
            if (!$userId) {
                return json_response(['error' => 'Refresh token invalide ou expiré'], 401);
            }


            // Récupérer les infos utilisateur
            $user = User::findById($userId);
            if (!$user) {
                return json_response(['error' => 'Utilisateur introuvable'], 404);
            }

            // Générer un nouveau token JWT
            $newAccessToken = JwtHandler::generateToken([
                'user_id' => $user['id'],
                'email'   => $user['email'],
                'role'    => $user['role'] ?? 'user',
                'name'    => $user['name'] ?? 'user'
            ]);

            // Générer un nouveau refresh token
            $newRefreshToken = RefreshTokenHandler::generateRefreshToken($userId);

            // Supprimer l'ancien refresh token
            RefreshTokenHandler::revokeRefreshToken($refreshToken);

            return json_response([
                'token' => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ]);
        } catch (Exception $e) {
            error_log('Erreur RefreshTokenController refreshToken: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur'], 500);
        }
    }
}
