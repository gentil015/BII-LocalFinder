<?php
/**
 * Negotiation System Helper Functions
 * Utility functions for managing the negotiation system
 */

/**
 * Format negotiation status for display
 */
function formatNegotiationStatus($status) {
    $statuses = [
        'pending' => ['label' => 'Pending', 'class' => 'warning', 'icon' => 'hourglass-half'],
        'accepted' => ['label' => 'Accepted', 'class' => 'success', 'icon' => 'check-circle'],
        'rejected' => ['label' => 'Rejected', 'class' => 'danger', 'icon' => 'times-circle'],
        'expired' => ['label' => 'Expired', 'class' => 'secondary', 'icon' => 'clock'],
        'withdrawn' => ['label' => 'Withdrawn', 'class' => 'muted', 'icon' => 'ban'],
    ];
    
    return $statuses[$status] ?? ['label' => $status, 'class' => 'secondary', 'icon' => 'question'];
}

/**
 * Get time remaining until expiry in human-readable format
 */
function getTimeRemaining($expires_at) {
    $now = new DateTime();
    $expiry = new DateTime($expires_at);
    
    if ($expiry <= $now) {
        return 'Expired';
    }
    
    $diff = $expiry->diff($now);
    
    if ($diff->days > 0) {
        return $diff->days . 'd ' . $diff->h . 'h remaining';
    } elseif ($diff->h > 0) {
        return $diff->h . 'h ' . $diff->i . 'm remaining';
    } elseif ($diff->i > 0) {
        return $diff->i . 'm ' . $diff->s . 's remaining';
    } else {
        return $diff->s . 's remaining';
    }
}

/**
 * Get percentage of negotiation time used
 */
function getNegotiationTimeUsage($created_at, $expires_at, $now = null) {
    if (!$now) {
        $now = new DateTime();
    }
    
    $start = new DateTime($created_at);
    $end = new DateTime($expires_at);
    $current = new DateTime($now);
    
    $totalSeconds = $end->getTimestamp() - $start->getTimestamp();
    $usedSeconds = $current->getTimestamp() - $start->getTimestamp();
    
    if ($totalSeconds <= 0) return 100;
    
    $percentage = min(100, max(0, ($usedSeconds / $totalSeconds) * 100));
    return round($percentage);
}

/**
 * Get action description for display
 */
function getActionDescription($action_type) {
    $descriptions = [
        'offer_created' => 'Client created an offer',
        'offer_accepted' => 'Offer was accepted',
        'offer_rejected' => 'Offer was rejected',
        'offer_expired' => 'Offer expired',
        'counteroffer_created' => 'Provider sent a counter-offer',
        'counteroffer_accepted' => 'Counter-offer was accepted',
        'counteroffer_rejected' => 'Counter-offer was rejected',
        'counteroffer_expired' => 'Counter-offer expired',
        'final_agreement' => 'Final price agreement',
    ];
    
    return $descriptions[$action_type] ?? $action_type;
}

/**
 * Format currency for display
 */
function formatCurrency($amount, $currency = 'RWF') {
    return $currency . ' ' . number_format($amount, 0, '.', ',');
}

/**
 * Check if user can create new offer for booking
 */
function canCreateNewOffer($db, $booking_id, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM service_offers 
        WHERE booking_id = ? AND client_id = ? AND status IN ('pending', 'accepted')
    ");
    $stmt->execute([$booking_id, $user_id]);
    return $stmt->fetchColumn() == 0;
}

/**
 * Get negotiation status HTML badge
 */
function getNegotiationStatusBadge($status) {
    $config = formatNegotiationStatus($status);
    return sprintf(
        '<span class="badge bg-%s"><i class="fas fa-%s me-1"></i>%s</span>',
        $config['class'],
        $config['icon'],
        $config['label']
    );
}

/**
 * Get price difference info
 */
