<?php

require_once __DIR__ . '/../../../services/admin/AdminSettingsService.php';

class AdminSettingsController
{
    private AdminSettingsService $service;

    public function __construct(?AdminSettingsService $service = null)
    {
        $this->service = $service ?? new AdminSettingsService();
    }

    public function index(PDO $db): array
    {
        return $this->service->buildViewModel($db);
    }

    public function updateGeneralSettings(PDO $db, array $post, array $files): array
    {
        try {
            $msg = $this->service->updateGeneralSettings($db, $post, $files);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updateUserSettings(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updateUserSettings($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updateBookingSettings(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updateBookingSettings($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updateNotificationSettings(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updateNotificationSettings($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updatePaymentSettings(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updatePaymentSettings($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function addCategory(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->addCategory($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function addDistrict(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->addDistrict($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function addPlan(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->addPlan($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updatePlan(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updatePlan($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function deletePlan(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->deletePlan($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updateSecuritySettings(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updateSecuritySettings($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function updateDeveloperSettings(PDO $db, array $post): array
    {
        try {
            $msg = $this->service->updateDeveloperSettings($db, $post);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function optimizeDatabase(PDO $db): array
    {
        try {
            $msg = $this->service->optimizeDatabase($db);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }

    public function clearCache(PDO $db): array
    {
        try {
            $msg = $this->service->clearCache($db);
            return ['success' => $msg, 'errors' => []];
        } catch (Exception $e) {
            return ['success' => '', 'errors' => [$e->getMessage()]];
        }
    }
}
