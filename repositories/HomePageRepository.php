<?php

class HomePageRepository
{
    public function getSchemaFlags(PDO $db): array
    {
        return [
            'has_is_active' => $this->hasColumn($db, 'service_providers', 'is_active'),
            'has_is_banned' => $this->hasColumn($db, 'service_providers', 'is_banned'),
            'has_is_featured' => $this->hasColumn($db, 'service_providers', 'is_featured'),
        ];
    }

    public function getCategories(PDO $db): array
    {
        $stmt = $db->query("SELECT id, name, description, icon, is_premium, monthly_fee FROM categories WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getFeaturedProviders(PDO $db, array $schemaFlags): array
    {
        $providerWhere = ["u.is_verified = 1", "sp.availability = 'available'"];
        if (!empty($schemaFlags['has_is_active'])) {
            $providerWhere[] = "sp.is_active = 1";
        }
        if (!empty($schemaFlags['has_is_banned'])) {
            $providerWhere[] = "sp.is_banned = 0";
        }

        $providerWhereSql = implode(' AND ', $providerWhere);

        if (!empty($schemaFlags['has_is_featured'])) {
            $orderBy = "sp.is_featured DESC, sp.verification_level DESC, sp.average_rating DESC";
            $featuredCondition = "(sp.is_featured = 1 OR sp.verification_level IN ('verified', 'gold', 'premium'))";
        } else {
            $orderBy = "sp.verification_level DESC, sp.average_rating DESC";
            $featuredCondition = "sp.verification_level IN ('verified', 'gold', 'premium')";
        }

        $idsStmt = $db->prepare("
            SELECT sp.id
            FROM service_providers sp
            JOIN users u ON u.id = sp.user_id
            WHERE {$providerWhereSql} AND {$featuredCondition}
            ORDER BY {$orderBy}
            LIMIT 8
        ");
        $idsStmt->execute();
        $ids = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return [];
        }

        $in = implode(',', array_map('intval', $ids));
        $selectFallbacks = [];
        $selectFallbacks[] = !empty($schemaFlags['has_is_featured']) ? "sp.is_featured" : "0 as is_featured";
        $selectFallbacks[] = !empty($schemaFlags['has_is_featured']) ? "sp.featured_until" : "NULL as featured_until";
        $selectFallbacks[] = !empty($schemaFlags['has_is_banned']) ? "sp.is_banned" : "0 as is_banned";
        $selectExtras = implode(', ', $selectFallbacks);

        $stmt = $db->query("
            SELECT 
                u.id as user_id,
                u.full_name,
                u.email,
                u.phone,
                u.profile_image,
                u.is_verified as user_verified,
                sp.id as provider_id,
                sp.profession,
                sp.bio,
                sp.location,
                sp.district,
                sp.sector,
                sp.experience_years,
                sp.hourly_rate,
                sp.average_rating,
                sp.total_reviews,
                sp.verification_level,
                {$selectExtras},
                GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_name,
                GROUP_CONCAT(DISTINCT c.icon SEPARATOR ', ') as category_icon
            FROM service_providers sp
            JOIN users u ON u.id = sp.user_id
            LEFT JOIN provider_services ps ON sp.id = ps.provider_id
            LEFT JOIN categories c ON ps.category_id = c.id
            WHERE sp.id IN ({$in})
            GROUP BY sp.id
            ORDER BY {$orderBy}
        ");

        return $stmt->fetchAll();
    }

    public function getNearbyProviders(PDO $db, array $schemaFlags): array
    {
        $providerWhere = ["u.is_verified = 1", "sp.availability = 'available'"];
        if (!empty($schemaFlags['has_is_active'])) {
            $providerWhere[] = "sp.is_active = 1";
        }
        if (!empty($schemaFlags['has_is_banned'])) {
            $providerWhere[] = "sp.is_banned = 0";
        }

        $providerWhereSql = implode(' AND ', $providerWhere);
        $selectFallbacks = [];
        $selectFallbacks[] = !empty($schemaFlags['has_is_featured']) ? "sp.is_featured" : "0 as is_featured";
        $selectExtras = implode(', ', $selectFallbacks);

        $stmt = $db->query("
            SELECT sp.district, COUNT(DISTINCT sp.id) as provider_count
            FROM service_providers sp
            JOIN users u ON u.id = sp.user_id
            WHERE {$providerWhereSql}
            GROUP BY sp.district
            ORDER BY provider_count DESC
            LIMIT 6
        ");

        return $stmt->fetchAll();
    }

    public function getRecentProviders(PDO $db, array $schemaFlags): array
    {
        $providerWhere = ["u.is_verified = 1"];
        if (!empty($schemaFlags['has_is_active'])) {
            $providerWhere[] = "sp.is_active = 1";
        }
        if (!empty($schemaFlags['has_is_banned'])) {
            $providerWhere[] = "sp.is_banned = 0";
        }

        $providerWhereSql = implode(' AND ', $providerWhere);
        $selectFallbacks = [];
        $selectFallbacks[] = !empty($schemaFlags['has_is_featured']) ? "sp.is_featured" : "0 as is_featured";
        $selectExtras = implode(', ', $selectFallbacks);

        $stmt = $db->query("
            SELECT 
                u.id as user_id,
                u.full_name,
                u.profile_image,
                sp.id as provider_id,
                sp.profession,
                sp.location,
                sp.district,
                sp.average_rating,
                sp.total_reviews,
                sp.verification_level,
                sp.created_at,
                {$selectExtras},
                GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_name,
                GROUP_CONCAT(DISTINCT c.icon SEPARATOR ', ') as category_icon
            FROM service_providers sp
            JOIN users u ON u.id = sp.user_id
            LEFT JOIN provider_services ps ON sp.id = ps.provider_id
            LEFT JOIN categories c ON ps.category_id = c.id
            WHERE {$providerWhereSql}
            GROUP BY sp.id
            ORDER BY sp.created_at DESC
            LIMIT 4
        ");

        return $stmt->fetchAll();
    }

    public function getDistricts(PDO $db): array
    {
        $stmt = $db->query("SELECT name, code FROM districts ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getPlatformSettings(PDO $db): array
    {
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([DB_NAME, $table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