function getPriceDifference($original_price, $offered_price) {
    $diff = $offered_price - $original_price;
    $percent = $original_price ? ($diff / $original_price) * 100 : 0;
    
    return [
        'difference' => $diff,
        'percentage' => $percent,
        'is_lower' => $diff < 0,
        'is_higher' => $diff > 0,
        'is_same' => $diff == 0,
    ];
}

/**
 * Get negotiation round progress
 */
function getNegotiationProgress($current_round, $max_rounds = 3) {
    $percentage = ($current_round / $max_rounds) * 100;
    return [
        'current' => $current_round,
        'max' => $max_rounds,
        'percentage' => $percentage,
        'has_more_rounds' => $current_round < $max_rounds,
    ];
}

/**
 * Send negotiation notification to user
 * (Requires email/notification system integration)
 */
function sendNegotiationNotification($db, $user_id, $type, $data = []) {
    // This would integrate with your notification system
    $notifications = [
        'offer_received' => 'You have received a new offer',
        'offer_accepted' => 'Your offer has been accepted',
        'offer_rejected' => 'Your offer was rejected',
        'counteroffer_received' => 'Provider sent a counter-offer',
        'price_finalized' => 'Price agreement finalized',
    ];
    
    $message = $notifications[$type] ?? 'Negotiation update';
    
    // TODO: Integrate with notification system
    error_log("Notification: [$type] $message");
    
    return true;
}

/**
 * Get negotiation summary for booking
 */
