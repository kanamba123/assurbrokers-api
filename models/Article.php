<?php
require_once __DIR__ . '/../config/database.php';

class Article
{
   public static function all()
{
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            articles.*,
            users.id AS auteur_id,
            users.name AS auteur_nom,
            users.email AS auteur_email
        FROM 
            articles
        LEFT JOIN 
            users ON articles.auteur_id = users.id 
            
        ORDER BY 
            articles.date_publication DESC
    ");
    
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Structure les données pour une meilleure sortie JSON
    $result = array_map(function($article) {
        return [
            'id' => $article['id'],
            'titre' => $article['titre'],
            'contenu' => $article['contenu'],
            'date_publication' => $article['date_publication'],
            'auteur' => $article['auteur_id'] ? [
                'id' => $article['auteur_id'],
                'nom' => $article['auteur_nom'],
                'email' => $article['auteur_email']
            ] : null
        ];
    }, $articles);
    
    return $result;
}

    public static function find($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO articles (titre, contenu, image_uri, date_publication, qui_peu_voir, auteur_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['titre'],
            $data['contenu'],
            $data['image_uri'],
            $data['date_publication'],
            $data['qui_peu_voir'],
            $data['auteur_id']
        ]);
        return ['id' => $pdo->lastInsertId()] + $data;
    }

    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM Article WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
