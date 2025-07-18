<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Connexion.php'; // Ajout du modèle de log de connexion
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../utils/RefreshTokenHandler.php';

class AuthController
{
    public function login($request = null)
    {
        try {
            if ($request === null) {
                $request = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return json_response(['error' => 'Données JSON invalides'], 400);
                }
            }

            $email = filter_var($request['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $request['password'] ?? '';

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return json_response(['error' => 'Email invalide ou manquant'], 400);
            }

            if (empty($password)) {
                return json_response(['error' => 'Mot de passe requis'], 400);
            }

            $user = User::findByEmail($email);

            if (!$user || !isset($user['password']) || !password_verify($password, $user['password'])) {
                return json_response(['error' => 'Identifiants incorrects'], 401);
            }

            $token = JwtHandler::generateToken([
                'user_id' => $user['id'],
                'email'   => $user['email'],
                'role'    => $user['role'] ?? 'user',
                'name'    => $user['name'] ?? 'user'
            ]);

            $refreshToken = RefreshTokenHandler::generateRefreshToken($user['id']);

            // 🟢 Log de connexion
            Connexion::log($user['id']);

            return json_response([
                'token' => $token,
                'refresh_token' => $refreshToken,
                'user' => [
                    'id'    => $user['id'],
                    'email' => $user['email'],
                    'role'  => $user['role'] ?? 'user',
                    'name'  => $user['name'] ?? 'user'
                ]
            ]);
        } catch (Exception $e) {
            error_log('Erreur AuthController: ' . $e->getMessage());
            return json_response(['error' => 'Erreur serveur',$e], 500);
        }
    }

    public function refreshToken($request = null)
    {
        if ($request === null) {
            $request = json_decode(file_get_contents('php://input'), true);
        }

        $refreshToken = $request['refresh_token'] ?? null;

        if (!$refreshToken) {
            return json_response(['error' => 'Refresh token manquant'], 400);
        }

        $userId = RefreshTokenHandler::validateRefreshToken($refreshToken);

        if (!$userId) {
            return json_response(['error' => 'Refresh token invalide ou expiré'], 401);
        }

        $user = User::findById($userId);
        if (!$user) {
            return json_response(['error' => 'Utilisateur introuvable'], 404);
        }

        $newToken = JwtHandler::generateToken([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'] ?? 'user',
            'name'    => $user['name'] ?? 'user'
        ]);

        return json_response(['token' => $newToken]);
    }

  public function logout($request = null)
{
    if ($request === null) {
        $request = json_decode(file_get_contents('php://input'), true);
    }

    $refreshToken = $request['refresh_token'] ?? null;

    if (!$refreshToken) {
        return json_response(['error' => 'Refresh token manquant'], 400);
    }

    // Récupérer user_id depuis refresh token
    $userId = RefreshTokenHandler::validateRefreshToken($refreshToken);
    if (!$userId) {
        // Même si token invalide, on peut renvoyer un succès pour éviter fuite d'infos
        return json_response(['message' => 'Déconnexion réussie']);
    }

    // Révoquer le refresh token
    RefreshTokenHandler::revokeRefreshToken($refreshToken);

    // Enregistrer la date de déconnexion (la plus récente connexion ouverte)
    // Récupérer IP et User-Agent
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Connexion PDO (exemple, adapte selon ton projet)
    global $pdo;
    
    // Mettre à jour la dernière connexion ouverte (sans date_deconnexion)
    $stmt = $pdo->prepare("UPDATE connexions 
                           SET date_deconnexion = NOW() 
                           WHERE user_id = ? AND ip = ? AND user_agent = ? AND date_deconnexion IS NULL
                           ORDER BY date_connexion DESC LIMIT 1");
    $stmt->execute([$userId, $ip, $userAgent]);

    return json_response(['message' => 'Déconnexion réussie']);
}
}
