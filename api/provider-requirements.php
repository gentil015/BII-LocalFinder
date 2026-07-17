<?php
/**
 * Provider Requirements API Endpoint
 * 
 * Returns provider requirements status as JSON
 * Used for AJAX updates and real-time progress tracking
 * 
 * Usage:
 * GET /api/provider-requirements.php?provider_id=12
 * GET /api/provider-requirements.php?provider_id=12&action=check
 * GET /api/provider-requirements.php?provider_id=12&action=next_step
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/provider_requirements.php';

try {
    // Validate provider ID
    $provider_id = intval($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
    if (!$provider_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Provider ID required']);
        exit;
    }
    
    // Get action (default is full status)
    $action = sanitize($_GET['action'] ?? $_POST['action'] ?? 'status');
    
    // Initialize database and requirements
    $db = Database::getInstance()->getConnection();
    $requirements = new ProviderRequirements($db, $provider_id);
    
    // Handle different actions
    switch ($action) {
        case 'status':
            // Return full status
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'completion_percentage' => $requirements->getCompletionPercentage(),
                'is_complete' => $requirements->isComplete(),
                'requirements' => $requirements->getAllRequirements(),
                'count' => $requirements->getCompletedCount(),
                'details' => $requirements->getRequirementsWithDetails(),
                'timestamp' => date('c')
            ]);
            break;
            
        case 'check':
            // Simple status check
            $count = $requirements->getCompletedCount();
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'completed' => $count['completed'],
                'total' => $count['total'],
                'percentage' => $requirements->getCompletionPercentage(),
                'is_complete' => $requirements->isComplete()
            ]);
            break;
            
        case 'next_step':
            // Get next incomplete requirement
            $next = $requirements->getNextStep();
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'next_step' => $next,
                'all_complete' => is_null($next)
            ]);
            break;
            
        case 'requirements':
            // Get detailed requirements only
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'requirements' => $requirements->getRequirementsWithDetails()
            ]);
            break;
            
        case 'badge':
            // Get badge HTML
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'badge_html' => $requirements->renderCompletionBadge(),
                'badge_class' => $requirements->isComplete() ? 'complete' : 
                                ($requirements->getCompletionPercentage() >= 60 ? 'partial' : 'incomplete')
            ]);
            break;
            
        case 'checklist_mini':
            // Get mini checklist HTML
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'html' => $requirements->renderMiniChecklist(true)
            ]);
            break;
            
        case 'checklist_full':
            // Get full checklist HTML
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'html' => $requirements->renderChecklist(true)
            ]);
            break;
            
        case 'ready_for_bookings':
            // Check if provider can receive bookings (>= 80%)
            $ready = $requirements->getCompletionPercentage() >= 80;
            echo json_encode([
                'success' => true,
                'provider_id' => $provider_id,
                'ready' => $ready,
                'percentage' => $requirements->getCompletionPercentage(),
                'message' => $ready ? 'Provider can receive bookings' : 
                           'Provider has ' . (80 - $requirements->getCompletionPercentage()) . '% more to complete'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Provider Requirements API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
