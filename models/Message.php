<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class Message
{
public static function getConversation($conversationId, $userId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT utilisateur1_id, utilisateur2_id FROM CONVERSATION WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        return [];
    }

    $user1Id = $conversation['utilisateur1_id'];
    $user2Id = $conversation['utilisateur2_id'];

    $stmt = $pdo->prepare("
        UPDATE MESSAGES SET lu = TRUE 
        WHERE conversation_id = ? AND expediteur_id != ? AND lu = FALSE
    ");
    $stmt->execute([$conversationId, $userId]);

    // Mettre à jour le compteur de non-lus
    $isUser1 = ($userId == $user1Id);
    $unreadField = $isUser1 ? 'nb_messages_non_lus_utilisateur1' : 'nb_messages_non_lus_utilisateur2';

    $stmt = $pdo->prepare("
        UPDATE CONVERSATION SET {$unreadField} = 0 WHERE id = ?
    ");
    $stmt->execute([$conversationId]);

    // Récupérer les messages
    $query = "
        SELECT 
            m.id,
            m.expediteur_id,
            u.email AS sender_name,
            m.contenu,
            m.date_envoi,
            m.lu,
            CASE WHEN md.id IS NULL THEN FALSE ELSE TRUE END AS is_deleted
        FROM MESSAGES m
        JOIN users u ON m.expediteur_id = u.id
        LEFT JOIN MESSAGE_DELETIONS md ON m.id = md.message_id AND md.user_id = ?
        WHERE m.conversation_id = ? AND (md.id IS NULL OR m.expediteur_id = ?)
        ORDER BY m.date_envoi ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $conversationId, $userId]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conversion explicite pour s'assurer que is_deleted est bien un booléen
    foreach ($messages as &$message) {
        $message['is_deleted'] = (bool)$message['is_deleted'];
        $message['destinataire_id'] = ($message['expediteur_id'] == $user1Id) ? $user2Id : $user1Id;
    }

    return $messages;
}


    /**
     * Supprime un message pour l'utilisateur courant (suppression logique)
     */
    public static function deleteMessage($messageId, $userId)
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // Vérifier que l'utilisateur a le droit de supprimer ce message
            $stmt = $pdo->prepare("
                SELECT m.id 
                FROM MESSAGES m
                JOIN CONVERSATION c ON m.conversation_id = c.id
                WHERE m.id = ? 
                AND (m.expediteur_id = ? OR c.utilisateur1_id = ? OR c.utilisateur2_id = ?)
            ");
            $stmt->execute([$messageId, $userId, $userId, $userId]);

            if (!$stmt->fetch()) {
                throw new Exception("Vous n'avez pas la permission de supprimer ce message");
            }

            // Marquer le message comme supprimé pour l'utilisateur
            $stmt = $pdo->prepare("
                INSERT INTO MESSAGE_DELETIONS (message_id, user_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE deleted_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$messageId, $userId]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Récupère un message supprimé (annule la suppression)
     */
    public static function restoreMessage($messageId, $userId)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            DELETE FROM MESSAGE_DELETIONS 
            WHERE message_id = ? AND user_id = ?
        ");
        return $stmt->execute([$messageId, $userId]);
    }



    public static function getUserConversations($userId)
    {
        global $pdo;

        // D'abord, vérifions si l'utilisateur existe
        $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $userCheck->execute([$userId]);
        if (!$userCheck->fetch()) {
            return []; // L'utilisateur n'existe pas
        }

        // Ensuite, vérifions les conversations
        $convCheck = $pdo->prepare("SELECT id FROM CONVERSATION WHERE utilisateur1_id = ? OR utilisateur2_id = ? LIMIT 1");
        $convCheck->execute([$userId, $userId]);
        if (!$convCheck->fetch()) {
            return []; // Aucune conversation trouvée
        }

        // Maintenant exécutons la requête complète avec debug
        $query = "
        SELECT 
            c.id,
            CASE 
                WHEN c.utilisateur1_id = ? THEN u2.id
                ELSE u1.id 
            END AS contact_id,
            CASE 
                WHEN c.utilisateur1_id = ? THEN u2.email
                ELSE u1.email 
            END AS contact_name,
            CASE 
                WHEN c.utilisateur1_id = ? THEN u2.email
                ELSE u1.email 
            END AS contact_email,
            CASE 
                WHEN c.utilisateur1_id = ? THEN u2.image_profile
                ELSE u1.image_profile 
            END AS contact_avatar,
            m.contenu AS last_message,
            m.date_envoi AS last_message_time,
            CASE
                WHEN c.utilisateur1_id = ? THEN c.nb_messages_non_lus_utilisateur1
                ELSE c.nb_messages_non_lus_utilisateur2
            END AS unread_count
        FROM CONVERSATION c
        JOIN users u1 ON c.utilisateur1_id = u1.id
        JOIN users u2 ON c.utilisateur2_id = u2.id
        LEFT JOIN MESSAGES m ON c.dernier_message_id = m.id
        WHERE c.utilisateur1_id = ? OR c.utilisateur2_id = ?
        ORDER BY COALESCE(m.date_envoi, c.date_creation) DESC
    ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);

        // Debug: afficher la requête et les erreurs
        error_log("Requête exécutée: " . $query);
        error_log("Paramètres: " . implode(", ", [$userId, $userId, $userId, $userId, $userId, $userId, $userId]));

        if ($stmt->errorCode() !== '00000') {
            $error = $stmt->errorInfo();
            error_log("Erreur SQL: " . $error[2]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function sendMessage($conversationId, $senderId, $content)
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // Insérer le message
            $stmt = $pdo->prepare("
                INSERT INTO MESSAGES (conversation_id, expediteur_id, contenu)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$conversationId, $senderId, $content]);
            $messageId = $pdo->lastInsertId();

            // Mettre à jour la conversation
            $isRecipientUser2 = self::isRecipientUser2($conversationId, $senderId);

            $query = "
                UPDATE CONVERSATION 
                SET dernier_message_id = ?,
                    nb_messages_non_lus_utilisateur1 = IF(?, nb_messages_non_lus_utilisateur1, nb_messages_non_lus_utilisateur1 + 1),
                    nb_messages_non_lus_utilisateur2 = IF(?, nb_messages_non_lus_utilisateur2 + 1, nb_messages_non_lus_utilisateur2)
                WHERE id = ?
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$messageId, $isRecipientUser2, $isRecipientUser2, $conversationId]);

            $pdo->commit();

            return $messageId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function findOrCreateConversation($user1Id, $user2Id)
    {
        global $pdo;

        echo json_encode([$user1Id, $user2Id]);

        // Trouver la conversation existante
        $stmt = $pdo->prepare("
            SELECT * FROM CONVERSATION 
WHERE (utilisateur1_id = ? AND utilisateur2_id = ?);
        ");
        $stmt->execute([$user2Id, $user1Id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conversation) {
            return $conversation['id'];
        }

        // Créer une nouvelle conversation
        $stmt = $pdo->prepare("
            INSERT INTO CONVERSATION (utilisateur1_id, utilisateur2_id)
            VALUES ( ?, ?)
        ");
        $stmt->execute([$user2Id, $user1Id]);

        return $pdo->lastInsertId();
    }

    private static function isUser1($conversationId, $userId)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT utilisateur1_id FROM CONVERSATION WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['utilisateur1_id'] === $userId;
    }

    private static function isRecipientUser2($conversationId, $senderId)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT utilisateur2_id FROM CONVERSATION WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['utilisateur2_id'] !== $senderId;
    }
}
