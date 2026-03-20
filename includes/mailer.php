<?php
// includes/mailer.php
// Mailer wrapper using PHPMailer for reliable email sending.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure PHPMailer is loaded
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

/**
 * Mailer class for BII LocalFinder
 * Comprehensive email notification system for all platform communications
 */
class Mailer
{
    protected $from;
    protected $fromName;
    protected $smtpConfig;

    /**
     * Constructor
     *
     * @param string|null $from
     * @param string|null $fromName
     */
    public function __construct($from = null, $fromName = null)
    {
        // Load settings from database
        $this->loadSmtpSettings();
        
        $this->from = $from ?: 'biilocalfinder@gmail.com';
        $this->fromName = $fromName ?: getPlatformName();
    }

    /**
     * Load SMTP settings from system settings
     */
    protected function loadSmtpSettings()
    {
        $this->smtpConfig = [
            'host' => getSetting('smtp_host', 'smtp.gmail.com'),
            'port' => intval(getSetting('smtp_port', 587)),
            'username' => getSetting('smtp_username', ''),
            'password' => getSetting('smtp_password', ''),
            'encryption' => getSetting('smtp_encryption', 'tls')
        ];
    }

    /**
     * Check if email notifications are enabled
     */
    protected function isEmailEnabled()
    {
        return isEmailNotificationsEnabled();
    }

    /**
     * Check if a provider has enabled a specific notification type (Email)
     *
     * @param int $providerId
     * @param string $notificationType The notification type (e.g., 'new_booking_email', 'chat_message_email')
     * @return bool
     */
    public static function isProviderNotificationEnabled($providerId, $notificationType)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT setting_value FROM provider_settings 
                WHERE provider_id = ? AND setting_key = ?
                LIMIT 1
            ");
            $stmt->execute([$providerId, 'notifications_' . $notificationType]);
            $result = $stmt->fetch();
            
            // Return setting value, default to true for important notifications
            if ($result) {
                return (bool)$result['setting_value'];
            }
            
            // Default values for different notification types
            $defaults = [
                'new_booking_email' => true,
                'new_booking_sms' => true,
                'new_booking_push' => true,
                'chat_message_email' => true,
                'chat_message_sms' => false,
                'chat_message_push' => true,
                'payment_received_email' => true,
                'payment_received_sms' => true,
                'review_received_email' => true,
                'review_received_sms' => false,
                'review_received_push' => true,
                'system_announcements_email' => true,
                'system_announcements_sms' => false,
                'marketing_email' => false,
                'marketing_sms' => false
            ];
            
