<?php

require_once __DIR__ . '/../../repositories/providers/AdminProvidersRepository.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/admin_ranking.php';

class AdminProvidersService
{
    private AdminProvidersRepository $repository;

    public function __construct(?AdminProvidersRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminProvidersRepository();
    }

    public function buildViewModel(PDO $db, array $filters): array
    {
        $search = trim($filters['search'] ?? '');
        $statusFilter = trim($filters['status'] ?? '');
        $categoryFilter = trim($filters['category'] ?? '');
        $verificationFilter = trim($filters['verification'] ?? '');
        $availabilityFilter = trim($filters['availability'] ?? '');

        $providers = $this->repository->listProviders($db, [], $search, $statusFilter, $categoryFilter, $verificationFilter, $availabilityFilter);
        $categories = $this->repository->getCategories($db);

        return [
            'providers' => $providers,
            'categories' => $categories,
            'search' => $search,
            'status_filter' => $statusFilter,
            'category_filter' => $categoryFilter,
            'verification_filter' => $verificationFilter,
            'availability_filter' => $availabilityFilter,
        ];
    }

    public function getProviderDetailViewModel(PDO $db, int $providerId): array
    {
        $details = $this->repository->getProviderDetails($db, $providerId);
        $stats = $this->repository->getProviderStats($db, $providerId);
        $scheduling = $this->repository->getProviderSchedulingData($db, $providerId);

        return [
            'details' => $details,
            'stats' => $stats,
            'scheduling' => $scheduling,
        ];
    }

