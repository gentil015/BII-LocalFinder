<script>
(function() {
    const pageUrl = window.location.href;
    const pageTitle = document.title;
    let pageStartTime = Date.now();

    function sendTrack(action, data = {}) {
        const payload = new URLSearchParams(Object.assign({
            action,
            page_url: pageUrl,
            page_title: pageTitle
        }, data));

        fetch('../api/track_user_behavior.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload
        }).catch(console.error);
    }

    function trackPageView() {
        sendTrack('track_page_view', { referrer: document.referrer || '' });
    }

    function trackSearch(searchQuery, searchType = 'general', filters = {}, resultsCount = 0) {
        if (!searchQuery || !searchQuery.toString().trim()) {
            return;
        }

        const payload = new URLSearchParams({
            action: 'track_search',
            search_query: searchQuery.toString().trim(),
            search_type: searchType || 'general',
            filters: typeof filters === 'string' ? filters : JSON.stringify(filters || {}),
            results_count: Number.isFinite(Number(resultsCount)) ? Number(resultsCount) : 0
        });

        fetch('../api/track_user_behavior.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload
        }).catch(console.error);
    }

    function trackClick(eventType, targetType = '', targetId = null, metadata = {}) {
        if (!eventType || !eventType.toString().trim()) {
            return;
        }

        const body = new URLSearchParams({
            action: 'track_click',
            event_type: eventType.toString().trim(),
            target_type: targetType ? targetType.toString() : '',
            target_id: targetId !== null ? String(targetId) : '',
            metadata: typeof metadata === 'string' ? metadata : JSON.stringify(metadata || {}),
            page_url: window.location.href
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon('../api/track_user_behavior.php', body);
        } else {
            fetch('../api/track_user_behavior.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).catch(console.error);
        }
    }

    window.trackSearch = trackSearch;
    window.trackClick = trackClick;

    function startPageSession() {
        sendTrack('start_page_session', { page_start: new Date(pageStartTime).toISOString() });
    }

    function endPageSession() {
        const pageEndTime = Date.now();
        const timeSpentSeconds = Math.floor((pageEndTime - pageStartTime) / 1000);
        sendTrack('end_page_session', {
            page_start: new Date(pageStartTime).toISOString(),
            page_end: new Date(pageEndTime).toISOString(),
            time_spent_seconds: timeSpentSeconds
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        trackPageView();
        startPageSession();
    });

    window.addEventListener('beforeunload', endPageSession);
    window.addEventListener('unload', endPageSession);

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            endPageSession();
        } else {
            pageStartTime = Date.now();
            startPageSession();
        }
    });
})();
</script>
