// Simple Event Bus for same-origin pages/tabs
(function(window){
    const supportsBC = typeof BroadcastChannel !== 'undefined';
    const channelName = 'bii_event_bus';
    const bc = supportsBC ? new BroadcastChannel(channelName) : null;

    function publish(event, payload){
        const message = {event, payload, ts: Date.now()};
        if (bc) {
            bc.postMessage(message);
        } else {
            // fallback to localStorage event
            try {
                localStorage.setItem('bii_event_bus_message', JSON.stringify(message));
                // remove to avoid filling storage
                localStorage.removeItem('bii_event_bus_message');
            } catch(e) {
                console.warn('eventBus localStorage fallback failed', e);
            }
        }
    }

    function subscribe(handler){
        if (bc) {
            bc.addEventListener('message', (ev) => handler(ev.data));
        } else {
            window.addEventListener('storage', (ev) => {
                if (ev.key === 'bii_event_bus_message' && ev.newValue) {
                    try {
                        const msg = JSON.parse(ev.newValue);
                        handler(msg);
                    } catch(e) {
                        // ignore
                    }
                }
            });
        }
    }

    window.eventBus = {
        publish,
        subscribe
    };

})(window);
