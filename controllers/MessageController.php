<?php
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';

class MessageController
{
    public static function getUserConversations()
    {
        try {
            $currentUser = JwtMiddleware::getPayload();
            $conversations = Message::getUserConversations($currentUser['user_id']);
            return json_response($conversations);
        } catch (Exception $e) {
            return json_response([
                "error" => "Erreur lors de la récupération des conversations",
                "details" => $e->getMessage()
            ], 500);
        }
    }

    public static function getConversationMessages($conversationId)
    {
        try {
            $currentUser = JwtMiddleware::getPayload();
            $messages = Message::getConversation($conversationId, $currentUser['user_id']);
            return json_response($messages);
        } catch (Exception $e) {
            return json_response([
                "error" => "Erreur lors de la récupération des messages",
                "details" => $e->getMessage()
            ], 500);
        }
    }

    public static function sendMessage()
    {
        $currentUser = JwtMiddleware::getPayload();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['destinataire_id'], $data['contenu'], $data['conversationId'])) {
            return json_response(["error" => "Champs requis manquants"], 400);
        }

        try {
            $messageId = Message::sendMessage(
                $data['conversationId'],
                $currentUser['user_id'],
                $data['contenu']
            );

            return json_response([
                "message" => "Message envoyé avec succès",
                "message_id" => $messageId,
                "conversation_id" => $data['conversationId']
            ], 201);
        } catch (Exception $e) {
            return json_response([
                "error" => "Erreur lors de l'envoi du message",
                "details" => $e->getMessage()
            ], 500);
        }
    }

    public static function startConversation()
    {
        $currentUser = JwtMiddleware::getPayload();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['destinataire_id'])) {
            return json_response(["error" => "ID du destinataire manquant"], 400);
        }

        try {
            $conversationId = Message::findOrCreateConversation(
                $currentUser['user_id'],
                $data['destinataire_id']
            );

            return json_response([
                "conversation_id" => $conversationId
            ]);
        } catch (Exception $e) {
            return json_response([
                "error" => "Erreur lors de la création de la conversation",
                "details" => $e->getMessage()
            ], 500);
        }
    }

    public static function deleteMessage($messageId)
    {
        try {
            $currentUser = JwtMiddleware::getPayload();
            
            $success = Message::deleteMessage($messageId, $currentUser['user_id']);
            
            if ($success) {
                return json_response(["message" => "Message supprimé avec succès"]);
            } else {
                return json_response(["error" => "Échec de la suppression du message"], 400);
            }
        } catch (Exception $e) {
            return json_response([
                "error" => "Erreur lors de la suppression du message",
                "details" => $e->getMessage()
            ], 500);
        }
    }

    public static function restoreMessage($messageId)
    {
        try {
            $currentUser = JwtMiddleware::getPayload();
            
            $success = Message::restoreMessage($messageId, $currentUser['user_id']);
            
            if ($success) {
                return json_response(["message" => "Message restauré avec succès"]);
            } else {
                return json_response(["error" => "Échec de la restauration du message"], 400);
            }
        } catch (Exception $e) {
            return json_response([
                "error" => "Erreur lors de la restauration du message",
                "details" => $e->getMessage()
            ], 500);
        }
    }
}