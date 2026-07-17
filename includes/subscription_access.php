<?php
/**
 * Subscription Access Control Helper Functions
 * Provides comprehensive permission checking for provider plans
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get the current active plan for a provider
 * @param int $provider_id The provider's user ID
 * @return array|null Returns plan details with subscription info or null
 */
function getProviderPlan(int $provider_id): ?array
{
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT p.*, ps.id as subscription_id, ps.start_date, ps.end_date, 
               ps.status as subscription_status, ps.grace_until, ps.auto_renew, 
               ps.last_boost_date
        FROM provider_subscriptions ps
        JOIN plans p ON ps.plan_id = p.id
        WHERE ps.provider_id = ? 
        AND ps.status IN ('active', 'grace_period') 
        AND (ps.end_date >= CURDATE() OR ps.grace_until >= CURDATE())
        ORDER BY ps.id DESC
        LIMIT 1
    ");
    $stmt->execute([$provider_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * Get plan details by ID
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
 * Check if a provider can create a new service
 * @param int $provider_id The provider's user ID
 * @return bool True if they can create more services
 */
function canCreateService(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    $service_limit = (int)$plan['service_limit'];
    
    // 0 means unlimited
    if ($service_limit === 0) {
        return true;
    }
    
    // Count current services
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM provider_services WHERE provider_id = (SELECT id FROM service_providers WHERE user_id = ?)");
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
 * Check if a provider can boost their listing
 * @param int $provider_id The provider's user ID
 * @return bool True if boost is available
 */
function canBoost(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    $cooldown = (int)$plan['boost_days_cooldown'];
    
    // Pro plan (cooldown = 0) can boost anytime
    if ($cooldown === 0) {
        return true;
    }
    
    // Check last boost date
    $last_boost = $plan['last_boost_date'] ?? null;
    
    if (!$last_boost) {
        return true;
    }
    
    $days_since_boost = (strtotime(date('Y-m-d')) - strtotime($last_boost)) / 86400;
    
    return $days_since_boost >= $cooldown;
}

/**
 * Get the boost cooldown days for a provider
 * @param int $provider_id The provider's user ID
 * @return int Number of days between boosts
 */
function getBoostCooldown(int $provider_id): int
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 14; // Default to Free plan cooldown
    }
    
    return (int)$plan['boost_days_cooldown'];
}

/**
 * Get the last boost date for a provider
 * @param int $provider_id The provider's user ID
 * @return string|null Last boost date
 */
function getLastBoostDate(int $provider_id): ?string
{
    $plan = getProviderPlan($provider_id);
    return $plan['last_boost_date'] ?? null;
}

/**
 * Record a boost action for a provider
 * @param int $provider_id The provider's user ID
 * @return bool True if successful
 */
function recordBoost(int $provider_id): bool
{
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            UPDATE provider_subscriptions 
            SET last_boost_date = CURDATE() 
            WHERE provider_id = ? AND status IN ('active', 'grace_period')
            AND (end_date >= CURDATE() OR grace_until >= CURDATE())
        ");
        $stmt->execute([$provider_id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error recording boost: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a provider can view advanced analytics
 * @param int $provider_id The provider's user ID
 * @return bool True if advanced analytics available
 */
function canViewAdvancedAnalytics(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return $plan['analytics_level'] === 'advanced';
}

/**
 * Check if a provider can view better analytics
 * @param int $provider_id The provider's user ID
 * @return bool True if better analytics available
 */
function canViewBetterAnalytics(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return in_array($plan['analytics_level'], ['better', 'advanced']);
}

/**
/**
 * Check if a provider can request verified status
 * @param int $provider_id The provider's user ID
 * @return bool True if verified request available
 */
function canRequestVerified(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return (int)$plan['verified_request'] === 1;
}

/**
 * Check if a provider has lead insights
 * @param int $provider_id The provider's user ID
 * @return bool True if lead insights available
 */
function hasLeadInsights(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return (int)$plan['lead_insights'] === 1;
}

/**
 * Check if a provider has featured badge
 * @param int $provider_id The provider's user ID
 * @return bool True if featured badge available
 */
function hasFeaturedBadge(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return (int)$plan['featured_badge'] === 1;
}

/**
 * Check if a provider has customer feedback insights
 * @param int $provider_id The provider's user ID
 * @return bool True if customer feedback available
 */
function hasCustomerFeedback(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return (int)$plan['customer_feedback'] === 1;
}

/**
 * Get the ranking level for a provider
 * @param int $provider_id The provider's user ID
 * @return string ranking level (standard, priority, featured)
 */
function getRankingLevel(int $provider_id): string
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 'standard';
    }
    
    return $plan['ranking_level'];
}

/**
 * Get the payout priority for a provider
 * @param int $provider_id The provider's user ID
 * @return string payout priority (standard, fast, instant)
 */
function getPayoutPriority(int $provider_id): string
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 'standard';
    }
    
    return $plan['payout_priority'];
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
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM provider_services WHERE provider_id = (SELECT id FROM service_providers WHERE user_id = ?)");
    $stmt->execute([$provider_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)$result['count'];
}

/**
 * Get days remaining in current subscription
 * @param int $provider_id The provider's user ID
 * @return int Number of days remaining (negative if expired)
 */
function getDaysRemaining(int $provider_id): int
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return 0;
    }
    
    $end_date = strtotime($plan['end_date']);
    $now = strtotime(date('Y-m-d'));
    
    return (int)(($end_date - $now) / 86400);
}

