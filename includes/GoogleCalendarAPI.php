<?php
/**
 * Google Calendar API Handler
 * 
 * Handles interactions with Google Calendar API
 */

class GoogleCalendarAPI {
    
    private $access_token;
    private $base_url = 'https://www.googleapis.com/calendar/v3';
    
    /**
     * Initialize API handler with access token
     * 
     * @param string $access_token Valid Google access token
     */
    public function __construct($access_token) {
        $this->access_token = $access_token;
    }
    
    /**
     * Get primary calendar ID
     * 
     * @return string|null Calendar ID
     */
    public function getPrimaryCalendarId() {
        try {
            $response = $this->makeRequest('GET', '/calendars/primary');
            return $response['id'] ?? 'primary';
        } catch (Exception $e) {
            error_log('Error getting primary calendar: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a calendar event
     * 
     * @param string $calendar_id Calendar ID
     * @param array $event_data Event data
     * @return array Event response
     */
    public function createEvent($calendar_id, $event_data) {
        try {
            return $this->makeRequest('POST', "/calendars/{$calendar_id}/events", $event_data);
        } catch (Exception $e) {
            error_log('Error creating calendar event: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update a calendar event
     * 
     * @param string $calendar_id Calendar ID
     * @param string $event_id Event ID
     * @param array $event_data Updated event data
     * @return array Event response
     */
    public function updateEvent($calendar_id, $event_id, $event_data) {
        try {
            return $this->makeRequest('PUT', "/calendars/{$calendar_id}/events/{$event_id}", $event_data);
        } catch (Exception $e) {
            error_log('Error updating calendar event: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete a calendar event
     * 
     * @param string $calendar_id Calendar ID
     * @param string $event_id Event ID
     * @return bool Success status
     */
    public function deleteEvent($calendar_id, $event_id) {
        try {
            $this->makeRequest('DELETE', "/calendars/{$calendar_id}/events/{$event_id}");
            return true;
        } catch (Exception $e) {
            error_log('Error deleting calendar event: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * List events from calendar
     * 
     * @param string $calendar_id Calendar ID
     * @param array $params Query parameters
     * @return array Events list
     */
    public function listEvents($calendar_id, $params = []) {
        try {
            $query_string = '';
            if (!empty($params)) {
                $query_string = '?' . http_build_query($params);
            }
            
            return $this->makeRequest('GET', "/calendars/{$calendar_id}/events{$query_string}");
        } catch (Exception $e) {
            error_log('Error listing calendar events: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check availability for a time range
     * 
     * @param string $calendar_id Calendar ID
     * @param string $start_time Start time (RFC 3339 format)
     * @param string $end_time End time (RFC 3339 format)
     * @return bool True if available
     */
    public function isTimeAvailable($calendar_id, $start_time, $end_time) {
        try {
            $events = $this->listEvents($calendar_id, [
                'timeMin' => $start_time,
                'timeMax' => $end_time,
                'singleEvents' => 'true',
                'showDeleted' => 'false'
            ]);
            
            return empty($events['items']);
        } catch (Exception $e) {
            error_log('Error checking availability: ' . $e->getMessage());
            return true; // Default to available on error
        }
    }
    
    /**
     * Add time off period
     * 
     * @param string $calendar_id Calendar ID
     * @param string $start_date Start date (YYYY-MM-DD)
     * @param string $end_date End date (YYYY-MM-DD)
     * @param string $reason Reason for time off
     * @return array Event response
     */
    public function addTimeOff($calendar_id, $start_date, $end_date, $reason = 'Time Off') {
        try {
            $event_data = [
                'summary' => $reason,
                'description' => 'Provider time off',
                'start' => ['date' => $start_date],
                'end' => ['date' => date('Y-m-d', strtotime($end_date . ' +1 day'))],
                'transparency' => 'transparent',
                'visibility' => 'private',
                'colorId' => '8' // Gray color for time off
            ];
            
            return $this->createEvent($calendar_id, $event_data);
        } catch (Exception $e) {
            error_log('Error adding time off: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Make HTTP request to Google Calendar API
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request body data
     * @return array Response data
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        if (in_array($method, ['POST', 'PUT']) && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        if ($http_code >= 400) {
            $error_data = json_decode($response, true);
            throw new Exception(
                'API Error ' . $http_code . ': ' . 
                ($error_data['error']['message'] ?? 'Unknown error')
            );
        }
        
        return json_decode($response, true) ?? [];
    }
}
