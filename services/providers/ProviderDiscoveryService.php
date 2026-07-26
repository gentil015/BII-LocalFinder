<?php

require_once __DIR__ . '/../../repositories/providers/ProviderDiscoveryRepository.php';
require_once __DIR__ . '/../../includes/recommendation_engine.php';
require_once __DIR__ . '/../../includes/geolocation.php';

/**
 * ProviderDiscoveryService
 * ---------------------------------------------------------------------
 * Builds the full personalized "discovery" view model for the Providers
 * page: which sections to show, in what order, and the enriched
 * provider cards (distance, trust score, badges) inside each one.
 *
 * Sections that carry ZERO providers for a given user are dropped
 * silently, so the page never renders an empty shelf.
 * ---------------------------------------------------------------------
 */
class ProviderDiscoveryService
{
    /** Sections whose providers should never be filtered out for "already shown elsewhere". */
    private const PROTECTED_SECTIONS = ['for_you', 'continue_booking', 'recently_viewed', 'matching_interests'];

    private const SECTION_LIMITS = [
        'continue_booking' => 3,
        'recently_viewed'  => 10,
        'default'          => 10,
    ];

    public function __construct(private ?ProviderDiscoveryRepository $repository = null)
    {
        $this->repository = $repository ?? new ProviderDiscoveryRepository();
    }

    public function buildDiscoveryPage(PDO $db, int $userId, array $filterContext = []): array
    {
        $repo = $this->repository;

        // 1) Gather real signals — no guessing, no ML, straight rule inputs.
        $favIds = $repo->getFavoriteProviderIds($db, $userId);
        $topProfessions = $repo->getTopProfessions($db, $userId);
        $coOccurring = $repo->getCoOccurringProfessions($db, $userId, $topProfessions);
        $recentlyViewedIds = $repo->getRecentlyViewedProviderIds($db, $userId);
        $openBookingProviderId = $repo->getOpenBookingProviderId($db, $userId);
        $frequentLocation = $repo->getClientFrequentLocation($db, $userId);
        $hasHistory = $repo->hasAnyHistory($db, $userId);

        $clientLocation = $filterContext['location'] ?? ($frequentLocation ?? '');
        $clientDistrict = $filterContext['district'] ?? '';

        $signals = [
            'has_history'              => $hasHistory,
            'top_professions'          => $topProfessions,
            'co_occurring_professions' => $coOccurring,
            'recently_viewed_ids'      => $recentlyViewedIds,
            'open_booking'             => $openBookingProviderId,
            'has_location'             => $clientLocation !== '',
            'prefers_fast_response'    => $repo->prefersFastResponse($db, $userId),
            'price_sensitive'          => $repo->isPriceSensitive($db, $userId),
        ];

        $now = new DateTime('now');
        $context = [
            'hour'         => (int) $now->format('G'),
            'day_of_week'  => (int) $now->format('N'), // 1=Mon ... 7=Sun
        ];

        // 2) Rule engine decides which shelves appear, and in what order.
        $planned = RecommendationEngine::planSections($userId, $signals, $context);

        $coords = $clientLocation !== '' ? $repo->getLocationCoordinates($db, $clientLocation) : null;

        $sections = [];
        $seenIds = [];

        foreach ($planned as $sectionKey) {
            $limit = self::SECTION_LIMITS[$sectionKey] ?? self::SECTION_LIMITS['default'];
            $providers = $this->fetchSection($db, $sectionKey, $repo, [
                'topProfessions'   => $topProfessions,
                'coOccurring'      => $coOccurring,
                'recentlyViewed'   => $recentlyViewedIds,
                'openBooking'      => $openBookingProviderId,
                'district'         => $clientDistrict,
                'favIds'           => $favIds,
                'limit'            => $limit,
                'seenIds'          => array_keys($seenIds),
            ]);

            if (empty($providers)) {
                continue;
            }

            if (!in_array($sectionKey, self::PROTECTED_SECTIONS, true)) {
                $providers = $this->deduplicate($providers, $seenIds, $limit);
                if (empty($providers)) {
                    continue;
                }
            }

            foreach ($providers as &$p) {
                $p = $this->enrichProvider($p, $coords, $clientDistrict, $sectionKey);
                $seenIds[(int) $p['id']] = true;
            }
            unset($p);

            $meta = RecommendationEngine::CATALOG[$sectionKey] ?? ['title' => ucfirst($sectionKey), 'icon' => 'fa-star'];
            $sections[] = [
                'key'       => $sectionKey,
                'title'     => $meta['title'],
                'icon'      => $meta['icon'],
                'providers' => $providers,
            ];
        }

        return [
            'sections'       => $sections,
            'has_history'    => $hasHistory,
            'client_location'=> $clientLocation,
            'fav_ids'        => $favIds,
        ];
    }

