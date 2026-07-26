<?php
/**
 * Expects in scope:
 *   $discovery = ['sections' => [...], 'has_history' => bool, 'fav_ids' => int[]]
 * Included from client/providers.php only when NO active search/filter is applied.
 */
$sections = $discovery['sections'] ?? [];
$favIds = $discovery['fav_ids'] ?? [];
if (empty($sections)) {
    return;
}
?>
<div class="discovery-wrap" id="discoveryWrap">
  <?php if (empty($discovery['has_history'])): ?>
    <div class="discovery-welcome">
      <i class="fas fa-hand-sparkles"></i>
      New here? Here's what's popular on <?= htmlspecialchars($platform_name ?? 'BII LocalFinder') ?> right now.
    </div>
  <?php endif; ?>

  <?php foreach ($sections as $section): ?>
    <section class="discovery-section" data-section-key="<?= htmlspecialchars($section['key']) ?>">
      <div class="discovery-header">
        <h2><i class="fas <?= htmlspecialchars($section['icon']) ?>"></i> <?= htmlspecialchars($section['title']) ?></h2>
        <div class="discovery-nav">
          <button type="button" class="scroll-btn" data-scroll-dir="-1" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
          <button type="button" class="scroll-btn" data-scroll-dir="1" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
        </div>
      </div>
      <div class="discovery-track" data-track tabindex="0">
        <?php foreach ($section['providers'] as $p): ?>
          <?php include __DIR__ . '/provider_card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
