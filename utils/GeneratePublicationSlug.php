<?php

class PublicationSlugGenerator
{
    /**
     * Génère un slug unique pour une publication combinant :
     * - Le titre slugifié
     * - Le résumé slugifié (tronqué)
     * - Un ID aléatoire de 20 caractères (chiffres + lettres)
     * 
     * @param string $titre Titre de la publication
     * @param string $resume Résumé de la publication
     * @param int $maxLength Longueur maximale (0 = pas de limite)
     * @return string Slug unique
     */
    public static function generatePublicationSlug(string $titre, string $resume, int $maxLength = 0): string
    {
        // 1. Slugifier le titre
        $slugTitre = self::slugify($titre);
        
        // 2. Slugifier et tronquer le résumé (premières 3-5 mots)
        $slugResume = self::slugify($resume);
        $resumeParts = explode('-', $slugResume);
        $shortResume = implode('-', array_slice($resumeParts, 0, min(5, count($resumeParts))));
        
        // 3. Générer un ID aléatoire (20 caractères alphanumériques)
        $randomId = self::generateRandomId(20);
        
        // Combiner les éléments
        $fullSlug = $slugTitre . '-' . $shortResume . '-' . $randomId;
        
        // Tronquer si nécessaire (en gardant toujours l'ID complet)
        if ($maxLength > 0 && strlen($fullSlug) > $maxLength) {
            $remainingLength = $maxLength - 21; // 20 pour l'ID + 1 pour le séparateur
            $slugTitre = substr($slugTitre, 0, max(1, (int)($remainingLength / 2)));
            $shortResume = substr($shortResume, 0, max(1, (int)($remainingLength / 2)));
            $fullSlug = $slugTitre . '-' . $shortResume . '-' . $randomId;
        }
        
        return $fullSlug;
    }

    /**
     * Génère un ID aléatoire alphanumérique
     * 
     * @param int $length Longueur de l'ID
     * @return string ID aléatoire
     */
    private static function generateRandomId(int $length): string
    {
        return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
    }

    /**
     * Convertit un texte en slug
     * 
     * @param string $text Texte à convertir
     * @return string Slug
     */
    private static function slugify(string $text): string
    {
        // Remplace les caractères spéciaux et espaces par des tirets
        $text = preg_replace('/[^\pL\pN]+/u', '-', $text);
        
        // Convertit les caractères accentués
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Supprime les caractères non alphanumériques
        $text = preg_replace('/[^a-zA-Z0-9-]/', '', $text);
        
        // Convertit en minuscules et supprime les tirets en double
        $text = strtolower($text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        
        return $text ?: 'pub-' . uniqid();
    }
}