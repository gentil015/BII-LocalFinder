<?php
/**
 * Google Calendar Helper Functions
 * 
 * Convenience functions for Google Calendar integration
 * Include this file to access helper functions throughout the app
 */

/**
 * Check if provider has Google Calendar connected
 * 
 * @param int $provider_id Provider ID
 * @return bool
 */
function isGoogleCalendarConnected($provider_id) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        return $auth->isAuthenticated();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get Google Calendar connection status
 * 
 * @param int $provider_id Provider ID
 * @return array Status information
 */
function getGoogleCalendarStatus($provider_id) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        return $auth->getAuthStatus();
    } catch (Exception $e) {
        return ['authenticated' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get access token for provider
 * 
 * @param int $provider_id Provider ID
 * @return string|null Access token or null if not authenticated
 */
function getGoogleAccessToken($provider_id) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        if ($auth->isAuthenticated()) {
            return $auth->getAccessToken();
        }
    } catch (Exception $e) {
        error_log('Error getting Google access token: ' . $e->getMessage());
    }
    return null;
}

/**
 * Get Google Calendar API instance for provider
 * 
 * @param int $provider_id Provider ID
 * @return GoogleCalendarAPI|null API instance or null if not authenticated
 */
function getGoogleCalendarAPI($provider_id) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        $token = $auth->getAccessToken();
        return new GoogleCalendarAPI($token);
    } catch (Exception $e) {
        error_log('Error getting Google Calendar API: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create booking event in Google Calendar
 * 
 * @param int $provider_id Provider ID
 * @param int $booking_id Booking ID
 * @param array $booking_data Booking data
 * @return array|bool Event data if successful, false otherwise
 */
function syncBookingToGoogleCalendar($provider_id, $booking_id, $booking_data) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        
        if (!$auth->isAuthenticated()) {
            return false; // Provider not authenticated
        }
        
        $api = getGoogleCalendarAPI($provider_id);
        if (!$api) {
            return false;
        }
        
        $calendar_id = $auth->getCalendarId();
        if (!$calendar_id) {
            return false; // Calendar ID not set
        }
        
        // Format event data
        $start_time = $booking_data['preferred_date'];
        if (isset($booking_data['preferred_time'])) {
            $start_time .= 'T' . $booking_data['preferred_time'];
        } else {
            $start_time .= 'T09:00:00';
        }
        
        $end_time = date('c', strtotime($start_time . ' +1 hour'));
        
        $event = [
            'summary' => 'Booking: ' . substr($booking_data['service_description'], 0, 50),
            'description' => "Booking ID: {$booking_id}\n" . $booking_data['service_description'],
            'start' => ['dateTime' => $start_time],
            'end' => ['dateTime' => $end_time]
        ];
        
        // Create event
        $result = $api->createEvent($calendar_id, $event);
        
        // Store Google event ID in database
        if (isset($result['id'])) {
            global $db;
            $stmt = $db->prepare("UPDATE bookings SET google_event_id = ? WHERE id = ?");
            $stmt->execute([$result['id'], $booking_id]);
            
            return $result;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log('Error syncing booking to Google Calendar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update booking event in Google Calendar
 * 
 * @param int $provider_id Provider ID
 * @param int $booking_id Booking ID
 * @param array $booking_data Updated booking data
 * @return bool Success status
 */
function updateBookingInGoogleCalendar($provider_id, $booking_id, $booking_data) {
    try {
        global $db;
        
        // Get Google event ID
        $stmt = $db->prepare("SELECT google_event_id FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking['google_event_id']) {
            // No event to update, create new one
            return syncBookingToGoogleCalendar($provider_id, $booking_id, $booking_data);
        }
        
        $auth = new GoogleCalendarAuth($provider_id);
        if (!$auth->isAuthenticated()) {
            return false;
        }
        
        $api = getGoogleCalendarAPI($provider_id);
        if (!$api) {
            return false;
        }
        
        $calendar_id = $auth->getCalendarId();
        if (!$calendar_id) {
            return false;
        }
        
        // Format updated event
        $start_time = $booking_data['preferred_date'];
        if (isset($booking_data['preferred_time'])) {
            $start_time .= 'T' . $booking_data['preferred_time'];
        } else {
            $start_time .= 'T09:00:00';
        }
        
        $end_time = date('c', strtotime($start_time . ' +1 hour'));
        
        $event = [
            'summary' => 'Booking: ' . substr($booking_data['service_description'], 0, 50),
            'description' => "Booking ID: {$booking_id}\n" . $booking_data['service_description'],
            'start' => ['dateTime' => $start_time],
            'end' => ['dateTime' => $end_time]
        ];
        
        // Update event
        $api->updateEvent($calendar_id, $booking['google_event_id'], $event);
        return true;
        
    } catch (Exception $e) {
        error_log('Error updating booking in Google Calendar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete booking event from Google Calendar
 * 
 * @param int $provider_id Provider ID
 * @param int $booking_id Booking ID
 * @return bool Success status
 */
function deleteBookingFromGoogleCalendar($provider_id, $booking_id) {
    try {
        global $db;
        
        // Get Google event ID
        $stmt = $db->prepare("SELECT google_event_id FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking['google_event_id']) {
            return true; // No event to delete
        }
        
        $auth = new GoogleCalendarAuth($provider_id);
        if (!$auth->isAuthenticated()) {
            return false;
        }
        
        $api = getGoogleCalendarAPI($provider_id);
        if (!$api) {
            return false;
        }
        
        $calendar_id = $auth->getCalendarId();
        if (!$calendar_id) {
            return false;
        }
        
        // Delete event
        $success = $api->deleteEvent($calendar_id, $booking['google_event_id']);
        
        if ($success) {
            // Clear event ID from database
            $stmt = $db->prepare("UPDATE bookings SET google_event_id = NULL WHERE id = ?");
            $stmt->execute([$booking_id]);
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log('Error deleting booking from Google Calendar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add provider time off to Google Calendar
 * 
 * @param int $provider_id Provider ID
 * @param string $start_date Start date (YYYY-MM-DD)
 * @param string $end_date End date (YYYY-MM-DD)
 * @param string $reason Reason for time off
 * @return array|bool Event data if successful, false otherwise
 */
function addTimeOffToGoogleCalendar($provider_id, $start_date, $end_date, $reason = 'Time Off') {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        
        if (!$auth->isAuthenticated()) {
            return false;
        }
        
        $api = getGoogleCalendarAPI($provider_id);
        if (!$api) {
            return false;
        }
        
        $calendar_id = $auth->getCalendarId();
        if (!$calendar_id) {
            return false;
        }
        
        return $api->addTimeOff($calendar_id, $start_date, $end_date, $reason);
        
    } catch (Exception $e) {
        error_log('Error adding time off to Google Calendar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check time slot availability in Google Calendar
 * 
 * @param int $provider_id Provider ID
 * @param string $start_time Start time (RFC 3339)
 * @param string $end_time End time (RFC 3339)
 * @return bool True if available
 */
function isTimeSlotAvailable($provider_id, $start_time, $end_time) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        
        if (!$auth->isAuthenticated()) {
            return true; // Allow if not authenticated
        }
        
        $api = getGoogleCalendarAPI($provider_id);
        if (!$api) {
            return true;
        }
        
        $calendar_id = $auth->getCalendarId();
        if (!$calendar_id) {
            return true;
        }
        
        return $api->isTimeAvailable($calendar_id, $start_time, $end_time);
        
    } catch (Exception $e) {
        error_log('Error checking availability: ' . $e->getMessage());
        return true; // Default to available on error
    }
}

/**
 * Disconnect provider's Google Calendar
 * 
 * @param int $provider_id Provider ID
 * @return bool Success status
 */
function disconnectGoogleCalendar($provider_id) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        return $auth->revokeAccess();
    } catch (Exception $e) {
        error_log('Error disconnecting Google Calendar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Format datetime for Google Calendar API (RFC 3339)
 * 
 * @param string $date Date (YYYY-MM-DD)
 * @param string $time Time (HH:MM:SS) or null for all-day
 * @param string $timezone Timezone (default: UTC)
 * @return string Formatted datetime
 */
function formatForGoogleCalendar($date, $time = null, $timezone = 'UTC') {
    if ($time) {
        $datetime = $date . 'T' . $time;
        return date('c', strtotime($datetime));
    } else {
        return $date; // All-day event format
    }
}

/**
 * Initialize Google Calendar integration for a provider
 * 
 * @param int $provider_id Provider ID
 * @return bool Success status
 */
function initializeGoogleCalendarForProvider($provider_id) {
    try {
        $auth = new GoogleCalendarAuth($provider_id);
        return $auth->initializeTokenTable();
    } catch (Exception $e) {
        error_log('Error initializing Google Calendar: ' . $e->getMessage());
        return false;
    }
}
