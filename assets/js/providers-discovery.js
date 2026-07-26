/**
 * Providers Discovery page behaviour.
 * Scoped entirely to #discoveryWrap — never touches the classic filtered
 * grid or any other page.
 */
(function () {
  'use strict';

  const WRAP_ID = 'discoveryWrap';

  function initScrollButtons(root) {
    root.querySelectorAll('.discovery-section').forEach((section) => {
      const track = section.querySelector('[data-track]');
      if (!track) return;

      const updateButtons = () => {
        const left = section.querySelector('[data-scroll-dir="-1"]');
        const right = section.querySelector('[data-scroll-dir="1"]');
        if (left) left.disabled = track.scrollLeft <= 2;
        if (right) right.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
      };

      section.querySelectorAll('.scroll-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const dir = parseInt(btn.dataset.scrollDir, 10) || 1;
          const cardWidth = track.querySelector('.pcard')?.offsetWidth ?? 260;
          track.scrollBy({ left: dir * (cardWidth + 16) * 2, behavior: 'smooth' });
        });
      });

      track.addEventListener('scroll', updateButtons, { passive: true });
      // Support trackpad/mouse-wheel horizontal scroll on desktop.
      track.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          track.scrollLeft += e.deltaY;
          e.preventDefault();
        }
      }, { passive: false });

      updateButtons();
    });
  }

  function initLazyImages(root) {
    const images = root.querySelectorAll('img.lazy-img[data-src]');
    if (!('IntersectionObserver' in window)) {
      images.forEach(loadImage);
      return;
    }
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          loadImage(entry.target);
          obs.unobserve(entry.target);
        }
      });
    }, { rootMargin: '200px 400px' });
    images.forEach((img) => observer.observe(img));
  }

  function loadImage(img) {
    const src = img.dataset.src;
    if (!src) return;
    const probe = new Image();
    probe.onload = () => { img.src = src; img.classList.add('lazy-loaded'); };
    probe.onerror = () => { img.classList.add('lazy-loaded'); };
    probe.src = src;
  }

  /**
   * Progressive shelf reveal: sections below the fold start with
   * .discovery-section--pending (hidden via CSS-less JS toggle) and pop in
   * as the user scrolls near them, keeping first paint light on long pages.
   */
  function initInfiniteReveal(root) {
    const sections = Array.from(root.querySelectorAll('.discovery-section'));
    if (sections.length <= 3 || !('IntersectionObserver' in window)) {
      sections.forEach((s) => s.classList.add('is-revealed'));
      return;
    }
    sections.slice(0, 3).forEach((s) => s.classList.add('is-revealed'));
    const pending = sections.slice(3);
    pending.forEach((s) => { s.style.opacity = '0'; s.style.transform = 'translateY(12px)'; });

    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        el.style.transition = 'opacity .35s ease, transform .35s ease';
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
        el.classList.add('is-revealed');
        obs.unobserve(el);
      });
    }, { rootMargin: '150px 0px' });

    pending.forEach((s) => observer.observe(s));
  }

  /**
   * Favorite toggle. Expects a JSON endpoint that flips the favorite state
   * for the current logged-in client and returns { success, is_favorite }.
   * Adjust FAVORITE_ENDPOINT to match your existing toggle-favorite route.
   */
  const FAVORITE_ENDPOINT = '/bii_localfinder/client/ajax/toggle_favorite.php';

  function initFavoriteButtons(root) {
    root.querySelectorAll('[data-fav-btn]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const providerId = btn.dataset.providerId;
        if (!providerId || btn.disabled) return;
        btn.disabled = true;
        const wasActive = btn.classList.contains('active');

        // Optimistic UI update.
        toggleFavButtonVisual(btn, !wasActive);

        try {
          const res = await fetch(FAVORITE_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: 'provider_id=' + encodeURIComponent(providerId),
          });
          const data = await res.json();
          if (!data || data.success !== true) throw new Error('toggle failed');
          toggleFavButtonVisual(btn, !!data.is_favorite);
        } catch (e) {
          toggleFavButtonVisual(btn, wasActive); // revert on failure
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  function toggleFavButtonVisual(btn, active) {
    btn.classList.toggle('active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    btn.title = active ? 'Remove from favorites' : 'Add to favorites';
    const icon = btn.querySelector('i');
    if (icon) icon.className = (active ? 'fas' : 'far') + ' fa-heart';
  }

  function init() {
    const root = document.getElementById(WRAP_ID);
    if (!root) return;
    initScrollButtons(root);
    initLazyImages(root);
    initInfiniteReveal(root);
    initFavoriteButtons(root);
  }

  document.addEventListener('DOMContentLoaded', init);
  // Re-init after the classic grid's own AJAX reload swaps DOM (in case both coexist on the page).
  document.addEventListener('bii:discovery-refresh', init);
})();
