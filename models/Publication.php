<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';
require_once __DIR__ . '/../utils/GeneratePublicationSlug.php';

class Publication
{
    public static function all(int $limit = null, int $offset = null): array
    {
        global $pdo;

        try {
            $query = "
                SELECT 
                    p.*,
                    u.id as user_id,
                    u.name as user_name,
                    u.email as user_email,
                    u.image_profile as user_image_profile,
                    (SELECT GROUP_CONCAT(pi.image_path) 
                     FROM publication_images pi 
                     WHERE pi.publication_id = p.id) as images
                FROM 
                    publications p
                JOIN 
                    users u ON p.user_id = u.id
                WHERE
                    p.actif = TRUE
                ORDER BY 
                    p.date_publication DESC
            ";

            if ($limit !== null) {
                $query .= " LIMIT :limit";
                if ($offset !== null) {
                    $query .= " OFFSET :offset";
                }
            }

            $stmt = $pdo->prepare($query);

            if ($limit !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                if ($offset !== null) {
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                }
            }

            $stmt->execute();

            $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatage des données
            return array_map(function ($pub) {
                return [
                    'id' => $pub['id'],
                    'titre' => $pub['titre'],
                    'slug' => $pub['slug'],
                    'auteur' => $pub['auteur'],
                    'resume' => $pub['resume'],
                    'contenu' => $pub['contenu'],
                    'prix' => $pub['prix'],
                    'image_path' => $pub['image_path'],
                    'date_publication' => $pub['date_publication'],
                    'User' => [
                        'id' => $pub['user_id'],
                        'name' => $pub['user_name'],
                        'email' => $pub['user_email'],
                        // 'image_profile' => $pub['user_image_profile']
                    ],
                    'images' => $pub['images'] ? explode(',', $pub['images']) : []
                ];
            }, $publications);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des publications: " . $e->getMessage());
            return [];
        }
    }

    public static function getById($id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.id as user_id,
                    u.name as user_name,
                    u.email as user_email,
                    u.image_profile as user_image_profile
                FROM 
                    publications p
                JOIN 
                    users u ON p.user_id = u.id
                WHERE 
                    p.id = ? 
            ");
            $stmt->execute([$id]);
            $publication = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$publication) return null;

            // Récupérer les images supplémentaires
            $imageStmt = $pdo->prepare("
                SELECT image_path FROM publication_images 
                WHERE publication_id = ?
            ");
            $imageStmt->execute([$id]);
            $publication['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

            return $publication;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de la publication: " . $e->getMessage());
            return null;
        }
    }

