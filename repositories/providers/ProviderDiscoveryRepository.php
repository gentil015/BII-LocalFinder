<?php
/**
 * ProviderDiscoveryRepository
 * ---------------------------------------------------------------------
 * All data access for the personalized Providers discovery page.
 * Every section = exactly ONE query. Aggregates (avg price, completed
 * jobs, total jobs, response time, view count) are computed once via
 * LEFT JOIN derived tables, never per-row correlated subqueries, so the
 * page never triggers N+1 SQL regardless of how many sections render.
 * ---------------------------------------------------------------------
 */
class ProviderDiscoveryRepository
{
    /**
     * The reusable "core" of every provider query: base table, join to
     * users, and pre-aggregated stats joined once as derived tables.
     * Returns [sql fragment, params] so callers just append WHERE/ORDER/LIMIT.
     */
    private function baseFromClause(): string
    {
        return "
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN (
                SELECT provider_id, AVG(price) AS avg_price
                FROM provider_services
                WHERE is_available = 1
                GROUP BY provider_id
            ) svc ON svc.provider_id = sp.id
            LEFT JOIN (
                SELECT provider_id,
                       COUNT(*) AS total_jobs,
                       SUM(status = 'completed') AS completed_jobs,
                       AVG(CASE WHEN responded_at IS NOT NULL
                                THEN TIMESTAMPDIFF(HOUR, created_at, responded_at) END) AS avg_response_hours
                FROM bookings
                GROUP BY provider_id
            ) bk ON bk.provider_id = sp.id
            LEFT JOIN (
                SELECT provider_id, COUNT(*) AS view_count
                FROM provider_views
                GROUP BY provider_id
            ) pv ON pv.provider_id = sp.id
            LEFT JOIN (
                SELECT target_id AS provider_id, COUNT(*) AS recent_activity
                FROM click_logs
                WHERE target_type = 'provider' AND created_at >= (NOW() - INTERVAL 7 DAY)
                GROUP BY target_id
            ) recent ON recent.provider_id = sp.id
            LEFT JOIN (
                SELECT provider_id, COUNT(*) AS offer_count
                FROM provider_services
                WHERE negotiable = 1
                GROUP BY provider_id
            ) offers ON offers.provider_id = sp.id
        ";
    }

    private function baseSelect(array $favIds): string
    {
        $favSql = empty($favIds)
            ? '0 AS is_favorite'
            : '(sp.id IN (' . implode(',', array_fill(0, count($favIds), '?')) . ')) AS is_favorite';

        return "
            SELECT sp.*, u.full_name, u.email, u.profile_image, u.is_verified AS user_verified,
                   sp.location AS provider_location,
                   COALESCE(svc.avg_price, 0) AS avg_price,
                   COALESCE(bk.total_jobs, 0) AS total_jobs,
                   COALESCE(bk.completed_jobs, 0) AS completed_jobs,
                   bk.avg_response_hours AS avg_response_hours,
                   COALESCE(pv.view_count, 0) AS view_count,
                   COALESCE(recent.recent_activity, 0) AS recent_activity,
                   COALESCE(offers.offer_count, 0) AS offer_count,
                   {$favSql}
        ";
    }

    /**
     * Run a fully-formed section query.
     *
     * @param string[] $whereParts extra WHERE conditions (already SQL-safe)
     * @param array    $whereParams params for $whereParts, in order
     * @param string   $orderSql    ORDER BY clause (no "ORDER BY" prefix)
     * @param int      $limit
     * @param int[]    $favIds      client favorite provider ids (for is_favorite flag)
     */
    private function run(PDO $db, array $whereParts, array $whereParams, string $orderSql, int $limit, array $favIds): array
    {
        $where = array_merge(["sp.is_active = 1", "sp.is_banned = 0", "u.user_type = 'provider'"], $whereParts);
        $sql = $this->baseSelect($favIds) . $this->baseFromClause()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $orderSql
            . ' LIMIT ' . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($favIds, $whereParams));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ------------------------------------------------------------------ *
     *  Signal collection (read-only lookups used to build the plan)
     * ------------------------------------------------------------------ */

