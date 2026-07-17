(function () {
    const currentWithId = window.chatContext?.withId || 0;
    const isProviderPage = window.chatContext?.isProvider || false;
    const apiUrl = '../api/chat-actions.php';

    const btn = document.getElementById('chatOptionsBtn');
    const menu = document.getElementById('chatOptionsDropdown');
    const toast = document.getElementById('chatActionToast');
    const confirmModal = document.getElementById('chatConfirmModal');
    const confirmTitle = document.getElementById('chatConfirmTitle');
    const confirmText = document.getElementById('chatConfirmText');
    const confirmInputWrapper = document.getElementById('chatConfirmInputWrapper');
    const confirmInput = document.getElementById('chatConfirmReason');
    const confirmCancel = document.getElementById('chatConfirmCancel');
    const confirmAction = document.getElementById('chatConfirmAction');

    function toggleMenu() {
        if (!menu) return;
        const shown = menu.classList.toggle('visible');
        menu.setAttribute('aria-hidden', shown ? 'false' : 'true');
    }

    function hideMenu() {
        if (!menu) return;
        menu.classList.remove('visible');
        menu.setAttribute('aria-hidden', 'true');
    }

    function showToast(message, type = 'success') {
        if (!toast) return;
        toast.textContent = message;
        toast.className = `chat-action-toast ${type}`;
        toast.style.opacity = 1;
        setTimeout(() => {
            toast.style.opacity = 0;
        }, 3000);
    }

    function safeFetch(data) {
        return fetch(apiUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data,
            credentials: 'same-origin'
        }).then(res => res.json());
    }

    function openConfirmation(options) {
        if (!confirmModal) return;

        confirmTitle.textContent = options.title;
        confirmText.textContent = options.text;

        if (options.showReason) {
            confirmInputWrapper.style.display = 'block';
            confirmInput.value = options.initialReason || '';
            confirmInput.required = true;
        } else {
            confirmInputWrapper.style.display = 'none';
            confirmInput.value = '';
        }

        confirmAction.textContent = options.confirmLabel || 'Confirm';

        confirmAction.onclick = function () {
            const payload = new FormData();
            payload.append('action', options.action);
            payload.append('with', currentWithId);
            if (options.action === 'report_user') {
                const reason = (confirmInput.value || '').trim();
                if (!reason) {
                    showToast('Please provide a report reason', 'error');
                    return;
                }
                payload.append('reason', reason);
            }

            safeFetch(payload).then((json) => {
                if (json.success) {
                    if (options.onSuccess) options.onSuccess(json);
                    showToast(json.message || 'Action completed', 'success');
                    if (options.postSuccess === 'reload') location.reload();
                    if (options.postSuccess === 'redirect' && json.redirect) window.location.href = json.redirect;
                } else {
                    showToast(json.message || 'Action failed', 'error');
                }
            }).catch(() => {
                showToast('Could not complete the request', 'error');
            }).finally(() => {
                confirmModal.classList.remove('show');
            });
        };

        confirmCancel.onclick = function () {
            confirmModal.classList.remove('show');
        };

        confirmModal.classList.add('show');
    }

    function setupOption(selector, options) {
        const el = document.querySelector(selector);
        if (!el) return;

        el.addEventListener('click', function (event) {
            event.stopPropagation();
            hideMenu();

            if (options.confirm) {
                openConfirmation(options);
                return;
            }

            const payload = new FormData();
            payload.append('action', options.action);
            payload.append('with', currentWithId);

            safeFetch(payload).then((json) => {
                if (json.success) {
                    if (options.onSuccess) {
                        options.onSuccess(json);
                    }
                    if (options.postSuccess === 'redirect' && json.redirect) {
                        window.location.href = json.redirect;
                        return;
                    }
                    if (options.postSuccess === 'reload') {
                        showToast(json.message || 'Success');
                        setTimeout(() => location.reload(), 400);
                        return;
                    }
                    if (options.postSuccess === 'toggle-muted') {
                        const status = json.status || 'muted';
                        const label = status === 'muted' ? 'Notifications muted' : 'Notifications unmuted';
                        showToast(label);
                        return;
                    }
                    showToast(json.message || 'Success');
                } else {
                    showToast(json.message || 'Action failed', 'error');
                }
            }).catch(() => {
                showToast('Could not complete request', 'error');
            });
        });
    }

    if (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleMenu();
        });
    }

    document.addEventListener('click', hideMenu);
    menu?.addEventListener('click', function (event) {
        event.stopPropagation();
    });

    setupOption('#chatOptViewOffers', { action: 'view_offers', postSuccess: 'redirect' });
    setupOption('#chatOptMute', { action: 'mute_notifications', postSuccess: 'toggle-muted' });
    setupOption('#chatOptClear', { action: 'clear_chat', confirm: true, title: 'Clear chat', text: 'This will delete all messages with this contact for everyone. Continue?', confirmLabel: 'Clear', onSuccess: () => { if (typeof window.handleChatCleared === 'function') window.handleChatCleared(); } });
    setupOption('#chatOptDelete', { action: 'delete_conversation', confirm: true, title: 'Delete conversation', text: 'Delete entire conversation permanently? This action cannot be undone.', confirmLabel: 'Delete', onSuccess: () => { if (typeof window.handleConversationDeleted === 'function') window.handleConversationDeleted(); } });
    setupOption('#chatOptReport', { action: 'report_user', confirm: true, showReason: true, title: 'Report user', text: 'Tell us why you are reporting this user.', confirmLabel: 'Report' });
    setupOption('#chatOptBlock', { action: 'block_user', confirm: true, title: 'Block user', text: 'Block user and prevent future messages?', confirmLabel: 'Block', postSuccess: 'reload' });

})();