    /** Drop providers already displayed in an earlier (higher priority) section, unless it would empty the shelf. */
    private function deduplicate(array $providers, array $seenIds, int $limit): array
    {
        $fresh = array_values(array_filter($providers, fn($p) => !isset($seenIds[(int) $p['id']])));
        $result = !empty($fresh) ? $fresh : $providers; // never leave a shelf empty just for variety
        return array_slice($result, 0, $limit);
    }

    private function fetchSection(PDO $db, string $key, ProviderDiscoveryRepository $repo, array $ctx): array
    {
        return match ($key) {
            'for_you'            => $repo->sectionForYou($db, $ctx['topProfessions'], $ctx['favIds'], $ctx['limit']),
            'continue_booking'   => $repo->sectionByIds($db, $ctx['openBooking'] ? [$ctx['openBooking']] : [], $ctx['favIds'], $ctx['limit']),
            'recently_viewed'    => $repo->sectionByIds($db, $ctx['recentlyViewed'], $ctx['favIds'], $ctx['limit']),
            'matching_interests' => $repo->sectionMatchingInterests($db, $ctx['topProfessions'], $ctx['seenIds'], $ctx['favIds'], $ctx['limit']),
            'you_may_like'       => $repo->sectionYouMayLike($db, $ctx['coOccurring'], $ctx['seenIds'], $ctx['favIds'], $ctx['limit']),
            'near_you'           => $repo->sectionNearYou($db, null, $ctx['favIds'], $ctx['limit']),
            'popular_in_city'    => $repo->sectionPopularInCity($db, $ctx['district'], $ctx['favIds'], $ctx['limit']),
            'available_now'      => $repo->sectionAvailableNow($db, $ctx['favIds'], $ctx['limit']),
            'top_rated_near_you' => $repo->sectionTopRatedNearYou($db, $ctx['favIds'], $ctx['limit']),
            'fast_responders'    => $repo->sectionFastResponders($db, $ctx['favIds'], $ctx['limit']),
            'trending'           => $repo->sectionTrending($db, $ctx['favIds'], $ctx['limit']),
            'most_trusted'       => $repo->sectionMostTrusted($db, $ctx['favIds'], $ctx['limit']),
            'verified'           => $repo->sectionVerified($db, $ctx['favIds'], $ctx['limit']),
            'premium'            => $repo->sectionPremium($db, $ctx['favIds'], $ctx['limit']),
            'special_offers'     => $repo->sectionSpecialOffers($db, $ctx['favIds'], $ctx['limit']),
            'weekend_picks'      => $repo->sectionWeekendPicks($db, $ctx['favIds'], $ctx['limit']),
            'emergency_services' => $repo->sectionEmergencyServices($db, $ctx['favIds'], $ctx['limit']),
            'new_providers'      => $repo->sectionNewProviders($db, $ctx['favIds'], $ctx['limit']),
            'hidden_gems'        => $repo->sectionHiddenGems($db, $ctx['favIds'], $ctx['limit']),
            default              => [],
        };
    }

    private function enrichProvider(array $p, ?array $coords, string $clientDistrict, string $sectionKey): array
    {
        $distanceKm = null;
        if ($coords && !empty($p['latitude']) && !empty($p['longitude'])) {
            $distanceKm = GeolocationHelper::haversineDistance(
                $coords['latitude'],
                $coords['longitude'],
                (float) $p['latitude'],
                (float) $p['longitude']
            );
        }

        $p['distance_km'] = $distanceKm;
        $p['same_city'] = $clientDistrict !== '' && ($p['district'] ?? '') === $clientDistrict;
        $p['is_trending'] = ((int) ($p['recent_activity'] ?? 0)) > 0;
        $p['trust_score'] = RecommendationEngine::trustScore($p);
        $p['badges'] = RecommendationEngine::badgesFor($p, ['section' => $sectionKey]);
        $p['is_open_now'] = strtolower((string) ($p['availability'] ?? '')) === 'available';

        return $p;
    }
}