    /** Profession counts from bookings + favorites + views + clicks + negotiations, weighted. */
    public function getTopProfessions(PDO $db, int $userId): array
    {
        $sql = "
            SELECT profession, SUM(weight) AS score FROM (
                SELECT sp.profession, COUNT(*) * 5 AS weight
                FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id
                WHERE b.client_id = ? GROUP BY sp.profession

                UNION ALL
                SELECT sp.profession, COUNT(*) * 4 AS weight
                FROM favorites f JOIN service_providers sp ON f.provider_id = sp.id
                WHERE f.client_id = ? GROUP BY sp.profession

                UNION ALL
                SELECT sp.profession, COUNT(*) * 2 AS weight
                FROM provider_views v JOIN service_providers sp ON v.provider_id = sp.id
                WHERE v.user_id = ? GROUP BY sp.profession

                UNION ALL
                SELECT sp.profession, COUNT(*) * 1 AS weight
                FROM click_logs c JOIN service_providers sp ON c.target_id = sp.id
                WHERE c.user_id = ? AND c.target_type = 'provider' GROUP BY sp.profession

                UNION ALL
                SELECT sp.profession, COUNT(*) * 3 AS weight
                FROM service_offers so
                JOIN service_providers sp ON so.provider_id = sp.user_id
                WHERE so.client_id = ? GROUP BY sp.profession
            ) t
            GROUP BY profession ORDER BY score DESC LIMIT 6
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Other clients who booked the same professions also booked these professions (simple co-occurrence rule, no ML). */
    public function getCoOccurringProfessions(PDO $db, int $userId, array $topProfessions): array
    {
        if (empty($topProfessions)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($topProfessions), '?'));
        $sql = "
            SELECT sp2.profession, COUNT(*) AS cnt
            FROM bookings b1
            JOIN service_providers sp1 ON b1.provider_id = sp1.id AND sp1.profession IN ($ph)
            JOIN bookings b2 ON b2.client_id = b1.client_id AND b2.client_id != ?
            JOIN service_providers sp2 ON b2.provider_id = sp2.id AND sp2.profession NOT IN ($ph)
            GROUP BY sp2.profession
            ORDER BY cnt DESC LIMIT 5
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge(array_keys($topProfessions), [$userId], array_keys($topProfessions)));
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getFavoriteProviderIds(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT provider_id FROM favorites WHERE client_id = ?");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Most recent distinct provider ids the client viewed/clicked, most recent first. */
    public function getRecentlyViewedProviderIds(PDO $db, int $userId, int $limit = 10): array
    {
        $sql = "
            SELECT provider_id, MAX(seen_at) AS seen_at FROM (
                SELECT target_id AS provider_id, created_at AS seen_at
                FROM click_logs WHERE user_id = ? AND target_type = 'provider'
                UNION ALL
                SELECT provider_id, viewed_at AS seen_at
                FROM provider_views WHERE user_id = ?
            ) t
            WHERE provider_id IS NOT NULL
            GROUP BY provider_id
            ORDER BY seen_at DESC
            LIMIT " . (int) $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** A booking that's not yet completed/cancelled -> "continue where you left off". */
    public function getOpenBookingProviderId(PDO $db, int $userId): ?int
    {
        $stmt = $db->prepare("
            SELECT provider_id FROM bookings
            WHERE client_id = ? AND status IN ('pending', 'confirmed')
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : null;
    }

    /** Client's most frequently used booking location text, used as a location fallback. */
    public function getClientFrequentLocation(PDO $db, int $userId): ?string
    {
        $stmt = $db->prepare("
            SELECT location FROM bookings
            WHERE client_id = ? AND location IS NOT NULL AND location NOT IN ('', 'Not specified')
            GROUP BY location ORDER BY COUNT(*) DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    public function hasAnyHistory(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare("
            SELECT
              (SELECT COUNT(*) FROM bookings WHERE client_id = ?) +
              (SELECT COUNT(*) FROM favorites WHERE client_id = ?) +
              (SELECT COUNT(*) FROM provider_views WHERE user_id = ?) +
              (SELECT COUNT(*) FROM click_logs WHERE user_id = ? AND target_type='provider')
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function prefersFastResponse(PDO $db, int $userId): bool
    {
        // Rule: if the client's own bookings historically got quick responses,
        // or they abandoned/cancelled bookings that took long to respond, they value speed.
        $stmt = $db->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at))
            FROM bookings WHERE client_id = ? AND responded_at IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $avg = $stmt->fetchColumn();
        return $avg !== false && $avg !== null && (float) $avg <= 6.0;
    }

    public function isPriceSensitive(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM service_offers WHERE client_id = ?
            UNION ALL
            SELECT COUNT(*) FROM negotiation_history WHERE actor_id = ? AND actor_type = 'client'
        ");
        $stmt->execute([$userId, $userId]);
        $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_sum(array_map('intval', $counts)) > 0;
    }

    public function getLocationCoordinates(PDO $db, string $locationName): ?array
    {
        if ($locationName === '') {
            return null;
        }
        $stmt = $db->prepare("SELECT latitude, longitude FROM location_coordinates WHERE location_name = ? LIMIT 1");
        $stmt->execute([$locationName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ['latitude' => (float) $row['latitude'], 'longitude' => (float) $row['longitude']] : null;
    }

    /* ------------------------------------------------------------------ *
     *  Section queries — each returns provider rows ready for the card
     * ------------------------------------------------------------------ */

    public function sectionForYou(PDO $db, array $topProfessions, array $favIds, int $limit): array
    {
        if (empty($topProfessions)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($topProfessions), '?'));
        return $this->run(
            $db,
            ["sp.profession IN ($ph)"],
            array_keys($topProfessions),
            'sp.average_rating DESC, sp.total_reviews DESC, sp.is_featured DESC',
            $limit,
            $favIds
        );
    }

    public function sectionByIds(PDO $db, array $providerIds, array $favIds, int $limit): array
    {
        if (empty($providerIds)) {
            return [];
        }
        $ids = array_slice(array_map('intval', $providerIds), 0, $limit);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->run($db, ["sp.id IN ($ph)"], $ids, 'sp.id', count($ids), $favIds);
        // Preserve the caller's original ordering (most-recent-first, etc).
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    public function sectionMatchingInterests(PDO $db, array $topProfessions, array $excludeIds, array $favIds, int $limit): array
    {
        if (empty($topProfessions)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($topProfessions), '?'));
        $where = ["sp.profession IN ($ph)"];
        $params = array_keys($topProfessions);
        if (!empty($excludeIds)) {
            $phEx = implode(',', array_fill(0, count($excludeIds), '?'));
            $where[] = "sp.id NOT IN ($phEx)";
            $params = array_merge($params, $excludeIds);
        }
        return $this->run($db, $where, $params, 'sp.total_reviews DESC, sp.average_rating DESC', $limit, $favIds);
    }

    public function sectionYouMayLike(PDO $db, array $coOccurringProfessions, array $excludeIds, array $favIds, int $limit): array
    {
        if (empty($coOccurringProfessions)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($coOccurringProfessions), '?'));
        $where = ["sp.profession IN ($ph)"];
        $params = array_keys($coOccurringProfessions);
        if (!empty($excludeIds)) {
            $phEx = implode(',', array_fill(0, count($excludeIds), '?'));
            $where[] = "sp.id NOT IN ($phEx)";
            $params = array_merge($params, $excludeIds);
        }
        return $this->run($db, $where, $params, 'sp.average_rating DESC, recent_activity DESC', $limit, $favIds);
    }

    public function sectionNearYou(PDO $db, ?array $coords, array $favIds, int $limit): array
    {
        // Distance is computed in PHP after fetch (see GeolocationHelper); here we
        // just widen the candidate pool to providers who have coordinates, or fall
        // back to "any active provider" if the client's location is unknown.
        $where = $coords ? ["sp.latitude IS NOT NULL", "sp.longitude IS NOT NULL"] : [];
        return $this->run($db, $where, [], 'sp.average_rating DESC', max($limit * 3, 30), $favIds);
    }

    public function sectionPopularInCity(PDO $db, string $district, array $favIds, int $limit): array
    {
        if ($district === '') {
            return [];
        }
        return $this->run(
            $db,
            ['sp.district = ?'],
            [$district],
            'bk.total_jobs DESC, sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionAvailableNow(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ["sp.availability = 'available'"],
            [],
            'sp.average_rating DESC, bk.avg_response_hours ASC',
            $limit,
            $favIds
        );
    }

    public function sectionTopRatedNearYou(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ['sp.average_rating >= 4', 'sp.total_reviews >= 1'],
            [],
            'sp.average_rating DESC, sp.total_reviews DESC',
            $limit,
            $favIds
        );
    }

    public function sectionFastResponders(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ['bk.avg_response_hours IS NOT NULL', 'bk.avg_response_hours <= 6'],
            [],
            'bk.avg_response_hours ASC, sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionTrending(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ['recent.recent_activity > 0'],
            [],
            'recent_activity DESC, sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionMostTrusted(PDO $db, array $favIds, int $limit): array
    {
        // Trust score itself is a PHP-side formula (RecommendationEngine::trustScore);
        // pre-filter with the strongest verifiable SQL-side proxies first.
        return $this->run(
            $db,
            ['bk.total_jobs >= 1'],
            [],
            'sp.average_rating DESC, bk.completed_jobs DESC, sp.total_reviews DESC',
            max($limit * 2, 24),
            $favIds
        );
    }

    public function sectionVerified(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ["(sp.is_verified = 1 OR sp.verification_level IN ('verified','gold','premium') OR u.is_verified = 1)"],
            [],
            'sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionPremium(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ["(sp.is_premium = 1 OR sp.verification_level = 'premium')"],
            [],
            'sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionSpecialOffers(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ['offers.offer_count > 0'],
            [],
            'offers.offer_count DESC, sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionWeekendPicks(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ["(sp.working_days LIKE '%6%' OR sp.working_days LIKE '%7%')"],
            [],
            'sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionEmergencyServices(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ["sp.id IN (
                SELECT provider_id FROM provider_settings
                WHERE setting_key = 'visibility_emergency_service' AND setting_value = '1'
            )"],
            [],
            'sp.average_rating DESC',
            $limit,
            $favIds
        );
    }

    public function sectionNewProviders(PDO $db, array $favIds, int $limit, int $withinDays = 45): array
    {
        return $this->run(
            $db,
            ['sp.created_at >= (NOW() - INTERVAL ' . (int) $withinDays . ' DAY)'],
            [],
            'sp.created_at DESC',
            $limit,
            $favIds
        );
    }

    public function sectionHiddenGems(PDO $db, array $favIds, int $limit): array
    {
        return $this->run(
            $db,
            ['sp.average_rating >= 4', 'sp.total_reviews <= 4', 'bk.completed_jobs >= 1'],
            [],
            'sp.average_rating DESC, sp.total_reviews ASC',
            $limit,
            $favIds
        );
    }
}