/**
 * Check if subscription is in grace period
 * @param int $provider_id The provider's user ID
 * @return bool True if in grace period
 */
function isInGracePeriod(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return $plan['subscription_status'] === 'grace_period';
}

/**
 * Activate a subscription for a provider
 * @param int $provider_id The provider's user ID
 * @param int $plan_id The plan ID to activate
 * @param int $duration_days Number of days for the subscription (default 30)
 * @return int|false Subscription ID on success, false on failure
 */
function activateSubscription(int $provider_id, int $plan_id, int $duration_days = 30)
{
    $db = Database::getInstance()->getConnection();
    
    try {
        // Deactivate any existing active subscriptions
        $stmt = $db->prepare("UPDATE provider_subscriptions SET status = 'cancelled' WHERE provider_id = ? AND status IN ('active', 'grace_period')");
        $stmt->execute([$provider_id]);
        
        // Calculate dates
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$duration_days} days"));
        
        // Insert new subscription
        $stmt = $db->prepare("
            INSERT INTO provider_subscriptions (provider_id, plan_id, start_date, end_date, status, auto_renew)
            VALUES (?, ?, ?, ?, 'active', 0)
        ");
        $stmt->execute([$provider_id, $plan_id, $start_date, $end_date]);
        
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error activating subscription: " . $e->getMessage());
        return false;
    }
}

/**
 * Downgrade provider to Free plan (called on expiry)
 * @param int $provider_id The provider's user ID
 * @return bool True if successful
 */