    public function handlePostAction(PDO $db, array $postData): array
    {
        $success = '';
        $errors = [];

        if (!empty($postData['approve_provider'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            try {
                $db->prepare("UPDATE users SET is_verified = 1, is_active = 1 WHERE id = (SELECT user_id FROM service_providers WHERE id = ?)")->execute([$id]);
                $db->prepare("UPDATE service_providers SET is_active = 1 WHERE id = ?")->execute([$id]);
                $success = 'Provider approved and activated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to approve provider: ' . $e->getMessage();
            }
        }

        if (!empty($postData['reject_provider'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $reason = trim((string) ($postData['rejection_reason'] ?? ''));
            try {
                $db->prepare("UPDATE service_providers SET application_status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
                $success = 'Provider application rejected';
            } catch (Throwable $e) {
                $errors[] = 'Failed to reject provider';
            }
        }

        if (!empty($postData['toggle_activation'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $currentStatus = (int) ($postData['current_status'] ?? 0);
            $newStatus = $currentStatus ? 0 : 1;
            try {
                $db->prepare("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM service_providers WHERE id = ?)")->execute([$newStatus, $id]);
                $db->prepare("UPDATE service_providers SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
                $action = $newStatus ? 'activated' : 'deactivated';
                $success = "Provider {$action} successfully";
            } catch (Throwable $e) {
                $errors[] = 'Failed to update provider status';
            }
        }

        if (!empty($postData['ban_provider'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $reason = trim((string) ($postData['ban_reason'] ?? ''));
            $user = [];
            try {
                $userStmt = $db->prepare("SELECT u.email, u.full_name FROM users u JOIN service_providers sp ON sp.user_id = u.id WHERE sp.id = ?");
                $userStmt->execute([$id]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $user = [];
            }

            try {
                $db->prepare("UPDATE service_providers SET is_banned = 1, ban_reason = ?, is_active = 0 WHERE id = ?")->execute([$reason, $id]);
                $success = 'Provider banned permanently';

                if (!empty($user['email'])) {
                    $subject = 'Account Banned — BII LocalFinder';
                    $body = "<p>Hello " . htmlspecialchars($user['full_name'] ?? 'User') . ",</p>
                    <p>Your provider account on <strong>BII LocalFinder</strong> has been banned by the administration.</p>
                    <p><strong>Reason:</strong><br>" . nl2br(htmlspecialchars($reason ?: 'No reason provided')) . "</p>
                    <p>If you believe this is an error or you would like to appeal, please reply to this email or contact support at <a href='mailto:support@biilocalfinder.example'>support@biilocalfinder.example</a>.</p>
                    <p>Regards,<br/>BII LocalFinder Team</p>";

                    try {
                        Mailer::sendAnnouncement($user['email'], $user['full_name'] ?? '', $subject, $body);
                        $success .= ' — provider notified by email.';
                    } catch (Throwable $e) {
                        error_log('Provider ban notification failed for provider_id ' . $id . ': ' . $e->getMessage());
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to ban provider';
            }
        }

        if (!empty($postData['unban_provider'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            try {
                $db->prepare("UPDATE service_providers SET is_banned = 0, ban_reason = NULL WHERE id = ?")->execute([$id]);
                $success = 'Provider unbanned successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to unban provider';
            }
        }

        if (!empty($postData['update_provider_profile'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $profession = trim((string) ($postData['profession'] ?? ''));
            $bio = trim((string) ($postData['bio'] ?? ''));
            $location = trim((string) ($postData['location'] ?? ''));
            $district = trim((string) ($postData['district'] ?? ''));
            $sector = trim((string) ($postData['sector'] ?? ''));
            $experienceYears = (int) ($postData['experience_years'] ?? 0);
            $hourlyRate = (float) ($postData['hourly_rate'] ?? 0);
            $availability = trim((string) ($postData['availability'] ?? ''));
            $workingDays = isset($postData['working_days']) ? implode(',', (array) $postData['working_days']) : '';
            $workingHoursStart = trim((string) ($postData['working_hours_start'] ?? ''));
            $workingHoursEnd = trim((string) ($postData['working_hours_end'] ?? ''));
            $breakStart = trim((string) ($postData['break_start'] ?? ''));
            $breakEnd = trim((string) ($postData['break_end'] ?? ''));
            $slotDuration = (int) ($postData['slot_duration'] ?? 0);
            $bufferTime = (int) ($postData['buffer_time'] ?? 0);
            $maxDailyBookings = (int) ($postData['max_daily_bookings'] ?? 0);
            try {
                $stmt = $db->prepare("UPDATE service_providers SET profession = ?, bio = ?, location = ?, district = ?, sector = ?, experience_years = ?, hourly_rate = ?, availability = ?, working_days = ?, working_hours_start = ?, working_hours_end = ?, break_start = ?, break_end = ?, slot_duration = ?, buffer_time = ?, max_daily_bookings = ? WHERE id = ?");
                $stmt->execute([$profession, $bio, $location, $district, $sector, $experienceYears, $hourlyRate, $availability, $workingDays, $workingHoursStart, $workingHoursEnd, $breakStart, $breakEnd, $slotDuration, $bufferTime, $maxDailyBookings, $id]);
                $success = 'Provider profile updated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update provider profile: ' . $e->getMessage();
            }
        }

        if (!empty($postData['update_verification'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $verificationLevel = trim((string) ($postData['verification_level'] ?? ''));
            $verificationNotes = trim((string) ($postData['verification_notes'] ?? ''));
            try {
                $db->prepare("UPDATE service_providers SET verification_level = ?, verification_notes = ? WHERE id = ?")->execute([$verificationLevel, $verificationNotes, $id]);
                $success = 'Verification level updated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update verification level';
            }
        }

        if (!empty($postData['update_featured_status'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $isFeatured = (int) ($postData['is_featured'] ?? 0);
            $featuredUntil = !empty($postData['featured_until']) ? trim((string) $postData['featured_until']) : null;
            try {
                $stmt = $db->prepare("UPDATE service_providers SET is_featured = ?, featured_until = ? WHERE id = ?");
                $stmt->execute([$isFeatured, $featuredUntil, $id]);
                $action = $isFeatured ? 'featured' : 'unfeatured';
                $success = "Provider {$action} successfully";
            } catch (Throwable $e) {
                $errors[] = 'Failed to update featured status';
            }
        }

        if (!empty($postData['update_search_boost'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $searchBoost = (int) ($postData['search_boost'] ?? 0);
            try {
                $db->prepare("UPDATE service_providers SET search_boost = ? WHERE id = ?")->execute([$searchBoost, $id]);
                $success = 'Search ranking boost updated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update search ranking';
            }
        }

        if (!empty($postData['update_admin_ranking'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $adminPromotionBoost = max(0, min(20, (int) ($postData['admin_promotion_boost'] ?? 0)));
            $adminPriorityLevel = max(0, min(3, (int) ($postData['admin_priority_level'] ?? 0)));
            $adminScoreOverride = isset($postData['admin_score_override']) && $postData['admin_score_override'] !== ''
                ? max(0, min(100, (int) $postData['admin_score_override']))
                : null;
            try {
                $stmt = $db->prepare("UPDATE service_providers SET admin_promotion_boost = ?, admin_priority_level = ?, admin_score_override = ? WHERE id = ?");
                $stmt->execute([$adminPromotionBoost, $adminPriorityLevel, $adminScoreOverride, $id]);

                if (function_exists('admin_ranking_table_has_column') && admin_ranking_table_has_column($db, 'service_providers', 'admin_ranking_score')) {
                    $provider = $this->repository->getProviderDetails($db, $id);
                    if ($provider) {
                        $score = calculate_admin_score($provider);
                        $db->prepare("UPDATE service_providers SET admin_ranking_score = ? WHERE id = ?")->execute([$score, $id]);
                    }
                }

                $success = 'Admin ranking settings updated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update admin ranking settings';
            }
        }

        if (!empty($postData['update_financial_settings'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $commissionRate = (float) ($postData['commission_rate'] ?? 0);
            $subscriptionPlan = trim((string) ($postData['subscription_plan'] ?? ''));
            $canReceiveJobs = (int) ($postData['can_receive_jobs'] ?? 0);
            try {
                $stmt = $db->prepare("UPDATE service_providers SET commission_rate = ?, subscription_plan = ?, can_receive_jobs = ? WHERE id = ?");
                $stmt->execute([$commissionRate, $subscriptionPlan, $canReceiveJobs, $id]);
                $success = 'Financial settings updated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update financial settings';
            }
        }

        if (!empty($postData['update_categories'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $categories = $postData['categories'] ?? [];
            try {
                $db->beginTransaction();
                $db->prepare("DELETE FROM provider_services WHERE provider_id = ?")->execute([$id]);
                $stmt = $db->prepare("INSERT INTO provider_services (provider_id, category_id) VALUES (?, ?)");
                foreach ((array) $categories as $categoryId) {
                    $stmt->execute([$id, (int) $categoryId]);
                }
                $db->commit();
                $success = 'Provider categories updated successfully';
            } catch (Throwable $e) {
                $db->rollBack();
                $errors[] = 'Failed to update categories: ' . $e->getMessage();
            }
        }

        if (!empty($postData['update_scheduling_settings'])) {
            $id = (int) ($postData['provider_id'] ?? 0);
            $workingDays = isset($postData['working_days']) ? implode(',', (array) $postData['working_days']) : '';
            $workingHoursStart = trim((string) ($postData['working_hours_start'] ?? ''));
            $workingHoursEnd = trim((string) ($postData['working_hours_end'] ?? ''));
            $breakStart = trim((string) ($postData['break_start'] ?? ''));
            $breakEnd = trim((string) ($postData['break_end'] ?? ''));
            $slotDuration = (int) ($postData['slot_duration'] ?? 0);
            $bufferTime = (int) ($postData['buffer_time'] ?? 0);
            $maxDailyBookings = (int) ($postData['max_daily_bookings'] ?? 0);
            $bookingLeadTime = (int) ($postData['booking_lead_time'] ?? 0);
            $cancellationCutoff = (int) ($postData['cancellation_cutoff'] ?? 0);
            try {
                $stmt = $db->prepare("UPDATE service_providers SET working_days = ?, working_hours_start = ?, working_hours_end = ?, break_start = ?, break_end = ?, slot_duration = ?, buffer_time = ?, max_daily_bookings = ?, booking_lead_time = ?, cancellation_cutoff = ? WHERE id = ?");
                $stmt->execute([$workingDays, $workingHoursStart, $workingHoursEnd, $breakStart, $breakEnd, $slotDuration, $bufferTime, $maxDailyBookings, $bookingLeadTime, $cancellationCutoff, $id]);
                $success = 'Scheduling settings updated successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update scheduling settings: ' . $e->getMessage();
            }
        }

        return [
            'success' => $success !== '' && empty($errors),
            'message' => $success ?: ($errors[0] ?? 'No action performed'),
            'errors' => $errors,
        ];
    }
}
