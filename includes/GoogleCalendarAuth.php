<?php
/**
 * Google Calendar Authentication Handler
 * 
 * Handles OAuth 2.0 authentication flow with Google Calendar API
 * Manages token storage, refresh, and calendar operations
 */

class GoogleCalendarAuth {
    
    private $db;
    private $provider_id;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $scopes = ['https://www.googleapis.com/auth/calendar'];
    private $token_table = 'google_calendar_tokens';
    private $settings_table = 'provider_settings';
    
    /**
     * Initialize Google Calendar Auth handler
     * 
     * @param int $provider_id Provider ID
     * @param string $client_id Google OAuth Client ID
     * @param string $client_secret Google OAuth Client Secret
     */
    public function __construct($provider_id, $client_id = null, $client_secret = null) {
        $this->db = Database::getInstance()->getConnection();
        $this->provider_id = $provider_id;
        
        // Load credentials from environment or database config
        $this->client_id = $client_id ?? getenv('GOOGLE_CLIENT_ID') ?? $_ENV['GOOGLE_CLIENT_ID'] ?? null;
        $this->client_secret = $client_secret ?? getenv('GOOGLE_CLIENT_SECRET') ?? $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
        
        // Set redirect URI to callback page
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $this->redirect_uri = $protocol . '://' . $host . '/provider/google-calendar-callback.php';
    }
    
    /**
     * Get the Google OAuth authorization URL
     * 
     * @return string Authorization URL for user to visit
     */
    public function getAuthorizationUrl() {
        if (!$this->client_id) {
            throw new Exception('Google Client ID not configured');
        }
        
        $state = $this->generateStateToken();
        
        // Store state in session for verification
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_provider_id'] = $this->provider_id;
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent' // Force consent screen for refresh token
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback and exchange code for tokens
     * 
     * @param string $code Authorization code from Google
     * @param string $state State token for verification
     * @return array Token response with access_token, refresh_token, etc.
     */
    public function handleCallback($code, $state) {
        // Verify state token
        if (!isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $state) {
            throw new Exception('Invalid state token. Possible CSRF attack.');
        }
        
        if (!$this->client_id || !$this->client_secret) {
            throw new Exception('Google Client ID or Secret not configured');
        }
        
        // Exchange authorization code for tokens
        $token_request = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri
        ];
        
        $response = $this->makeTokenRequest($token_request);
        
        if (isset($response['error'])) {
            throw new Exception('Token exchange failed: ' . $response['error_description']);
        }
        
        // Store tokens in database
        $this->storeTokens($response);
        
        // Clean up session
        unset($_SESSION['google_oauth_state']);
        
        return $response;
    }
    
    /**
     * Get a valid access token, refreshing if necessary
     * 
     * @return string Valid access token
     */
    public function getAccessToken() {
        $tokens = $this->getStoredTokens();
        
        if (!$tokens) {
            throw new Exception('No Google Calendar tokens found. User must authenticate first.');
        }
        
        // Check if token is expired
        if (isset($tokens['expires_at']) && time() >= $tokens['expires_at']) {
            // Refresh the token
            $this->refreshAccessToken($tokens['refresh_token']);
            $tokens = $this->getStoredTokens();
        }
        
        return $tokens['access_token'];
    }
    
    /**
     * Refresh the access token using refresh token
     * 
     * @param string $refresh_token Refresh token
     * @return array New token response
     */
    public function refreshAccessToken($refresh_token = null) {
        $tokens = $this->getStoredTokens();
        $refresh_token = $refresh_token ?? ($tokens['refresh_token'] ?? null);
        
        if (!$refresh_token) {
            throw new Exception('No refresh token available');
        }
        
        $token_request = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        ];
        
        $response = $this->makeTokenRequest($token_request);
        
        if (isset($response['error'])) {
            throw new Exception('Token refresh failed: ' . $response['error_description']);
        }
        
        // Keep existing refresh token if not provided in response
        if (!isset($response['refresh_token'])) {
            $response['refresh_token'] = $refresh_token;
        }
        
        // Store updated tokens
        $this->storeTokens($response);
        