            return $defaults[$notificationType] ?? true;
        } catch (Exception $e) {
            error_log("Error checking provider notification settings: " . $e->getMessage());
            return true; // Default to enabled if error occurs
        }
    }

    /**
     * Get all notification preferences for a provider
     *
     * @param int $providerId
     * @return array
     */
    public static function getProviderNotificationPreferences($providerId)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT setting_key, setting_value FROM provider_settings 
                WHERE provider_id = ? AND setting_key LIKE 'notifications_%'
            ");
            $stmt->execute([$providerId]);
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $preferences = [];
            foreach ($settings as $key => $value) {
                $settingName = str_replace('notifications_', '', $key);
                $preferences[$settingName] = (bool)$value;
            }
            
            return $preferences;
        } catch (Exception $e) {
            error_log("Error fetching provider notification preferences: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send an email using PHPMailer with configured SMTP settings
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param bool $isHtml
     * @param array $additionalHeaders
     * @param string|null $from
     * @param string|null $fromName
     * @return bool
     * @throws RuntimeException when PHPMailer throws an exception
     */
    public static function send($to, $subject, $body, $isHtml = true, array $additionalHeaders = [], $from = null, $fromName = null)
    {
        $mailer = new self();
        return $mailer->sendEmail($to, $subject, $body, $isHtml, $additionalHeaders, $from, $fromName);
    }

    /**
     * Instance method for sending email
     */
    public function sendEmail($to, $subject, $body, $isHtml = true, array $additionalHeaders = [], $from = null, $fromName = null)
    {
        // Check if email notifications are enabled
        if (!$this->isEmailEnabled()) {
            error_log("Email notifications are disabled. Skipping email to: {$to}");
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig['username'];
            $mail->Password = $this->smtpConfig['password'];
            $mail->SMTPSecure = $this->smtpConfig['encryption'];
            $mail->Port = $this->smtpConfig['port'];
            
            // Disable SSL certificate verification (for localhost/development)
            // WARNING: Only use this in development! For production, fix your SSL certificates.
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Set from address
            $mail->setFrom($from ?? $this->from, $fromName ?? $this->fromName);
            
            // Add recipient
            $mail->addAddress($to);
            
            // Set email format
            $mail->isHTML($isHtml);
            
            // Set subject and body
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Send email
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("PHPMailer error for {$to}: {$mail->ErrorInfo}");
            return false; // Return false instead of throwing exception
        }
    }

    /**
     * Send verification email to new users
     *
     * @param string $email User's email address
     * @param string $fullName User's full name
     * @param string $token Verification token
     * @return bool
     */
    public static function sendVerificationEmail($email, $fullName, $token)
    {
        // Check if email verification is enabled
        if (!isEmailVerificationEnabled()) {
            error_log("Email verification is disabled. Skipping verification email for: {$email}");
            return false;
        }

        $mailer = new self();
        
        $verifyUrl = sprintf(
            "%s://%s/bii_localfinder/verify.php?token=%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['SERVER_NAME'],
            urlencode($token)
        );
        
        $subject = "Verify Your " . getPlatformName() . " Account";
        
        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #0d6efd, #0dcaf0); padding: 40px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>Welcome to " . getPlatformName() . "</h1>
                        <p style='margin: 8px 0 0 0; opacity: 0.95;'>Verify your email to get started</p>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 20px 0; font-size: 16px;'>Hi <strong>{$fullName}</strong>,</p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.7;'>
                            Thank you for joining " . getPlatformName() . "! We're excited to have you on board.
                        </p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; color: #666;'>
                            To complete your account setup and start connecting with service providers, please verify your email address by clicking the button below:
                        </p>
                        
                        <div style='text-align: center; margin: 32px 0;'>
                            <a href='{$verifyUrl}' style='
                                background: linear-gradient(135deg, #0d6efd, #0dcaf0);
                                color: white;
                                padding: 14px 40px;
                                text-decoration: none;
                                border-radius: 8px;
                                display: inline-block;
                                font-weight: 600;
                                font-size: 16px;
                                transition: all 0.3s ease;
                            '>Verify Email Address</a>
                        </div>
                        
                        <div style='background: #f8f9fa; border-left: 4px solid #0d6efd; padding: 16px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #666;'>
                                <strong>✓ Button not working?</strong> Copy and paste this link in your browser:
                            </p>
                            <p style='margin: 8px 0 0 0; font-size: 12px; word-break: break-all; font-family: monospace; color: #0d6efd;'>{$verifyUrl}</p>
                        </div>
                        
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #856404;'>
                                ⏱️ This verification link expires in <strong>24 hours</strong>. Act now to secure your account!
                            </p>
                        </div>
                        
                        <p style='margin: 24px 0 0 0; font-size: 14px; color: #666;'>
                            Didn't create this account? No worries—simply ignore this email and your account won't be created.
                        </p>
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 24px 30px; background: #f8f9fa;'>
                        <p style='margin: 0 0 12px 0; font-size: 13px; color: #666;'>
                            Questions? Contact our support team:
                        </p>
                        <p style='margin: 4px 0; font-size: 13px;'>
                            📧 <a href='mailto:" . getContactEmail() . "' style='color: #0d6efd; text-decoration: none;'>" . getContactEmail() . "</a>
                        </p>
                        <p style='margin: 12px 0 0 0; font-size: 12px; color: #999;'>
                            This is an automated message. Please don't reply to this email.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        return $mailer->sendEmail($email, $subject, $body, true);
    }

    /**
     * Send verification OTP to new users
     *
     * @param string $email
     * @param string $fullName
     * @param string|int $otp
     * @param int $expiresInMinutes
     * @return bool
     */
    public static function sendVerificationOTP($email, $fullName, $otp, $expiresInMinutes = 60)
    {
        if (!isEmailVerificationEnabled()) {
            error_log("Email verification is disabled. Skipping OTP email for: {$email}");
            return false;
        }

        $mailer = new self();

        $subject = "Your " . getPlatformName() . " Verification Code";

        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #198754, #20c997); padding: 30px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>Email Verification</h1>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 24px 0; font-size: 16px;'>Hi {$fullName},</p>
                        
                        <p style='margin: 0 0 32px 0; font-size: 15px; line-height: 1.7; color: #666;'>
                            Your verification code is below. Enter this code to complete your account activation:
                        </p>
                        
                        <div style='background: #f8f9fa; border: 2px solid #198754; border-radius: 8px; padding: 24px; text-align: center; margin: 32px 0;'>
                            <p style='margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666;'>Your Code</p>
                            <p style='margin: 0; font-size: 42px; font-weight: 700; letter-spacing: 6px; color: #198754; font-family: monospace;'>{$otp}</p>
                        </div>
                        
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #856404;'>
                                ⏱️ This code expires in <strong>{$expiresInMinutes} minutes</strong>
                            </p>
                        </div>
                        
                        <p style='margin: 24px 0 0 0; font-size: 13px; color: #666;'>
                            🔒 Never share this code with anyone. " . getPlatformName() . " will never ask for it via email or message.
                        </p>
                        
                        <p style='margin: 16px 0 0 0; font-size: 13px; color: #666;'>
                            Didn't request this code? Ignore this email and your account will remain unverified.
                        </p>
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 20px 30px; background: #f8f9fa; text-align: center;'>
                        <p style='margin: 0; font-size: 12px; color: #999;'>
                            This is an automated message. Please don't reply to this email.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        return $mailer->sendEmail($email, $subject, $body, true);
    }

    /**
     * Send password reset OTP to user
     *
     * @param string $email
     * @param string $fullName
     * @param string|int $otp
     * @param int $expiresInMinutes
     * @return bool
     */
    public static function sendPasswordResetOTP($email, $fullName, $otp, $expiresInMinutes = 30)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping password reset email for: {$email}");
            return false;
        }

        $mailer = new self();

        $subject = "Reset Your " . getPlatformName() . " Password";

        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #dc3545, #ff6b7a); padding: 30px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>🔐 Password Reset</h1>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 24px 0; font-size: 16px;'>Hi {$fullName},</p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #666;'>
                            We received a password reset request for your account. Use the verification code below to create a new password:
                        </p>
                        
                        <div style='background: #fff5f5; border: 2px dashed #dc3545; border-radius: 8px; padding: 24px; text-align: center; margin: 32px 0;'>
                            <p style='margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666;'>Reset Code</p>
                            <p style='margin: 0; font-size: 42px; font-weight: 700; letter-spacing: 6px; color: #dc3545; font-family: monospace;'>{$otp}</p>
                        </div>
                        
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #856404;'>
                                ⏱️ Code expires in <strong>{$expiresInMinutes} minutes</strong>. Act quickly!
                            </p>
                        </div>
                        
                        <div style='background: #f0f7ff; border-left: 4px solid #0d6efd; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #003d7a;'>
                                ✓ <strong>Didn't request this?</strong> Ignore this email. Your account is safe.
                            </p>
                        </div>
                        
                        <p style='margin: 0; font-size: 13px; color: #666;'>
                            🔒 Never share this code. " . getPlatformName() . " staff will never ask for it.
                        </p>
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 24px 30px; background: #f8f9fa;'>
                        <p style='margin: 0 0 12px 0; font-size: 13px; color: #666;'>
                            Need help?
                        </p>
                        <p style='margin: 0; font-size: 13px;'>
                            <a href='mailto:" . getContactEmail() . "' style='color: #0d6efd; text-decoration: none;'>Contact Support</a>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        return $mailer->sendEmail($email, $subject, $body, true);
    }

    /**
     * Send booking notification to a provider
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $clientName
     * @param string $serviceDescription
     * @param int|null $providerId Provider ID to check notification preferences
     * @param string|null $subject
     * @return bool
     */
    public static function sendBookingNotification($toEmail, $toName, $clientName, $serviceDescription, $providerId = null, $subject = null)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping booking notification for: {$toEmail}");
            return false;
        }

        // Check if provider has enabled new booking email notifications
        if ($providerId !== null && !self::isProviderNotificationEnabled($providerId, 'new_booking_email')) {
            error_log("Provider {$providerId} has disabled new booking email notifications. Skipping for: {$toEmail}");
            return false;
        }

        $mailer = new self();
        $subject = $subject ?: "🔔 New Booking Request from {$clientName}";
        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #ffc107, #ff9800); padding: 30px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>📋 New Booking Request</h1>
                        <p style='margin: 8px 0 0 0; opacity: 0.95;'>Someone wants to book your service!</p>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 24px 0; font-size: 16px;'>Hi {$toName},</p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #666;'>
                            <strong>{$clientName}</strong> has submitted a booking request for your services.
                        </p>
                        
                        <div style='background: #f8f9fa; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin: 24px 0;'>
                            <p style='margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 600;'>Service Request Details</p>
                            <div style='background: white; padding: 16px; border-radius: 6px; margin: 12px 0 0 0; border: 1px solid #e9ecef;'>
                                <pre style='white-space: pre-wrap; margin: 0; font-family: inherit; font-size: 14px; color: #333; line-height: 1.6;'>{$serviceDescription}</pre>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 32px 0;'>
                            <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . "://" . $_SERVER['SERVER_NAME'] . "/provider/dashboard.php' style='
                                background: linear-gradient(135deg, #ffc107, #ff9800);
                                color: white;
                                padding: 14px 40px;
                                text-decoration: none;
                                border-radius: 8px;
                                display: inline-block;
                                font-weight: 600;
                                font-size: 16px;
                                transition: all 0.3s ease;
                            '>Review Request</a>
                        </div>
                        
                        <div style='background: #f0f7ff; border-left: 4px solid #0d6efd; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #003d7a;'>
                                ⏱️ <strong>Act quickly!</strong> Respond promptly to secure this booking.
                            </p>
                        </div>
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 20px 30px; background: #f8f9fa; text-align: center;'>
                        <p style='margin: 0; font-size: 12px; color: #999;'>
                            This is an automated message from " . getPlatformName() . ".
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        try {
            return (bool) $mailer->sendEmail($toEmail, $subject, $body, true);
        } catch (\Throwable $e) {
            error_log("sendBookingNotification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send booking status update to client
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $providerName
     * @param string $serviceDescription
     * @param string $status
     * @param string|null $additionalNotes
     * @return bool
     */
    public static function sendBookingStatusUpdate($toEmail, $toName, $providerName, $serviceDescription, $status, $additionalNotes = null)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping booking status update for: {$toEmail}");
            return false;
        }

        $mailer = new self();
        
        $statusTitles = [
            'confirmed' => '✅ Booking Confirmed',
            'completed' => '🎉 Service Completed',
            'cancelled' => '❌ Booking Cancelled',
            'reassigned' => '🔄 Provider Reassigned'
        ];
        
        $statusColors = [
            'confirmed' => '#198754',
            'completed' => '#0dcaf0',
            'cancelled' => '#dc3545',
            'reassigned' => '#0d6efd'
        ];
        
        $subject = $statusTitles[$status] ?? "Booking Update" . " - {$providerName}";
        
        $statusMessages = [
            'confirmed' => "Great news! Your booking with {$providerName} has been <strong>confirmed</strong>. They will contact you shortly to coordinate the service details.",
            'completed' => "Your service has been <strong>completed</strong>. Thank you for choosing {$providerName}! " . 
                          (isRatingRequiredAfterCompletion() ? "Would you like to leave a review?" : ""),
            'cancelled' => "Your booking has been <strong>cancelled</strong>. " . ($additionalNotes ?: "Contact support if you have any questions."),
            'reassigned' => "Your booking has been <strong>reassigned</strong> to a new provider. " . ($additionalNotes ?: "The new provider will contact you soon.")
        ];
        
        $message = $statusMessages[$status] ?? "There's an important update regarding your booking.";
        $bgColor = $statusColors[$status] ?? '#0d6efd';
        
        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, {$bgColor}, lighter); padding: 30px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>{$statusTitles[$status]}</h1>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 24px 0; font-size: 16px;'>Hi {$toName},</p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #666;'>
                            {$message}
                        </p>
                        
                        <div style='background: #f8f9fa; border-left: 4px solid {$bgColor}; padding: 20px; border-radius: 8px; margin: 24px 0;'>
                            <p style='margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 600;'>Booking Details</p>
                            <div style='background: white; padding: 16px; border-radius: 6px; margin: 12px 0 0 0; border: 1px solid #e9ecef; font-size: 14px;'>
                                <p style='margin: 0 0 10px 0;'><strong>Provider:</strong> {$providerName}</p>
                                <p style='margin: 0 0 10px 0;'><strong>Service:</strong> {$serviceDescription}</p>
                                <p style='margin: 0;'><strong>Status:</strong> <span style='color: {$bgColor}; font-weight: bold;'>{$status}</span></p>
                            </div>
                        </div>
                        
                        " . ($additionalNotes ? "<div style='background: #f0f7ff; border-left: 4px solid #0d6efd; padding: 14px; border-radius: 6px; margin: 24px 0;'><p style='margin: 0; font-size: 13px; color: #003d7a;'><strong>📌 Additional Info:</strong> {$additionalNotes}</p></div>" : "") . "
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 24px 30px; background: #f8f9fa;'>
                        <p style='margin: 0 0 12px 0; font-size: 13px; color: #666;'>
                            Need assistance?
                        </p>
                        <p style='margin: 0; font-size: 13px;'>
                            <a href='mailto:" . getContactEmail() . "' style='color: #0d6efd; text-decoration: none;'>Contact Our Support Team</a>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        try {
            return (bool) $mailer->sendEmail($toEmail, $subject, $body, true);
        } catch (\Throwable $e) {
            error_log("sendBookingStatusUpdate error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send provider account notification
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $notificationType
     * @param string|null $customMessage
     * @param string|null $additionalInfo
     * @return bool
     */
    public static function sendProviderAccountNotification($toEmail, $toName, $notificationType, $customMessage = null, $additionalInfo = null)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping provider notification for: {$toEmail}");
            return false;
        }

        $mailer = new self();
        
        $notificationConfig = [
            'account_approved' => [
                'subject' => 'Your ' . getPlatformName() . ' Account Has Been Approved',
                'template' => "Congratulations {$toName}! Your provider account has been approved and is now active. You can start receiving booking requests immediately."
            ],
            'account_rejected' => [
                'subject' => 'Your ' . getPlatformName() . ' Application Status',
                'template' => "Thank you for your interest in " . getPlatformName() . ". After reviewing your application, we regret to inform you that we cannot approve your provider account at this time."
            ],
            'account_suspended' => [
                'subject' => 'Account Suspension Notice',
                'template' => "Your " . getPlatformName() . " provider account has been temporarily suspended due to violations of our terms of service."
            ],
            'document_rejected' => [
                'subject' => 'Document Verification Update',
                'template' => "Some of the documents you submitted require additional verification or clarification."
            ],
            'verification_upgrade' => [
                'subject' => 'Upgrade Your Verification Level',
                'template' => "Great news! You're eligible for a verification level upgrade on " . getPlatformName() . "."
            ],
            'warning' => [
                'subject' => 'Important Notice Regarding Your Account',
                'template' => "We need to bring an important matter to your attention regarding your " . getPlatformName() . " account."
            ]
        ];
        
        $config = $notificationConfig[$notificationType] ?? $notificationConfig['warning'];
        $subject = $config['subject'];
        $message = $customMessage ?: $config['template'];
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height:1.6; color:#333;'>
            <div style='max-width:600px;margin:0 auto;padding:20px;'>
                <h2>{$subject}</h2>
                <p>Hello {$toName},</p>
                <p>{$message}</p>";
                
        if ($additionalInfo) {
            $body .= "<div style='background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;'>
                        <strong>Additional Information:</strong><br>
                        {$additionalInfo}
                      </div>";
        }
        
        $body .= "
                <p>If you have any questions or need further assistance, please don't hesitate to contact our support team.</p>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                <p style='font-size:12px;color:#666;'>
                    " . getPlatformName() . " Provider Support<br>
                    <a href='mailto:" . getContactEmail() . "'>" . getContactEmail() . "</a>
                </p>
            </div>
        </body>
        </html>";

        try {
            return (bool) $mailer->sendEmail($toEmail, $subject, $body, true);
        } catch (\Throwable $e) {
            error_log("sendProviderAccountNotification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send review reminder to client after completed booking
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $providerName
     * @param string $serviceDescription
     * @param string $reviewUrl
     * @return bool
     */
    public static function sendReviewReminder($toEmail, $toName, $providerName, $serviceDescription, $reviewUrl)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping review reminder for: {$toEmail}");
            return false;
        }

        // Check if rating is required after completion
        if (!isRatingRequiredAfterCompletion()) {
            error_log("Rating after completion is not required. Skipping review reminder for: {$toEmail}");
            return false;
        }

        $mailer = new self();
        
        $subject = "⭐ Share Your Experience with {$providerName}";
        
        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #ffc107, #ff9800); padding: 30px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>⭐ Your Feedback Matters</h1>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 24px 0; font-size: 16px;'>Hi {$toName},</p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #666;'>
                            Your service with <strong>{$providerName}</strong> is now complete. We'd love to hear about your experience!
                        </p>
                        
                        <div style='background: #f8f9fa; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin: 24px 0;'>
                            <p style='margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 600;'>Service Completed</p>
                            <p style='margin: 0; font-size: 15px; color: #333;'>{$serviceDescription}</p>
                        </div>
                        
                        <div style='background: #f0f7ff; border-left: 4px solid #0d6efd; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #003d7a;'>
                                💡 Your review helps {$providerName} improve and helps others find trustworthy service providers.
                            </p>
                        </div>
                        
                        <div style='text-align: center; margin: 32px 0;'>
                            <a href='{$reviewUrl}' style='
                                background: linear-gradient(135deg, #ffc107, #ff9800);
                                color: white;
                                padding: 14px 40px;
                                text-decoration: none;
                                border-radius: 8px;
                                display: inline-block;
                                font-weight: 600;
                                font-size: 16px;
                                transition: all 0.3s ease;
                            '>Write a Review</a>
                        </div>
                        
                        <p style='margin: 24px 0 0 0; font-size: 13px; color: #999; text-align: center;'>
                            ⏱️ Review link expires in 7 days
                        </p>
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 24px 30px; background: #f8f9fa;'>
                        <p style='margin: 0; font-size: 13px; color: #666; text-align: center;'>
                            Thank you for building a trusted community on " . getPlatformName() . "!
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        try {
            return (bool) $mailer->sendEmail($toEmail, $subject, $body, true);
        } catch (\Throwable $e) {
            error_log("sendReviewReminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bulk notifications with error handling
     *
     * @param array $recipients Array of ['email' => '', 'name' => '']
     * @param string $subject
     * @param string $message
     * @param callable|null $progressCallback
     * @return array ['sent' => int, 'failed' => int, 'errors' => array]
     */
    public static function sendBulkNotifications($recipients, $subject, $message, $progressCallback = null)
    {
        // Check if email notifications are enabled
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping bulk notification send.");
            return ['sent' => 0, 'failed' => count($recipients), 'errors' => ['Email notifications are disabled']];
        }

        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $mailer = new self();

        foreach ($recipients as $index => $recipient) {
            try {
                if ($mailer->sendEmail($recipient['email'], $subject, $message, true)) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to send to: {$recipient['email']}";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error sending to {$recipient['email']}: " . $e->getMessage();
            }

            // Call progress callback if provided
            if ($progressCallback && is_callable($progressCallback)) {
                $progressCallback($index + 1, count($recipients));
            }

            // Small delay to avoid overwhelming the SMTP server
            usleep(100000); // 0.1 second
        }

        return $results;
    }

    /**
     * Test SMTP configuration
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function testSmtpConfiguration()
    {
        $mailer = new self();
        
        try {
            $testEmail = $mailer->smtpConfig['username'];
            $testSubject = "SMTP Configuration Test - " . getPlatformName();
            $testBody = "
            <html>
            <body>
                <h2>SMTP Test Successful</h2>
                <p>This is a test email to verify that your SMTP configuration is working correctly.</p>
                <p><strong>Platform:</strong> " . getPlatformName() . "</p>
                <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>SMTP Server:</strong> {$mailer->smtpConfig['host']}:{$mailer->smtpConfig['port']}</p>
            </body>
            </html>";
            
            $result = $mailer->sendEmail($testEmail, $testSubject, $testBody, true);
            
            return [
                'success' => $result,
                'message' => $result ? 
                    'SMTP configuration test successful! Test email sent.' : 
                    'Failed to send test email.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send announcement/generic message
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $message
     * @param int|null $providerId Optional provider ID to check system announcements preference
     * @return bool
     */
    public static function sendAnnouncement($toEmail, $toName, $subject, $message, $providerId = null)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping announcement for: {$toEmail}");
            return false;
        }

        // Check if provider has enabled system announcements
        if ($providerId !== null && !self::isProviderNotificationEnabled($providerId, 'system_announcements_email')) {
            error_log("Provider {$providerId} has disabled system announcement emails. Skipping for: {$toEmail}");
            return false;
        }

        $mailer = new self();
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height:1.6; color:#333;'>
            <div style='max-width:600px;margin:0 auto;padding:20px;'>
                <p>Hello {$toName},</p>
                {$message}
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                <p style='font-size:12px;color:#666;'>
                    " . getPlatformName() . "<br>
                    <a href='mailto:" . getContactEmail() . "'>" . getContactEmail() . "</a>
                </p>
            </div>
        </body>
        </html>";

        try {
            $result = $mailer->sendEmail($toEmail, $subject, $body, true);
            
            // Log the notification attempt to notification_logs table
            // Use NULL for user_id instead of 0 to comply with foreign key constraint
            if ($result) {
                global $db;
                try {
                    $stmt = $db->prepare("
                        INSERT INTO notification_logs 
                        (user_id, user_type, notification_type, subject, message, target_audience, sent_via, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        null, // user_id (NULL for system/broadcast messages)
                        'admin',
                        'announcement',
                        $subject,
                        substr($message, 0, 255), // Truncate message to 255 chars for subject field
                        'broadcast',
                        'email',
                        'sent'
                    ]);
                } catch (\Exception $logError) {
                    error_log("Failed to log announcement success: " . $logError->getMessage());
                }
            }
            
            return (bool) $result;
        } catch (\Throwable $e) {
            error_log("sendAnnouncement error: PHPMailer error: " . $e->getMessage());
            
            // Log failed notification with NULL user_id
            try {
                global $db;
                $stmt = $db->prepare("
                    INSERT INTO notification_logs 
                    (user_id, user_type, notification_type, subject, message, target_audience, sent_via, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    null,
                    'admin',
                    'announcement',
                    $subject,
                    substr($message, 0, 255),
                    'broadcast',
                    'email',
                    'failed'
                ]);
            } catch (\Exception $logError) {
                error_log("Failed to log announcement error: " . $logError->getMessage());
            }
            
            return false;
        }
    }

    /**
     * Send complaint notification to admin
     *
     * @param string $toEmail
     * @param string $toName
     * @param int $complaintId
     * @param string $complaintType
     * @param string $priorityLevel
     * @return bool
     */
    public static function sendComplaintNotification($toEmail, $toName, $complaintId, $complaintType, $priorityLevel)
    {
        if (!isEmailNotificationsEnabled()) {
            error_log("Email notifications are disabled. Skipping complaint notification for: {$toEmail}");
            return false;
        }

        $mailer = new self();
        
        $complaintRef = "COMP" . str_pad($complaintId, 6, '0', STR_PAD_LEFT);
        $priorityColor = $priorityLevel === 'high' ? '#dc3545' : ($priorityLevel === 'medium' ? '#ffc107' : '#198754');
        $priorityIcon = $priorityLevel === 'high' ? '🔴' : ($priorityLevel === 'medium' ? '🟡' : '🟢');
        
        $subject = "{$priorityIcon} New Complaint - {$complaintRef} [{$priorityLevel}]";
        
        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, {$priorityColor}, #ff6b7a); padding: 30px 20px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>{$priorityIcon} New Complaint Received</h1>
                        <p style='margin: 8px 0 0 0; opacity: 0.95;'>Action Required</p>
                    </div>
                    
                    <div style='padding: 40px 30px;'>
                        <p style='margin: 0 0 24px 0; font-size: 16px;'>Hi {$toName},</p>
                        
                        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #666;'>
                            A new complaint has been submitted on " . getPlatformName() . " and requires your immediate review and action.
                        </p>
                        
                        <div style='background: {$priorityColor}1a; border-left: 4px solid {$priorityColor}; padding: 20px; border-radius: 8px; margin: 24px 0;'>
                            <p style='margin: 0 0 16px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 600;'>Complaint Details</p>
                            <div style='background: white; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef;'>
                                <p style='margin: 0 0 12px 0; font-size: 14px;'>
                                    <strong>Reference:</strong> <span style='font-family: monospace; color: {$priorityColor};'>{$complaintRef}</span>
                                </p>
                                <p style='margin: 0 0 12px 0; font-size: 14px;'>
                                    <strong>Type:</strong> {$complaintType}
                                </p>
                                <p style='margin: 0 0 0 0; font-size: 14px;'>
                                    <strong>Priority:</strong> <span style='color: {$priorityColor}; font-weight: bold;'>" . ucfirst($priorityLevel) . "</span>
                                </p>
                            </div>
                        </div>
                        
                        <div style='background: #f0f7ff; border-left: 4px solid #0d6efd; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #003d7a;'>
                                	🖋️ <strong>Submitted:</strong> " . date('M j, Y \\a\\t g:i A') . "
                            </p>
                        </div>
                        
                        <div style='text-align: center; margin: 32px 0;'>
                            <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . "://" . $_SERVER['SERVER_NAME'] . "/admin/complaints.php?id={$complaintId}' style='
                                background: linear-gradient(135deg, {$priorityColor}, #ff6b7a);
                                color: white;
                                padding: 14px 40px;
                                text-decoration: none;
                                border-radius: 8px;
                                display: inline-block;
                                font-weight: 600;
                                font-size: 16px;
                                transition: all 0.3s ease;
                            '>Review Complaint</a>
                        </div>
                        
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 14px; border-radius: 6px; margin: 24px 0;'>
                            <p style='margin: 0; font-size: 13px; color: #856404;'>
                                ⚠️ <strong>" . (strtoupper($priorityLevel) === 'HIGH' ? 'High Priority - Requires immediate attention!' : 'Review and address this complaint promptly.') . "</strong>
                            </p>
                        </div>
                    </div>
                    
                    <div style='border-top: 1px solid #e9ecef; padding: 20px 30px; background: #f8f9fa; text-align: center;'>
                        <p style='margin: 0; font-size: 12px; color: #999;'>
                            This is an automated notification from " . getPlatformName() . " complaint system.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        try {
            return (bool) $mailer->sendEmail($toEmail, $subject, $body, true);
        } catch (\Throwable $e) {
            error_log("sendComplaintNotification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send custom email (generic email for sharing and general communications)
     *
     * @param string $toEmail
     * @param string $subject
     * @param string $body
     * @param string|null $toName
     * @return bool
     */
    public static function sendCustomEmail($toEmail, $subject, $body, $toName = null)
    {
        try {
            $mailer = new Mailer();
            $result = $mailer->sendEmail($toEmail, $subject, $body, true);
            
            if ($result) {
                error_log("Email successfully sent to {$toEmail}");
                return true;
            } else {
                error_log("Failed to send email to {$toEmail}");
                return false;
            }
        } catch (\Throwable $e) {
            error_log("sendCustomEmail exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send offer accepted notification to client
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $providerName
     * @param float $finalPrice
     * @param int $bookingId
     * @return bool
     */
    public static function sendOfferAcceptedNotification($toEmail, $toName, $providerName, $finalPrice, $bookingId)
    {
        try {
            $subject = "🎉 Your Offer Was Accepted - Booking Confirmed!";
            
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #198754, #20c997); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                        <h2 style='margin: 0; font-size: 24px;'>✅ Booking Confirmed!</h2>
                    </div>
                    
                    <p>Hello <strong>{$toName}</strong>,</p>
                    
                    <p>Great news! <strong>{$providerName}</strong> has accepted your price offer.</p>
                    
                    <div style='background: #f8f9fa; border-left: 4px solid #198754; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='margin: 0 0 10px 0;'><strong>Booking Details:</strong></p>
                        <p style='margin: 5px 0;'><strong>Booking ID:</strong> #" . str_pad($bookingId, 6, '0', STR_PAD_LEFT) . "</p>
                        <p style='margin: 5px 0;'><strong>Provider:</strong> {$providerName}</p>
                        <p style='margin: 5px 0; font-size: 18px; color: #198754;'><strong>Agreed Price:</strong> RWF " . number_format($finalPrice, 0) . "</p>
                    </div>
                    
                    <p><strong>What's Next?</strong></p>
                    <ul style='margin: 10px 0;'>
                        <li>Your booking is now confirmed and locked in</li>
                        <li>The provider will be notified of the confirmed booking</li>
                        <li>You can view all details in your bookings dashboard</li>
                        <li>Payment will be processed as per the agreed terms</li>
                    </ul>
                    
                    <p style='margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;'>
                        <a href='http://localhost/bii_localfinder/client/my-bookings.php?view=bookings' style='background: #198754; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>View Your Bookings</a>
                    </p>
                    
                    <p style='color: #666; font-size: 12px; margin-top: 20px;'>
                        If you have any questions, please contact us at support@biilocalfinder.com
                    </p>
                </div>
            </body>
            </html>";
            
            return self::send($toEmail, $subject, $body, true, [], null, 'BII LocalFinder');
        } catch (\Throwable $e) {
            error_log("sendOfferAcceptedNotification exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send offer acceptance confirmation to provider
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $clientName
     * @param float $finalPrice
     * @param int $bookingId
     * @param int|null $providerId Provider ID to check notification preferences
     * @return bool
     */
    public static function sendOfferAcceptanceConfirmation($toEmail, $toName, $clientName, $finalPrice, $bookingId, $providerId = null)
    {
        // Check if provider has enabled new booking email notifications
        if ($providerId !== null && !self::isProviderNotificationEnabled($providerId, 'new_booking_email')) {
            error_log("Provider {$providerId} has disabled new booking email notifications. Skipping offer acceptance confirmation for: {$toEmail}");
            return false;
        }

        try {
            $subject = "📌 Your Price Offer Was Accepted - Booking Confirmed";
            
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                        <h2 style='margin: 0; font-size: 24px;'>✅ Booking Confirmed!</h2>
                    </div>
                    
                    <p>Hello <strong>{$toName}</strong>,</p>
                    
                    <p>Excellent news! <strong>{$clientName}</strong> has accepted your price offer and the booking is now confirmed.</p>
                    
                    <div style='background: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='margin: 0 0 10px 0;'><strong>Booking Details:</strong></p>
                        <p style='margin: 5px 0;'><strong>Booking ID:</strong> #" . str_pad($bookingId, 6, '0', STR_PAD_LEFT) . "</p>
                        <p style='margin: 5px 0;'><strong>Client:</strong> {$clientName}</p>
                        <p style='margin: 5px 0; font-size: 18px; color: #0d6efd;'><strong>Confirmed Price:</strong> RWF " . number_format($finalPrice, 0) . "</p>
                    </div>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul style='margin: 10px 0;'>
                        <li>The booking is locked in with the agreed price</li>
                        <li>Review the booking details in your dashboard</li>
                        <li>Communicate with the client regarding service delivery</li>
                        <li>Update the booking status as work progresses</li>
                    </ul>
                    
                    <p style='margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;'>
                        <a href='http://localhost/bii_localfinder/provider/bookings.php?view=bookings' style='background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>View Confirmed Booking</a>
                    </p>
                    
                    <p style='color: #666; font-size: 12px; margin-top: 20px;'>
                        If you have any questions, please contact us at support@biilocalfinder.com
                    </p>
                </div>
            </body>
            </html>";
            
            return self::send($toEmail, $subject, $body, true, [], null, 'BII LocalFinder');
        } catch (\Throwable $e) {
            error_log("sendOfferAcceptanceConfirmation exception: " . $e->getMessage());
            return false;
        }
    }
}