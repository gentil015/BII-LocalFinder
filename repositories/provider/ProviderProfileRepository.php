<?php

class ProviderProfileRepository
{
    public function getProviderProfile(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare(
            'SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
             FROM service_providers sp
             JOIN users u ON sp.user_id = u.id
             WHERE sp.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        return $provider === false ? null : $provider;
    }

    public function getProviderCategories(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare(
            'SELECT c.*
             FROM categories c
             JOIN provider_categories pc ON c.id = pc.category_id
             WHERE pc.provider_id = ?'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllCategories(PDO $db): array
    {
        $stmt = $db->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllDistricts(PDO $db): array
    {
        $stmt = $db->query('SELECT * FROM districts ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPortfolioImages(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM portfolio_images
             WHERE provider_id = ? AND is_active = 1
             ORDER BY display_order, uploaded_at DESC'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPortfolioVideo(PDO $db, int $providerId): ?array
    {
        $stmt = $db->prepare(
            'SELECT * FROM portfolio_videos
             WHERE provider_id = ? AND is_active = 1
             ORDER BY uploaded_at DESC
             LIMIT 1'
        );
        $stmt->execute([$providerId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        return $video === false ? null : $video;
    }

    public function getPlatformSettings(PDO $db, array $keys): array
    {
        $settings = [];
        foreach ($keys as $key) {
            $stmt = $db->prepare('SELECT value FROM platform_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetch(PDO::FETCH_COLUMN);
            $settings[$key] = $value !== false ? $value : null;
        }

        return $settings;
    }

    public function insertPortfolioImage(PDO $db, int $providerId, string $imagePath, string $title, string $description, int $displayOrder): void
    {
        $stmt = $db->prepare(
            'INSERT INTO portfolio_images (provider_id, image_path, title, description, display_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$providerId, $imagePath, $title, $description, $displayOrder]);
    }

    public function getPortfolioImagePaths(PDO $db, int $providerId, array $imageIds): array
    {
        if (empty($imageIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $stmt = $db->prepare(
            "SELECT id, image_path
             FROM portfolio_images
             WHERE provider_id = ? AND id IN ($placeholders)"
        );
        $params = array_merge([$providerId], array_map('intval', $imageIds));
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deletePortfolioImages(PDO $db, int $providerId, array $imageIds): void
    {
        if (empty($imageIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $stmt = $db->prepare(
            "DELETE FROM portfolio_images
             WHERE provider_id = ? AND id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$providerId], array_map('intval', $imageIds)));
    }

    public function updatePortfolioImageMetadata(PDO $db, int $providerId, array $items): void
    {
        foreach ($items as $item) {
            $stmt = $db->prepare(
                'UPDATE portfolio_images
                 SET title = ?, description = ?
                 WHERE id = ? AND provider_id = ?'
            );
            $stmt->execute([
                $item['title'] ?? '',
                $item['description'] ?? '',
                (int) $item['id'],
                $providerId,
            ]);
        }
    }

    public function insertPortfolioVideo(PDO $db, int $providerId, string $videoPath, string $title, string $description): void
    {
        $stmt = $db->prepare(
            'INSERT INTO portfolio_videos (provider_id, video_path, title, description, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$providerId, $videoPath, $title, $description]);
    }

    public function deletePortfolioVideo(PDO $db, int $providerId): ?string
    {
        $stmt = $db->prepare('SELECT video_path FROM portfolio_videos WHERE provider_id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$providerId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($video === false) {
            return null;
        }

        $deleteStmt = $db->prepare('DELETE FROM portfolio_videos WHERE provider_id = ?');
        $deleteStmt->execute([$providerId]);

        return $video['video_path'] ?? null;
    }

    public function updateProviderProfile(PDO $db, int $userId, array $data): void
    {
        $stmt = $db->prepare(
            'UPDATE users
             SET full_name = ?, phone = ?, profile_image = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['full_name'],
            $data['phone'],
            $data['profile_image'],
            $userId,
        ]);

        $providerId = $this->getProviderProfile($db, $userId)['id'] ?? null;
        if (!$providerId) {
            return;
        }

        $stmt = $db->prepare(
            'UPDATE service_providers
             SET professional_title = ?, profession = ?, bio = ?, location = ?, district = ?, sector = ?, experience_years = ?, website = ?, facebook = ?, twitter = ?, instagram = ?, linkedin = ?, youtube = ?, whatsapp = ?, tiktok = ?, other_social = ?, other_social_label = ?, updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([
            $data['professional_title'],
            $data['profession'],
            $data['bio'],
            $data['location'],
            $data['district'],
            $data['sector'],
            $data['experience_years'],
            $data['social_links']['website'] ?? '',
            $data['social_links']['facebook'] ?? '',
            $data['social_links']['twitter'] ?? '',
            $data['social_links']['instagram'] ?? '',
            $data['social_links']['linkedin'] ?? '',
            $data['social_links']['youtube'] ?? '',
            $data['social_links']['whatsapp'] ?? '',
            $data['social_links']['tiktok'] ?? '',
            $data['social_links']['other_social'] ?? '',
            $data['social_links']['other_social_label'] ?? '',
            $userId,
        ]);
    }

    public function updateBasicInfo(PDO $db, int $userId, array $data): void
    {
        $stmt = $db->prepare(
            'UPDATE users
             SET full_name = ?, phone = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['full_name'],
            $data['phone'],
            $userId,
        ]);

        $stmt = $db->prepare(
            'UPDATE service_providers
             SET profession = ?, bio = ?, experience_years = ?, updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([
            $data['profession'],
            $data['bio'],
            $data['experience_years'],
            $userId,
        ]);
    }

    public function updateLocationInfo(PDO $db, int $userId, array $data): void
    {
        $stmt = $db->prepare(
            'UPDATE service_providers
             SET location = ?, district = ?, sector = ?, updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([
            $data['location'],
            $data['district'],
            $data['sector'],
            $userId,
        ]);
    }

    public function updateSocialLinks(PDO $db, int $userId, array $socialLinks): void
    {
        $stmt = $db->prepare(
            'UPDATE service_providers
             SET website = ?, facebook = ?, twitter = ?, instagram = ?, linkedin = ?, youtube = ?, whatsapp = ?, tiktok = ?, other_social = ?, other_social_label = ?, updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([
            $socialLinks['website'] ?? '',
            $socialLinks['facebook'] ?? '',
            $socialLinks['twitter'] ?? '',
            $socialLinks['instagram'] ?? '',
            $socialLinks['linkedin'] ?? '',
            $socialLinks['youtube'] ?? '',
            $socialLinks['whatsapp'] ?? '',
            $socialLinks['tiktok'] ?? '',
            $socialLinks['other_social'] ?? '',
            $socialLinks['other_social_label'] ?? '',
            $userId,
        ]);
    }

    public function syncProviderCategories(PDO $db, int $providerId, array $categoryIds): void
    {
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM provider_categories WHERE provider_id = ?')->execute([$providerId]);
            if (!empty($categoryIds)) {
                foreach ($categoryIds as $categoryId) {
                    $stmt = $db->prepare('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)');
                    $stmt->execute([$providerId, $categoryId]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