        return $response;
    }
    
    /**
     * Revoke Google Calendar access
     * 
     * @return bool Success status
     */
    public function revokeAccess() {
        $tokens = $this->getStoredTokens();
        
        if (!$tokens || !isset($tokens['access_token'])) {
            return true; // Already revoked or not authenticated
        }
        
        try {
            // Revoke with Google
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://oauth2.googleapis.com/revoke',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['token' => $tokens['access_token']]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            curl_exec($ch);
            curl_close($ch);
            
            // Delete tokens from database
            $stmt = $this->db->prepare("DELETE FROM {$this->token_table} WHERE provider_id = ?");
            $stmt->execute([$this->provider_id]);
            
            // Clear calendar ID setting
            $stmt = $this->db->prepare("DELETE FROM {$this->settings_table} WHERE provider_id = ? AND setting_key = 'google_calendar_id'");
            $stmt->execute([$this->provider_id]);
            
            return true;
        } catch (Exception $e) {
            error_log('Google Calendar revoke error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if provider has valid Google Calendar authentication
     * 
     * @return bool
     */
    public function isAuthenticated() {
        try {
            $tokens = $this->getStoredTokens();
            return !empty($tokens) && !empty($tokens['access_token']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get authentication status with details
     * 
     * @return array Status information
     */
    public function getAuthStatus() {
        try {
            $tokens = $this->getStoredTokens();
            
            if (!$tokens) {
                return [
                    'authenticated' => false,
                    'message' => 'Not authenticated'
                ];
            }
            
            $status = [
                'authenticated' => true,
                'authenticated_at' => $tokens['authenticated_at'] ?? null,
                'expires_at' => $tokens['expires_at'] ?? null,
                'calendar_id' => $this->getCalendarId(),
            ];
            
            if (isset($tokens['expires_at'])) {
                $status['expires_in'] = $tokens['expires_at'] - time();
                $status['is_expired'] = time() >= $tokens['expires_at'];
            }
            
            return $status;
        } catch (Exception $e) {
            return [
                'authenticated' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get stored calendar ID
     * 
     * @return string|null Calendar ID
     */
    public function getCalendarId() {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM {$this->settings_table} WHERE provider_id = ? AND setting_key = 'google_calendar_id'");
            $stmt->execute([$this->provider_id]);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Set the Google Calendar ID to sync with
     * 
     * @param string $calendar_id Calendar ID (usually email address)
     * @return bool
     */
    public function setCalendarId($calendar_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->settings_table} (provider_id, setting_key, setting_value)
                VALUES (?, 'google_calendar_id', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            return $stmt->execute([$this->provider_id, $calendar_id]);
        } catch (Exception $e) {
            error_log('Error setting calendar ID: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a table for storing Google Calendar tokens if it doesn't exist
     * 
     * @return bool
     */
    public function initializeTokenTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS {$this->token_table} (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    provider_id INT NOT NULL UNIQUE,
                    access_token LONGTEXT NOT NULL,
                    refresh_token LONGTEXT DEFAULT NULL,
                    expires_in INT DEFAULT NULL,
                    expires_at INT DEFAULT NULL,
                    token_type VARCHAR(50) DEFAULT 'Bearer',
                    scope LONGTEXT DEFAULT NULL,
                    authenticated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE
                )
            ";
            
            $this->db->exec($sql);
            return true;
        } catch (Exception $e) {
            error_log('Error creating token table: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store tokens in database
     * 
     * @param array $token_data Token response from Google
     * @return bool
     */
    private function storeTokens($token_data) {
        try {
            $expires_at = time() + ($token_data['expires_in'] ?? 3600);
            
            $stmt = $this->db->prepare("
                INSERT INTO {$this->token_table} 
                (provider_id, access_token, refresh_token, expires_in, expires_at, token_type, scope)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    access_token = VALUES(access_token),
                    refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                    expires_in = VALUES(expires_in),
                    expires_at = VALUES(expires_at),
                    token_type = VALUES(token_type),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                $this->provider_id,
                $token_data['access_token'],
                $token_data['refresh_token'] ?? null,
                $token_data['expires_in'] ?? 3600,
                $expires_at,
                $token_data['token_type'] ?? 'Bearer',
                implode(' ', $this->scopes)
            ]);
        } catch (Exception $e) {
            error_log('Error storing tokens: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get stored tokens from database
     * 
     * @return array|null Token data
     */
    private function getStoredTokens() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->token_table} WHERE provider_id = ?");
            $stmt->execute([$this->provider_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table might not exist yet
            return null;
        }
    }
    
    /**
     * Make HTTP request to Google OAuth token endpoint
     * 
     * @param array $params Request parameters
     * @return array Response data
     */
    private function makeTokenRequest($params) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('HTTP Error ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Generate a random state token for OAuth
     * 
     * @return string Random state token
     */
    private function generateStateToken() {
        return bin2hex(random_bytes(32));
    }
}
