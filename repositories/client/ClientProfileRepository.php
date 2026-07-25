<?php

class ClientProfileRepository
{
    public function getClientById(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        return $client === false ? null : $client;
    }

    public function getTotalBookings(PDO $db, int $userId): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM bookings WHERE client_id = ?');
        $stmt->execute([$userId]);
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function getTotalReviews(PDO $db, int $userId): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE client_id = ?');
        $stmt->execute([$userId]);
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function getRecentActivities(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT * FROM user_activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateClientProfile(PDO $db, int $userId, string $fullName, string $phone, string $profileImage): void
    {
        $stmt = $db->prepare('UPDATE users SET full_name = ?, phone = ?, profile_image = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$fullName, $phone, $profileImage, $userId]);
    }
}
