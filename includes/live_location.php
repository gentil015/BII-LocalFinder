<?php
/**
 * Live location helpers for BII LocalFinder chat.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function normalizeConversationKey(int $userA, int $userB): string
{
    if ($userA === $userB) {
        return '';
    }

    $low = min($userA, $userB);
    $high = max($userA, $userB);

    return "chat_{$low}_{$high}";
}

function normalizeConversationKeyFromInput($conversationId, int $userId): ?string
{
    $conversationId = trim((string)$conversationId);

    if ($conversationId === '') {
        return null;
    }

    if (preg_match('/^chat_(\d+)_(\d+)$/', $conversationId, $matches)) {
        $idA = intval($matches[1]);
        $idB = intval($matches[2]);

        if ($idA === $idB) {
            return null;
        }

        return normalizeConversationKey($idA, $idB);
    }

    if (preg_match('/^\d+$/', $conversationId)) {
        return normalizeConversationKey($userId, intval($conversationId));
    }

    return null;
}

function ensureLiveLocationTablesExist(): void
{
    try {
        $db = Database::getInstance()->getConnection();

        $db->exec("CREATE TABLE IF NOT EXISTS live_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            conversation_id VARCHAR(64) NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_live_location_user_conversation (user_id, conversation_id),
            KEY idx_live_location_conversation (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS location_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            conversation_id VARCHAR(64) NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_location_history_conversation (conversation_id),
            KEY idx_location_history_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('ensureLiveLocationTablesExist error: ' . $e->getMessage());
    }
}

function validateConversationAccess(int $userId, string $conversationKey): bool
{
    $participants = parseConversationKey($conversationKey);
    if (!$participants) {
        return false;
    }

    [$first, $second] = $participants;
    if ($userId !== $first && $userId !== $second) {
        return false;
    }

    $partnerId = $userId === $first ? $second : $first;

    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            "SELECT 1 FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1"
        );
        $stmt->execute([$userId, $partnerId, $partnerId, $userId]);
        if ($stmt->fetchColumn()) {
            return true;
        }

        $stmt = $db->prepare(
            "SELECT 1 FROM bookings WHERE (client_id = ? AND provider_id = ?) OR (client_id = ? AND provider_id = ?) LIMIT 1"
        );
        $stmt->execute([$userId, $partnerId, $partnerId, $userId]);
        if ($stmt->fetchColumn()) {
            return true;
        }

        return false;
    } catch (Throwable $e) {
        error_log('validateConversationAccess error: ' . $e->getMessage());
        return false;
    }
}

function parseConversationKey(string $conversationKey): ?array
{
    if (!preg_match('/^chat_(\d+)_(\d+)$/', $conversationKey, $matches)) {
        return null;
    }

    return [intval($matches[1]), intval($matches[2])];
}

function createWebsocketToken(int $userId, string $conversationKey, int $ttlSeconds = 900): string
{
    $payload = json_encode([
        'uid' => $userId,
        'room' => $conversationKey,
        'exp' => time() + $ttlSeconds,
    ]);

    $encoded = base64_encode($payload);
    $signature = hash_hmac('sha256', $encoded, WS_AUTH_SECRET);

    return $encoded . '.' . $signature;
}

function validateWebsocketToken(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }

    [$encoded, $signature] = $parts;
    $expected = hash_hmac('sha256', $encoded, WS_AUTH_SECRET);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payloadJson = base64_decode($encoded, true);
    if ($payloadJson === false) {
        return null;
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || empty($payload['uid']) || empty($payload['room']) || empty($payload['exp'])) {
        return null;
    }

    if (time() > intval($payload['exp'])) {
        return null;
    }

    return $payload;
}

function updateLiveLocation(int $userId, string $conversationKey, float $latitude, float $longitude, bool $saveHistory = false): bool
{
    if ($userId <= 0 || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return false;
    }

    ensureLiveLocationTablesExist();

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO live_locations (user_id, conversation_id, latitude, longitude) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId, $conversationKey, $latitude, $longitude]);

        if ($saveHistory) {
            $stmt = $db->prepare(
                "INSERT INTO location_history (user_id, conversation_id, latitude, longitude) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $conversationKey, $latitude, $longitude]);
        }

        return true;
    } catch (Throwable $e) {
        error_log('updateLiveLocation error: ' . $e->getMessage());
        return false;
    }
}

function clearLiveLocation(int $userId, string $conversationKey): bool
{
    ensureLiveLocationTablesExist();

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM live_locations WHERE user_id = ? AND conversation_id = ?");
        return (bool)$stmt->execute([$userId, $conversationKey]);
    } catch (Throwable $e) {
        error_log('clearLiveLocation error: ' . $e->getMessage());
        return false;
    }
}

function getLiveLocations(string $conversationKey): array
{
    ensureLiveLocationTablesExist();

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM live_locations WHERE conversation_id = ?");
        $stmt->execute([$conversationKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('getLiveLocations error: ' . $e->getMessage());
        return [];
    }
}

function getLocationHistory(string $conversationKey, int $limit = 100): array
{
    ensureLiveLocationTablesExist();

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM location_history WHERE conversation_id = ? ORDER BY recorded_at ASC LIMIT ?"
        );
        $stmt->bindValue(1, $conversationKey, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('getLocationHistory error: ' . $e->getMessage());
        return [];
    }
}
