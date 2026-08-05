<?php

class ClientBookingRepository
{
    public function ensureProviderShareIdColumn(PDO $db): void
    {
        try {
            $colStmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'provider_share_id'");
            $colStmt->execute();
            if ($colStmt->fetchColumn() == 0) {
                $db->exec("ALTER TABLE bookings ADD COLUMN provider_share_id INT NULL AFTER status");
            }
        } catch (Exception $e) {
            error_log('Booking table share column check failed: ' . $e->getMessage());
        }
    }

    public function getProviderById(PDO $db, int $providerId): ?array
    {
        $stmt = $db->prepare(
            'SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.created_at as member_since, u.is_verified as user_verified
             FROM service_providers sp
             JOIN users u ON sp.user_id = u.id
             WHERE sp.id = ? AND sp.is_active = 1 AND sp.is_banned = 0'
        );
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        return $provider === false ? null : $provider;
    }

    public function getServicesForProvider(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare(
            'SELECT ps.*, c.name as category_name, c.icon as category_icon
             FROM provider_services ps
             JOIN categories c ON ps.category_id = c.id
             WHERE ps.provider_id = ? AND ps.is_available = 1
             ORDER BY ps.created_at DESC'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScheduleForProvider(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare(
            'SELECT working_days, working_hours_start, working_hours_end, break_start, break_end, slot_duration, buffer_time, max_daily_bookings
             FROM service_providers
             WHERE id = ?'
        );
        $stmt->execute([$providerId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        return $schedule === false ? [] : $schedule;
    }

    public function getAvailabilityExceptions(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare(
            'SELECT date, is_available
             FROM provider_availability
             WHERE provider_id = ? AND date >= CURDATE()'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTimeOffPeriods(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare(
            'SELECT start_date, end_date, reason
             FROM provider_time_off
             WHERE provider_id = ? AND end_date >= CURDATE() AND is_approved = 1'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFullyBookedDates(PDO $db, int $providerId, int $slotDuration, int $bufferMinutes, int $maxDailyBookings, ?string $workingHoursStart, ?string $workingHoursEnd, ?string $breakStart, ?string $breakEnd): array
    {
        $workingDays = [];
        $schedule = $this->getScheduleForProvider($db, $providerId);
        if (!empty($schedule['working_days'])) {
            $workingDays = array_map('intval', array_filter(array_map('trim', explode(',', $schedule['working_days']))));
        }
        if (empty($workingDays)) {
            $workingDays = [1, 2, 3, 4, 5];
        }

        $fullyBookedDates = [];
        $slotsPerDay = 0;
        if (!empty($workingHoursStart) && !empty($workingHoursEnd)) {
            $startTs = strtotime($workingHoursStart);
            $endTs = strtotime($workingHoursEnd);
            if ($endTs > $startTs) {
                $totalMinutes = intval(($endTs - $startTs) / 60);
                if (!empty($breakStart) && !empty($breakEnd)) {
                    $breakStartTs = strtotime($breakStart);
                    $breakEndTs = strtotime($breakEnd);
                    if ($breakEndTs > $breakStartTs) {
                        $totalMinutes -= intval(($breakEndTs - $breakStartTs) / 60);
                    }
                }

                $chunk = max(15, $slotDuration + $bufferMinutes);
                $slotsPerDay = intval(floor($totalMinutes / $chunk));
                if ($maxDailyBookings > 0) {
                    $slotsPerDay = min($slotsPerDay, $maxDailyBookings);
                }
            }
        }

        if ($maxDailyBookings > 0) {
            $slotsPerDay = $maxDailyBookings;
        }

        if ($slotsPerDay <= 0) {
            return [];
        }

        $stmt = $db->prepare(
            'SELECT preferred_date, COUNT(*) as cnt
             FROM bookings
             WHERE provider_id = ? AND preferred_date >= CURDATE() AND status IN (\'pending\',\'confirmed\')
             GROUP BY preferred_date'
        );
        $stmt->execute([$providerId]);
        $bookingsPerDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bookingsPerDay as $row) {
            if (!empty($row['preferred_date']) && intval($row['cnt']) >= $slotsPerDay) {
                $fullyBookedDates[] = $row['preferred_date'];
            }
        }

        return $fullyBookedDates;
    }

    public function insertBooking(PDO $db, array $bookingData): int
    {
        $stmt = $db->prepare(
            'INSERT INTO bookings (client_id, provider_id, service_id, service_description, preferred_date, preferred_time, location, amount, status, provider_share_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $ok = $stmt->execute([
            $bookingData['client_id'],
            $bookingData['provider_id'],
            $bookingData['service_id'],
            $bookingData['service_description'],
            $bookingData['preferred_date'],
            $bookingData['preferred_time'],
            $bookingData['location'],
            $bookingData['amount'],
            $bookingData['status'],
            $bookingData['provider_share_id'],
        ]);

        if (!$ok) {
            throw new RuntimeException('Failed to save booking.');
        }

        return intval($db->lastInsertId());
    }
}
