<?php
/**
 * Expects $p (enriched provider row) in scope. Include from a foreach loop.
 * Optional $favIds (int[]) in scope for the favorite-button initial state.
 */
$favIds = $favIds ?? [];
$badgeIcons = [
    'top_rated'           => 'fa-star',
    'trending'            => 'fa-fire',
    'fast_response'       => 'fa-bolt',
    'highly_recommended'  => 'fa-thumbs-up',
    'verified'            => 'fa-circle-check',
    'new'                 => 'fa-seedling',
    'popular_nearby'      => 'fa-location-dot',
    'most_booked'         => 'fa-briefcase',
    'trusted'             => 'fa-shield-halved',
    'available_now'       => 'fa-circle',
];

$pid = (int) $p['id'];
$name = htmlspecialchars($p['full_name'] ?? 'Provider');
$profession = htmlspecialchars($p['profession'] ?? '');
$photo = !empty($p['profile_image'])
    ? '../uploads/profiles/' . rawurlencode($p['profile_image'])
    : '../assets/img/avatar-placeholder.png';
$rating = round((float) ($p['average_rating'] ?? 0), 1);
$reviews = (int) ($p['total_reviews'] ?? 0);
$completedJobs = (int) ($p['completed_jobs'] ?? 0);
$distance = $p['distance_km'] ?? null;
$responseHours = $p['avg_response_hours'] ?? null;
$experience = (int) ($p['experience_years'] ?? 0);
$bio = htmlspecialchars(mb_strimwidth((string) ($p['bio'] ?? ''), 0, 90, '…'));
$isFav = !empty($p['is_favorite']) || in_array($pid, $favIds, true);
$trustScore = (float) ($p['trust_score'] ?? 0);
$isOpen = !empty($p['is_open_now']);
?>
<article class="pcard" id="dcard-<?= $pid ?>" data-provider-id="<?= $pid ?>" data-lazy-visible="0">
  <div class="pcard-media">
    <img data-src="<?= htmlspecialchars($photo) ?>" src="/bii_localfinder/assets/img/card-skeleton.svg"
         alt="<?= $name ?>" class="pcard-photo lazy-img" loading="lazy" width="120" height="120">
    <button type="button" class="pcard-fav <?= $isFav ? 'active' : '' ?>"
            data-fav-btn data-provider-id="<?= $pid ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
            title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">
      <i class="<?= $isFav ? 'fas' : 'far' ?> fa-heart"></i>
    </button>
    <?php if ($isOpen): ?>
      <span class="pcard-open-pill"><i class="fas fa-circle"></i> Open now</span>
    <?php endif; ?>
  </div>

  <div class="pcard-body">
    <div class="pcard-badges">
      <?php foreach (($p['badges'] ?? []) as $bk => $label): ?>
        <span class="badge-pill badge-<?= htmlspecialchars($bk) ?>">
          <i class="fas <?= $badgeIcons[$bk] ?? 'fa-star' ?>"></i><?= htmlspecialchars($label) ?>
        </span>
      <?php endforeach; ?>
    </div>

    <h3 class="pcard-name"><?= $name ?></h3>
    <div class="pcard-profession"><?= $profession ?></div>

    <div class="pcard-meta">
      <span title="Average rating"><i class="fas fa-star text-warning"></i> <?= $rating ?> <small>(<?= $reviews ?>)</small></span>
      <span title="Completed jobs"><i class="fas fa-check-circle text-success"></i> <?= $completedJobs ?> jobs</span>
      <?php if ($distance !== null): ?>
        <span title="Distance"><i class="fas fa-location-dot"></i> <?= round($distance, 1) ?> km</span>
      <?php endif; ?>
      <?php if ($responseHours !== null): ?>
        <span title="Typical response time"><i class="fas fa-clock"></i> ~<?= round((float) $responseHours, 1) ?>h</span>
      <?php endif; ?>
      <?php if ($experience > 0): ?>
        <span title="Years of experience"><i class="fas fa-briefcase"></i> <?= $experience ?>y exp.</span>
      <?php endif; ?>
    </div>

    <div class="pcard-trust" title="Trust score: verified rule-based blend of rating, completion rate, verification, review volume and tenure">
      <div class="trust-bar"><div class="trust-fill" style="width:<?= max(0, min(100, $trustScore)) ?>%"></div></div>
      <span><?= $trustScore ?>/100 trust</span>
    </div>

    <?php if ($bio !== ''): ?><p class="pcard-desc"><?= $bio ?></p><?php endif; ?>

    <div class="pcard-actions">
      <a class="btn btn-sm btn-outline-primary" href="/bii_localfinder/client/messages.php?with=<?= (int) ($p['user_id'] ?? 0) ?>">
        <i class="fas fa-comment-dots"></i> Quick Contact
      </a>
      <a class="btn btn-sm btn-primary" href="/bii_localfinder/client/booking.php?provider_id=<?= $pid ?>">
        <i class="fas fa-calendar-check"></i> Book Now
      </a>
    </div>
  </div>
</article>
