<?php

require_once __DIR__ . '/../../repositories/provider/ProviderProfileRepository.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/provider_requirements.php';
require_once __DIR__ . '/../../includes/profession_titles.php';

class ProviderProfileService
{
    private ProviderProfileRepository $repository;

    public function __construct(?ProviderProfileRepository $repository = null)
    {
        $this->repository = $repository ?? new ProviderProfileRepository();
    }

    public function buildViewModel(PDO $db, int $userId, string $section): array
    {
        $provider = $this->repository->getProviderProfile($db, $userId);
        $validSections = ['basic', 'services', 'portfolio', 'social', 'requirements'];
        if (!in_array($section, $validSections, true)) {
            $section = 'basic';
        }

        $viewData = [
            'section' => $section,
            'provider' => $provider,
            'provider_categories' => [],
            'provider_category_ids' => [],
            'all_categories' => $this->repository->getAllCategories($db),
            'districts' => $this->repository->getAllDistricts($db),
            'portfolio_images' => [],
            'portfolio_video' => null,
            'has_portfolio_video' => false,
            'portfolio_count' => 0,
            'max_portfolio_images' => 6,
            'portfolio_enabled' => true,
            'social_platforms' => [],
            'enable_ai_features' => false,
            'ai_description_improvement_enabled' => false,
            'success' => '',
            'errors' => [],
        ];

        if ($provider === null) {
            return $viewData;
        }

        $viewData['provider_categories'] = $this->repository->getProviderCategories($db, (int) $provider['id']);
        $viewData['provider_category_ids'] = array_column($viewData['provider_categories'], 'id');
        $viewData['portfolio_images'] = $this->repository->getPortfolioImages($db, (int) $provider['id']);
        $viewData['portfolio_count'] = count($viewData['portfolio_images']);
        $viewData['portfolio_video'] = $this->repository->getPortfolioVideo($db, (int) $provider['id']);
        $viewData['has_portfolio_video'] = !empty($viewData['portfolio_video']);
        $viewData['social_platforms'] = [
            'website' => $provider['website'] ?? '',
            'facebook' => $provider['facebook'] ?? '',
            'twitter' => $provider['twitter'] ?? '',
            'instagram' => $provider['instagram'] ?? '',
            'linkedin' => $provider['linkedin'] ?? '',
            'youtube' => $provider['youtube'] ?? '',
            'whatsapp' => $provider['whatsapp'] ?? '',
            'tiktok' => $provider['tiktok'] ?? '',
            'other_social' => $provider['other_social'] ?? '',
            'other_social_label' => $provider['other_social_label'] ?? '',
        ];
        $viewData['enable_ai_features'] = !empty($provider['id']) && isProviderAIEnabled($provider['id']);
        $viewData['ai_description_improvement_enabled'] = getProviderSetting($provider['id'], 'ai_features_ai_description_improvement') == '1';

        $platformSettings = $this->repository->getPlatformSettings($db, ['max_portfolio_images', 'portfolio_enabled']);
        $viewData['max_portfolio_images'] = isset($platformSettings['max_portfolio_images']) ? intval($platformSettings['max_portfolio_images']) : 6;
        $viewData['portfolio_enabled'] = isset($platformSettings['portfolio_enabled']) ? ($platformSettings['portfolio_enabled'] === '1') : true;

        return $viewData;
    }

