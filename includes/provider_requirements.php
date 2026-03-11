<?php
/**
 * Provider Requirements Checker
 * 
 * This utility checks if a provider has completed all required onboarding steps:
 * 1. National ID / Passport (verified)
 * 2. Profile Photo (real face)
 * 3. Short Bio / Experience
 * 4. At least 1 Service + Price
 * 5. Availability (Working hours)
 */

class ProviderRequirements {
    private $db;
    private $provider_id;
    
    public function __construct($db, $provider_id) {
        $this->db = $db;
        $this->provider_id = $provider_id;
    }
    
    /**
     * Get all requirements status
     * 
     * @return array Array with requirement keys and completion status
     */
    public function getAllRequirements() {
        return [
            'national_id' => $this->hasNationalId(),
            'profile_photo' => $this->hasProfilePhoto(),
            'bio_experience' => $this->hasBioAndExperience(),
            'service_with_price' => $this->hasServiceWithPrice(),
            'availability' => $this->hasAvailability(),
        ];
    }
    
    /**
     * Get requirements with detailed info
     * 
     * @return array Array with detailed status for each requirement
     */
    public function getRequirementsWithDetails() {
        return [
            'national_id' => [
                'name' => 'National ID / Passport',
                'description' => 'Upload and verify your national ID or passport',
                'completed' => $this->hasNationalId(),
                'status' => $this->getNationalIdStatus(),
                'icon' => 'fa-id-card',
                'help_text' => 'Upload a clear photo of your national ID or passport for identity verification',
                'required' => true
            ],
            'profile_photo' => [
                'name' => 'Profile Photo',
                'description' => 'Add a real profile photo (your face)',
                'completed' => $this->hasProfilePhoto(),
                'status' => $this->getProfilePhotoStatus(),
                'icon' => 'fa-camera',
                'help_text' => 'Upload a clear profile photo showing your face - this helps clients recognize you',
                'required' => true
            ],
            'bio_experience' => [
                'name' => 'Bio & Experience',
                'description' => 'Add your professional bio and years of experience',
                'completed' => $this->hasBioAndExperience(),
                'status' => $this->getBioAndExperienceStatus(),
                'icon' => 'fa-user-circle',
                'help_text' => 'Write a brief professional bio and specify your years of experience',
                'required' => true
            ],
            'service_with_price' => [
                'name' => 'Service & Pricing',
                'description' => 'Add at least one service with a price',
                'completed' => $this->hasServiceWithPrice(),
                'status' => $this->getServiceWithPriceStatus(),
                'icon' => 'fa-shopping-cart',
                'help_text' => 'Add at least one service offering with clear pricing to start receiving bookings',
                'required' => true
            ],
            'availability' => [
                'name' => 'Working Hours',
                'description' => 'Set your working hours and availability',
                'completed' => $this->hasAvailability(),
                'status' => $this->getAvailabilityStatus(),
                'icon' => 'fa-clock',
                'help_text' => 'Define your working days, hours, and availability schedule',
                'required' => true
            ],
        ];
    }
    
    /**
     * Check if provider has uploaded national ID (verified)
     * 
     * @return bool
     */
    public function hasNationalId() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM verification_documents 
                WHERE provider_id = ? 
                AND document_type IN ('national_id', 'passport')
                AND status = 'approved'
            ");
            $stmt->execute([$this->provider_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error checking national ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get national ID verification status
     * 
     * @return string 'approved', 'pending', 'rejected', or 'missing'
     */
    public function getNationalIdStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT status FROM verification_documents 
                WHERE provider_id = ? 
                AND document_type IN ('national_id', 'passport')
                ORDER BY uploaded_at DESC
                LIMIT 1
            ");
            $stmt->execute([$this->provider_id]);
            $status = $stmt->fetchColumn();
            return $status ?: 'missing';
        } catch (Exception $e) {
            error_log("Error getting national ID status: " . $e->getMessage());
            return 'missing';
        }
    }
    
