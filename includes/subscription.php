<?php
/**
 * Subscription Helper Functions
 * Provides functions to manage provider subscriptions and plan limits
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get the current plan for a provider
 * @param int $provider_id The provider's user ID
 * @return array|null Returns plan details or null if no subscription
 */
function getProviderPlan(int $provider_id): ?array
{
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT p.*, ps.start_date, ps.end_date, ps.status as subscription_status
        FROM provider_subscriptions ps
        JOIN plans p ON ps.plan_id = p.id
        WHERE ps.provider_id = ? AND ps.status = 'active' AND ps.end_date >= CURDATE()
        ORDER BY ps.id DESC
        LIMIT 1
    ");
    $stmt->execute([$provider_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * Check if a provider can create a new service
 * @param int $provider_id The provider's user ID
 * @return bool True if they can create more services
 */
function canCreateService(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        // No active subscription - default to Free plan limits
        return false;
    }
    
    $service_limit = (int)$plan['service_limit'];
    
    // 0 means unlimited
    if ($service_limit === 0) {
        return true;
    }
    
    // Count current services
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE provider_id = ?");
    $stmt->execute([$provider_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)$result['count'] < $service_limit;
}

/**
 * Get the service limit for a provider's plan
 * @param int $provider_id The provider's user ID
 * @return int The service limit (0 = unlimited)
 */
function getServiceLimit(int $provider_id): int
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 3; // Default to Free plan limit
    }
    
    return (int)$plan['service_limit'];
}

/**
 * Get the photo limit for a provider's plan
 * @param int $provider_id The provider's user ID
 * @return int The photo limit (0 = unlimited)
 */
function getPhotoLimit(int $provider_id): int
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 3; // Default to Free plan limit
    }
    
    return (int)$plan['photo_limit'];
}

/**
 * Check if a provider can use AI features
 * @param int $provider_id The provider's user ID
 * @return bool True if AI is enabled for their plan
 */
function canUseAI(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return (int)$plan['ai_enabled'] === 1;
}

/**
 * Get the analytics level for a provider's plan
 * @param int $provider_id The provider's user ID
 * @return string The analytics level (none, basic, better, advanced)
 */
function getAnalyticsLevel(int $provider_id): string
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 'none';
    }
    
    return $plan['analytics_level'];
}

/**
 * Get the number of services a provider has created
 * @param int $provider_id The provider's user ID
 * @return int The number of services
 */
function getProviderServiceCount(int $provider_id): int
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE provider_id = ?");
    $stmt->execute([$provider_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)$result['count'];
}

/**
 * Get all available plans
 * @return array List of all plans
 */
function getAllPlans(): array
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM plans ORDER BY price ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get a specific plan by ID
 * @param int $plan_id The plan ID
 * @return array|null Plan details or null
 */
function getPlanById(int $plan_id): ?array
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * Activate a subscription for a provider
 * @param int $provider_id The provider's user ID
 * @param int $plan_id The plan ID to activate
 * @param int $duration_days Number of days for the subscription (default 30)
 * @return bool True if successful
 */
function activateSubscription(int $provider_id, int $plan_id, int $duration_days = 30): bool
{
    $db = Database::getInstance()->getConnection();
    
    try {
        // Deactivate any existing active subscriptions
        $stmt = $db->prepare("UPDATE provider_subscriptions SET status = 'cancelled' WHERE provider_id = ? AND status = 'active'");
        $stmt->execute([$provider_id]);
        
        // Calculate dates
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$duration_days} days"));
        
        // Insert new subscription
        $stmt = $db->prepare("
            INSERT INTO provider_subscriptions (provider_id, plan_id, start_date, end_date, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$provider_id, $plan_id, $start_date, $end_date]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error activating subscription: " . $e->getMessage());
        return false;
    }
}

/**
 * Get subscription status message for a provider
 * @param int $provider_id The provider's user ID
 * @return string Status message
 */
function getSubscriptionStatusMessage(int $provider_id): string
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return "No active subscription";
    }
    
    $service_limit = $plan['service_limit'];
    $service_limit_text = ($service_limit === 0) ? "Unlimited" : $service_limit;
    
    return "{$plan['name']} plan - {$service_limit_text} services";
}