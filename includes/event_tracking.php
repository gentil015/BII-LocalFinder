<?php
/**
 * Event Tracking System
 * Centralized event logging for user actions across the application
 */

require_once __DIR__ . '/../config/database.php';

class EventTracker {
    private $db;

    public function __construct($db = null) {
        if ($db === null) {
            $this->db = Database::getInstance()->getConnection();
        } else {
            $this->db = $db;
        }
    }

    /**
     * Track an event in the event_logs table
     *
     * @param string $eventType The type of event (e.g., 'search', 'provider_view', 'message_sent', 'booking_created')
     * @param string $entityType The type of entity involved (e.g., 'provider', 'booking', 'message', 'service')
     * @param int|null $entityId The ID of the entity involved
     * @param array $metadata Additional metadata as key-value pairs
     * @param int|null $userId User ID (optional, will try to get from session)
     * @param string|null $sessionId Session ID (optional, will try to get from session)
     * @return bool Success status
     */
    public function trackEvent($eventType, $entityType = null, $entityId = null, $metadata = [], $userId = null, $sessionId = null) {
        try {
            // Get user ID and session ID from session if not provided
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            if ($sessionId === null && isset($_SESSION['session_id'])) {
                $sessionId = $_SESSION['session_id'];
            } elseif ($sessionId === null && session_id()) {
                $sessionId = session_id();
            }

            // Validate required fields
            if (empty($eventType)) {
                error_log("Event tracking failed: eventType is required");
                return false;
            }

            if (empty($sessionId)) {
                error_log("Event tracking failed: sessionId is required");
                return false;
            }

            // Prepare metadata as JSON
            $metadataJson = json_encode($metadata);
            if ($metadataJson === false) {
                error_log("Event tracking failed: Invalid metadata format");
                return false;
            }

            // Prepare the SQL statement
            $sql = "INSERT INTO event_logs (user_id, session_id, event_type, entity_type, entity_id, metadata, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                error_log("Event tracking failed: Failed to prepare statement - " . $this->db->error);
                return false;
            }

            // Bind parameters
            $stmt->bindParam(1, $userId, PDO::PARAM_INT);
            $stmt->bindParam(2, $sessionId, PDO::PARAM_STR);
            $stmt->bindParam(3, $eventType, PDO::PARAM_STR);
            $stmt->bindParam(4, $entityType, PDO::PARAM_STR);
            $stmt->bindParam(5, $entityId, PDO::PARAM_INT);
            $stmt->bindParam(6, $metadataJson, PDO::PARAM_STR);

            // Execute the statement
            $result = $stmt->execute();
            if (!$result) {
                error_log("Event tracking failed: Failed to execute statement - " . $stmt->errorInfo()[2]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            error_log("Event tracking exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Track a search event
     *
     * @param string $searchQuery The search query
     * @param string $searchType Type of search (e.g., 'provider', 'service', 'general')
     * @param array $filters Applied filters
     * @param int $resultsCount Number of results returned
     * @param int|null $userId User ID
     * @param string|null $sessionId Session ID
     * @return bool Success status
     */
    public function trackSearch($searchQuery, $searchType = 'general', $filters = [], $resultsCount = 0, $userId = null, $sessionId = null) {
        $metadata = [
            'search_query' => $searchQuery,
            'search_type' => $searchType,
            'filters' => $filters,
            'results_count' => $resultsCount
        ];

        return $this->trackEvent('search', 'search', null, $metadata, $userId, $sessionId);
    }

    /**
     * Track a provider profile view/click event
     *
     * @param int $providerId Provider ID
     * @param string $action Action type ('view', 'click', 'contact')
     * @param array $additionalMetadata Additional metadata
     * @param int|null $userId User ID
     * @param string|null $sessionId Session ID
     * @return bool Success status
     */
    public function trackProviderInteraction($providerId, $action = 'view', $additionalMetadata = [], $userId = null, $sessionId = null) {
        $metadata = array_merge([
            'action' => $action,
            'provider_id' => $providerId
        ], $additionalMetadata);

        return $this->trackEvent('provider_' . $action, 'provider', $providerId, $metadata, $userId, $sessionId);
    }

    /**
     * Track a messaging event
     *
     * @param int $messageId Message ID
     * @param string $action Action type ('sent', 'received', 'read')
     * @param int $recipientId Recipient user ID
     * @param array $additionalMetadata Additional metadata
     * @param int|null $userId User ID
     * @param string|null $sessionId Session ID
     * @return bool Success status
     */
    public function trackMessage($messageId, $action = 'sent', $recipientId = null, $additionalMetadata = [], $userId = null, $sessionId = null) {
        $metadata = array_merge([
            'action' => $action,
            'message_id' => $messageId,
            'recipient_id' => $recipientId
        ], $additionalMetadata);

        return $this->trackEvent('message_' . $action, 'message', $messageId, $metadata, $userId, $sessionId);
    }

    /**
     * Track a booking event
     *
     * @param int $bookingId Booking ID
     * @param string $action Action type ('created', 'confirmed', 'cancelled', 'completed')
     * @param array $additionalMetadata Additional metadata
     * @param int|null $userId User ID
     * @param string|null $sessionId Session ID
     * @return bool Success status
     */
    public function trackBooking($bookingId, $action = 'created', $additionalMetadata = [], $userId = null, $sessionId = null) {
        $metadata = array_merge([
            'action' => $action,
            'booking_id' => $bookingId
        ], $additionalMetadata);

        return $this->trackEvent('booking_' . $action, 'booking', $bookingId, $metadata, $userId, $sessionId);
    }
}

/**
 * Global function to track events easily
 *
 * @param string $eventType The type of event
 * @param string $entityType The type of entity involved
 * @param int|null $entityId The ID of the entity involved
 * @param array $metadata Additional metadata
 * @param int|null $userId User ID
 * @param string|null $sessionId Session ID
 * @return bool Success status
 */
function trackEvent($eventType, $entityType = null, $entityId = null, $metadata = [], $userId = null, $sessionId = null) {
    static $tracker = null;

    if ($tracker === null) {
        $tracker = new EventTracker();
    }

    return $tracker->trackEvent($eventType, $entityType, $entityId, $metadata, $userId, $sessionId);
}
?>