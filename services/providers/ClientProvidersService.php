<?php

require_once __DIR__ . '/../../repositories/providers/ClientProvidersRepository.php';
require_once __DIR__ . '/../../includes/geolocation.php';
require_once __DIR__ . '/../../includes/final_ranking.php';

class ClientProvidersService
{
    private ClientProvidersRepository $repository;

    public function __construct(?ClientProvidersRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientProvidersRepository();
    }

    public function buildViewModel(PDO $db, int $userId, array $filters): array
    {
        $platformName = $this->repository->getPlatformSetting($db, 'platform_name', 'BII LocalFinder');
        $client = $this->repository->getClient($db, $userId);
        $clientLocation = trim($client['location'] ?? '');
        $clientName = $client['full_name'] ?? 'there';

        $bookedProfessions = $this->repository->getBookedProfessions($db, $userId);
        $favIds = $this->repository->getFavoriteProviderIds($db, $userId);
        $recentlyViewedIds = $this->repository->getRecentlyViewedProviderIds($db, $userId);
        $userStats = $this->repository->getUserBookingStats($db, $userId);
        $filterOptions = $this->repository->getFilterOptions($db);

        $search = trim($filters['search'] ?? '');
        $category = trim($filters['category'] ?? '');
        $location = trim($filters['location'] ?? '');
        $sort = trim($filters['sort'] ?? 'ml');
        $avail = trim($filters['avail'] ?? '');
        $minRating = (float) ($filters['min_rating'] ?? 0);
        $verified = !empty($filters['verified']);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $where = ["sp.is_active = 1", "sp.is_banned = 0", "u.user_type = 'provider'"];
        $params = [];

        if ($search !== '') {
            $where[] = "(u.full_name LIKE ? OR sp.profession LIKE ? OR sp.bio LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($category !== '') {
            $where[] = "sp.profession = ?";
            $params[] = $category;
        }
        if ($location !== '') {
            $where[] = "sp.location = ?";
            $params[] = $location;
        }
        if ($avail !== '') {
            $where[] = "sp.availability = ?";
            $params[] = $avail;
        }
        if ($minRating > 0) {
            $where[] = "sp.average_rating >= ?";
            $params[] = $minRating;
        }
        if ($verified) {
            $where[] = "(sp.is_verified = 1 OR u.is_verified = 1)";
        }

        $totalProviders = $this->repository->countProviders($db, $where, $params);
        $totalPages = max(1, (int) ceil($totalProviders / $perPage));

        $fetchLimit = ($sort === 'ml' || $sort === 'system') ? min($totalProviders, 120) : $perPage;
        $fetchOffset = ($sort === 'ml' || $sort === 'system') ? 0 : $offset;

        $providerColumns = [];
        try {
            $colStmt = $db->query("SHOW COLUMNS FROM service_providers");
            $providerColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Throwable $e) {
            $providerColumns = [];
        }

        $rawProviders = $this->repository->fetchProviders($db, $where, $params, $favIds, $sort, $fetchLimit, $fetchOffset, $providerColumns);

        $mlApiStatus = 'fallback';
        $mlScoreMap = [];
        $isSystemSort = $sort === 'system';

        if ($sort === 'ml' && !empty($rawProviders)) {
            $finalRankingWeights = [
                'ml' => (float) $this->repository->getPlatformSetting($db, 'ranking_weight_ml', '0.5'),
                'system' => (float) $this->repository->getPlatformSetting($db, 'ranking_weight_system', '0.3'),
                'admin' => (float) $this->repository->getPlatformSetting($db, 'ranking_weight_admin', '0.2'),
            ];

            $targetLocation = $location !== '' ? $location : $clientLocation;
            $userCoords = GeolocationHelper::getLocationCoordinates($db, $targetLocation);
            $useSearchRanking = $search !== '' || $category !== '' || $location !== '';
            $predictEndpoint = $useSearchRanking ? '/predict/search_ranking/batch' : '/predict/recommendation/batch';

            $batchPayload = [];
            foreach ($rawProviders as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $clicks = 0.0;
                try {
                    $s = $db->prepare("SELECT COUNT(*) FROM click_logs WHERE target_type='provider' AND target_id=?");
                    $s->execute([$pid]);
                    $clicks = (float) $s->fetchColumn();
                } catch (Throwable $e) {}

                $msgs = 0.0;
                try {
                    $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=?");
                    $s->execute([(int) ($p['user_id'] ?? 0)]);
                    $msgs = (float) $s->fetchColumn();
                } catch (Throwable $e) {}

                $avgResp = 24.0;
                try {
                    $s = $db->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,responded_at)) FROM bookings WHERE provider_id=? AND responded_at IS NOT NULL");
                    $s->execute([$pid]);
                    $ar = $s->fetchColumn();
                    if ($ar !== null && $ar !== false) {
                        $avgResp = (float) $ar;
                    }
                } catch (Throwable $e) {}

                $providerPrice = max(0.0, (float) ($p['avg_price'] ?? 0));
                $priceMatch = 0;
                if ($userStats['user_avg_price'] > 0 && $providerPrice > 0) {
                    $priceMatch = ($providerPrice >= $userStats['user_avg_price'] * 0.8 && $providerPrice <= $userStats['user_avg_price'] * 1.2) ? 1 : 0;
                }

                $features = [
                    'views' => max(0.0, (float) ($p['view_count'] ?? 0)),
                    'clicks' => max(0.0, $clicks),
                    'messages' => max(0.0, $msgs),
                    'rating' => min(5.0, max(0.0, (float) ($p['average_rating'] ?? 0))),
                    'price' => $providerPrice,
                    'avg_response_time' => max(0.0, $avgResp),
                ];

                if ($useSearchRanking) {
                    $features = array_merge($features, [
                        'is_verified' => (int) ((($p['is_verified'] ?? 0) || ($p['user_verified'] ?? 0))),
                        'is_featured' => (int) ($p['is_featured'] ?? 0),
                        'experience_years' => max(0, (int) ($p['experience_years'] ?? 0)),
                        'completion_rate' => isset($p['total_jobs']) && (int) $p['total_jobs'] > 0
                            ? min(1.0, max(0.0, (int) ($p['completed_jobs'] ?? 0) / max(1, (int) $p['total_jobs'])))
                            : 0.0,
                        'search_query_length' => mb_strlen($search),
                        'category_match' => $category !== '' && trim($p['profession'] ?? '') === $category ? 1 : 0,
                        'location_match' => $location !== '' && trim($p['provider_location'] ?? '') === $location ? 1 : 0,
                        'price_match' => $priceMatch,
                        'availability_match' => $avail === '' || strtolower(trim($p['availability'] ?? '')) === strtolower($avail) ? 1 : 0,
                        'user_search_frequency' => min(50, max(0, $userStats['user_total_bookings'])),
                        'user_category_preference' => isset($bookedProfessions[$p['profession']]) ? 1.0 : 0.0,
                        'user_price_range_preference' => $userStats['user_avg_price'] > 0 ? $userStats['user_avg_price'] : 0.0,
                    ]);
                } else {
                    $features = array_merge($features, [
                        'user_avg_price' => max(0.0, $userStats['user_avg_price']),
                        'user_avg_response_time' => max(0.0, $userStats['user_avg_response_time']),
                        'user_total_bookings' => max(0, $userStats['user_total_bookings']),
                    ]);
                }

                $batchPayload[] = $features;
            }

