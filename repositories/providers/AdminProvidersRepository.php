<?php

class AdminProvidersRepository
{
    public function listProviders(PDO $db, array $params, string $search, string $statusFilter, string $categoryFilter, string $verificationFilter, string $availabilityFilter): array
    {
        $query = "
            SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.is_verified as user_verified,
                   u.created_at as user_created, u.updated_at as user_updated,
                   sp.is_featured, sp.is_banned, sp.ban_reason, sp.featured_until,
                   GROUP_CONCAT(DISTINCT c.name) as categories,
                   COUNT(DISTINCT b.id) as total_bookings,
                   COUNT(DISTINCT r.id) as total_reviews,
                   AVG(r.rating) as average_rating,
                   sp.working_days, sp.working_hours_start, sp.working_hours_end,
                   sp.break_start, sp.break_end, sp.slot_duration, sp.buffer_time, sp.max_daily_bookings
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN provider_services ps ON sp.id = ps.provider_id
            LEFT JOIN categories c ON ps.category_id = c.id
            LEFT JOIN bookings b ON sp.id = b.provider_id
            LEFT JOIN reviews r ON sp.id = r.provider_id
            WHERE u.user_type = 'provider'
        ";

        if (!empty($search)) {
            $query .= " AND (u.full_name LIKE ? OR sp.profession LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($statusFilter)) {
            if ($statusFilter === 'active') {
                $query .= " AND sp.is_active = 1";
            } elseif ($statusFilter === 'inactive') {
                $query .= " AND sp.is_active = 0";
            } elseif ($statusFilter === 'banned') {
                $query .= " AND sp.is_banned = 1";
            } elseif ($statusFilter === 'pending') {
                $query .= " AND u.is_verified = 0";
            }
        }

        if (!empty($categoryFilter)) {
            $query .= " AND c.id = ?";
            $params[] = $categoryFilter;
        }

        if (!empty($verificationFilter)) {
            $query .= " AND sp.verification_level = ?";
            $params[] = $verificationFilter;
        }

        if (!empty($availabilityFilter)) {
            $query .= " AND sp.availability = ?";
            $params[] = $availabilityFilter;
        }

        $query .= " GROUP BY sp.id ORDER BY u.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(PDO $db): array
    {
        $stmt = $db->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProviderDetails(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("
            SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.is_verified as user_verified,
                   u.created_at as user_created, u.updated_at as user_updated,
                   GROUP_CONCAT(DISTINCT c.id) as category_ids,
                   GROUP_CONCAT(DISTINCT c.name) as category_names
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN provider_services ps ON sp.id = ps.provider_id
            LEFT JOIN categories c ON ps.category_id = c.id
            WHERE sp.id = ?
            GROUP BY sp.id
        ");
        $stmt->execute([$providerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProviderStats(PDO $db, int $providerId): array
    {
        $stats = [];
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'completed'");
        $stmt->execute([$providerId]);
        $stats['completed_jobs'] = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'");
        $stmt->execute([$providerId]);
        $stats['pending_jobs'] = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT SUM(hourly_rate) as total_earnings FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id WHERE b.provider_id = ? AND b.status = 'completed'");
        $stmt->execute([$providerId]);
        $stats['total_earnings'] = $stmt->fetchColumn() ?? 0;

        $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reported_user_id = (SELECT user_id FROM service_providers WHERE id = ?)");
        $stmt->execute([$providerId]);
        $stats['complaints_received'] = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status IN ('confirmed', 'pending') AND preferred_date >= CURDATE()");
        $stmt->execute([$providerId]);
        $stats['upcoming_bookings'] = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'cancelled'");
        $stmt->execute([$providerId]);
        $stats['cancelled_bookings'] = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE()");
        $stmt->execute([$providerId]);
        $stats['time_off_days'] = $stmt->fetchColumn();

        return $stats;
    }

    public function getProviderSchedulingData(PDO $db, int $providerId): array
    {
        $data = [];
        $stmt = $db->prepare("SELECT working_days, working_hours_start, working_hours_end, break_start, break_end, slot_duration, buffer_time, max_daily_bookings FROM service_providers WHERE id = ?");
        $stmt->execute([$providerId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $db->prepare("SELECT * FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE() ORDER BY start_date ASC LIMIT 10");
        $stmt->execute([$providerId]);
        $data['time_off'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM provider_availability WHERE provider_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 10");
        $stmt->execute([$providerId]);
        $data['availability_exceptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT b.*, u.full_name as client_name 
            FROM bookings b
            JOIN users u ON b.client_id = u.id
            WHERE b.provider_id = ? AND b.status IN ('confirmed', 'pending') 
            AND DATE(b.preferred_date) >= CURDATE()
            ORDER BY b.preferred_date ASC
            LIMIT 10
        ");
        $stmt->execute([$providerId]);
        $data['upcoming_bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    public function getWorkingDaysArray(string $workingDays): array
    {
        if (empty($workingDays)) {
            return [1, 2, 3, 4, 5];
        }
        return explode(',', $workingDays);
    }
}