    /**
     * Check if provider has a profile photo
     * 
     * @return bool
     */
    public function hasProfilePhoto() {
        try {
            $stmt = $this->db->prepare("
                SELECT u.profile_image FROM users u
                JOIN service_providers sp ON u.id = sp.user_id
                WHERE sp.id = ? AND u.profile_image IS NOT NULL AND u.profile_image != ''
            ");
            $stmt->execute([$this->provider_id]);
            return !is_null($stmt->fetchColumn());
        } catch (Exception $e) {
            error_log("Error checking profile photo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get profile photo status
     * 
     * @return string 'complete' or 'missing'
     */
    public function getProfilePhotoStatus() {
        return $this->hasProfilePhoto() ? 'complete' : 'missing';
    }
    
    /**
     * Check if provider has bio and experience info
     * 
     * @return bool
     */
    public function hasBioAndExperience() {
        try {
            $stmt = $this->db->prepare("
                SELECT bio, experience_years FROM service_providers 
                WHERE id = ?
            ");
            $stmt->execute([$this->provider_id]);
            $provider = $stmt->fetch();
            
            // Check if bio exists and has at least 10 characters, OR experience_years is set
            $has_bio = !empty($provider['bio']) && strlen(trim($provider['bio'])) >= 10;
            $has_experience = !is_null($provider['experience_years']) && $provider['experience_years'] > 0;
            
            return $has_bio && $has_experience;
        } catch (Exception $e) {
            error_log("Error checking bio and experience: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get bio and experience status with details
     * 
     * @return array
     */
    public function getBioAndExperienceStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT bio, experience_years FROM service_providers 
                WHERE id = ?
            ");
            $stmt->execute([$this->provider_id]);
            $provider = $stmt->fetch();
            
            $status = [];
            
            // Check bio
            if (empty($provider['bio']) || strlen(trim($provider['bio'])) < 10) {
                $status[] = 'missing_bio';
            } else {
                $status[] = 'has_bio';
            }
            
            // Check experience
            if (is_null($provider['experience_years']) || $provider['experience_years'] <= 0) {
                $status[] = 'missing_experience';
            } else {
                $status[] = 'has_experience';
            }
            
            if (in_array('missing_bio', $status) || in_array('missing_experience', $status)) {
                return 'incomplete';
            }
            return 'complete';
        } catch (Exception $e) {
            error_log("Error getting bio/experience status: " . $e->getMessage());
            return 'missing';
        }
    }
    
    /**
     * Check if provider has at least one service with a price
     * 
     * @return bool
     */
    public function hasServiceWithPrice() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM provider_services 
                WHERE provider_id = ? 
                AND is_available = 1 
                AND price IS NOT NULL 
                AND price > 0
            ");
            $stmt->execute([$this->provider_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error checking service with price: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get service with price status
     * 
     * @return array
     */
    public function getServiceWithPriceStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM provider_services 
                WHERE provider_id = ? AND is_available = 1
            ");
            $stmt->execute([$this->provider_id]);
            $total_services = $stmt->fetchColumn();
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM provider_services 
                WHERE provider_id = ? 
                AND is_available = 1 
                AND price IS NOT NULL 
                AND price > 0
            ");
            $stmt->execute([$this->provider_id]);
            $priced_services = $stmt->fetchColumn();
            
            if ($priced_services > 0) {
                return 'complete';
            } elseif ($total_services > 0) {
                return 'has_services_no_price';
            }
            return 'missing';
        } catch (Exception $e) {
            error_log("Error getting service status: " . $e->getMessage());
            return 'missing';
        }
    }
    
    /**
     * Check if provider has availability/working hours set
     * 
     * @return bool
     */
    public function hasAvailability() {
        try {
            $stmt = $this->db->prepare("
                SELECT working_days, working_hours_start, working_hours_end 
                FROM service_providers 
                WHERE id = ?
            ");
            $stmt->execute([$this->provider_id]);
            $provider = $stmt->fetch();
            
            // Check if working days are set (not empty)
            $has_working_days = !empty($provider['working_days']);
            
            // Check if working hours are set (not default null/00:00:00)
            $has_working_hours = !empty($provider['working_hours_start']) && 
                                 !empty($provider['working_hours_end']) &&
                                 $provider['working_hours_start'] !== '00:00:00' &&
                                 $provider['working_hours_end'] !== '00:00:00';
            
            return $has_working_days && $has_working_hours;
        } catch (Exception $e) {
            error_log("Error checking availability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get availability status
     * 
     * @return string
     */
    public function getAvailabilityStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT working_days, working_hours_start, working_hours_end 
                FROM service_providers 
                WHERE id = ?
            ");
            $stmt->execute([$this->provider_id]);
            $provider = $stmt->fetch();
            
            if (empty($provider['working_days'])) {
                return 'missing_days';
            }
            if (empty($provider['working_hours_start']) || empty($provider['working_hours_end'])) {
                return 'missing_hours';
            }
            if ($provider['working_hours_start'] === '00:00:00' || $provider['working_hours_end'] === '00:00:00') {
                return 'invalid_hours';
            }
            return 'complete';
        } catch (Exception $e) {
            error_log("Error getting availability status: " . $e->getMessage());
            return 'missing';
        }
    }
    
    /**
     * Calculate completion percentage (0-100)
     * 
     * @return int
     */
    public function getCompletionPercentage() {
        $requirements = $this->getAllRequirements();
        $completed = array_sum(array_map(fn($r) => $r ? 1 : 0, $requirements));
        return (int) ($completed / count($requirements) * 100);
    }
    
    /**
     * Check if all requirements are met
     * 
     * @return bool
     */
    public function isComplete() {
        $requirements = $this->getAllRequirements();
        return array_sum(array_map(fn($r) => $r ? 1 : 0, $requirements)) === count($requirements);
    }
    
    /**
     * Get count of completed requirements
     * 
     * @return array ['completed' => int, 'total' => int]
     */
    public function getCompletedCount() {
        $requirements = $this->getAllRequirements();
        $completed = array_sum(array_map(fn($r) => $r ? 1 : 0, $requirements));
        return [
            'completed' => $completed,
            'total' => count($requirements)
        ];
    }
    
    /**
     * Get next required step (first incomplete requirement)
     * 
     * @return array|null Array with requirement key and details, or null if all complete
     */
    public function getNextStep() {
        $details = $this->getRequirementsWithDetails();
        foreach ($details as $key => $requirement) {
            if (!$requirement['completed']) {
                return ['key' => $key] + $requirement;
            }
        }
        return null;
    }
    
    /**
     * Generate HTML checklist widget
     * 
     * @param bool $show_help_text Whether to show help text
     * @return string HTML
     */
    public function renderChecklist($show_help_text = true) {
        $details = $this->getRequirementsWithDetails();
        $count = $this->getCompletedCount();
        $completion_pct = $this->getCompletionPercentage();
        
        $html = '';
        $html .= '<div class="provider-requirements-checklist">';
        
        // Header
        $html .= '<div class="checklist-header">';
        $html .= '<div class="checklist-title">';
        $html .= '<i class="fas fa-clipboard-check"></i>';
        $html .= '<h3>Complete Your Profile</h3>';
        $html .= '</div>';
        $html .= '<div class="checklist-progress-info">';
        $html .= '<span class="progress-number">' . $count['completed'] . ' of ' . $count['total'] . ' Complete</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Progress bar
        $html .= '<div class="checklist-progress-bar">';
        $html .= '<div class="progress-bar-fill" style="width: ' . $completion_pct . '%"></div>';
        $html .= '</div>';
        
        // Items
        $html .= '<div class="checklist-items">';
        foreach ($details as $key => $item) {
            $completed_class = $item['completed'] ? 'completed' : 'incomplete';
            $icon_class = $item['completed'] ? 'fa-check-circle text-success' : 'fa-circle text-secondary';
            
            $html .= '<div class="checklist-item ' . $completed_class . '">';
            $html .= '<div class="item-check">';
            $html .= '<i class="fas ' . $icon_class . '"></i>';
            $html .= '</div>';
            $html .= '<div class="item-content">';
            $html .= '<div class="item-title">';
            $html .= '<i class="fas ' . $item['icon'] . ' me-2"></i>';
            $html .= $item['name'];
            if ($item['required']) {
                $html .= ' <span class="badge bg-danger">Required</span>';
            }
            $html .= '</div>';
            $html .= '<div class="item-description">' . $item['description'] . '</div>';
            if ($show_help_text && !$item['completed']) {
                $html .= '<div class="item-help">' . $item['help_text'] . '</div>';
            }
            $html .= '<div class="item-status">';
            $html .= '<small class="text-muted">' . ucfirst(str_replace('_', ' ', $item['status'])) . '</small>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // Completion message
        if ($this->isComplete()) {
            $html .= '<div class="alert alert-success mt-3">';
            $html .= '<i class="fas fa-check-circle me-2"></i>';
            $html .= '<strong>Great!</strong> Your profile is complete. You can now receive bookings!';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate HTML mini checklist for provider directory listings
     * Shows a compact inline checklist ideal for provider cards
     * 
     * @param bool $show_percentage Whether to show percentage
     * @return string HTML
     */
    public function renderMiniChecklist($show_percentage = true) {
        $count = $this->getCompletedCount();
        $completion_pct = $this->getCompletionPercentage();
        $is_complete = $this->isComplete();
        
        $html = '';
        $html .= '<div class="checklist-mini">';
        
        // Progress bar
        $html .= '<div class="checklist-progress-bar" style="height: 4px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin-bottom: 0.5rem;">';
        $html .= '<div style="height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); width: ' . $completion_pct . '%;"></div>';
        $html .= '</div>';
        
        // Info
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-bottom: 0.5rem;">';
        $html .= '<span style="font-weight: 600; color: #212529;">';
        $html .= '<i class="fas fa-clipboard-check"></i> Profile ' . ($is_complete ? '<span style="color: #28a745;">Complete</span>' : 'Status') . '</span>';
        if ($show_percentage) {
            $html .= '<span style="color: #6c757d;">' . $count['completed'] . '/' . $count['total'] . ' (' . $completion_pct . '%)</span>';
        } else {
            $html .= '<span style="color: #6c757d;">' . $count['completed'] . '/' . $count['total'] . '</span>';
        }
        $html .= '</div>';
        
        // Mini items
        $details = $this->getRequirementsWithDetails();
        $html .= '<div class="checklist-mini-items">';
        foreach ($details as $key => $item) {
            $class = $item['completed'] ? 'complete' : 'incomplete';
            $icon = $item['completed'] ? 'fa-check-circle' : 'fa-circle';
            $html .= '<div class="checklist-mini-item ' . $class . '" title="' . $item['name'] . '">';
            $html .= '<i class="fas ' . $icon . '"></i>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Generate HTML mini badge for quick status display
     * Perfect for provider cards in directories
     * 
     * @return string HTML badge
     */
    public function renderCompletionBadge() {
        $count = $this->getCompletedCount();
        $completion_pct = $this->getCompletionPercentage();
        $is_complete = $this->isComplete();
        
        $badge_class = $is_complete ? 'complete' : ($completion_pct >= 60 ? 'partial' : 'incomplete');
        $icon = $is_complete ? 'fa-check-circle' : 'fa-exclamation-circle';
        $label = $is_complete ? 'Profile Complete' : $count['completed'] . '/' . $count['total'] . ' Complete';
        
        return '<span class="profile-completion-badge ' . $badge_class . '" title="Profile ' . $completion_pct . '% Complete">' .
               '<i class="fas ' . $icon . '"></i> ' . $label .
               '</span>';
    }
    
    /**
     * Generate HTML tooltip showing incomplete requirements
     * Shows what provider needs to complete
     * 
     * @return string HTML
     */
    public function renderIncompleteTooltip() {
        $details = $this->getRequirementsWithDetails();
        $incomplete = [];
        
        foreach ($details as $key => $item) {
            if (!$item['completed']) {
                $incomplete[] = $item['name'];
            }
        }
        
        if (empty($incomplete)) {
            return '<div class="requirement-tooltip"><strong>✓ All requirements complete!</strong></div>';
        }
        
        $html = '<div class="requirement-tooltip">';
        $html .= '<strong>Incomplete requirements:</strong>';
        $html .= '<ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">';
        foreach ($incomplete as $req) {
            $html .= '<li style="margin: 0.25rem 0; font-size: 0.9rem;">' . $req . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get JSON data suitable for JavaScript/AJAX operations
     * 
     * @return array
     */
    public function toJSON() {
        return [
            'provider_id' => $this->provider_id,
            'requirements' => $this->getAllRequirements(),
            'completion_percentage' => $this->getCompletionPercentage(),
            'is_complete' => $this->isComplete(),
            'completed_count' => $this->getCompletedCount()['completed'],
            'total_count' => $this->getCompletedCount()['total'],
            'next_step' => $this->getNextStep(),
            'details' => $this->getRequirementsWithDetails()
        ];
    }
}

/**
 * Helper function to get requirements for a provider
 * Convenient shortcut for views
 * 
 * @param PDO $db Database connection
 * @param int $provider_id Provider ID
 * @return ProviderRequirements
 */
function getProviderRequirements($db, $provider_id) {
    return new ProviderRequirements($db, $provider_id);
}

