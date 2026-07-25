<?php

require_once __DIR__ . '/../../repositories/client/ClientProfileRepository.php';
require_once __DIR__ . '/../../includes/functions.php';

class ClientProfileService
{
    private ClientProfileRepository $repository;

    public function __construct(?ClientProfileRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientProfileRepository();
    }

    public function buildViewModel(PDO $db, int $userId, array $systemSettings): array
    {
        $client = $this->repository->getClientById($db, $userId);
        if ($client === null) {
            return [
                'client' => null,
                'system_settings' => $systemSettings,
                'total_bookings' => 0,
                'total_reviews' => 0,
                'recent_activities' => [],
                'needs_email_verification' => false,
                'needs_phone_verification' => false,
            ];
        }

        $totalBookings = $this->repository->getTotalBookings($db, $userId);
        $totalReviews = $this->repository->getTotalReviews($db, $userId);
        $recentActivities = $this->repository->getRecentActivities($db, $userId);

        return [
            'client' => $client,
            'system_settings' => $systemSettings,
            'total_bookings' => $totalBookings,
            'total_reviews' => $totalReviews,
            'recent_activities' => $recentActivities,
            'needs_email_verification' => !empty($systemSettings['email_verification']) && empty($client['email_verified']),
            'needs_phone_verification' => !empty($systemSettings['phone_verification']) && empty($client['phone_verified']),
        ];
    }

    public function handleSubmit(PDO $db, int $userId, array $post, array $files, array $systemSettings): array
    {
        $errors = [];
        $success = '';

        $client = $this->repository->getClientById($db, $userId);
        if ($client === null) {
            return ['success' => '', 'errors' => ['Client profile not found.']];
        }

        $fullName = sanitize($post['full_name'] ?? '');
        $phone = sanitize($post['phone'] ?? '');

        if (empty($fullName) || empty($phone)) {
            $errors[] = 'All fields are required';
        }

        $profileImage = $client['profile_image'];
        if (!empty($files['profile_image']['name']) && $files['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = explode(',', $systemSettings['allowed_file_types'] ?? 'jpg,jpeg,png,pdf,doc,docx');
            $allowedMimeTypes = [];
            foreach ($allowedTypes as $type) {
                $type = trim($type);
                if ($type === 'jpg' || $type === 'jpeg') {
                    $allowedMimeTypes[] = 'image/jpeg';
                }
                if ($type === 'png') {
                    $allowedMimeTypes[] = 'image/png';
                }
                if ($type === 'gif') {
                    $allowedMimeTypes[] = 'image/gif';
                }
            }

            $fileType = $files['profile_image']['type'] ?? '';
            $fileSize = intval($files['profile_image']['size'] ?? 0);
            $maxFileSize = intval($systemSettings['max_file_size'] ?? 10) * 1024 * 1024;

            if (!in_array($fileType, $allowedMimeTypes, true)) {
                $errors[] = 'Invalid image type. Allowed types: ' . str_replace(',', ', ', $systemSettings['allowed_file_types'] ?? 'jpg,jpeg,png');
            } elseif ($fileSize > $maxFileSize) {
                $errors[] = 'Image size must be less than ' . ($systemSettings['max_file_size'] ?? 10) . 'MB';
            } else {
                $fileExtension = strtolower(pathinfo($files['profile_image']['name'], PATHINFO_EXTENSION));
                $newFilename = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
                $uploadPath = '../uploads/' . $newFilename;

                if (move_uploaded_file($files['profile_image']['tmp_name'], $uploadPath)) {
                    if (!empty($profileImage) && file_exists('../uploads/' . $profileImage)) {
                        @unlink('../uploads/' . $profileImage);
                    }
                    $profileImage = $newFilename;
                } else {
                    $errors[] = 'Failed to upload image';
                }
            }
        }

        if (empty($errors)) {
            try {
                $this->repository->updateClientProfile($db, $userId, $fullName, $phone, $profileImage);
                $_SESSION['user_name'] = $fullName;
                $success = 'Profile updated successfully!';
                logActivity($db, $userId, 'profile_update', 'Updated profile information');
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile. Please try again.';
                error_log($e->getMessage());
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }
}
