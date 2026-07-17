<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

interface NotificationProviderInterface
{
    public function send(string $to, string $subject, string $message, array $options = []);
}

class EmailProvider implements NotificationProviderInterface
{
    private $smtpConfig;

    public function __construct(array $smtpConfig = [])
    {
        $this->smtpConfig = array_merge([
            'host' => getSetting('smtp_host', 'smtp.gmail.com'),
            'port' => intval(getSetting('smtp_port', '587')),
            'username' => getSetting('smtp_username', ''),
            'password' => getSetting('smtp_password', ''),
            'encryption' => getSetting('smtp_encryption', 'tls'),
            'from_email' => getSetting('default_notification_email', 'noreply@biilocalfinder.com'),
            'from_name' => getPlatformName(),
        ], $smtpConfig);
    }

    public function send(string $to, string $subject, string $message, array $options = [])
    {
        if (empty($to)) {
            return ['success' => false, 'message' => 'No recipient email provided'];
        }

        if (!isEmailNotificationsEnabled()) {
            return ['success' => false, 'message' => 'Email notifications are disabled'];
        }

        // Ensure Mailer uses DB-configured SMTP
        $mailer = new Mailer($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);

        $sent = $mailer->sendEmail($to, $subject, $message, true, [], $this->smtpConfig['from_email'], $this->smtpConfig['from_name']);

        return [
            'success' => $sent === true,
            'message' => $sent ? 'Email sent' : 'Failed to send email',
        ];
    }
}

class SMSProvider
{
    private $providerName;
    private $apiKey;
    private $apiUrl;
    private $from;

    public function __construct(array $config = [])
    {
        $this->providerName = $config['provider'] ?? getSetting('sms_provider', 'twilio');
        $this->apiKey = $config['api_key'] ?? getSetting('sms_api_key', '');
        $this->apiUrl = $config['api_url'] ?? getSetting('sms_api_url', '');
        $this->from = $config['from'] ?? getSetting('sms_from_number', '');
    }

    public function send(string $to, string $subject, string $message, array $options = [])
    {
        if (empty($to)) {
            return ['success' => false, 'message' => 'No recipient phone provided'];
        }

        if (!isSMSNotificationsEnabled()) {
            return ['success' => false, 'message' => 'SMS notifications are disabled'];
        }

        if (empty($this->apiKey)) {
            return ['success' => true, 'demo_mode' => true, 'message' => 'SMS API key is missing; demo mode enabled'];
        }

        $provider = strtolower($this->providerName);

        switch ($provider) {
            case 'mtn':
                $driver = new MTNSMSProvider($this->apiKey, $this->apiUrl, $this->from);
                break;

            case 'twilio':
                $driver = new TwilioSMSProvider($this->apiKey, $this->apiUrl, $this->from);
                break;

            default:
                return ['success' => false, 'message' => "Unsupported SMS provider: {$this->providerName}"];
        }

        $subject = $options['subject'] ?? '';
        return $driver->send($to, $subject, $message, $options);
    }
}

abstract class BaseSMSProvider implements NotificationProviderInterface
{
    protected $apiKey;
    protected $apiUrl;
    protected $from;

    public function __construct(string $apiKey, string $apiUrl = '', string $from = '')
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        $this->from = $from;
    }

    protected function httpPost($url, $payload, array $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => "HTTP error: {$error}", 'http_code' => $http_code];
        }

        curl_close($ch);

        return ['success' => $http_code >= 200 && $http_code < 300, 'http_code' => $http_code, 'response' => $response];
    }

    abstract public function send(string $to, string $subject, string $message, array $options = []);
}

class MTNSMSProvider extends BaseSMSProvider
{
    public function send(string $to, string $subject, string $message, array $options = [])
    {
        $url = $this->apiUrl ?: getSetting('sms_api_url', '');

        if (empty($url)) {
            return ['success' => false, 'message' => 'MTN SMS API URL is not configured'];
        }

        $payload = json_encode([
            'to' => $to,
            'message' => $message,
            'from' => $this->from,
        ]);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $result = $this->httpPost($url, $payload, $headers);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'MTN SMS sent' : ('MTN SMS failed: ' . ($result['response'] ?? $result['message'])),
            'http_code' => $result['http_code'] ?? null,
            'raw_response' => $result['response'] ?? null,
        ];
    }
}

