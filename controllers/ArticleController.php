<?php
require_once __DIR__ . '/../models/Article.php';
require_once __DIR__ . '/../helpers/response.php';

class ArticleController
{
    public function index()
    {
        $articles = Article::all();
        return json_response($articles);
    }

    public function show($id)
    {
        $article = Article::find($id);
        if ($article) {
            return json_response($article);
        } else {
            return json_response(['error' => 'Article introuvable'], 404);
        }
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $required = ['titre', 'contenu', 'image_uri', 'date_publication', 'qui_peu_voir', 'auteur_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_response(['error' => "Champ '$field' manquant"], 400);
            }
        }

        $article = Article::create($data);
        return json_response(['message' => 'Article ajouté', 'article' => $article], 201);
    }

    public function destroy($id)
    {
        $deleted = Article::delete($id);
        if ($deleted) {
            return json_response(['message' => 'Article supprimé']);
        } else {
            return json_response(['error' => 'Échec de la suppression'], 500);
        }
    }
}
