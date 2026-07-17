<?php
/**
 * Subscription Expiry Handler
 * Cron job to handle expired subscriptions and automatic downgrades
 * 
 * Run this script daily via cron or scheduled task
 * Example: 0 6 * * * php /path/to/cron_subscription_expiry.php
 */

require_once 'config/database.php';
require_once 'includes/subscription_access.php';

$db = Database::getInstance()->getConnection();

echo "=== Subscription Expiry Handler ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$today = date('Y-m-d');

// 1. Find subscriptions that expired today
$stmt = $db->prepare("
    SELECT ps.*, p.name as plan_name, p.monthly_price, u.email, u.full_name
    FROM provider_subscriptions ps
    JOIN plans p ON ps.plan_id = p.id
    JOIN users u ON ps.provider_id = u.id
    WHERE ps.status = 'active' 
    AND ps.end_date < ?
    AND p.monthly_price > 0
");
$stmt->execute([$today]);
$expired_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($expired_subscriptions) . " expired paid subscriptions\n";

foreach ($expired_subscriptions as $sub) {
    echo "\nProcessing provider ID: {$sub['provider_id']} ({$sub['full_name']})\n";
    echo "  Plan: {$sub['plan_name']} (expired {$sub['end_date']})\n";
    
    // Check if auto-renew is enabled
    if ($sub['auto_renew']) {
        // In production, this would trigger payment retry
        echo "  Auto-renew enabled - would attempt renewal\n";
        // For now, just downgrade
    }
    
    // Downgrade to Free plan
    if (downgradeToFree($sub['provider_id'])) {
        echo "  ✓ Downgraded to Free plan\n";
        
        // Log the downgrade
        error_log("Provider {$sub['provider_id']} ({$sub['full_name']}) downgraded from {$sub['plan_name']} to Free");
    } else {
        echo "  ✗ Failed to downgrade\n";
    }
}

// 2. Find subscriptions entering grace period (expired 1-7 days ago, not yet processed)
$grace_start = date('Y-m-d', strtotime('-7 days'));
$stmt = $db->prepare("
    SELECT ps.*, p.name as plan_name, u.email, u.full_name
    FROM provider_subscriptions ps
    JOIN plans p ON ps.plan_id = p.id
    JOIN users u ON ps.provider_id = u.id
    WHERE ps.status = 'active' 
    AND ps.end_date BETWEEN ? AND ?
    AND p.monthly_price > 0
");
$stmt->execute([$grace_start, $today]);
$grace_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nFound " . count($grace_subscriptions) . " subscriptions in grace period\n";

foreach ($grace_subscriptions as $sub) {
    // Set grace period (7 days from expiry)
    $grace_until = date('Y-m-d', strtotime($sub['end_date'] . ' +7 days'));
    
    $stmt = $db->prepare("UPDATE provider_subscriptions SET status = 'grace_period', grace_until = ? WHERE id = ?");
    $stmt->execute([$grace_until, $sub['id']]);
    
    echo "  Provider {$sub['provider_id']}: Set grace period until {$grace_until}\n";
}

// 3. Find grace periods that ended today - actually downgrade now
$stmt = $db->prepare("
    SELECT ps.*, p.name as plan_name, u.email, u.full_name
    FROM provider_subscriptions ps
    JOIN plans p ON ps.plan_id = p.id
    JOIN users u ON ps.provider_id = u.id
    WHERE ps.status = 'grace_period' 
    AND ps.grace_until < ?
");
$stmt->execute([$today]);
$final_expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nFound " . count($final_expired) . " grace periods ending today\n";

foreach ($final_expired as $sub) {
    echo "\nFinal downgrade for provider ID: {$sub['provider_id']} ({$sub['full_name']})\n";
    
    if (downgradeToFree($sub['provider_id'])) {
        echo "  ✓ Downgraded to Free plan\n";
        
        // In production: send email notification
        // $to = $sub['email'];
        // $subject = "Your subscription has been downgraded";
        // $message = "Your {$sub['plan_name']} plan has expired and been downgraded to Free...";
        // mail($to, $subject, $message);
    }
}

// 4. Clean up old cancelled/expired subscriptions (keep for 90 days)
$cleanup_date = date('Y-m-d', strtotime('-90 days'));
$stmt = $db->prepare("DELETE FROM provider_subscriptions WHERE status IN ('cancelled', 'expired') AND end_date < ?");
$stmt->execute([$cleanup_date]);
$deleted = $stmt->rowCount();

if ($deleted > 0) {
    echo "\nCleaned up {$deleted} old subscription records\n";
}

echo "\n=== Completed at: " . date('Y-m-d H:i:s') . " ===\n";