    public static function getBySlug($slug)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.id as user_id,
                    u.name as user_name,
                    u.email as user_email,
                    u.image_profile as user_image_profile
                FROM 
                    publications p
                JOIN 
                    users u ON p.user = u.id
                WHERE 
                    p.slug = ? AND p.actif = TRUE
            ");
            $stmt->execute([$slug]);
            $publication = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$publication) return null;

            // Récupérer les images supplémentaires
            $imageStmt = $pdo->prepare("
                SELECT image_path FROM publication_images 
                WHERE publication_id = ?
            ");
            $imageStmt->execute([$publication['id']]);
            $publication['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

            return $publication;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de la publication: " . $e->getMessage());
            return null;
        }
    }

    public static function create($data)
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $currentUser = JwtMiddleware::getPayload();
            $user_id = $currentUser['user_id'];

            $slug = PublicationSlugGenerator::generatePublicationSlug(
                $data['titre'],
                $data['resume'] ?? '',
                255
            );

            // Insertion de la publication principale
            $stmt = $pdo->prepare("
                INSERT INTO publications 
                (titre, slug, image_path,user_id,auteur, resume, contenu, prix)
                VALUES (?, ?, ?, ?, ?, ?, ?,?)
            ");
            $stmt->execute([
                $data['titre'],
                $slug,
                $data['image_path'] ?? null,
                $user_id,
                $data['auteur'],
                $data['resume'] ?? '',
                $data['contenu'],
                $data['prix'] ?? 0.00
            ]);
            $publicationId = $pdo->lastInsertId();

            // Insertion des images supplémentaires
            if (!empty($data['image_path'])) {
                // Convert to array if it's a string
                $imagePaths = is_array($data['image_path']) ? $data['image_path'] : [$data['image_path']];

                $imageStmt = $pdo->prepare("
        INSERT INTO publication_images 
        (publication_id, image_path)
        VALUES (?, ?)
    ");

                foreach ($imagePaths as $imagePath) {
                    $imageStmt->execute([$publicationId, $imagePath]);
                }
            }

            $pdo->commit();
            return $publicationId;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur lors de la création de la publication: " . $e->getMessage());
            throw $e;
        }
    }

    public static function update($id, $data)
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $currentUser = JwtMiddleware::getPayload();
            $user_id = $currentUser['user_id'];

            // Vérification que l'utilisateur est bien l'auteur
            $stmt = $pdo->prepare("SELECT user_id FROM publications WHERE id = ?");
            $stmt->execute([$id]);
            $authorId = $stmt->fetchColumn();

            if ($authorId != $user_id) {
                throw new Exception("Non autorisé", 403);
            }

            $slug = isset($data['titre']) ?
                PublicationSlugGenerator::generatePublicationSlug($data['titre'], $data['resume'] ?? '', 255) :
                null;

            // Mise à jour de la publication
            $updateFields = [];
            $params = [];

            if (isset($data['titre'])) {
                $updateFields[] = "titre = ?";
                $params[] = $data['titre'];
            }

            if ($slug) {
                $updateFields[] = "slug = ?";
                $params[] = $slug;
            }
            if (isset($data['auteur'])) {
                $updateFields[] = "auteur = ?";
                $params[] = $data['auteur'];
            }

            if (isset($data['image_path'])) {
                $updateFields[] = "image_path = ?";
                $params[] = $data['image_path'];
            }

            if (isset($data['resume'])) {
                $updateFields[] = "resume = ?";
                $params[] = $data['resume'];
            }

            if (isset($data['contenu'])) {
                $updateFields[] = "contenu = ?";
                $params[] = $data['contenu'];
            }

            if (isset($data['prix'])) {
                $updateFields[] = "prix = ?";
                $params[] = $data['prix'];
            }

            if (empty($updateFields)) {
                throw new Exception("Aucune donnée à mettre à jour", 400);
            }

            $query = "UPDATE publications SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            // Gestion des images supplémentaires
            if (isset($data['images'])) {
                // Suppression des anciennes images
                $delStmt = $pdo->prepare("DELETE FROM publication_images WHERE publication_id = ?");
                $delStmt->execute([$id]);

                // Insertion des nouvelles images
                if (!empty($data['images'])) {
                    $imageStmt = $pdo->prepare("
                        INSERT INTO publication_images 
                        (publication_id, image_path)
                        VALUES (?, ?)
                    ");

                    foreach ($data['images'] as $imagePath) {
                        $imageStmt->execute([$id, $imagePath]);
                    }
                }
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur lors de la mise à jour de la publication: " . $e->getMessage());
            throw $e;
        }
    }

    public static function delete($id)
    {
        global $pdo;

        try {
            $currentUser = JwtMiddleware::getPayload();
            $user_id = $currentUser['user_id'];

            // Vérification que l'utilisateur est bien l'auteur
            $stmt = $pdo->prepare("SELECT user_id FROM publications WHERE id = ?");
            $stmt->execute([$id]);
            $authorId = $stmt->fetchColumn();

            if ($authorId != $user_id) {
                throw new Exception("Non autorisé", 403);
            }

            // Soft delete
            $stmt = $pdo->prepare("UPDATE publications SET actif = FALSE WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression de la publication: " . $e->getMessage());
            throw $e;
        }
    }
}
