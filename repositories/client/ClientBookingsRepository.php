<?php

class ClientBookingsRepository
{
    public function getSetting(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (string) $result : $default;
    }

    public function getClientById(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        return $client === false ? null : $client;
    }

    public function getBookingStats(PDO $db, int $userId): array
    {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'monthly_cancellations' => 0,
        ];

        $stmt = $db->prepare('SELECT COUNT(*) as total FROM bookings WHERE client_id = ?');
        $stmt->execute([$userId]);
        $stats['total'] = (int) ($stmt->fetchColumn() ?: 0);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $stats['pending'] = (int) ($stmt->fetchColumn() ?: 0);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'confirmed'");
        $stmt->execute([$userId]);
        $stats['confirmed'] = (int) ($stmt->fetchColumn() ?: 0);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'completed'");
        $stmt->execute([$userId]);
        $stats['completed'] = (int) ($stmt->fetchColumn() ?: 0);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'cancelled' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stmt->execute([$userId]);
        $stats['monthly_cancellations'] = (int) ($stmt->fetchColumn() ?: 0);

        return $stats;
    }

    public function getBookings(PDO $db, int $userId, array $filters): array
    {
        $query = "
            SELECT b.*,
                   sp.profession, sp.location, sp.availability, sp.hourly_rate,
                   u.full_name as provider_name, u.phone as provider_phone,
                   u.email as provider_email, u.profile_image as provider_image
            FROM bookings b
            JOIN service_providers sp ON b.provider_id = sp.id
            JOIN users u ON sp.user_id = u.id
            WHERE b.client_id = ?
        ";

        $params = [$userId];

        if (!empty($filters['status'])) {
            $query .= ' AND b.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $query .= ' AND DATE(b.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= ' AND DATE(b.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $query .= ' AND (u.full_name LIKE ? OR sp.profession LIKE ? OR b.service_description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query .= ' ORDER BY b.created_at DESC';

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOfferByIdForClient(PDO $db, int $offerId, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM service_offers WHERE id = ? AND client_id = ?');
        $stmt->execute([$offerId, $userId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $offer === false ? null : $offer;
    }

    public function getCounterOfferByIdForClient(PDO $db, int $counterId, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM service_counteroffers WHERE id = ? AND client_id = ?');
        $stmt->execute([$counterId, $userId]);
        $counter = $stmt->fetch(PDO::FETCH_ASSOC);
        return $counter === false ? null : $counter;
    }

    public function updateOfferStatus(PDO $db, int $offerId, string $status): bool
    {
        $stmt = $db->prepare('UPDATE service_offers SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $offerId]);
    }

    public function updateCounterOfferStatus(PDO $db, int $counterId, string $status): bool
    {
        $stmt = $db->prepare('UPDATE service_counteroffers SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $counterId]);
    }

    public function insertFinalizedServicePrice(PDO $db, array $data): bool
    {
        $stmt = $db->prepare(
            'INSERT INTO finalized_service_prices 
                (booking_id, service_id, client_id, provider_id, finalized_price, negotiation_rounds, provider_final_counteroffer_id, status)
             VALUES (?, ?, ?, ?, ?, 2, ?, "active")
             ON DUPLICATE KEY UPDATE
                finalized_price = VALUES(finalized_price),
                negotiation_rounds = 2,
                provider_final_counteroffer_id = VALUES(provider_final_counteroffer_id),
                updated_at = NOW()'
        );
        return $stmt->execute([
            $data['booking_id'],
            $data['service_id'],
            $data['client_id'],
            $data['provider_id'],
            $data['finalized_price'],
            $data['provider_final_counteroffer_id'],
        ]);
    }

    public function confirmBooking(PDO $db, int $bookingId, int $userId, float $finalizedPrice): bool
    {
        $stmt = $db->prepare('UPDATE bookings SET status = "confirmed", agreed_price = ? WHERE id = ? AND client_id = ?');
        return $stmt->execute([$finalizedPrice, $bookingId, $userId]);
    }

    public function logNegotiationHistory(PDO $db, int $bookingId, int $offerId, int $counterId, float $priceOffered, int $actorId): bool
    {
        $stmt = $db->prepare(
            'INSERT INTO negotiation_history (booking_id, offer_id, counteroffer_id, action_type, price_offered, actor_id, actor_type, notes)
             VALUES (?, ?, ?, "counteroffer_accepted", ?, ?, "client", "Counter-offer accepted by client - Booking confirmed")'
        );
        return $stmt->execute([$bookingId, $offerId, $counterId, $priceOffered, $actorId]);
    }

    public function cancelBooking(PDO $db, int $bookingId, int $userId, ?string $reason): bool
    {
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ? AND client_id = ? AND status IN ("pending", "confirmed")');
        $stmt->execute([$bookingId, $userId]);
        if ($stmt->fetch() === false) {
            return false;
        }

        $update = $db->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?");
        return $update->execute([$reason, $bookingId]);
    }

    public function getBookedProviderIds(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT DISTINCT provider_id FROM bookings WHERE client_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'provider_id');
    }

    public function getClientCategoryIds(PDO $db, array $providerIds): array
    {
        if (empty($providerIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
        $stmt = $db->prepare('SELECT DISTINCT category_id FROM provider_categories WHERE provider_id IN (' . $placeholders . ')');
        $stmt->execute($providerIds);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');
    }

    public function getRecommendedProviders(PDO $db, int $userId, array $bookedProviderIds, array $categoryIds): array
    {
        $excludeIds = !empty($bookedProviderIds) ? $bookedProviderIds : [0];
        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $stmt = $db->prepare(
                "SELECT DISTINCT sp.id, sp.profession, sp.location, sp.average_rating, sp.total_reviews,
                        sp.is_featured, sp.availability, u.full_name, u.profile_image
                 FROM service_providers sp
                 JOIN users u ON sp.user_id = u.id
                 JOIN provider_categories pc ON pc.provider_id = sp.id
                 WHERE pc.category_id IN ($placeholders)
                   AND sp.is_active = 1 AND sp.is_banned = 0 AND sp.status = 'active'
                   AND sp.id NOT IN ($excludePlaceholders)
                 ORDER BY sp.is_featured DESC, sp.average_rating DESC, sp.total_reviews DESC
                 LIMIT 4"
            );
            $params = array_merge($categoryIds, $excludeIds);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($result)) {
                return $result;
            }
        }

        $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $stmt = $db->prepare(
            "SELECT sp.id, sp.profession, sp.location, sp.average_rating, sp.total_reviews,
                    sp.is_featured, sp.availability, u.full_name, u.profile_image
             FROM service_providers sp
             JOIN users u ON sp.user_id = u.id
             WHERE sp.is_active = 1 AND sp.is_banned = 0 AND sp.status = 'active'
               AND sp.id NOT IN ($excludePlaceholders)
             ORDER BY sp.is_featured DESC, sp.average_rating DESC, sp.total_reviews DESC
             LIMIT 4"
        );
        $stmt->execute($excludeIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
