<?php

class ClientProvidersRepository
{
    public function getPlatformSetting(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : $default;
    }

    public function getClient(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getBookedProfessions(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT sp.profession, COUNT(*) as cnt
            FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id
            WHERE b.client_id = ?
            GROUP BY sp.profession ORDER BY cnt DESC LIMIT 6
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getFavoriteProviderIds(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("SELECT provider_id FROM favorites WHERE client_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getRecentlyViewedProviderIds(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT target_id FROM click_logs
                WHERE user_id = ? AND target_type = 'provider' AND target_id IS NOT NULL
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getUserBookingStats(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
        $stmt->execute([$userId]);
        $userTotalBookings = (int) $stmt->fetchColumn();

        $userAvgPrice = 0.0;
        $userAvgResp = 24.0;

        try {
            $s = $db->prepare("SELECT user_avg_price, user_avg_response_time FROM user_profiles WHERE user_id = ?");
            $s->execute([$userId]);
            $up = $s->fetch(PDO::FETCH_ASSOC);
            if ($up) {
                $userAvgPrice = (float) ($up['user_avg_price'] ?? 0);
                $userAvgResp = (float) ($up['user_avg_response_time'] ?? 24);
            } else {
                $s = $db->prepare("SELECT AVG(amount) AS avg_price, AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) AS avg_response_time FROM bookings WHERE client_id = ?");
                $s->execute([$userId]);
                $fb = $s->fetch(PDO::FETCH_ASSOC);
                if ($fb) {
                    $userAvgPrice = (float) ($fb['avg_price'] ?? 0);
                    $userAvgResp = (float) ($fb['avg_response_time'] ?? 24);
                }
            }
        } catch (Throwable $e) {
        }

        return [
            'user_total_bookings' => $userTotalBookings,
            'user_avg_price' => $userAvgPrice,
            'user_avg_response_time' => $userAvgResp,
        ];
    }

    public function getFilterOptions(PDO $db): array
    {
        $stmt = $db->prepare("
            SELECT sp.profession as cat, COUNT(DISTINCT sp.id) as cnt
            FROM service_providers sp WHERE sp.is_active=1 AND sp.is_banned=0
            GROUP BY sp.profession ORDER BY cnt DESC LIMIT 16
        ");
        $stmt->execute();
        $allCats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT DISTINCT sp.location FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.is_active=1 AND sp.is_banned=0 AND sp.location != ''
            ORDER BY sp.location LIMIT 30
        ");
        $stmt->execute();
        $allLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return ['categories' => $allCats, 'locations' => $allLocations];
    }

    public function countProviders(PDO $db, array $where, array $params): int
    {
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COUNT(DISTINCT sp.id) FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE {$whereSql}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function fetchProviders(PDO $db, array $where, array $params, array $favIds, string $sort, int $fetchLimit, int $fetchOffset, array $providerColumns): array
    {
        $favCheck = count($favIds) > 0 ? "(sp.id IN (" . implode(',', array_fill(0, count($favIds), '?')) . ")) as is_fav" : "0 as is_fav";
        $favParams = count($favIds) > 0 ? $favIds : [];
        $extraSelects = [];
        if (in_array('admin_score', $providerColumns, true)) {
            $extraSelects[] = 'sp.admin_score';
        }
        if (in_array('system_score', $providerColumns, true)) {
            $extraSelects[] = 'sp.system_score';
        }
        if (in_array('system_ranking_score', $providerColumns, true)) {
            $extraSelects[] = 'sp.system_ranking_score';
        }
        $extraSelectsSql = $extraSelects ? ', ' . implode(', ', $extraSelects) : '';

        $whereSql = implode(' AND ', $where);
        $orderBy = match($sort) {
            'rating' => 'sp.average_rating DESC, sp.total_reviews DESC',
            'reviews' => 'sp.total_reviews DESC, sp.average_rating DESC',
            'newest' => 'sp.created_at DESC',
            'price_asc' => 'avg_price ASC NULLS LAST',
            'price_desc' => 'avg_price DESC',
            default => 'sp.average_rating DESC, sp.total_reviews DESC',
        };

        $mainSql = "
            SELECT sp.*, u.full_name, u.email, u.profile_image,
                   sp.location as provider_location, u.is_verified as user_verified,
                   {$favCheck},
                   (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) as avg_price,
                   (SELECT COUNT(*) FROM bookings b WHERE b.provider_id=sp.id AND b.status='completed') as completed_jobs,
                   (SELECT COUNT(*) FROM bookings b WHERE b.provider_id=sp.id) as total_jobs,
                   (SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) FROM bookings WHERE provider_id=sp.id AND responded_at IS NOT NULL) as avg_response_hours,
                   (SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id=sp.id) as view_count{$extraSelectsSql}
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$fetchLimit} OFFSET {$fetchOffset}
        ";

        $stmt = $db->prepare($mainSql);
        $stmt->execute(array_merge($favParams, $params));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getForYouProviders(PDO $db, array $providers, array $profList): array
    {
        if (empty($profList)) {
            return [];
        }

        $shownIds = array_column($providers, 'id');
        $where = ["sp.is_active=1", "sp.is_banned=0", "u.user_type='provider'", "sp.average_rating>=3.5"];
        $params = [];
        $ph = implode(',', array_fill(0, count($profList), '?'));
        $where[] = "sp.profession IN ({$ph})";
        $params = array_merge($params, $profList);
        if (!empty($shownIds)) {
            $ph2 = implode(',', array_fill(0, count($shownIds), '?'));
            $where[] = "sp.id NOT IN ({$ph2})";
            $params = array_merge($params, $shownIds);
        }

        $sql = "SELECT sp.*, u.full_name, u.profile_image, sp.location as provider_location,
                     (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) as avg_price
              FROM service_providers sp JOIN users u ON sp.user_id=u.id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY sp.average_rating DESC LIMIT 6";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
