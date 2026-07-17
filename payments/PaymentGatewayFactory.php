<?php
require_once 'PaymentGateway.php';
require_once 'FakeGateway.php';
require_once 'FakeMomoGateway.php';
require_once 'FakeAirtelGateway.php';
require_once 'FakeCardGateway.php';

/**
 * Factory class for creating payment gateway instances
 * Uses Factory pattern to dynamically select gateway based on configuration
 */
class PaymentGatewayFactory
{
    /**
     * Supported gateway names and aliases
     *
     * @return array<string,string>
     */
    private static function gatewayAliases(): array
    {
        return [
            'fake' => 'fake',
            'fake_momo' => 'mtn',
            'mtn' => 'mtn',
            'fake_airtel' => 'airtel',
            'airtel' => 'airtel',
            'fake_card' => 'card',
            'card' => 'card',
            'stripe' => 'card'
        ];
    }

    /**
     * Create a payment gateway instance based on the gateway name
     *
     * @param string $gatewayName Name of the gateway to create
     * @return PaymentGateway|null Gateway instance or null if not found
     */
    public static function create(string $gatewayName): ?PaymentGateway
    {
        $gatewayName = strtolower(trim($gatewayName));
        $gatewayAlias = self::gatewayAliases()[$gatewayName] ?? null;

        switch ($gatewayAlias) {
            case 'mtn':
                return new FakeMomoGateway();
            case 'airtel':
                return new FakeAirtelGateway();
            case 'card':
                return new FakeCardGateway();
            case 'fake':
                return new FakeGateway();
            default:
                return null;
        }
    }

    /**
     * Get the default gateway from system settings
     *
     * @return PaymentGateway|null Default gateway instance
     */
    public static function getDefaultGateway(): ?PaymentGateway
    {
        require_once '../config/database.php';
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_gateway'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $gatewayName = $result ? $result['setting_value'] : 'fake';
        return self::create($gatewayName);
    }

    /**
     * Get all available gateways
     *
     * @return array List of available gateway names
     */
    public static function getAvailableGateways(): array
    {
        require_once '../config/database.php';
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'available_methods'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $methods = [];
        if ($result && !empty($result['setting_value'])) {
            $methods = array_filter(array_map('trim', explode(',', $result['setting_value'])));
        }

        if (empty($methods)) {
            $methods = ['mtn', 'airtel', 'card'];
        }

        return array_values(array_unique($methods));
    }

    /**
     * Get friendly gateway labels for display
     *
     * @return array<string,string>
     */
    public static function getGatewayOptions(): array
    {
        return [
            'fake' => 'Fake Gateway (Testing)',
            'mtn' => 'MTN Mobile Money',
            'airtel' => 'Airtel Money',
            'card' => 'Card Payment (Visa/Mastercard)',
            'stripe' => 'Stripe'
        ];
    }

    /**
     * Get friendly label for a gateway
     *
     * @param string $gatewayName
     * @return string
     */
    public static function getGatewayDisplayName(string $gatewayName): string
    {
        $options = self::getGatewayOptions();
        return $options[strtolower($gatewayName)] ?? ucfirst($gatewayName);
    }

    /**
     * Check if a gateway is available
     *
     * @param string $gatewayName Gateway name to check
     * @return bool True if gateway is available
     */
    public static function isGatewayAvailable(string $gatewayName): bool
    {
        $gateway = self::create($gatewayName);
        return $gateway !== null && $gateway->isAvailable();
    }
}
?>