class TwilioSMSProvider extends BaseSMSProvider
{
    public function send(string $to, string $subject, string $message, array $options = [])
    {
        $url = $this->apiUrl;

        $sid = '';
        $token = '';

        if (strpos($this->apiKey, ':') !== false) {
            list($sid, $token) = explode(':', $this->apiKey, 2);
        } else {
            $sid = getSetting('twilio_account_sid', '');
            $token = getSetting('twilio_auth_token', '');
        }

        if (empty($sid) || empty($token)) {
            return ['success' => false, 'message' => 'Twilio account SID or auth token is missing'];
        }

        if (empty($url)) {
            $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        }

        $from = $this->from ?: getSetting('sms_from_number', '');

        if (empty($from)) {
            return ['success' => false, 'message' => 'Twilio "from" number is not configured'];
        }

        $postFields = http_build_query([
            'From' => $from,
            'To' => $to,
            'Body' => $message,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => "Twilio HTTP error: {$error}", 'http_code' => $http_code];
        }

        curl_close($ch);

        $sent = $http_code >= 200 && $http_code < 300;
        return [
            'success' => $sent,
            'message' => $sent ? 'Twilio SMS sent' : ('Twilio SMS failed: ' . $response),
            'http_code' => $http_code,
            'raw_response' => $response,
        ];
    }
}

class NotificationEngine
{
    private $emailProvider;
    private $smsProvider;
    private $settings;

    public function __construct()
    {
        $this->settings = [
            'sms' => [
                'enabled' => isSMSNotificationsEnabled(),
                'provider' => getSetting('sms_provider', 'twilio'),
                'api_key' => getSetting('sms_api_key', ''),
                'api_url' => getSetting('sms_api_url', ''),
                'from' => getSetting('sms_from_number', ''),
            ],
            'email' => [
                'enabled' => isEmailNotificationsEnabled(),
                'host' => getSetting('smtp_host', 'smtp.gmail.com'),
                'port' => intval(getSetting('smtp_port', '587')),
                'username' => getSetting('smtp_username', ''),
                'password' => getSetting('smtp_password', ''),
                'encryption' => getSetting('smtp_encryption', 'tls'),
                'from' => getSetting('default_notification_email', 'noreply@biilocalfinder.com'),
            ],
        ];

        $this->emailProvider = new EmailProvider($this->settings['email']);
        $this->smsProvider = new SMSProvider($this->settings['sms']);
    }

    public function send(array $user, string $subject, string $message, array $options = [])
    {
        $result = [
            'email' => null,
            'sms' => null,
            'status' => 'skipped',
            'errors' => [],
        ];

        $forceSms = isset($options['force_sms']) ? (bool)$options['force_sms'] : null;
        $forceEmail = isset($options['force_email']) ? (bool)$options['force_email'] : null;

        // Send SMS first if enabled or forced
        if ($forceSms === true || ($forceSms !== false && $this->settings['sms']['enabled'])) {
            if (!empty($user['phone'])) {
                $smsResult = $this->smsProvider->send($user['phone'], $subject, $message, $options);
                $result['sms'] = $smsResult;

                if (!empty($smsResult['success'])) {
                    $result['status'] = 'sms_sent';
                    return $result;
                }

                if (!empty($smsResult['demo_mode'])) {
                    $result['status'] = 'demo_sms';
                    return $result;
                }

                $result['errors'][] = $smsResult['message'] ?? 'SMS failed';

                // fallback to email on SMS failure
                if (!$forceEmail && $this->settings['email']['enabled']) {
                    $forceEmail = true;
                }
            }
        }

        if ($forceEmail === true || ($forceEmail !== false && $this->settings['email']['enabled'])) {
            if (!empty($user['email'])) {
                $emailResult = $this->emailProvider->send($user['email'], $subject, $message, $options);
                $result['email'] = $emailResult;

                if (!empty($emailResult['success'])) {
                    $result['status'] = $result['status'] === 'sms_sent' ? 'sms_and_email_sent' : 'email_sent';
                    return $result;
                }

                $result['errors'][] = $emailResult['message'] ?? 'Email failed';
                $result['status'] = 'failed';
                return $result;
            }

            $result['errors'][] = 'No user email provided for fallback';
            $result['status'] = 'failed';
            return $result;
        }

        $result['status'] = 'no_action';
        return $result;
    }
}
