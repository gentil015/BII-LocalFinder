<?php
/**
 * Provider Directory Helper Functions
 * 
 * This file contains helper functions for displaying provider requirements
 * in directory listings, search results, and provider cards.
 */

require_once __DIR__ . '/provider_requirements.php';

/**
 * Display provider card with requirements checklist
 * 
 * @param array $provider Provider data from database
 * @param PDO $db Database connection
 * @return string HTML
 */
function renderProviderCardWithRequirements($provider, $db) {
    $req = new ProviderRequirements($db, $provider['id']);
    
    $html = '<div class="provider-card-with-requirements">';
    
    // Provider basic info
    $html .= '<div class="provider-card-header">';
    $html .= '<h3>' . htmlspecialchars($provider['full_name'] ?? '') . '</h3>';
    $html .= '<p class="profession">' . htmlspecialchars($provider['profession'] ?? '') . '</p>';
    $html .= '</div>';
    
    // Mini checklist
    $html .= '<div class="provider-card-checklist">';
    $html .= $req->renderMiniChecklist(false);
    $html .= '</div>';
    
    // Completion badge
    $html .= '<div class="provider-card-badge">';
    $html .= $req->renderCompletionBadge();
    $html .= '</div>';
    
    $html .= '</div>';
    return $html;
}

/**
 * Display provider row in directory with completion status
 * Used in admin and client directories
 * 
 * @param array $provider Provider data
 * @param PDO $db Database connection
 * @return string HTML
 */
function renderProviderRow($provider, $db) {
    $req = new ProviderRequirements($db, $provider['id']);
    $count = $req->getCompletedCount();
    
    $html = '';
    $html .= '<div class="provider-row">';
    
    // Name and profession
    $html .= '<div class="row-name">';
    $html .= '<strong>' . htmlspecialchars($provider['full_name'] ?? '') . '</strong>';
    $html .= '<br><small class="text-muted">' . htmlspecialchars($provider['profession'] ?? '') . '</small>';
    $html .= '</div>';
    
    // Completion status
    $html .= '<div class="row-completion">';
    $html .= '<span class="completion-badge">';
    $html .= $count['completed'] . '/' . $count['total'] . ' Requirements';
    $html .= '</span>';
    $html .= '</div>';
    
    // Badge
    $html .= '<div class="row-badge">';
    $html .= $req->renderCompletionBadge();
    $html .= '</div>';
    
    $html .= '</div>';
    return $html;
}

/**
 * Get provider requirements status as HTML data attributes
 * For use with tooltips and data attributes
 * 
 * @param array $provider Provider data
 * @param PDO $db Database connection
 * @return string HTML attributes
 */
function getProviderRequirementsDataAttrs($provider, $db) {
    $req = new ProviderRequirements($db, $provider['id']);
    $data = $req->toJSON();
    
    $attrs = '';
    $attrs .= ' data-completion="' . $data['completion_percentage'] . '"';
    $attrs .= ' data-complete="' . ($data['is_complete'] ? 'true' : 'false') . '"';
    $attrs .= ' data-count="' . $data['completed_count'] . '/' . $data['total_count'] . '"';
    
    return $attrs;
}

/**
 * Check if provider has met minimum requirements for receiving bookings
 * 
 * @param array $provider Provider data
 * @param PDO $db Database connection
 * @param int $minimum_percentage Minimum completion percentage required (default 80)
 * @return bool
 */
function isProviderReadyForBookings($provider, $db, $minimum_percentage = 80) {
    $req = new ProviderRequirements($db, $provider['id']);
    return $req->getCompletionPercentage() >= $minimum_percentage;
}

/**
 * Get remaining requirements for a provider
 * 
 * @param PDO $db Database connection
 * @param int $provider_id Provider ID
 * @return array Array of incomplete requirements
 */
function getRemainingRequirements($db, $provider_id) {
    $req = new ProviderRequirements($db, $provider_id);
    $details = $req->getRequirementsWithDetails();
    $remaining = [];
    
    foreach ($details as $key => $item) {
        if (!$item['completed']) {
            $remaining[$key] = $item;
        }
    }
    
    return $remaining;
}

/**
 * Render a provider directory list with requirements
 * Perfect for admin panels and provider search
 * 
 * @param array $providers Array of provider data
 * @param PDO $db Database connection
 * @param bool $show_mini Show mini checklist (true) or just badge (false)
 * @return string HTML
 */
function renderProviderDirectoryList($providers, $db, $show_mini = true) {
    $html = '<div class="provider-directory-list">';
    
    foreach ($providers as $provider) {
        $req = new ProviderRequirements($db, $provider['id']);
        $count = $req->getCompletedCount();
        $pct = $req->getCompletionPercentage();
        
        $html .= '<div class="directory-item" ' . getProviderRequirementsDataAttrs($provider, $db) . '>';
        
        $html .= '<div class="item-header">';
        $html .= '<div class="item-name">';
        $html .= '<h4>' . htmlspecialchars($provider['full_name'] ?? '') . '</h4>';
        $html .= '<p>' . htmlspecialchars($provider['profession'] ?? '') . '</p>';
        $html .= '</div>';
        $html .= '<div class="item-status">';
        $html .= $req->renderCompletionBadge();
        $html .= '</div>';
        $html .= '</div>';
        
        if ($show_mini) {
            $html .= '<div class="item-details">';
            $html .= $req->renderMiniChecklist(true);
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Generate a summary report of provider completion across system
 * 
 * @param PDO $db Database connection
 * @return array Statistics about provider completions
 */
function getProviderCompletionStats($db) {
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as total_providers,
                   SUM(CASE WHEN sp.bio IS NOT NULL AND sp.experience_years IS NOT NULL THEN 1 ELSE 0 END) as bio_complete,
                   SUM(CASE WHEN u.profile_image IS NOT NULL THEN 1 ELSE 0 END) as photo_complete,
                   SUM(CASE WHEN sp.working_days IS NOT NULL AND sp.working_days != '' THEN 1 ELSE 0 END) as availability_set,
                   SUM(CASE WHEN sp.working_hours_start IS NOT NULL AND sp.working_hours_end IS NOT NULL THEN 1 ELSE 0 END) as hours_set
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.is_active = 1
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting completion stats: " . $e->getMessage());
        return [];
    }
}