    public function handleSubmit(PDO $db, int $userId, array $post, array $files, array $server): array
    {
        $section = isset($post['section']) ? sanitize($post['section']) : 'basic';
        $viewData = $this->buildViewModel($db, $userId, $section);
        $errors = [];
        $success = '';
        $provider = $viewData['provider'];

        if ($provider === null) {
            return ['success' => '', 'errors' => ['Provider profile not found.']];
        }

        $fullName = sanitize($post['full_name'] ?? '');
        $phone = sanitize($post['phone'] ?? '');
        $professionalTitle = sanitize($post['professional_title'] ?? '');
        $profession = sanitize($post['profession'] ?? '');
        $bio = sanitize($post['bio'] ?? '');
        $location = sanitize($post['location'] ?? '');
        $district = sanitize($post['district'] ?? '');
        $sector = sanitize($post['sector'] ?? '');
        $experienceYears = intval($post['experience_years'] ?? 0);
        $selectedCategories = $post['categories'] ?? [];
        $socialLinksData = [
            'website' => sanitize($post['website'] ?? ''),
            'facebook' => sanitize($post['facebook'] ?? ''),
            'twitter' => sanitize($post['twitter'] ?? ''),
            'instagram' => sanitize($post['instagram'] ?? ''),
            'linkedin' => sanitize($post['linkedin'] ?? ''),
            'youtube' => sanitize($post['youtube'] ?? ''),
            'whatsapp' => sanitize($post['whatsapp'] ?? ''),
            'tiktok' => sanitize($post['tiktok'] ?? ''),
            'other_social' => sanitize($post['other_social'] ?? ''),
            'other_social_label' => sanitize($post['other_social_label'] ?? ''),
        ];

        if (empty($fullName) || empty($phone) || empty($profession) || empty($professionalTitle) || empty($location)) {
            $errors[] = 'Please fill all required fields';
        }

        if (!isValidProfession($profession)) {
            $errors[] = 'Invalid profession selected';
        }

        if (!isValidProfessionalTitle($profession, $professionalTitle)) {
            $errors[] = 'Invalid professional title selected for this profession';
        }

        if (empty($selectedCategories)) {
            $errors[] = 'Please select at least one service category';
        }

        if (!empty($phone) && !preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number';
        }

        foreach ($socialLinksData as $platform => $url) {
            if (!empty($url) && $platform !== 'whatsapp' && $platform !== 'other_social_label' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid URL for $platform";
            }
        }

        $profileImage = $provider['profile_image'];
        $allowedTypes = getAllowedFileTypes();
        $maxFileSize = getMaxFileSize() * 1024 * 1024;
        $portfolioEnabled = $viewData['portfolio_enabled'] ?? true;
        $maxPortfolioImages = (int) ($viewData['max_portfolio_images'] ?? 6);
        $portfolioCount = (int) ($viewData['portfolio_count'] ?? 0);
        $hasPortfolioVideo = (bool) ($viewData['has_portfolio_video'] ?? false);

        if (!empty($files['profile_image']['name']) && $files['profile_image']['error'] === UPLOAD_ERR_OK) {
            $fileType = $files['profile_image']['type'] ?? '';
            $fileSize = intval($files['profile_image']['size'] ?? 0);
            $fileExtension = strtolower(pathinfo($files['profile_image']['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExtension, $allowedTypes, true) && !in_array($fileType, ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'], true)) {
                $errors[] = 'Invalid image type. Please upload JPG, PNG, or GIF files only.';
            } elseif ($fileSize > $maxFileSize) {
                $errors[] = 'Image size must be less than ' . getMaxFileSize() . 'MB';
            } else {
                $newFilename = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
                $uploadPath = '../uploads/profiles/' . $newFilename;

                if (!is_dir('../uploads/profiles')) {
                    mkdir('../uploads/profiles', 0755, true);
                }

                if (move_uploaded_file($files['profile_image']['tmp_name'], $uploadPath)) {
                    if (!empty($profileImage) && file_exists('../uploads/profiles/' . $profileImage)) {
                        @unlink('../uploads/profiles/' . $profileImage);
                    }
                    $profileImage = $newFilename;
                } else {
                    $errors[] = 'Failed to upload image. Please try again.';
                }
            }
        }

        $portfolioFiles = $files['portfolio_images'] ?? [];
        $portfolioTitles = $post['portfolio_titles'] ?? [];
        $portfolioDescriptions = $post['portfolio_descriptions'] ?? [];
        $deletedPortfolioIds = array_map('intval', $post['deleted_portfolio'] ?? []);
        $existingPortfolioIds = array_map('intval', $post['existing_portfolio_ids'] ?? []);
        $existingPortfolioTitles = $post['existing_portfolio_titles'] ?? [];
        $existingPortfolioDescriptions = $post['existing_portfolio_descriptions'] ?? [];
        $uploadedPortfolioVideos = $files['portfolio_videos'] ?? [];
        $portfolioVideoTitles = $post['portfolio_video_titles'] ?? [];
        $portfolioVideoDescriptions = $post['portfolio_video_descriptions'] ?? [];
        $deletePortfolioVideo = isset($post['delete_portfolio_video']) && $post['delete_portfolio_video'] == '1';

        if (!empty($portfolioFiles['name'][0]) && $portfolioEnabled) {
            $uploadedCount = 0;
            foreach ($portfolioFiles['name'] as $index => $name) {
                if ($portfolioCount + $uploadedCount >= $maxPortfolioImages) {
                    $errors[] = "Maximum $maxPortfolioImages portfolio images allowed";
                    break;
                }

                if ($portfolioFiles['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $fileType = $portfolioFiles['type'][$index] ?? '';
                $fileSize = intval($portfolioFiles['size'][$index] ?? 0);
                $fileExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!in_array($fileExtension, $allowedTypes, true) && !in_array($fileType, ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'], true)) {
                    $errors[] = 'Invalid image type for portfolio image: ' . htmlspecialchars($name);
                    continue;
                }

                if ($fileSize > $maxFileSize) {
                    $errors[] = 'Portfolio image too large: ' . htmlspecialchars($name) . ' (Max: ' . getMaxFileSize() . 'MB)';
                    continue;
                }

                $newFilename = 'portfolio_' . $provider['id'] . '_' . time() . '_' . $index . '.' . $fileExtension;
                $uploadPath = '../uploads/portfolio/' . $newFilename;

                if (!is_dir('../uploads/portfolio')) {
                    mkdir('../uploads/portfolio', 0755, true);
                }

                if (move_uploaded_file($portfolioFiles['tmp_name'][$index], $uploadPath)) {
                    $this->repository->insertPortfolioImage(
                        $db,
                        (int) $provider['id'],
                        $newFilename,
                        sanitize($portfolioTitles[$index] ?? ''),
                        sanitize($portfolioDescriptions[$index] ?? ''),
                        $portfolioCount + $uploadedCount
                    );
                    $uploadedCount++;
                }
            }
        }

        if (!empty($deletedPortfolioIds) && $portfolioEnabled) {
            $deletedFiles = $this->repository->getPortfolioImagePaths($db, (int) $provider['id'], $deletedPortfolioIds);
            foreach ($deletedFiles as $image) {
                $filePath = '../uploads/portfolio/' . $image['image_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            $this->repository->deletePortfolioImages($db, (int) $provider['id'], $deletedPortfolioIds);
        }

        if (!empty($existingPortfolioIds) && $portfolioEnabled) {
            $items = [];
            foreach ($existingPortfolioIds as $index => $imageId) {
                $items[] = [
                    'id' => (int) $imageId,
                    'title' => sanitize($existingPortfolioTitles[$index] ?? ''),
                    'description' => sanitize($existingPortfolioDescriptions[$index] ?? ''),
                ];
            }
            $this->repository->updatePortfolioImageMetadata($db, (int) $provider['id'], $items);
        }

        if (!empty($uploadedPortfolioVideos['name'][0]) && $portfolioEnabled) {
            foreach ($uploadedPortfolioVideos['name'] as $index => $name) {
                if ($uploadedPortfolioVideos['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $fileType = $uploadedPortfolioVideos['type'][$index] ?? '';
                $fileSize = intval($uploadedPortfolioVideos['size'][$index] ?? 0);
                $fileExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowedVideoFormats = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'ogv'];
                $maxVideoSize = getMaxFileSize() * 2 * 1024 * 1024;

                if (!in_array($fileExtension, $allowedVideoFormats, true)) {
                    $errors[] = 'Invalid video format. Allowed formats: ' . implode(', ', $allowedVideoFormats);
                    continue;
                }

                if ($fileSize > $maxVideoSize) {
                    $errors[] = 'Video file size must be less than ' . (getMaxFileSize() * 2) . 'MB';
                    continue;
                }

                if ($hasPortfolioVideo) {
                    $oldVideoPath = $this->repository->deletePortfolioVideo($db, (int) $provider['id']);
                    if (!empty($oldVideoPath)) {
                        $oldFilePath = '../uploads/portfolio/' . $oldVideoPath;
                        if (file_exists($oldFilePath)) {
                            @unlink($oldFilePath);
                        }
                    }
                }

                $newFilename = 'portfolio_video_' . $provider['id'] . '_' . time() . '.' . $fileExtension;
                $uploadPath = '../uploads/portfolio/' . $newFilename;

                if (!is_dir('../uploads/portfolio')) {
                    mkdir('../uploads/portfolio', 0755, true);
                }

                if (move_uploaded_file($uploadedPortfolioVideos['tmp_name'][$index], $uploadPath)) {
                    $this->repository->insertPortfolioVideo(
                        $db,
                        (int) $provider['id'],
                        $newFilename,
                        sanitize($portfolioVideoTitles[$index] ?? 'Portfolio Video'),
                        sanitize($portfolioVideoDescriptions[$index] ?? '')
                    );
                    $hasPortfolioVideo = true;
                    break;
                }

                $errors[] = 'Failed to upload video. Please try again.';
            }
        }

        if ($deletePortfolioVideo && $portfolioEnabled && $hasPortfolioVideo) {
            $oldVideoPath = $this->repository->deletePortfolioVideo($db, (int) $provider['id']);
            if (!empty($oldVideoPath)) {
                $oldFilePath = '../uploads/portfolio/' . $oldVideoPath;
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }
            $hasPortfolioVideo = false;
        }

        if (empty($errors)) {
            try {
                $this->repository->updateProviderProfile($db, $userId, [
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'professional_title' => $professionalTitle,
                    'profession' => $profession,
                    'bio' => $bio,
                    'location' => $location,
                    'district' => $district,
                    'sector' => $sector,
                    'experience_years' => $experienceYears,
                    'profile_image' => $profileImage,
                    'social_links' => $socialLinksData,
                ]);
                $this->repository->syncProviderCategories($db, (int) $provider['id'], array_map('intval', $selectedCategories));
                $_SESSION['user_name'] = $fullName;
                $success = 'Profile updated successfully!';
                logActivity($db, $userId, 'profile_update', 'Updated profile information');
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile. Please try again.';
                error_log($e->getMessage());
            }
        }

        return ['success' => $success, 'errors' => $errors, 'viewData' => $this->buildViewModel($db, $userId, $section)];
    }

    public function handleAjaxSection(PDO $db, int $userId, array $post, array $files, array $server): array
    {
        $section = sanitize($post['ajax_section'] ?? '');
        $response = ['success' => false, 'message' => 'Unknown error', 'errors' => []];
        $viewData = $this->buildViewModel($db, $userId, $section);
        $provider = $viewData['provider'];

        if ($provider === null) {
            $response['errors'][] = 'Provider profile not found.';
            return $response;
        }

        try {
            $db->beginTransaction();

            switch ($section) {
                case 'basic_info':
                    $fullName = sanitize($post['full_name'] ?? '');
                    $phone = sanitize($post['phone'] ?? '');
                    $profession = sanitize($post['profession'] ?? '');
                    $professionalTitle = sanitize($post['professional_title'] ?? '');
                    $bio = sanitize($post['bio'] ?? '');
                    $experienceYears = intval($post['experience_years'] ?? 0);

                    if (empty($fullName) || empty($phone) || empty($profession) || empty($professionalTitle)) {
                        $response['errors'][] = 'Please fill all required fields';
                    } elseif (!isValidProfession($profession)) {
                        $response['errors'][] = 'Invalid profession selected';
                    } elseif (!isValidProfessionalTitle($profession, $professionalTitle)) {
                        $response['errors'][] = 'Invalid professional title for selected profession';
                    } elseif (!preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone)) {
                        $response['errors'][] = 'Please enter a valid phone number';
                    } else {
                        $aiEnabled = !empty($provider['id']) && isProviderAIEnabled($provider['id']);
                        $aiDescriptionImprovementEnabled = getProviderSetting($provider['id'], 'ai_features_ai_description_improvement') == '1';
                        if ($aiEnabled && $aiDescriptionImprovementEnabled && !empty($bio) && strlen($bio) > 20) {
                            $improvedBio = (new AIHelper($db))->improveProfessionalBio($bio, $profession, $experienceYears);
                            if ($improvedBio !== $bio && strlen($improvedBio) > strlen($bio) * 0.8) {
                                $bio = $improvedBio;
                            }
                        }

                        $this->repository->updateBasicInfo($db, $userId, [
                            'full_name' => $fullName,
                            'phone' => $phone,
                            'profession' => $profession,
                            'bio' => $bio,
                            'experience_years' => $experienceYears,
                        ]);

                        $response['success'] = true;
                        $response['message'] = 'Basic information updated successfully!';
                    }
                    break;

                case 'location_info':
                    $location = sanitize($post['location'] ?? '');
                    $district = sanitize($post['district'] ?? '');
                    $sector = sanitize($post['sector'] ?? '');

                    if (empty($location) || empty($district)) {
                        $response['errors'][] = 'Please fill all required location fields';
                    } else {
                        $this->repository->updateLocationInfo($db, $userId, [
                            'location' => $location,
                            'district' => $district,
                            'sector' => $sector,
                        ]);

                        $response['success'] = true;
                        $response['message'] = 'Location information updated successfully!';
                    }
                    break;

                case 'services':
                    $selectedCategories = $post['categories'] ?? [];
                    if (empty($selectedCategories)) {
                        $response['errors'][] = 'Please select at least one service category';
                    } else {
                        $this->repository->syncProviderCategories($db, (int) $provider['id'], array_map('intval', $selectedCategories));
                        $response['success'] = true;
                        $response['message'] = 'Services updated successfully!';
                    }
                    break;

                case 'social_media':
                    $socialLinksData = [
                        'website' => sanitize($post['website'] ?? ''),
                        'facebook' => sanitize($post['facebook'] ?? ''),
                        'twitter' => sanitize($post['twitter'] ?? ''),
                        'instagram' => sanitize($post['instagram'] ?? ''),
                        'linkedin' => sanitize($post['linkedin'] ?? ''),
                        'youtube' => sanitize($post['youtube'] ?? ''),
                        'whatsapp' => sanitize($post['whatsapp'] ?? ''),
                        'tiktok' => sanitize($post['tiktok'] ?? ''),
                        'other_social' => sanitize($post['other_social'] ?? ''),
                        'other_social_label' => sanitize($post['other_social_label'] ?? ''),
                    ];

                    foreach ($socialLinksData as $platform => $url) {
                        if (!empty($url) && $platform !== 'whatsapp' && $platform !== 'other_social_label' && !filter_var($url, FILTER_VALIDATE_URL)) {
                            $response['errors'][] = "Invalid URL for $platform";
                        }
                    }

                    if (empty($response['errors'])) {
                        $this->repository->updateSocialLinks($db, $userId, $socialLinksData);
                        $response['success'] = true;
                        $response['message'] = 'Social media links updated successfully!';
                    }
                    break;

                case 'portfolio':
                    $deletedPortfolioIds = array_map('intval', $post['deleted_portfolio'] ?? []);
                    $existingPortfolioIds = array_map('intval', $post['existing_portfolio_ids'] ?? []);
                    $existingPortfolioTitles = $post['existing_portfolio_titles'] ?? [];
                    $existingPortfolioDescriptions = $post['existing_portfolio_descriptions'] ?? [];

                    if (!empty($deletedPortfolioIds)) {
                        $deletedFiles = $this->repository->getPortfolioImagePaths($db, (int) $provider['id'], $deletedPortfolioIds);
                        foreach ($deletedFiles as $image) {
                            $filePath = '../uploads/portfolio/' . $image['image_path'];
                            if (file_exists($filePath)) {
                                @unlink($filePath);
                            }
                        }
                        $this->repository->deletePortfolioImages($db, (int) $provider['id'], $deletedPortfolioIds);
                    }

                    if (!empty($existingPortfolioIds)) {
                        $items = [];
                        foreach ($existingPortfolioIds as $index => $imageId) {
                            $items[] = [
                                'id' => (int) $imageId,
                                'title' => sanitize($existingPortfolioTitles[$index] ?? ''),
                                'description' => sanitize($existingPortfolioDescriptions[$index] ?? ''),
                            ];
                        }
                        $this->repository->updatePortfolioImageMetadata($db, (int) $provider['id'], $items);
                    }

                    $response['success'] = true;
                    $response['message'] = 'Portfolio updated successfully!';
                    break;

                default:
                    $response['errors'][] = 'Unsupported AJAX section.';
                    break;
            }

            if ($response['success']) {
                $db->commit();
            } else {
                $db->rollBack();
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log('AJAX update error: ' . $e->getMessage());
            $response['errors'][] = 'Failed to update. Please try again.';
        }

        return $response;
    }
}