            if (extension_loaded('curl')) {
                $ch = curl_init('http://localhost:8000' . $predictEndpoint);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($batchPayload),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_CONNECTTIMEOUT => 2,
                ]);
                $mlRaw = curl_exec($ch);
                $mlErrno = curl_errno($ch);
                curl_close($ch);
            } else {
                $mlRaw = null;
                $mlErrno = 1;
            }

            if (!$mlErrno && $mlRaw) {
                $mlResults = json_decode($mlRaw, true);
                if (is_array($mlResults) && !empty($mlResults)) {
                    foreach ($mlResults as $index => $r) {
                        $providerId = (int) ($rawProviders[$index]['id'] ?? 0);
                        $prediction = isset($r['prediction']) ? (float) $r['prediction'] : 0.0;
                        $mlScoreMap[$providerId] = [
                            'score' => $prediction,
                            'prediction' => $prediction,
                            'confidence' => isset($r['probabilities']) ? 'ml' : 'n/a',
                        ];
                    }
                    $mlApiStatus = 'ml';
                }
            }

            foreach ($rawProviders as &$p) {
                $pid = (int) ($p['id'] ?? 0);
                $ml = $mlScoreMap[$pid] ?? null;

                $personalBoost = 0.0;
                if (isset($bookedProfessions[$p['profession']])) {
                    $personalBoost += min(0.15, $bookedProfessions[$p['profession']] * 0.03);
                }
                if (in_array($pid, $favIds, true)) {
                    $personalBoost += 0.12;
                }
                if (($p['provider_location'] ?? '') === $clientLocation && $clientLocation !== '') {
                    $personalBoost += 0.08;
                }
                if (in_array($pid, $recentlyViewedIds, true)) {
                    $personalBoost += 0.05;
                }

                $baseScore = $ml ? $ml['score'] : (
                    (float) ($p['average_rating'] ?? 0) / 5 * 0.5 +
                    min(0.3, (int) ($p['total_reviews'] ?? 0) / 100 * 0.3)
                );

                if ($useSearchRanking) {
                    $baseScore = min(100.0, max(0.0, $baseScore)) / 100.0;
                }

                $p['ml_score'] = min(1.0, $baseScore + $personalBoost);
                $p['ml_raw_score'] = $baseScore;
                $p['ml_confidence'] = $ml ? $ml['confidence'] : 'low';
                $p['personal_boost'] = $personalBoost;

                $distanceKm = null;
                if ($userCoords && !empty($p['latitude']) && !empty($p['longitude'])) {
                    $distanceKm = GeolocationHelper::haversineDistance(
                        $userCoords['latitude'],
                        $userCoords['longitude'],
                        (float) $p['latitude'],
                        (float) $p['longitude']
                    );
                }

                $distanceScore = $distanceKm !== null ? GeolocationHelper::calculateDistanceScore($distanceKm) : 5.0;
                $availability = strtolower(trim($p['availability'] ?? ''));
                $availabilityScore = $availability === 'available' ? 10.0 : ($availability === 'busy' ? 7.0 : 4.0);

                $responseHours = isset($p['avg_response_hours']) && $p['avg_response_hours'] !== null ? (float) $p['avg_response_hours'] : null;
                if ($responseHours === null) {
                    $responseScore = 6.0;
                } elseif ($responseHours <= 1) {
                    $responseScore = 10.0;
                } elseif ($responseHours <= 3) {
                    $responseScore = 9.0;
                } elseif ($responseHours <= 6) {
                    $responseScore = 8.0;
                } elseif ($responseHours <= 12) {
                    $responseScore = 7.0;
                } elseif ($responseHours <= 24) {
                    $responseScore = 5.0;
                } elseif ($responseHours <= 48) {
                    $responseScore = 3.0;
                } else {
                    $responseScore = 1.0;
                }

                $totalJobs = max(0, (int) ($p['total_jobs'] ?? 0));
                $completedJobs = max(0, (int) ($p['completed_jobs'] ?? 0));
                $completionScore = $totalJobs > 0 ? min(10.0, ($completedJobs / $totalJobs) * 10) : 5.0;

                $p['distance_km'] = $distanceKm;
                $p['system_score'] = round(
                    $distanceScore * 0.30 +
                    $availabilityScore * 0.25 +
                    $responseScore * 0.25 +
                    $completionScore * 0.20,
                    2
                );
                $p['distance_score'] = $distanceScore;
                $p['availability_score'] = $availabilityScore;
                $p['response_score'] = $responseScore;
                $p['completion_score'] = $completionScore;

                $p = calculate_final_score($p, $finalRankingWeights);
            }
            unset($p);

            usort($rawProviders, fn($a, $b) => ($b['final_score'] ?? 0) <=> ($a['final_score'] ?? 0));
            $providers = array_slice($rawProviders, $offset, $perPage);
        } elseif ($isSystemSort && !empty($rawProviders)) {
            $targetLocation = $location !== '' ? $location : $clientLocation;
            $userCoords = GeolocationHelper::getLocationCoordinates($db, $targetLocation);

            foreach ($rawProviders as &$p) {
                $distanceKm = null;
                if ($userCoords && !empty($p['latitude']) && !empty($p['longitude'])) {
                    $distanceKm = GeolocationHelper::haversineDistance(
                        $userCoords['latitude'],
                        $userCoords['longitude'],
                        (float) $p['latitude'],
                        (float) $p['longitude']
                    );
                }

                $distanceScore = $distanceKm !== null ? GeolocationHelper::calculateDistanceScore($distanceKm) : 5.0;
                $availability = strtolower(trim($p['availability'] ?? ''));
                $availabilityScore = $availability === 'available' ? 10.0 : ($availability === 'busy' ? 7.0 : 4.0);

                $responseHours = isset($p['avg_response_hours']) && $p['avg_response_hours'] !== null ? (float) $p['avg_response_hours'] : null;
                if ($responseHours === null) {
                    $responseScore = 6.0;
                } elseif ($responseHours <= 1) {
                    $responseScore = 10.0;
                } elseif ($responseHours <= 3) {
                    $responseScore = 9.0;
                } elseif ($responseHours <= 6) {
                    $responseScore = 8.0;
                } elseif ($responseHours <= 12) {
                    $responseScore = 7.0;
                } elseif ($responseHours <= 24) {
                    $responseScore = 5.0;
                } elseif ($responseHours <= 48) {
                    $responseScore = 3.0;
                } else {
                    $responseScore = 1.0;
                }

                $totalJobs = max(0, (int) ($p['total_jobs'] ?? 0));
                $completedJobs = max(0, (int) ($p['completed_jobs'] ?? 0));
                $completionScore = $totalJobs > 0 ? min(10.0, ($completedJobs / $totalJobs) * 10) : 5.0;

                $p['distance_km'] = $distanceKm;
                $p['system_score'] = round(
                    $distanceScore * 0.30 +
                    $availabilityScore * 0.25 +
                    $responseScore * 0.25 +
                    $completionScore * 0.20,
                    2
                );
                $p['distance_score'] = $distanceScore;
                $p['availability_score'] = $availabilityScore;
                $p['response_score'] = $responseScore;
                $p['completion_score'] = $completionScore;
                $p['ml_score'] = 0.0;
                $p['ml_raw_score'] = 0.0;
                $p['ml_confidence'] = 'n/a';
                $p['personal_boost'] = 0.0;
            }
            unset($p);

            usort($rawProviders, function ($a, $b) {
                $scoreCompare = $b['system_score'] <=> $a['system_score'];
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }
                $ratingCompare = ($b['average_rating'] ?? 0) <=> ($a['average_rating'] ?? 0);
                if ($ratingCompare !== 0) {
                    return $ratingCompare;
                }
                return ($b['total_reviews'] ?? 0) <=> ($a['total_reviews'] ?? 0);
            });
            $providers = array_slice($rawProviders, $offset, $perPage);
        } else {
            foreach ($rawProviders as &$p) {
                $p['ml_score'] = 0.0;
                $p['ml_raw_score'] = 0.0;
                $p['ml_confidence'] = 'n/a';
                $p['personal_boost'] = 0.0;
            }
            unset($p);
            $providers = $rawProviders;
        }

        $forYouProviders = [];
        if (!empty($bookedProfessions) || !empty($favIds)) {
            $forYouProviders = $this->repository->getForYouProviders($db, $providers, array_keys($bookedProfessions));
        }

        $catIcons = [
            'Plumbing' => 'fa-wrench', 'Plumber' => 'fa-wrench', 'Electrical' => 'fa-bolt', 'Electrician' => 'fa-bolt',
            'Construction' => 'fa-hammer', 'Carpenter' => 'fa-hammer', 'Carpentry' => 'fa-hammer',
            'Cleaning' => 'fa-broom', 'Painting' => 'fa-paint-brush', 'Painter' => 'fa-paint-brush',
            'Landscaping' => 'fa-leaf', 'Gardener' => 'fa-leaf', 'HVAC' => 'fa-fan', 'Roofing' => 'fa-house-damage',
            'Welding' => 'fa-fire', 'Masonry' => 'fa-cube', 'Mechanic' => 'fa-tools', 'Hairdresser' => 'fa-scissors',
            'Tutoring' => 'fa-book', 'IT Support' => 'fa-laptop', 'Photography' => 'fa-camera', 'default' => 'fa-star'
        ];

        return [
            'platform_name' => $platformName,
            'client_name' => $clientName,
            'client_location' => $clientLocation,
            'search' => $search,
            'category' => $category,
            'location' => $location,
            'sort' => $sort,
            'avail' => $avail,
            'min_rating' => $minRating,
            'verified' => $verified,
            'page' => $page,
            'per_page' => $perPage,
            'total_providers' => $totalProviders,
            'total_pages' => $totalPages,
            'all_cats' => $filterOptions['categories'],
            'all_locations' => $filterOptions['locations'],
            'providers' => $providers,
            'for_you_providers' => $forYouProviders,
            'booked_professions' => $bookedProfessions,
            'fav_ids' => $favIds,
            'recently_viewed_ids' => $recentlyViewedIds,
            'user_total_bookings' => $userStats['user_total_bookings'],
            'user_avg_price' => $userStats['user_avg_price'],
            'user_avg_response_time' => $userStats['user_avg_response_time'],
            'ml_api_status' => $mlApiStatus,
            'cat_icons' => $catIcons,
        ];
    }
}
