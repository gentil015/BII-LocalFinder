// ------------------------------------------------------------
// Modern, modular JavaScript for the homepage
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // 1. Lazy-load images (Bootstrap already has lazy via `loading="lazy"` in HTML5)
    //    – we add a fallback for older browsers
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img.lazy').forEach(img => {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    } else {
        // fallback: IntersectionObserver
        const lazyImages = document.querySelectorAll('img[data-src]');
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    obs.unobserve(img);
                }
            });
        });
        lazyImages.forEach(img => observer.observe(img));
    }

    // 2. Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // 3. Optional: animate cards on scroll (AOS-like without library)
    const animateOnScroll = () => {
        const cards = document.querySelectorAll('.card');
        const triggerBottom = window.innerHeight * 0.85;

        cards.forEach(card => {
            const top = card.getBoundingClientRect().top;
            if (top < triggerBottom) {
                card.classList.add('animate__animated', 'animate__fadeInUp');
            }
        });
    };
    window.addEventListener('scroll', animateOnScroll);
    animateOnScroll(); // initial check

    // 4. Search form – add spinner on submit
    const searchForm = document.querySelector('.hero form');
    if (searchForm) {
        searchForm.addEventListener('submit', () => {
            const btn = searchForm.querySelector('button[type="submit"]');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>Searching…`;
            // Reset after navigation (won’t run, but safe)
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            }, 3000);
        });
    }
});