function downgradeToFree(int $provider_id): bool
{
    $db = Database::getInstance()->getConnection();
    
    try {
        // Get Free plan ID
        $stmt = $db->query("SELECT id FROM plans WHERE name = 'Free'");
        $free_plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$free_plan) {
            return false;
        }
        
        // Update current subscription to expired
        $stmt = $db->prepare("
            UPDATE provider_subscriptions 
            SET status = 'expired' 
            WHERE provider_id = ? AND status = 'active'
        ");
        $stmt->execute([$provider_id]);
        
        // Create new Free subscription (1 year duration)
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+1 year'));
        
        $stmt = $db->prepare("
            INSERT INTO provider_subscriptions (provider_id, plan_id, start_date, end_date, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$provider_id, $free_plan['id'], $start_date, $end_date]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error downgrading to free: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a pending payment record
 * @param int $provider_id The provider's user ID
 * @param int $plan_id The plan ID
 * @param float $amount Payment amount
 * @param string $currency Currency code
 * @return int|false Payment ID on success
 */
function createPendingPayment(int $provider_id, int $plan_id, float $amount, string $currency = 'FRW')
{
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO subscription_payments (provider_id, subscription_id, amount, currency, status)
            VALUES (?, NULL, ?, ?, 'pending')
        ");
        $stmt->execute([$provider_id, $amount, $currency]);
        
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Update payment status after processing
 * @param int $payment_id The payment ID
 * @param string $status New status (paid, failed, refunded)
 * @param string|null $transaction_ref Transaction reference
 * @return bool True if successful
 */
function updatePaymentStatus(int $payment_id, string $status, ?string $transaction_ref = null): bool
{
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            UPDATE subscription_payments 
            SET status = ?, transaction_ref = ? 
            WHERE id = ?
        ");
        $stmt->execute([$status, $transaction_ref, $payment_id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error updating payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment history for a provider
 * @param int $provider_id The provider's user ID
 * @param int $limit Number of records to return
 * @return array Payment records
 */
function getPaymentHistory(int $provider_id, int $limit = 10): array
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT * FROM subscription_payments 
        WHERE provider_id = ? 
        ORDER BY created_at DESC 
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute([$provider_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $days_remaining = getDaysRemaining($provider_id);
    
    return "{$plan['name']} plan - {$service_limit_text} services - {$days_remaining} days remaining";
}

/**
 * Check if provider has an active paid subscription
 * @param int $provider_id The provider's user ID
 * @return bool True if has paid plan
 */
function hasPaidPlan(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        return false;
    }
    
    return (float)$plan['monthly_price'] > 0;
}

/**
 * Get provider's internal ID from user ID
 * @param int $user_id The user ID
 * @return int|null Provider ID
 */
function getProviderIdFromUserId(int $user_id): ?int
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? (int)$result['id'] : null;
}

// ============================================================
// NEW PLAN FEATURE CHECK FUNCTIONS (Based on User Requirements)
// ============================================================

/**
 * Check if provider can use AI title suggestion
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function canUseAITitleSuggestion(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['ai_title_suggestion'] === 1;
}

/**
 * Check if provider can use AI description generator
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function canUseAIDescriptionGenerator(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['ai_description_generator'] === 1;
}

/**
 * Check if provider can use AI pricing recommendation
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function canUseAIPricingRecommendation(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['ai_pricing_recommendation'] === 1;
}

/**
 * Check if provider has basic lead insight
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function hasBasicLeadInsight(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['basic_lead_insight'] === 1;
}

/**
 * Check if provider has customer repeat insight
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function hasCustomerRepeatInsight(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['customer_repeat_insight'] === 1;
}

/**
 * Check if provider can export reports
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function canExportReports(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['export_reports'] === 1;
}

/**
 * Check if provider has faster payout
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function hasFasterPayout(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['faster_payout'] === 1;
}

/**
 * Check if provider has instant payout priority
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function hasInstantPayoutPriority(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['instant_payout_priority'] === 1;
}

/**
 * Check if provider can request verified badge
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function canRequestVerifiedBadge(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['verified_badge_request'] === 1;
}

/**
 * Check if provider has priority ranking boost
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function hasPriorityRanking(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['priority_ranking'] === 1;
}

/**
 * Check if provider has higher search ranking
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function hasHigherSearchRanking(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['higher_search_ranking'] === 1;
}

/**
 * Check if provider can boost any time
 * @param int $provider_id The provider's user ID
 * @return bool True if available
 */
function canBoostAnyTime(int $provider_id): bool
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return false;
    return (int)$plan['boost_any_time'] === 1;
}

/**
 * Get the boost fair limit for provider's plan
 * @param int $provider_id The provider's user ID
 * @return int Number of boost days allowed
 */
function getBoostFairLimit(int $provider_id): int
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return 0;
    return (int)$plan['boost_fair_limit'];
}

/**
 * Get the ranking boost days for provider's plan
 * @param int $provider_id The provider's user ID
 * @return int Number of boost days
 */
function getRankingBoostDays(int $provider_id): int
{
    $plan = getProviderPlan($provider_id);
    if (!$plan) return 14; // Default to Free plan
    return (int)$plan['ranking_boost_days'];
}

/**
 * Get complete plan features for display
 * @param int $provider_id The provider's user ID
 * @return array Plan features with status
 */
function getPlanFeatures(int $provider_id): array
{
    $plan = getProviderPlan($provider_id);
    
    if (!$plan) {
        // Return Free plan defaults
        return [
            'plan_name' => 'Free',
            'price' => 0,
            'service_limit' => 3,
            'photo_limit' => 3,
            'analytics_level' => 'basic',
            'ai_enabled' => false,
            'ai_title_suggestion' => false,
            'ai_description_generator' => false,
            'ai_pricing_recommendation' => false,
            'ranking_boost_days' => 14,
            'priority_ranking' => false,
            'higher_search_ranking' => false,
            'verified_badge_request' => false,
            'faster_payout' => false,
            'instant_payout_priority' => false,
            'basic_lead_insight' => false,
            'customer_repeat_insight' => false,
            'export_reports' => false,
            'boost_any_time' => false,
            'boost_fair_limit' => 0,
            'days_remaining' => 0,
            'is_paid' => false
        ];
    }
    
    return [
        'plan_name' => $plan['name'],
        'price' => (float)$plan['price'],
        'service_limit' => (int)$plan['service_limit'],
        'photo_limit' => (int)$plan['photo_limit'],
        'analytics_level' => $plan['analytics_level'],
        'ai_enabled' => (int)$plan['ai_enabled'] === 1,
        'ai_title_suggestion' => (int)$plan['ai_title_suggestion'] === 1,
        'ai_description_generator' => (int)$plan['ai_description_generator'] === 1,
        'ai_pricing_recommendation' => (int)$plan['ai_pricing_recommendation'] === 1,
        'ranking_boost_days' => (int)$plan['ranking_boost_days'],
        'priority_ranking' => (int)$plan['priority_ranking'] === 1,
        'higher_search_ranking' => (int)$plan['higher_search_ranking'] === 1,
        'verified_badge_request' => (int)$plan['verified_badge_request'] === 1,
        'faster_payout' => (int)$plan['faster_payout'] === 1,
        'instant_payout_priority' => (int)$plan['instant_payout_priority'] === 1,
        'basic_lead_insight' => (int)$plan['basic_lead_insight'] === 1,
        'customer_repeat_insight' => (int)$plan['customer_repeat_insight'] === 1,
        'export_reports' => (int)$plan['export_reports'] === 1,
        'boost_any_time' => (int)$plan['boost_any_time'] === 1,
        'boost_fair_limit' => (int)$plan['boost_fair_limit'],
        'days_remaining' => getDaysRemaining($provider_id),
        'is_paid' => (float)$plan['price'] > 0
    ];
}