function getNegotiationSummary($db, $booking_id) {
    // Get offer history
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_actions,
            SUM(CASE WHEN action_type LIKE '%offer%' THEN 1 ELSE 0 END) as offer_count,
            SUM(CASE WHEN action_type LIKE '%counteroffer%' THEN 1 ELSE 0 END) as counteroffer_count,
            MAX(created_at) as last_action
        FROM negotiation_history
        WHERE booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $history = $stmt->fetch();
    
    // Get finalized price
    $stmt = $db->prepare("
        SELECT * FROM finalized_service_prices WHERE booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $finalized = $stmt->fetch();
    
    return [
        'history' => $history,
        'finalized' => $finalized,
        'total_interactions' => $history['total_actions'] ?? 0,
        'offers_count' => $history['offer_count'] ?? 0,
        'counteroffers_count' => $history['counteroffer_count'] ?? 0,
        'is_finalized' => (bool) $finalized,
        'final_price' => $finalized['finalized_price'] ?? null,
        'rounds' => $finalized['negotiation_rounds'] ?? 0,
    ];
}

/**
 * Export negotiation history to CSV
 */
function exportNegotiationHistoryCSV($db, $booking_id, $output_file = null) {
    $stmt = $db->prepare("
        SELECT 
            nh.id,
            nh.action_type,
            nh.actor_type,
            nh.price_offered,
            nh.notes,
            nh.created_at,
            u.full_name as actor_name
        FROM negotiation_history nh
        LEFT JOIN users u ON nh.actor_id = u.id
        WHERE nh.booking_id = ?
        ORDER BY nh.created_at DESC
    ");
    $stmt->execute([$booking_id]);
    $history = $stmt->fetchAll();
    
    if (!$history) {
        return false;
    }
    
    $csv = "Action,Actor,Actor Type,Price,Notes,Timestamp\n";
    
    foreach ($history as $row) {
        $csv .= sprintf(
            "%s,%s,%s,%s,\"%s\",%s\n",
            $row['action_type'],
            $row['actor_name'] ?? 'System',
            $row['actor_type'],
            $row['price_offered'] ?? 'N/A',
            str_replace('"', '""', $row['notes']),
            $row['created_at']
        );
    }
    
    if ($output_file) {
        file_put_contents($output_file, $csv);
        return $output_file;
    }
    
    return $csv;
}

/**
 * Validate price within negotiation range
 */
function validateNegotiationPrice($price, $min_price, $max_price) {
    return $price >= $min_price && $price <= $max_price;
}

/**
 * Get negotiation statistics for provider
 */
function getProviderNegotiationStats($db, $provider_id) {
    // Total negotiations
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT booking_id) as total_negotiations
        FROM service_offers
        WHERE provider_id = ?
    ");
    $stmt->execute([$provider_id]);
    $total = $stmt->fetchColumn() ?? 0;
    
    // Successful negotiations (finalized)
    $stmt = $db->prepare("
        SELECT COUNT(*) as successful
        FROM finalized_service_prices
        WHERE provider_id = ? AND status = 'active'
    ");
    $stmt->execute([$provider_id]);
    $successful = $stmt->fetchColumn() ?? 0;
    
    // Average rounds
    $stmt = $db->prepare("
        SELECT AVG(negotiation_rounds) as avg_rounds
        FROM finalized_service_prices
        WHERE provider_id = ? AND status = 'active'
    ");
    $stmt->execute([$provider_id]);
    $avg_rounds = $stmt->fetchColumn() ?? 0;
    
    // Average final price
    $stmt = $db->prepare("
        SELECT AVG(finalized_price) as avg_price
        FROM finalized_service_prices
        WHERE provider_id = ? AND status = 'active'
    ");
    $stmt->execute([$provider_id]);
    $avg_price = $stmt->fetchColumn() ?? 0;
    
    return [
        'total_negotiations' => $total,
        'successful_negotiations' => $successful,
        'success_rate' => $total ? round(($successful / $total) * 100, 1) : 0,
        'average_rounds' => round($avg_rounds, 2),
        'average_final_price' => round($avg_price, 2),
    ];
}

/**
 * Get client negotiation statistics
 */
function getClientNegotiationStats($db, $client_id) {
    // Total offers made
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_offers
        FROM service_offers
        WHERE client_id = ?
    ");
    $stmt->execute([$client_id]);
    $total_offers = $stmt->fetchColumn() ?? 0;
    
    // Accepted offers
    $stmt = $db->prepare("
        SELECT COUNT(*) as accepted_offers
        FROM service_offers
        WHERE client_id = ? AND status = 'accepted'
    ");
    $stmt->execute([$client_id]);
    $accepted = $stmt->fetchColumn() ?? 0;
    
    // Rejected offers
    $stmt = $db->prepare("
        SELECT COUNT(*) as rejected_offers
        FROM service_offers
        WHERE client_id = ? AND status = 'rejected'
    ");
    $stmt->execute([$client_id]);
    $rejected = $stmt->fetchColumn() ?? 0;
    
    return [
        'total_offers' => $total_offers,
        'accepted_offers' => $accepted,
        'rejected_offers' => $rejected,
        'acceptance_rate' => $total_offers ? round(($accepted / $total_offers) * 100, 1) : 0,
    ];
}

/**
 * Clean up old negotiation data (archive old data)
 */
function archiveOldNegotiations($db, $days = 90) {
    $cutoff_date = date('Y-m-d', strtotime("-$days days"));
    
    $count = 0;
    
    // Move to archive (or delete if no archive table)
    // This is a simple cleanup - adjust based on your requirements
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM finalized_service_prices
        WHERE created_at < ?
    ");
    $stmt->execute([$cutoff_date]);
    $count = $stmt->fetchColumn() ?? 0;
    
    // TODO: Implement archival logic
    error_log("Found $count negotiations older than $cutoff_date");
    
    return $count;
}

// Export all functions for include
return [
    'formatNegotiationStatus',
    'getTimeRemaining',
    'getNegotiationTimeUsage',
    'getActionDescription',
    'formatCurrency',
    'canCreateNewOffer',
    'getNegotiationStatusBadge',
    'getPriceDifference',
    'getNegotiationProgress',
    'sendNegotiationNotification',
    'getNegotiationSummary',
    'exportNegotiationHistoryCSV',
    'validateNegotiationPrice',
    'getProviderNegotiationStats',
    'getClientNegotiationStats',
    'archiveOldNegotiations',
];
