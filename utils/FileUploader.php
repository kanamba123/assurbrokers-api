<?php

namespace App\Utils;

class FileUploader
{
    public static function uploadImage(array $file, string $baseUploadPath = 'publications', string $prefix = 'pub_'): array
    {
        try {
            if (empty($file)) {
                throw new \RuntimeException('Aucun fichier fourni', 400);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/' . $baseUploadPath . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Validation du type MIME
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($mime, $allowedTypes)) {
                throw new \RuntimeException('Type de fichier non autorisé', 400);
            }

            // Génération du nom de fichier
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $extension;
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \RuntimeException('Échec du téléchargement', 500);
            }

            // Construction de l'URL
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $imagePath = '/gisaanalytica-api/public/uploads/' . $baseUploadPath . '/' . $filename;
            return [
                'success' => true,
                'image_path' => $imagePath,
                'image_url' => $protocol . $host . $imagePath,
                'filename' => $filename,
                'mime_type' => $mime
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
}
