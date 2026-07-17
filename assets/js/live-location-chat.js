/* Live location chat support for BII LocalFinder */
(function () {
    const WS_PORT = 8765;
    const WS_PATH = '/ws/live_location';
    const THROTTLE_MS = 2000;
    const MAX_RECONNECT_DELAY_MS = 15000;
    const DEFAULT_SHARE_DURATION_MINUTES = 15;

    if (typeof window === 'undefined') {
        return;
    }

    const chatRoom = window.chatLocationRoom || null;
    const chatUserId = Number(window.chatUserId || 0);
    const chatPartnerId = Number(window.chatPartnerId || 0);
    const chatBookingId = Number(window.chatBookingId || 0);
    const messagesArea = document.getElementById('messagesArea');
    const liveLocationPanel = document.getElementById('liveLocationPanel');
    const liveLocationStatus = document.getElementById('liveLocationStatus');
    const shareLocationBtn = document.getElementById('shareLocationBtn');
    const stopLocationBtn = document.getElementById('stopLocationBtn');
    const shareDurationSelect = document.getElementById('shareDurationSelect');
    const liveLocationMapEl = document.getElementById('liveLocationMap');
    const shareLocationTrigger = document.getElementById('shareLocationTrigger');
    const shareLocationTriggerModal = document.getElementById('shareLocationTriggerModal');
    const inputMenuDropdown = document.getElementById('inputMenuDropdown');

    if (!chatRoom || !chatUserId || !chatPartnerId || !liveLocationPanel || !liveLocationMapEl) {
        return;
    }

    let hasSentLocationMessage = false;

    let socket = null;
    let watchId = null;
    let lastSendTime = 0;
    let reconnectDelay = 2000;
    let reconnectTimer = null;
    let shareTimer = null;
    let isSharingLocation = false;
    let map = null;
    let markers = {};
    let paths = {};
    let remoteSharingActive = false;

    function buildWebsocketUrl(token) {
        const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
        const hostname = window.location.hostname;
        return `${protocol}://${hostname}:${WS_PORT}${WS_PATH}?conversation_id=${encodeURIComponent(chatRoom)}&token=${encodeURIComponent(token)}`;
    }

    function appendLiveLocationMessage(text, isSent = false) {
        if (!messagesArea) {
            return;
        }

        const existing = messagesArea.querySelector('.live-location-message');
        if (existing) {
            existing.remove();
        }

        const group = document.createElement('div');
        group.className = 'message-group live-location-message';

        const message = document.createElement('div');
        message.className = `message ${isSent ? 'sent' : 'received'}`;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.innerHTML = `<strong>${text}</strong>`;

        message.appendChild(bubble);
        group.appendChild(message);

        messagesArea.appendChild(group);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function updatePanelState(statusText, active) {
        liveLocationStatus.textContent = statusText;
        if (shareLocationBtn) {
            shareLocationBtn.style.display = active ? 'none' : '';
        }
        if (stopLocationBtn) {
            stopLocationBtn.style.display = active ? '' : 'none';
        }
        if (map) {
            setTimeout(() => { map.invalidateSize(); }, 120);
        }
    }

    function showLiveLocationPanel() {
        if (!liveLocationPanel) {
            return;
        }
        liveLocationPanel.style.display = 'block';
        if (map) {
            setTimeout(() => { map.invalidateSize(); }, 120);
        }
    }

    function hideLiveLocationPanel() {
        if (!liveLocationPanel) {
            return;
        }
        liveLocationPanel.style.display = 'none';
    }

    function startMap() {
        if (map) {
            return;
        }

        if (typeof L === 'undefined') {
            throw new Error('Leaflet library not loaded');
        }

        map = L.map('liveLocationMap', {
            zoomControl: false,
            attributionControl: true,
        }).setView([0, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
    }

    function waitForLeaflet(callback) {
        if (typeof L !== 'undefined') {
            callback();
            return;
        }

        const leafletScript = document.querySelector('script[src*="leaflet"]');
        if (!leafletScript) {
            updatePanelState('Live location unavailable: map library missing', false);
            return;
        }

        const onLoad = function() {
            leafletScript.removeEventListener('load', onLoad);
            leafletScript.removeEventListener('error', onError);
            callback();
        };
        const onError = function() {
            leafletScript.removeEventListener('load', onLoad);
            leafletScript.removeEventListener('error', onError);
            updatePanelState('Live location unavailable: map library failed to load', false);
        };

        leafletScript.addEventListener('load', onLoad);
        leafletScript.addEventListener('error', onError);

        // If the script already finished loading before event listener was attached
        if (leafletScript.readyState === 'complete' || leafletScript.readyState === 'loaded') {
            onLoad();
        }
    }

    function fitMapBounds() {
        if (!map || Object.keys(markers).length === 0) {
            return;
        }

        const group = L.featureGroup(Object.values(markers));
        map.fitBounds(group.getBounds().pad(0.25));
    }

    function addOrUpdateMarker(data, isSelf) {
        if (!map) {
            startMap();
        }

        const markerId = String(data.user_id);
        const latlng = [data.latitude, data.longitude];

        if (markers[markerId]) {
            markers[markerId].setLatLng(latlng);
        } else {
            const marker = L.circleMarker(latlng, {
                radius: 10,
                weight: 2,
                fillOpacity: 0.85,
                color: isSelf ? '#0b69ff' : '#16a34a',
                fillColor: isSelf ? '#60a5fa' : '#34d399',
            }).bindTooltip(isSelf ? 'You' : 'Other user', {permanent: false, direction: 'top'});
            markers[markerId] = marker.addTo(map);
        }

        if (!paths[markerId]) {
            paths[markerId] = L.polyline([latlng], {color: isSelf ? '#0b69ff' : '#16a34a', weight: 3, opacity: 0.85}).addTo(map);
        } else {
            paths[markerId].addLatLng(latlng);
        }

        fitMapBounds();
    }

    async function fetchLiveLocationToken() {
        try {
            const resp = await fetch(`../api/ws_token.php?conversation_id=${encodeURIComponent(chatRoom)}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!resp.ok) {
                throw new Error('Unable to fetch token');
            }

            return resp.json();
        } catch (error) {
            updatePanelState('Live location unavailable: auth failed', false);
            console.error('live location token error', error);
            return { success: false, error: error.message };
        }
    }

    async function fetchInitialLocations() {
        try {
            const resp = await fetch(`../api/get_location_history.php?conversation_id=${encodeURIComponent(chatRoom)}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });
            const data = await resp.json();
            if (!data.success) {
                return;
            }

            let hasOtherLocation = false;
            data.live_locations.forEach(location => {
                addOrUpdateMarker(location, Number(location.user_id) === chatUserId);
                if (Number(location.user_id) !== chatUserId) {
                    remoteSharingActive = true;
                    hasOtherLocation = true;
                }
            });

            if (data.live_locations.length > 0) {
                showLiveLocationPanel();
                updatePanelState('Live location active', false);
            } else {
                hideLiveLocationPanel();
            }
        } catch (error) {
            console.warn('Failed to fetch live location state', error);
        }
    }

    function sendSocketMessage(payload) {
        if (!socket || socket.readyState !== WebSocket.OPEN) {
            return;
        }
        socket.send(JSON.stringify(payload));
    }

    function handleSocketData(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        if (data.type === 'receive_location' && data.payload) {
            addOrUpdateMarker(data.payload, Number(data.payload.user_id) === chatUserId);
            showLiveLocationPanel();
            updatePanelState('Partner is sharing live location', isSharingLocation);
            if (!remoteSharingActive && Number(data.payload.user_id) !== chatUserId) {
                appendLiveLocationMessage('Partner started sharing live location');
                remoteSharingActive = true;
            }
        }

        if (data.type === 'user_stopped_sharing' && data.payload) {
            updatePanelState('Partner stopped sharing location', isSharingLocation);
            if (remoteSharingActive) {
                appendLiveLocationMessage('Partner stopped sharing live location');
            }
            remoteSharingActive = false;
        }
    }

    function handleSocketOpen() {
        reconnectDelay = 2000;
        updatePanelState(isSharingLocation ? 'Connected, sharing live location' : 'Live location ready');
    }

    function handleSocketClose(event) {
        if (isSharingLocation) {
            updatePanelState('Reconnecting to live location...', true);
        }

        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
        }
        reconnectTimer = setTimeout(async () => {
            reconnectTimer = null;
            const tokenData = await fetchLiveLocationToken();
            if (tokenData.success) {
                openSocket(tokenData.token);
            }
        }, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 1.5, MAX_RECONNECT_DELAY_MS);
    }

    function handleSocketMessage(event) {
        try {
            const payload = JSON.parse(event.data);
            handleSocketData(payload);
        } catch (error) {
            console.warn('Invalid live location message', error);
        }
    }

    function handleSocketError() {
        updatePanelState('Live location connection error', false);
    }

    async function openSocket(token) {
        if (socket) {
            try {
                socket.close();
            } catch (error) {
                // ignore
            }
            socket = null;
        }

        const wsUrl = buildWebsocketUrl(token);
        socket = new WebSocket(wsUrl);
        socket.addEventListener('open', handleSocketOpen);
        socket.addEventListener('message', handleSocketMessage);
        socket.addEventListener('close', handleSocketClose);
        socket.addEventListener('error', handleSocketError);
    }

    function onPositionUpdate(position) {
        if (!position || !position.coords) {
            return;
        }

        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;
        const now = Date.now();
        if (now - lastSendTime < THROTTLE_MS) {
            return;
        }

        lastSendTime = now;
        addOrUpdateMarker({ user_id: chatUserId, latitude, longitude }, true);

        if (!hasSentLocationMessage) {
            hasSentLocationMessage = true;
            sendLocationMessage(latitude, longitude);
        }

        sendSocketMessage({
            type: 'send_location',
            conversation_id: chatRoom,
            user_id: chatUserId,
            latitude,
            longitude,
            action: 'update',
        });
    }

    function onPositionError(error) {
        console.warn('Geolocation error', error);
        showToast('Unable to read location. Please allow location access.', 'error');
        updatePanelState('Unable to read location. Please allow location access.', false);
    }

    function sendLocationMessage(latitude, longitude) {
        const formData = new FormData();
        formData.append('receiver_id', chatPartnerId);
        formData.append('ajax', '1');
        if (chatBookingId) {
            formData.append('booking_id', chatBookingId);
        }
        formData.append('message_type', 'location');
        formData.append('message', JSON.stringify({
            latitude,
            longitude,
            label: 'Live location shared'
        }));

        fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.message) {
                return;
            }
            if (messagesArea) {
                renderLocationMessage(data.message, true);
                lastMessageId = Math.max(lastMessageId, parseInt(data.message.id || 0));
            }
        })
        .catch(console.error);
    }

    function renderLocationMessage(messageData, isSent) {
        if (!messagesArea || !messageData) {
            return;
        }

        const locationData = typeof messageData.message === 'string' ? JSON.parse(messageData.message) : null;
        if (!locationData || typeof locationData.latitude === 'undefined' || typeof locationData.longitude === 'undefined') {
            return;
        }

        const group = document.createElement('div');
        group.className = 'message-group';
        if (messageData.id) {
            group.dataset.messageId = messageData.id;
        }

        const msg = document.createElement('div');
        msg.className = `message ${isSent ? 'sent' : 'received'}`;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        const mapUrl = `https://www.openstreetmap.org/?mlat=${locationData.latitude}&mlon=${locationData.longitude}#map=18/${locationData.latitude}/${locationData.longitude}`;
        bubble.innerHTML = `
            <div class="location-card">
                <div class="location-card-header"><i class="fas fa-map-marker-alt"></i> ${locationData.label || 'Shared live location'}</div>
                <div class="location-card-body">Latitude: ${locationData.latitude}<br>Longitude: ${locationData.longitude}</div>
                <div class="location-card-actions"><a href="${mapUrl}" target="_blank" rel="noopener noreferrer">Open in map</a></div>
            </div>
        `;

        msg.appendChild(bubble);
        group.appendChild(msg);

        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        group.appendChild(time);

        messagesArea.appendChild(group);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function beginSharing() {
        hasSentLocationMessage = false;
        if (!navigator.geolocation) {
            showToast('Location sharing requires browser geolocation support.', 'error');
            return;
        }

        if (watchId !== null) {
            showToast('Live location is already being shared.', 'error');
            return;
        }

        const duration = Number(shareDurationSelect.value) || DEFAULT_SHARE_DURATION_MINUTES;
        shareTimer = window.setTimeout(stopSharing, duration * 60 * 1000);
        showLiveLocationPanel();
        updatePanelState('Sharing live location', true);
        appendLiveLocationMessage('You started sharing live location');
        showToast('Live location sharing started');

        watchId = navigator.geolocation.watchPosition(onPositionUpdate, onPositionError, {
            enableHighAccuracy: true,
            maximumAge: 2000,
            timeout: 10000,
        });

        isSharingLocation = true;
        if (socket && socket.readyState === WebSocket.OPEN) {
            sendSocketMessage({
                type: 'send_location',
                conversation_id: chatRoom,
                user_id: chatUserId,
                action: 'start',
            });
        }
    }

    function stopSharing() {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        if (shareTimer) {
            clearTimeout(shareTimer);
            shareTimer = null;
        }

        isSharingLocation = false;
        updatePanelState('Location sharing stopped', false);
        appendLiveLocationMessage('You stopped sharing live location');
        showToast('Live location sharing stopped');

        if (socket && socket.readyState === WebSocket.OPEN) {
            sendSocketMessage({
                type: 'send_location',
                conversation_id: chatRoom,
                user_id: chatUserId,
                action: 'stop',
            });
        }
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('chatActionToast');
        if (!toast) {
            console.log(message);
            return;
        }
        toast.textContent = message;
        toast.className = `chat-action-toast ${type}`;
        toast.style.opacity = '1';
        setTimeout(() => { toast.style.opacity = '0'; }, 3000);
    }

    function showLiveLocationPanel() {
        if (!liveLocationPanel) {
            return;
        }
        liveLocationPanel.style.display = 'block';
        if (map) {
            setTimeout(() => { map.invalidateSize(); }, 120);
        }
    }

    async function init() {
        waitForLeaflet(async function() {
            try {
                startMap();
            } catch (err) {
                console.error(err);
                updatePanelState('Live location unavailable: map init failed', false);
                return;
            }

            updatePanelState('Live location pending', false);
            await fetchInitialLocations();

            const tokenData = await fetchLiveLocationToken();
            if (tokenData.success) {
                await openSocket(tokenData.token);
            }

            if (shareLocationBtn) {
                shareLocationBtn.addEventListener('click', beginSharing);
            }
            if (stopLocationBtn) {
                stopLocationBtn.addEventListener('click', stopSharing);
            }
        });
    }

    function launchSharing() {
        if (inputMenuDropdown) {
            inputMenuDropdown.classList.remove('visible');
        }
        showLiveLocationPanel();
        if (shareLocationBtn) {
            shareLocationBtn.click();
        } else {
            beginSharing();
        }
    }

    if (shareLocationTrigger) {
        shareLocationTrigger.addEventListener('click', launchSharing);
    }
    if (shareLocationTriggerModal) {
        shareLocationTriggerModal.addEventListener('click', function() {
            closeServiceModal();
            launchSharing();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
