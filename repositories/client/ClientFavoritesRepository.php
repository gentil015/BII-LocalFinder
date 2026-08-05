<?php

class ClientFavoritesRepository
{
    public function getClientById(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        return $client === false ? null : $client;
    }

    public function getSetting(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (string) $result : $default;
    }

    public function ensureFavoritesTable(PDO $db): void
    {
        try {
            $db->query('SELECT 1 FROM favorites LIMIT 1');
            return;
        } catch (Exception $e) {
            $createTable = "
                CREATE TABLE favorites (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    provider_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_favorite (client_id, provider_id)
                )
            ";
            $db->exec($createTable);
        }
    }

    public function getFavoriteProviders(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT 
                f.*, 
                sp.id as provider_id,
                sp.profession,
                sp.location,
                sp.availability,
                sp.hourly_rate,
                sp.average_rating,
                sp.total_reviews,
                sp.experience_years,
                sp.is_verified,
                u.full_name as provider_name,
                u.email as provider_email,
                u.phone as provider_phone,
                u.profile_image as provider_image,
                u.is_verified as user_verified
             FROM favorites f
             JOIN service_providers sp ON f.provider_id = sp.id
             JOIN users u ON sp.user_id = u.id
             WHERE f.client_id = ?
             ORDER BY f.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecommendedProviders(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT 
                sp.id,
                sp.profession,
                sp.location,
                sp.availability,
                sp.hourly_rate,
                sp.average_rating,
                sp.total_reviews,
                sp.experience_years,
                sp.is_verified,
                u.full_name as provider_name,
                u.profile_image as provider_image,
                u.is_verified as user_verified
             FROM service_providers sp
             JOIN users u ON sp.user_id = u.id
             WHERE sp.id NOT IN (
                 SELECT provider_id FROM favorites WHERE client_id = ?
             )
             AND sp.average_rating >= 4.0
             AND sp.is_active = 1
             ORDER BY sp.average_rating DESC, sp.total_reviews DESC
             LIMIT 6'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addFavorite(PDO $db, int $userId, int $providerId): bool
    {
        $this->ensureFavoritesTable($db);
        $stmt = $db->prepare('INSERT IGNORE INTO favorites (client_id, provider_id) VALUES (?, ?)');
        return $stmt->execute([$userId, $providerId]);
    }

    public function removeFavorite(PDO $db, int $userId, int $providerId): bool
    {
        $this->ensureFavoritesTable($db);
        $stmt = $db->prepare('DELETE FROM favorites WHERE client_id = ? AND provider_id = ?');
        return $stmt->execute([$userId, $providerId]);
    }

    public function clearFavorites(PDO $db, int $userId): bool
    {
        $this->ensureFavoritesTable($db);
        $stmt = $db->prepare('DELETE FROM favorites WHERE client_id = ?');
        return $stmt->execute([$userId]);
    }

    public function isFavorite(PDO $db, int $userId, int $providerId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM favorites WHERE client_id = ? AND provider_id = ? LIMIT 1');
        $stmt->execute([$userId, $providerId]);
        return (bool) $stmt->fetchColumn();
    }
}
