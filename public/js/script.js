// Shared script loaded by all pages. Keep every feature defensive.
(function () {
    var imobilUploadLimits = window.ImobilUploadLimits || {};
    var UPLOAD_TARGET = imobilUploadLimits.target || {};
    var UPLOAD_SERVER_MAX = imobilUploadLimits.serverMaxBytes || (3 * 1024 * 1024);
    var UPLOAD_TARGET_PROFILE = UPLOAD_TARGET.profilePhoto || (512 * 1024);
    var UPLOAD_TARGET_PROOF = UPLOAD_TARGET.imageProof || (512 * 1024);
    var UPLOAD_TARGET_PROPERTY = UPLOAD_TARGET.propertyImage || (2 * 1024 * 1024);
    var UPLOAD_TARGET_DOCUMENT = UPLOAD_TARGET.document || (3 * 1024 * 1024);
    var UPLOAD_TARGET_CHAT = UPLOAD_TARGET.chatAttachment || (512 * 1024);
    var formatUploadBytes = imobilUploadLimits.formatBytesShort || function (bytes) {
        bytes = Number(bytes) || 0;
        if (bytes >= 1024 * 1024) {
            var mb = bytes / (1024 * 1024);
            var rounded = Math.round(mb * 10) / 10;
            return (Math.abs(rounded - Math.round(rounded)) < 0.05 ? String(Math.round(rounded)) : String(rounded).replace('.', ',')) + ' MB';
        }
        return String(Math.max(1, Math.round(bytes / 1024))) + ' KB';
    };
    var formatUploadTarget = imobilUploadLimits.formatTargetLabel || function (key) {
        return formatUploadBytes(UPLOAD_TARGET[key] || UPLOAD_SERVER_MAX);
    };

    function setCookie(name, value, days) {
        var maxAge = Math.max(1, (days || 180) * 24 * 60 * 60);
        var cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; samesite=lax';
        if (window.location.protocol === 'https:') {
            cookie += '; secure';
        }
        document.cookie = cookie;
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function initCookieConsent() {
        var banner = document.getElementById('cookieConsentBanner');
        if (!banner) {
            return;
        }

        var acceptBtn = document.getElementById('cookieAcceptBtn');
        var rejectBtn = document.getElementById('cookieRejectBtn');
        var openers = document.querySelectorAll('[data-open-cookie-consent]');

        function hideBanner() {
            banner.classList.add('is-hidden');
        }

        function showBanner() {
            banner.classList.remove('is-hidden');
        }

        var currentConsent = getCookie('imobil_behavioral_consent');
        if (currentConsent === 'accepted' || currentConsent === 'rejected') {
            document.body.setAttribute('data-cookie-behavioral', currentConsent);
            hideBanner();
        }

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                setCookie('imobil_behavioral_consent', 'accepted', 180);
                document.body.setAttribute('data-cookie-behavioral', 'accepted');
                hideBanner();
                window.setTimeout(function () {
                    window.location.reload();
                }, 150);
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function () {
                setCookie('imobil_behavioral_consent', 'rejected', 180);
                document.body.setAttribute('data-cookie-behavioral', 'rejected');
                hideBanner();
            });
        }

        openers.forEach(function (opener) {
            opener.addEventListener('click', function (event) {
                event.preventDefault();
                showBanner();
            });
        });
    }

    initCookieConsent();

    var slides = document.querySelector('.slides');
    var dotsContainer = document.querySelector('.dots');
    var prevBtn = document.getElementById('prev');
    var nextBtn = document.getElementById('next');
    var currentIndex = 0;
    var interval = null;

    function updateSlide(index) {
        if (!slides) {
            return;
        }

        slides.style.transform = 'translateX(-' + (index * 100) + '%)';
        document.querySelectorAll('.dot').forEach(function (dot, i) {
            dot.classList.toggle('active', i === index);
        });
    }

    function nextSlide() {
        if (!slides) {
            return;
        }

        currentIndex = currentIndex < slides.children.length - 1 ? currentIndex + 1 : 0;
        updateSlide(currentIndex);
    }

    function prevSlide() {
        if (!slides) {
            return;
        }

        currentIndex = currentIndex > 0 ? currentIndex - 1 : slides.children.length - 1;
        updateSlide(currentIndex);
    }

    function startAutoSlide() {
        if (!slides || slides.children.length <= 1) {
            return;
        }
        interval = window.setInterval(nextSlide, 5000);
    }

    function resetInterval() {
        if (interval) {
            window.clearInterval(interval);
        }
        startAutoSlide();
    }

    function createDots() {
        if (!slides || !dotsContainer) {
            return;
        }

        for (var i = 0; i < slides.children.length; i++) {
            (function (dotIndex) {
                var dot = document.createElement('div');
                dot.classList.add('dot');
                dot.addEventListener('click', function () {
                    currentIndex = dotIndex;
                    updateSlide(currentIndex);
                    resetInterval();
                });
                dotsContainer.appendChild(dot);
            })(i);
        }
    }

    if (slides && dotsContainer && prevBtn && nextBtn) {
        if (slides.children.length <= 1) {
            prevBtn.disabled = true;
            prevBtn.setAttribute('aria-disabled', 'true');
            nextBtn.disabled = true;
            nextBtn.setAttribute('aria-disabled', 'true');
        }

        prevBtn.addEventListener('click', function () {
            prevSlide();
            resetInterval();
        });

        nextBtn.addEventListener('click', function () {
            nextSlide();
            resetInterval();
        });

        createDots();
        updateSlide(currentIndex);
        startAutoSlide();
    }

    // Mobile menu fallback.
    var mobileMenu = document.getElementById('menu');
    var mobileMenuButton = document.querySelector('.menu-button');
    var siteHeader = document.querySelector('.site-header');

    function setMobileMenuState(isOpen) {
        if (!mobileMenu) {
            return;
        }

        mobileMenu.classList.toggle('menu-open', !!isOpen);
        if (siteHeader) {
            siteHeader.classList.toggle('is-nav-open', !!isOpen);
        }
        if (mobileMenuButton) {
            mobileMenuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            mobileMenuButton.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
            var menuIcon = mobileMenuButton.querySelector('i');
            if (menuIcon) {
                menuIcon.className = isOpen ? 'fa fa-times' : 'fa fa-bars';
            }
        }
    }

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var nextState = !mobileMenu.classList.contains('menu-open');
            setMobileMenuState(nextState);
        });

        document.addEventListener('click', function (event) {
            if (!mobileMenu.classList.contains('menu-open')) {
                return;
            }

            var clickedInsideMenu = mobileMenu.contains(event.target);
            var clickedButton = mobileMenuButton.contains(event.target);
            if (!clickedInsideMenu && !clickedButton) {
                setMobileMenuState(false);
            }
        });

        mobileMenu.addEventListener('click', function (event) {
            if (event.target && event.target.tagName === 'A') {
                setMobileMenuState(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mobileMenu.classList.contains('menu-open')) {
                setMobileMenuState(false);
            }
        });
    }

    // Header notification dropdown support.
    (function initNotificationDropdown() {
        var notificationMenu = document.getElementById('notificationMenu');
        if (!notificationMenu) {
            return;
        }

        var trigger = notificationMenu.querySelector('.notification-trigger');
        var list = notificationMenu.querySelector('#notificationList');
        var emptyMessage = notificationMenu.querySelector('#notificationEmpty');
        var form = document.getElementById('notificationReadForm');
        var csrfTokenInput = form ? form.querySelector('input[name="csrf_token"]') : null;
        var unreadLabel = notificationMenu.querySelector('#notificationUnreadLabel');
        var badge = notificationMenu.querySelector('#notificationBadge');
        var feedUrl = notificationMenu.dataset.feedUrl;
        var loaded = false;
        var lastToastId = parseInt(localStorage.getItem('lastNotificationToastId') || '0', 10) || 0;
        var toastPollInterval = 30000; // 30s

        function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && meta.content) {
                return meta.content;
            }
            return csrfTokenInput ? csrfTokenInput.value : '';
        }

        function setCsrfToken(token) {
            if (!token) {
                return;
            }
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                meta.content = token;
            }
            if (csrfTokenInput) {
                csrfTokenInput.value = token;
            }
            document.querySelectorAll('input[name="csrf_token"]').forEach(function (input) {
                input.value = token;
            });
        }

        function setUnreadCount(count) {
            if (!badge || !unreadLabel) {
                return;
            }

            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-flex';
                unreadLabel.textContent = count + ' não lidas';
                unreadLabel.hidden = false;
            } else {
                badge.style.display = 'none';
                unreadLabel.hidden = true;
            }
        }

        function serializeForm(data) {
            var params = [];
            Object.keys(data).forEach(function (key) {
                params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            });
            return params.join('&');
        }

        async function postAction(url) {
            if (!url) {
                return null;
            }

            try {
                var response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: serializeForm({ csrf_token: getCsrfToken() })
                });

                if (!response.ok) {
                    return null;
                }

                var payload = await response.json();
                if (payload && payload.csrf_token) {
                    setCsrfToken(payload.csrf_token);
                }
                return payload;
            } catch (error) {
                return null;
            }
        }

        function refreshMenuDisplay(items) {
            if (!list) {
                return;
            }

            if (!Array.isArray(items) || items.length === 0) {
                list.innerHTML = '';
                if (emptyMessage) {
                    emptyMessage.hidden = false;
                }
                return;
            }

            emptyMessage.hidden = true;
            list.innerHTML = items.map(function (notification) {
                return buildNotificationItemHtml(notification, notificationMenu.dataset.dashboardUrl || '#');
            }).join('');

            attachNotificationHandlers();
        }

        function attachNotificationHandlers() {
            if (!list) {
                return;
            }

            list.querySelectorAll('.notification-item-main').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var readUrl = link.getAttribute('data-notification-read-url');
                    var isRead = link.closest('.notification-feed-item, .notification-item').classList.contains('is-read');
                    if (!isRead && readUrl) {
                        navigator.sendBeacon(readUrl, serializeForm({ csrf_token: getCsrfToken() }));
                    }
                });
            });

            list.querySelectorAll('.notification-toggle-read-btn').forEach(function (button) {
                button.addEventListener('click', async function () {
                    var unreadUrl = button.getAttribute('data-notification-unread-url');
                    var notificationItem = button.closest('.notification-feed-item, .notification-item');
                    var response = await postAction(unreadUrl);
                    if (response && response.success) {
                        if (notificationItem) {
                            notificationItem.classList.remove('is-read');
                            notificationItem.classList.add('is-unread');
                            var unreadDot = notificationItem.querySelector('.notification-feed-unread-dot');
                            if (!unreadDot) {
                                var link = notificationItem.querySelector('.notification-feed-link, .notification-item-main');
                                if (link) {
                                    var dot = document.createElement('span');
                                    dot.className = 'notification-feed-unread-dot';
                                    dot.setAttribute('aria-label', 'Não lida');
                                    link.appendChild(dot);
                                }
                            }
                        }
                        button.hidden = true;
                        if (response.unread_count !== undefined) {
                            setUnreadCount(response.unread_count);
                        }
                    }
                });
            });
        }

        async function loadNotifications() {
            if (!feedUrl || !list) {
                return;
            }

            try {
                var response = await fetch(feedUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    return;
                }

                var data = await response.json();
                if (data && Array.isArray(data.notifications)) {
                    refreshMenuDisplay(data.notifications);
                    if (typeof data.unread_count === 'number') {
                        setUnreadCount(data.unread_count);
                    }
                    // Show toasts for newly arrived unread notifications
                    try {
                        showToastsForNew(data.notifications || []);
                    } catch (e) {
                        // ignore
                    }
                }
            } catch (error) {
                // ignore silently
            }
        }

        if (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var isOpen = !notificationMenu.classList.contains('is-open');
                setNotificationMenuOpen(isOpen);
                if (isOpen && !loaded) {
                    loaded = true;
                    loadNotifications();
                }
            });
        }

        var backdrop = document.getElementById('notificationDropdownBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setNotificationMenuOpen(false);
            });
        }

        var closeButton = document.getElementById('notificationDropdownClose');
        if (closeButton) {
            closeButton.addEventListener('click', function () {
                setNotificationMenuOpen(false);
            });
        }

        document.addEventListener('click', function (event) {
            var clickedTrigger = event.target.closest('.notification-trigger');
            if (clickedTrigger) {
                return;
            }

            if (event.target.closest('#notificationDropdownBackdrop, #notificationSheetPortal, #notificationDropdown')) {
                return;
            }

            if (notificationMenu && !notificationMenu.contains(event.target)) {
                setNotificationMenuOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (!notificationMenu) {
                return;
            }

            setNotificationMenuOpen(false);
        });
        // Toast helpers: create small UI toasts for new notifications
        function ensureToastContainer() {
            var container = document.getElementById('notificationToasts');
            if (container) return container;
            // lazy-load CSS for notification toasts
            try {
                var cssHref = '/css/notification-toasts.css';
                if (!document.querySelector('link[href="' + cssHref + '"]')) {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = cssHref;
                    document.head.appendChild(link);
                }
            } catch (e) {}

            container = document.createElement('div');
            container.id = 'notificationToasts';
            container.style.position = 'fixed';
            container.style.right = '12px';
            container.style.bottom = '12px';
            container.style.zIndex = 10000;
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '8px';
            document.body.appendChild(container);
            return container;
        }

        function createToast(notification) {
            var container = ensureToastContainer();
            var toast = document.createElement('div');
            toast.className = 'notification-toast';
            toast.dataset.notificationId = notification.id;
            toast.style.minWidth = '260px';
            toast.style.maxWidth = '360px';
            toast.style.background = '#fff';
            toast.style.border = '1px solid rgba(0,0,0,0.08)';
            toast.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';
            toast.style.padding = '12px';
            toast.style.borderRadius = '6px';
            toast.style.cursor = 'pointer';

            var title = document.createElement('strong');
            title.textContent = notification.title || 'Notificação';
            toast.appendChild(title);

            // add variant class based on notification type or label
            try {
                var rawType = notification.type || notification.type_label || '';
                if (rawType) {
                    var typeClass = rawType.toString().toLowerCase().replace(/[^a-z0-9]+/g, '-');
                    if (typeClass) {
                        toast.classList.add('notification-toast--' + typeClass);
                    }
                } else {
                    toast.classList.add('notification-toast--info');
                }
            } catch (e) {}

            if (notification.message) {
                var p = document.createElement('div');
                p.style.marginTop = '6px';
                p.style.fontSize = '13px';
                p.style.color = '#333';
                p.textContent = notification.message;
                toast.appendChild(p);
            }

            toast.addEventListener('click', function () {
                var readUrl = notification.mark_read_url;
                if (readUrl) {
                    navigator.sendBeacon(readUrl, serializeForm({ csrf_token: getCsrfToken() }));
                }
                if (notification.target_url) {
                    window.location = notification.target_url;
                } else {
                    toast.remove();
                }
            });

            setTimeout(function () { try { toast.remove(); } catch (e) {} }, 6000);
            container.appendChild(toast);
        }

        function showToastsForNew(notifications) {
            if (!Array.isArray(notifications) || notifications.length === 0) return;
            notifications.sort(function (a, b) { return (a.id || 0) - (b.id || 0); });
            var maxId = lastToastId || 0;
            notifications.forEach(function (n) {
                var id = Number(n.id || 0);
                if (!n.is_read && id > lastToastId) {
                    createToast(n);
                    if (id > maxId) maxId = id;
                }
            });
            if (maxId > lastToastId) {
                lastToastId = maxId;
                try { localStorage.setItem('lastNotificationToastId', String(lastToastId)); } catch (e) {}
            }
        }

        function pollForToasts() {
            if (!feedUrl) return;
            fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('bad'); return r.json(); })
                .then(function (data) { if (data && Array.isArray(data.notifications)) showToastsForNew(data.notifications); })
                .catch(function () {});
        }

        try { setInterval(pollForToasts, toastPollInterval); } catch (e) {}
    })();

    // Document modal fallback.
    function findDocModalParent(element) {
        var node = element;
        while (node && node !== document.body) {
            if (node.classList && node.classList.contains('doc-modal')) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    (function initSheetModals() {
        var activeSheet = null;
        var sheetTrigger = null;
        var sheetFocusTrap = null;
        var mobileSheetQuery = window.matchMedia('(max-width: 768px)');

        function sheetFocusableNodes(container) {
            if (!container) {
                return [];
            }

            return Array.prototype.slice.call(
                container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
            ).filter(function (node) {
                return node.offsetParent !== null || node === document.activeElement;
            });
        }

        function releaseSheetFocusTrap() {
            if (sheetFocusTrap) {
                document.removeEventListener('keydown', sheetFocusTrap);
                sheetFocusTrap = null;
            }
        }

        function bindSheetFocusTrap(modal) {
            releaseSheetFocusTrap();
            var panel = modal.querySelector('.sheet-modal-panel, .doc-modal-panel');
            if (!panel) {
                return;
            }

            sheetFocusTrap = function (event) {
                if (event.key !== 'Tab' || !activeSheet) {
                    return;
                }

                var nodes = sheetFocusableNodes(panel);
                if (!nodes.length) {
                    return;
                }

                var first = nodes[0];
                var last = nodes[nodes.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            };

            document.addEventListener('keydown', sheetFocusTrap);
            var nodes = sheetFocusableNodes(panel);
            if (nodes.length) {
                nodes[0].focus();
            }
        }

        window.openSheetModal = function (modal, trigger) {
            if (!modal) {
                return;
            }

            activeSheet = modal;
            sheetTrigger = trigger || document.activeElement;
            modal.removeAttribute('hidden');
            modal.classList.add('is-open');
            modal.style.display = 'flex';
            document.body.classList.add('sheet-modal-open');
            if (modal.classList.contains('doc-modal')) {
                document.body.classList.add('doc-modal-open');
                document.body.setAttribute('data-active-doc-modal', modal.id || '');
            }
            bindSheetFocusTrap(modal);
        };

        window.closeSheetModal = function (modal) {
            if (!modal) {
                return;
            }

            modal.setAttribute('hidden', 'hidden');
            modal.classList.remove('is-open');
            modal.style.display = 'none';
            releaseSheetFocusTrap();

            if (activeSheet === modal) {
                activeSheet = null;
            }

            if (!document.querySelector('.sheet-modal.is-open, .doc-modal.is-open:not([hidden])')) {
                document.body.classList.remove('sheet-modal-open');
                document.body.classList.remove('doc-modal-open');
                document.body.removeAttribute('data-active-doc-modal');
            }

            if (sheetTrigger && typeof sheetTrigger.focus === 'function') {
                sheetTrigger.focus();
            }
            sheetTrigger = null;
        };

        window.docModalOpen = function (modalId) {
            var modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            if (modal.classList.contains('sheet-modal')) {
                window.openSheetModal(modal, document.activeElement);
                return;
            }

            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            document.body.classList.add('doc-modal-open');
            document.body.setAttribute('data-active-doc-modal', modalId);
        };

        window.docModalClose = function (source) {
            var modal = null;

            if (typeof source === 'string') {
                modal = document.getElementById(source);
            } else {
                modal = findDocModalParent(source);
            }

            if (!modal) {
                return;
            }

            if (modal.classList.contains('sheet-modal')) {
                window.closeSheetModal(modal);
                return;
            }

            modal.setAttribute('hidden', 'hidden');
            modal.style.display = 'none';
            document.body.classList.remove('doc-modal-open');
            document.body.removeAttribute('data-active-doc-modal');
        };

        document.addEventListener('click', function (event) {
            var sheetOpenBtn = event.target.closest('[data-sheet-open]');
            if (sheetOpenBtn) {
                var sheetTargetId = sheetOpenBtn.getAttribute('data-sheet-open') || sheetOpenBtn.getAttribute('aria-controls');
                var sheetModal = sheetTargetId ? document.getElementById(sheetTargetId) : null;
                if (sheetModal) {
                    event.preventDefault();
                    window.openSheetModal(sheetModal, sheetOpenBtn);
                }
                return;
            }

            var sheetCloseBtn = event.target.closest('[data-sheet-close]');
            if (sheetCloseBtn) {
                var sheetToClose = sheetCloseBtn.closest('.sheet-modal, .doc-modal');
                if (sheetToClose) {
                    window.closeSheetModal(sheetToClose);
                }
                return;
            }

            var openBtn = event.target.closest('[data-doc-modal-open]');
            if (openBtn) {
                var modalId = openBtn.getAttribute('data-doc-modal-open');
                if (modalId) {
                    window.docModalOpen(modalId);
                }
                return;
            }

            var closeBtn = event.target.closest('[data-doc-modal-close]');
            if (closeBtn) {
                window.docModalClose(closeBtn);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !activeSheet) {
                return;
            }
            window.closeSheetModal(activeSheet);
        });
    })();

    // Property create image previews.
    var imageInput = document.getElementById('images');
    var previewContainer = document.getElementById('property-image-preview');

    // Payment accounts dynamic fields (works even when inline JS is blocked).
    (function initPaymentAccountsDynamicFields() {
        var methodSelect = document.getElementById('method_id');
        var dynamicFieldsContainer = document.getElementById('dynamic-payment-fields');
        if (!methodSelect || !dynamicFieldsContainer) {
            return;
        }

        var fieldMeta = {
            account_name: { label: 'Titular da Conta', type: 'text', maxlength: 150, placeholder: 'Seu nome completo' },
            account_number: { label: 'Numero de Conta', type: 'text', maxlength: 120, placeholder: 'Numero de conta' },
            iban: { label: 'IBAN', type: 'text', maxlength: 80, placeholder: 'IBAN internacional' },
            bank_name: { label: 'Banco / Instituicao', type: 'text', maxlength: 120, placeholder: 'Nome do banco' },
            wallet_provider: { label: 'Provedor de Carteira', type: 'text', maxlength: 80, placeholder: 'Ex: Multicaixa, Vodacom' },
            phone_number: { label: 'Numero de Telefone', type: 'tel', maxlength: 30, placeholder: '+244 9XX XXX XXX' }
        };

        function renderFields() {
            var selectedOption = methodSelect.options[methodSelect.selectedIndex];
            var config = {
                account_name: false,
                account_number: false,
                iban: false,
                bank_name: false,
                wallet_provider: false,
                phone_number: false
            };

            if (selectedOption) {
                config.account_name = selectedOption.getAttribute('data-account_name') === '1';
                config.account_number = selectedOption.getAttribute('data-account_number') === '1';
                config.iban = selectedOption.getAttribute('data-iban') === '1';
                config.bank_name = selectedOption.getAttribute('data-bank_name') === '1';
                config.wallet_provider = selectedOption.getAttribute('data-wallet_provider') === '1';
                config.phone_number = selectedOption.getAttribute('data-phone_number') === '1';
            }

            dynamicFieldsContainer.innerHTML = '';
            var rendered = 0;

            Object.keys(fieldMeta).forEach(function (fieldName) {
                if (!config[fieldName]) {
                    return;
                }

                var meta = fieldMeta[fieldName];
                var wrapper = document.createElement('div');
                wrapper.className = 'form-group payment-field';

                var label = document.createElement('label');
                label.setAttribute('for', fieldName);
                label.textContent = meta.label;

                var input = document.createElement('input');
                input.type = meta.type;
                input.name = fieldName;
                input.id = fieldName;
                input.maxLength = meta.maxlength;
                input.placeholder = meta.placeholder;
                input.autocomplete = 'off';

                wrapper.appendChild(label);
                wrapper.appendChild(input);
                dynamicFieldsContainer.appendChild(wrapper);
                rendered += 1;
            });

            if (rendered === 0) {
                var empty = document.createElement('p');
                empty.className = 'dashboard-inline-note';
                empty.textContent = methodSelect.value
                    ? 'Este metodo nao exige dados adicionais.'
                    : 'Selecione um metodo para carregar os campos correspondentes.';
                dynamicFieldsContainer.appendChild(empty);
            }
        }

        methodSelect.addEventListener('change', renderFields);
        renderFields();
    })();

    if (imageInput && previewContainer) {
        var PROPERTY_IMAGE_MAX = 8;
        var isEditForm = !!(imageInput.form && imageInput.form.classList.contains('property-edit-form'));
        var manifestInput = document.getElementById('images_manifest');
        var galleryTouchedInput = document.getElementById('images_gallery_touched');
        var gallery = [];

        function markGalleryTouched() {
            if (galleryTouchedInput) {
                galleryTouchedInput.value = '1';
            }
        }
        var selectedFiles = [];
        var canSyncFileList = false;
        var isProcessingPropertyImages = false;
        var propertyImageTargetBytes = UPLOAD_TARGET_PROPERTY;
        var propertyImageMaxDimension = 1920;

        function createFileTransfer() {
            if (typeof DataTransfer === 'function') {
                try {
                    return new DataTransfer();
                } catch (error) {
                    // ignore
                }
            }

            try {
                var clipboardEvent = new ClipboardEvent('copy');
                if (clipboardEvent.clipboardData) {
                    return clipboardEvent.clipboardData;
                }
            } catch (error) {
                // ignore
            }

            return null;
        }

        canSyncFileList = !!createFileTransfer();

        function syncInputFiles() {
            var transfer = createFileTransfer();
            if (!transfer || !transfer.items || typeof transfer.items.add !== 'function') {
                canSyncFileList = false;
                return false;
            }

            selectedFiles.forEach(function (file) {
                transfer.items.add(file);
            });

            imageInput.files = transfer.files;
            canSyncFileList = true;
            return true;
        }

        function rebuildSelectedFilesFromGallery() {
            selectedFiles = gallery
                .filter(function (item) { return item.kind === 'new'; })
                .map(function (item) { return item.file; });

            if (selectedFiles.length && canSyncFileList) {
                syncInputFiles();
            }
        }

        function syncManifest() {
            if (!manifestInput) {
                return;
            }

            var manifest = gallery.map(function (item) {
                if (item.kind === 'existing') {
                    return { kind: 'existing', path: item.path };
                }
                return { kind: 'new' };
            });

            manifestInput.value = JSON.stringify(manifest);
        }

        function initGalleryFromExisting() {
            if (!isEditForm) {
                return;
            }

            var raw = previewContainer.getAttribute('data-existing-images') || '[]';
            try {
                var items = JSON.parse(raw);
                if (!Array.isArray(items)) {
                    return;
                }

                items.forEach(function (item) {
                    if (!item || !item.path) {
                        return;
                    }
                    gallery.push({
                        kind: 'existing',
                        path: String(item.path),
                        url: String(item.url || item.path)
                    });
                });
            } catch (error) {
                // ignore invalid JSON
            }
        }

        function renderPreview() {
            previewContainer.innerHTML = '';
            syncManifest();

            if (!gallery.length) {
                if (isEditForm) {
                    var emptyNote = document.createElement('small');
                    emptyNote.className = 'dashboard-inline-note';
                    emptyNote.textContent = 'Sem imagens na galeria. Adicione pelo menos uma antes de guardar.';
                    previewContainer.appendChild(emptyNote);
                }
                return;
            }

            gallery.forEach(function (item, index) {
                var card = document.createElement('div');
                card.className = 'property-image-thumb';

                if (index === 0) {
                    var badge = document.createElement('span');
                    badge.className = 'property-image-cover-badge';
                    badge.textContent = 'Capa';
                    card.appendChild(badge);
                }

                var img = document.createElement('img');
                var meta = document.createElement('small');
                var actions = document.createElement('div');
                actions.className = 'property-image-thumb-actions';

                var coverBtn = document.createElement('button');
                coverBtn.type = 'button';
                coverBtn.className = 'btn-secondary property-thumb-action-btn';
                coverBtn.textContent = 'Capa';
                coverBtn.disabled = index === 0;
                coverBtn.addEventListener('click', function () {
                    if (index === 0) {
                        return;
                    }
                    var chosen = gallery.splice(index, 1)[0];
                    gallery.unshift(chosen);
                    markGalleryTouched();
                    rebuildSelectedFilesFromGallery();
                    renderPreview();
                });

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'property-thumb-remove-btn';
                removeBtn.innerHTML = '<i class="fa fa-times" aria-hidden="true"></i>';
                removeBtn.setAttribute('aria-label', 'Remover imagem');
                removeBtn.setAttribute('title', 'Remover imagem');
                removeBtn.addEventListener('click', function () {
                    gallery.splice(index, 1);
                    markGalleryTouched();
                    rebuildSelectedFilesFromGallery();
                    renderPreview();
                });

                card.appendChild(removeBtn);
                actions.appendChild(coverBtn);

                if (item.kind === 'existing') {
                    img.alt = 'Imagem do imóvel';
                    img.src = item.url;
                    meta.textContent = 'Imagem actual';
                } else {
                    img.alt = item.file.name || 'Nova imagem';
                    meta.textContent = item.file.name || 'Nova imagem';
                    var reader = new FileReader();
                    reader.onload = function (event) {
                        img.src = String(event.target && event.target.result ? event.target.result : '');
                    };
                    reader.readAsDataURL(item.file);
                }

                card.appendChild(img);
                card.appendChild(meta);
                card.appendChild(actions);
                previewContainer.appendChild(card);
            });

            if (!isEditForm && !canSyncFileList && gallery.length) {
                var warning = document.createElement('small');
                warning.className = 'dashboard-inline-note';
                warning.textContent = 'Seu navegador não permite remover ou reordenar arquivos antes do envio.';
                warning.style.gridColumn = '1 / -1';
                previewContainer.appendChild(warning);
            }
        }

        function showPropertySubmitFeedback(message, tone) {
            var feedback = document.getElementById('property-create-form-feedback');
            if (!feedback) {
                return;
            }

            var text = String(message || '').trim();
            if (!text) {
                feedback.textContent = '';
                feedback.hidden = true;
                feedback.classList.remove('auth-message-error', 'auth-message-success');
                return;
            }

            feedback.textContent = text;
            feedback.hidden = false;
            feedback.classList.remove('auth-message-error', 'auth-message-success');
            if (tone === 'error') {
                feedback.classList.add('auth-message-error');
            } else if (tone === 'success') {
                feedback.classList.add('auth-message-success');
            }

            if (typeof feedback.scrollIntoView === 'function') {
                feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function renderProcessingMessage(message, isError) {
            previewContainer.innerHTML = '';
            var note = document.createElement('small');
            note.className = 'dashboard-inline-note';
            note.textContent = message;
            if (isError) {
                note.style.color = '#a11c2f';
                showPropertySubmitFeedback(message, 'error');
            } else {
                showPropertySubmitFeedback('', '');
            }
            previewContainer.appendChild(note);
        }

        function enablePropertyLocationFieldsForSubmit() {
            var countrySelect = propertyForm ? propertyForm.querySelector('#country_id') : null;
            var regionSelect = propertyForm ? propertyForm.querySelector('#region_id') : null;
            if (regionSelect && countrySelect && countrySelect.value !== '') {
                regionSelect.disabled = false;
            }
        }

        function loadImageFromFile(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () {
                    reject(new Error('Falha ao ler uma imagem selecionada.'));
                };
                reader.onload = function (event) {
                    var img = new Image();
                    img.onerror = function () {
                        reject(new Error('Formato de imagem não suportado para conversão.'));
                    };
                    img.onload = function () {
                        resolve(img);
                    };
                    img.src = String(event.target && event.target.result ? event.target.result : '');
                };
                reader.readAsDataURL(file);
            });
        }

        function canvasToWebpBlob(canvas, quality) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Falha ao gerar arquivo WebP.'));
                        return;
                    }
                    resolve(blob);
                }, 'image/webp', quality);
            });
        }

        async function convertFileToOptimizedWebp(file) {
            var img = await loadImageFromFile(file);
            var width = img.naturalWidth || img.width;
            var height = img.naturalHeight || img.height;

            if (width <= 0 || height <= 0) {
                throw new Error('Dimensões inválidas de imagem.');
            }

            if (width > propertyImageMaxDimension || height > propertyImageMaxDimension) {
                var scale = Math.min(propertyImageMaxDimension / width, propertyImageMaxDimension / height);
                width = Math.max(320, Math.floor(width * scale));
                height = Math.max(320, Math.floor(height * scale));
            }

            var qualities = [0.9, 0.84, 0.78, 0.72, 0.66, 0.6, 0.54, 0.48, 0.42];
            var lastBlob = null;

            for (var round = 0; round < 4; round++) {
                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('Falha ao preparar canvas para conversão.');
                }

                ctx.drawImage(img, 0, 0, width, height);

                for (var i = 0; i < qualities.length; i++) {
                    var blob = await canvasToWebpBlob(canvas, qualities[i]);
                    lastBlob = blob;
                    if (blob.size <= propertyImageTargetBytes) {
                        return blob;
                    }
                }

                if (!lastBlob) {
                    break;
                }

                var ratio = Math.sqrt(propertyImageTargetBytes / lastBlob.size) * 0.95;
                if (!isFinite(ratio) || ratio >= 0.98) {
                    break;
                }

                width = Math.max(320, Math.floor(width * ratio));
                height = Math.max(320, Math.floor(height * ratio));
            }

            if (lastBlob && lastBlob.size <= propertyImageTargetBytes) {
                return lastBlob;
            }

            throw new Error('Não foi possível otimizar uma imagem para envio em WebP.');
        }

        async function convertSelectionToWebp(files) {
            var output = [];

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                if (!file.type || file.type.indexOf('image/') !== 0) {
                    throw new Error('Somente arquivos de imagem são permitidos.');
                }

                var optimizedBlob = await convertFileToOptimizedWebp(file);
                var baseName = (file.name || ('imagem_' + (i + 1))).replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                output.push(new File([optimizedBlob], baseName + '.webp', {
                    type: 'image/webp',
                    lastModified: Date.now()
                }));
            }

            return output;
        }

        function ensureGalleryFilesReadyForSubmit() {
            syncManifest();
            rebuildSelectedFilesFromGallery();

            var newFilesInGallery = gallery.filter(function (item) { return item.kind === 'new'; }).length;
            if (newFilesInGallery === 0) {
                return true;
            }

            if (!canSyncFileList) {
                var nativeCount = imageInput.files ? imageInput.files.length : 0;
                return nativeCount >= newFilesInGallery;
            }

            if (!syncInputFiles()) {
                return false;
            }

            return !!(imageInput.files && imageInput.files.length >= newFilesInGallery);
        }

        var propertyFormNativeSubmit = false;
        var propertySubmitButton = null;
        var propertySubmitButtonDefaultLabel = '';

        var propertyForm = imageInput.form;
        if (propertyForm) {
            propertySubmitButton = propertyForm.querySelector('button[type="submit"]');
            if (propertySubmitButton) {
                propertySubmitButtonDefaultLabel = propertySubmitButton.textContent || '';
            }

            propertyForm.addEventListener('submit', function (event) {
                if (propertyFormNativeSubmit) {
                    return;
                }

                event.preventDefault();
                showPropertySubmitFeedback('', '');

                if (isProcessingPropertyImages) {
                    renderProcessingMessage('Aguarde: as imagens ainda estão a ser convertidas para WebP.', true);
                    return;
                }

                if (typeof propertyForm.checkValidity === 'function' && !propertyForm.checkValidity()) {
                    propertyForm.reportValidity();
                    return;
                }

                if (!isEditForm && gallery.length === 0) {
                    renderProcessingMessage('Adicione pelo menos 1 imagem do imóvel.', true);
                    if (imageInput && typeof imageInput.focus === 'function') {
                        imageInput.focus();
                    }
                    return;
                }

                if (isEditForm && gallery.length === 0) {
                    renderProcessingMessage('Mantenha pelo menos uma imagem no anúncio.', true);
                    return;
                }

                if (!ensureGalleryFilesReadyForSubmit()) {
                    renderProcessingMessage(
                        canSyncFileList
                            ? 'Não foi possível preparar as imagens para envio. Tente adicionar novamente.'
                            : 'Seu navegador não permite enviar as imagens convertidas. Tente outro navegador ou dispositivo.',
                        true
                    );
                    return;
                }

                enablePropertyLocationFieldsForSubmit();

                if (propertySubmitButton) {
                    propertySubmitButton.disabled = true;
                    propertySubmitButton.textContent = 'A enviar...';
                }

                propertyFormNativeSubmit = true;
                propertyForm.submit();
            });
        }

        imageInput.addEventListener('change', async function () {
            var files = Array.prototype.slice.call(imageInput.files || []);
            if (!files.length) {
                if (!isEditForm) {
                    gallery = [];
                    selectedFiles = [];
                    renderPreview();
                }
                return;
            }

            var slotsLeft = PROPERTY_IMAGE_MAX - gallery.length;
            if (slotsLeft <= 0) {
                imageInput.value = '';
                renderProcessingMessage('Limite de 8 imagens atingido. Remova uma para adicionar outra.', true);
                return;
            }

            if (files.length > slotsLeft) {
                files = files.slice(0, slotsLeft);
            }

            isProcessingPropertyImages = true;
            renderProcessingMessage('A converter e otimizar imagens para WebP...', false);

            try {
                var converted = await convertSelectionToWebp(files);
                if (!isEditForm) {
                    gallery = converted.map(function (file) {
                        return { kind: 'new', file: file };
                    });
                } else {
                    converted.forEach(function (file) {
                        gallery.push({ kind: 'new', file: file });
                    });
                    markGalleryTouched();
                }

                rebuildSelectedFilesFromGallery();
                if (!isEditForm && gallery.length && canSyncFileList && !syncInputFiles()) {
                    throw new Error('Seu navegador não permite substituir os arquivos antes do envio.');
                }
                renderPreview();
            } catch (error) {
                if (!isEditForm) {
                    gallery = [];
                    selectedFiles = [];
                }
                renderProcessingMessage(String(error && error.message ? error.message : 'Falha ao processar imagens.'), true);
            } finally {
                isProcessingPropertyImages = false;
                if (!gallery.length) {
                    imageInput.value = '';
                }
            }
        });

        initGalleryFromExisting();
        if (isEditForm) {
            renderPreview();
        }
    }

    // Register profile photo preview.
    (function initRegisterProfilePreview() {
        var passwordInput = document.getElementById('password');
        var passwordConfirmInput = document.getElementById('password_confirm');
        var passwordFeedback = document.getElementById('password-confirm-feedback');
        var profileInput = document.getElementById('profile_photo');
        var uploader = document.getElementById('register-photo-uploader');
        var preview = document.getElementById('register-profile-preview');
        var previewImage = document.getElementById('register-profile-preview-image');
        var previewName = document.getElementById('register-profile-preview-name');
        var previewSize = document.getElementById('register-profile-preview-size');

        function updatePasswordFeedback() {
            if (!passwordInput || !passwordConfirmInput || !passwordFeedback) {
                return;
            }

            var password = passwordInput.value || '';
            var confirmation = passwordConfirmInput.value || '';

            passwordFeedback.classList.remove('is-match', 'is-mismatch');

            if (confirmation === '') {
                passwordConfirmInput.setCustomValidity('');
                passwordFeedback.textContent = '';
                return;
            }

            if (password === confirmation) {
                passwordConfirmInput.setCustomValidity('');
                passwordFeedback.textContent = 'As senhas coincidem.';
                passwordFeedback.classList.add('is-match');
                return;
            }

            passwordConfirmInput.setCustomValidity('Confirmação de senha não coincide.');
            passwordFeedback.textContent = 'As senhas não coincidem.';
            passwordFeedback.classList.add('is-mismatch');
        }

        function resetPreview() {
            if (!preview || !previewImage || !uploader) {
                return;
            }

            preview.classList.remove('is-ready');
            preview.setAttribute('hidden', 'hidden');
            previewImage.removeAttribute('src');
            if (previewName) {
                previewName.textContent = 'Nenhuma imagem selecionada';
            }
            if (previewSize) {
                previewSize.textContent = '';
            }
            uploader.removeAttribute('hidden');
        }

        if (passwordInput && passwordConfirmInput && passwordFeedback) {
            passwordInput.addEventListener('input', updatePasswordFeedback);
            passwordConfirmInput.addEventListener('input', updatePasswordFeedback);
            updatePasswordFeedback();
        }

        var photoFeedback = document.getElementById('register-photo-feedback');
        var profileForm = profileInput ? profileInput.form : null;
        var isProcessingPhoto = false;
        var maxProfilePhotoBytes = UPLOAD_TARGET_PROFILE;
        var maxProfileDimension = 1600;

        function setPhotoFeedback(message, tone) {
            if (!photoFeedback) {
                return;
            }

            photoFeedback.textContent = message || '';
            photoFeedback.classList.remove('is-match', 'is-mismatch');
            if (tone === 'success') {
                photoFeedback.classList.add('is-match');
            } else if (tone === 'error') {
                photoFeedback.classList.add('is-mismatch');
            }
        }

        function createTransfer() {
            if (typeof DataTransfer === 'function') {
                try {
                    return new DataTransfer();
                } catch (error) {
                    return null;
                }
            }

            return null;
        }

        function loadImage(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () {
                    reject(new Error('Não foi possível ler a imagem selecionada.'));
                };

                reader.onload = function (event) {
                    var image = new Image();
                    image.onerror = function () {
                        reject(new Error('Não foi possível processar o formato desta imagem.'));
                    };
                    image.onload = function () {
                        resolve(image);
                    };
                    image.src = String(event.target && event.target.result ? event.target.result : '');
                };

                reader.readAsDataURL(file);
            });
        }

        function canvasToJpegBlob(canvas, quality) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Falha ao gerar imagem JPG.'));
                        return;
                    }
                    resolve(blob);
                }, 'image/jpeg', quality);
            });
        }

        async function convertToOptimizedJpeg(file) {
            var image = await loadImage(file);
            var width = image.naturalWidth || image.width;
            var height = image.naturalHeight || image.height;

            if (width <= 0 || height <= 0) {
                throw new Error('Dimensões da imagem inválidas.');
            }

            if (width > maxProfileDimension || height > maxProfileDimension) {
                var dimensionScale = Math.min(maxProfileDimension / width, maxProfileDimension / height);
                width = Math.max(280, Math.floor(width * dimensionScale));
                height = Math.max(280, Math.floor(height * dimensionScale));
            }

            var qualities = [0.9, 0.84, 0.78, 0.72, 0.66, 0.6, 0.54, 0.48, 0.42];
            var bestBlob = null;

            for (var round = 0; round < 4; round++) {
                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('Não foi possível preparar o processamento da imagem.');
                }

                ctx.drawImage(image, 0, 0, width, height);

                for (var i = 0; i < qualities.length; i++) {
                    var candidate = await canvasToJpegBlob(canvas, qualities[i]);
                    bestBlob = candidate;
                    if (candidate.size <= maxProfilePhotoBytes) {
                        return candidate;
                    }
                }

                if (!bestBlob) {
                    break;
                }

                var ratio = Math.sqrt(maxProfilePhotoBytes / bestBlob.size) * 0.95;
                if (!isFinite(ratio) || ratio >= 0.98) {
                    break;
                }

                width = Math.max(280, Math.floor(width * ratio));
                height = Math.max(280, Math.floor(height * ratio));
            }

            if (bestBlob && bestBlob.size <= maxProfilePhotoBytes) {
                return bestBlob;
            }

            throw new Error('Não foi possível reduzir a imagem para até ' + formatUploadTarget('profilePhoto') + '. Escolha outro ficheiro de até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.');
        }

        function syncProfileInputFile(file) {
            var transfer = createTransfer();
            if (!transfer || !transfer.items || typeof transfer.items.add !== 'function') {
                return false;
            }

            transfer.items.add(file);
            profileInput.files = transfer.files;
            return true;
        }

        if (!profileInput || !preview || !previewImage || !uploader) {
            return;
        }

        if (profileForm) {
            profileForm.addEventListener('submit', function (event) {
                if (!isProcessingPhoto) {
                    return;
                }

                event.preventDefault();
                setPhotoFeedback('Aguarde o processamento da foto terminar para concluir o envio.', 'error');
            });
        }

        profileInput.addEventListener('change', async function () {
            var file = profileInput.files && profileInput.files[0] ? profileInput.files[0] : null;
            if (!file) {
                resetPreview();
                setPhotoFeedback('Imagem (JPG, PNG, etc.), até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.', '');
                return;
            }

            if (!file.type || file.type.indexOf('image/') !== 0) {
                resetPreview();
                setPhotoFeedback('Selecione um arquivo de imagem válido.', 'error');
                return;
            }

            isProcessingPhoto = true;
            setPhotoFeedback('A processar...', '');

            try {
                var optimizedBlob = await convertToOptimizedJpeg(file);
                var safeNameBase = (file.name || 'foto_perfil').replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                var optimizedFile = new File([optimizedBlob], safeNameBase + '.jpg', {
                    type: 'image/jpeg',
                    lastModified: Date.now()
                });

                if (!syncProfileInputFile(optimizedFile)) {
                    throw new Error('Seu navegador não permite substituir o arquivo antes do envio.');
                }

                uploader.setAttribute('hidden', 'hidden');
                preview.removeAttribute('hidden');
                preview.classList.add('is-ready');
                if (previewName) {
                    previewName.textContent = optimizedFile.name;
                }
                if (previewSize) {
                    previewSize.textContent = Math.round(optimizedFile.size / 1024) + ' KB';
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    previewImage.src = String(event.target && event.target.result ? event.target.result : '');
                };
                reader.readAsDataURL(optimizedFile);

                setPhotoFeedback('', '');
            } catch (error) {
                resetPreview();
                profileInput.value = '';
                setPhotoFeedback(String(error && error.message ? error.message : 'Falha ao processar a foto.'), 'error');
            } finally {
                isProcessingPhoto = false;
            }
        });
    })();

    // Trust badge: bloqueio UX global na secção do perfil (todos os utilizadores).
    (function () {
        var section = document.getElementById('trust-badge-section');
        if (!section) {
            return;
        }

        var canSubmit = section.getAttribute('data-can-submit') === '1';
        var blockers = section.getAttribute('data-blockers') || '';

        function removeTrustRequestForm() {
            var form = section.querySelector('#trust-badge-request-form');
            if (form && form.parentNode) {
                form.parentNode.removeChild(form);
            }
            ['trust_badge_months', 'payment_proof', 'trust-badge-submit-btn'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el && el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            });
        }

        function blockTrustSubmit(ev) {
            if (ev && typeof ev.preventDefault === 'function') {
                ev.preventDefault();
                ev.stopPropagation();
            }
            setProofFeedback(
                blockers !== ''
                    ? blockers
                    : 'Não cumpre os requisitos para solicitar o selo de confiança.',
                'error'
            );
            return false;
        }

        if (!canSubmit) {
            removeTrustRequestForm();
            section.classList.add('trust-badge-section--locked');
        }

        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!form || form.id !== 'trust-badge-request-form') {
                return;
            }
            if (section.getAttribute('data-can-submit') !== '1') {
                blockTrustSubmit(ev);
            }
        }, true);

        var sel = document.getElementById('trust_badge_months');
        var tot = document.getElementById('trustBadgeTotalValue');
        var proofInput = document.getElementById('payment_proof');
        var proofWrap = document.getElementById('proofPreviewWrap');
        var proofImg = document.getElementById('proofPreview');
        var proofMeta = document.getElementById('proofPreviewMeta');
        var proofFb = document.getElementById('proofFeedback');
        var isProcessingProof = false;
        var proofMaxBytes = UPLOAD_TARGET_PROOF;
        var proofMaxDim = 1920;

        function fmtKz(v) {
            return Math.max(0, Math.round(v))
                .toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' Kz';
        }

        function setProofFeedback(msg, tone) {
            var target = proofFb || section.querySelector('.trust-badge-eligibility-foot');
            if (!target) { return; }
            target.textContent = msg || '';
            target.style.color = tone === 'error' ? '#a11c2f' : tone === 'success' ? '#1e7e39' : '';
        }

        function hideProofPreview() {
            if (!proofWrap || !proofImg) { return; }
            proofWrap.style.display = 'none';
            proofImg.removeAttribute('src');
            if (proofMeta) { proofMeta.textContent = ''; }
        }

        function showProofPreview(name, sizeBytes, dataUrl) {
            if (!proofWrap || !proofImg) { return; }
            proofImg.src = dataUrl;
            proofWrap.style.display = 'block';
            if (proofMeta) {
                proofMeta.textContent = name + ' — ' + Math.round(sizeBytes / 1024) + ' KB';
            }
        }

        function syncProofInput(file) {
            if (!proofInput || typeof DataTransfer !== 'function') { return; }
            try {
                var xfer = new DataTransfer();
                xfer.items.add(file);
                proofInput.files = xfer.files;
            } catch (e) { /* unsupported */ }
        }

        function loadProofImage(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () { reject(new Error('Não foi possível ler a imagem.')); };
                reader.onload = function (ev) {
                    var img = new Image();
                    img.onerror = function () { reject(new Error('Formato de imagem não suportado.')); };
                    img.onload = function () { resolve(img); };
                    img.src = String(ev.target && ev.target.result ? ev.target.result : '');
                };
                reader.readAsDataURL(file);
            });
        }

        function blobToWebp(canvas, q) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) { reject(new Error('Falha ao gerar WebP.')); return; }
                    resolve(blob);
                }, 'image/webp', q);
            });
        }

        async function convertProofToWebp(file) {
            var img = await loadProofImage(file);
            var w = img.naturalWidth || img.width;
            var h = img.naturalHeight || img.height;
            if (w <= 0 || h <= 0) { throw new Error('Dimensões inválidas.'); }

            if (w > proofMaxDim || h > proofMaxDim) {
                var sc = Math.min(proofMaxDim / w, proofMaxDim / h);
                w = Math.max(320, Math.floor(w * sc));
                h = Math.max(320, Math.floor(h * sc));
            }

            var qualities = [0.9, 0.84, 0.78, 0.72, 0.66, 0.6, 0.54, 0.48];
            var last = null;

            for (var round = 0; round < 4; round++) {
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                if (!ctx) { throw new Error('Canvas não disponível.'); }
                ctx.drawImage(img, 0, 0, w, h);

                for (var qi = 0; qi < qualities.length; qi++) {
                    var blob = await blobToWebp(canvas, qualities[qi]);
                    last = blob;
                    if (blob.size <= proofMaxBytes) { return blob; }
                }

                if (!last) { break; }
                var ratio = Math.sqrt(proofMaxBytes / last.size) * 0.95;
                if (!isFinite(ratio) || ratio >= 0.98) { break; }
                w = Math.max(320, Math.floor(w * ratio));
                h = Math.max(320, Math.floor(h * ratio));
            }

            if (last && last.size <= proofMaxBytes) { return last; }
            throw new Error('Não foi possível reduzir o comprovativo para até ' + formatUploadTarget('imageProof') + '. Escolha outro ficheiro de até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.');
        }

        if (sel && tot) {
            var monthlyFee = parseFloat(sel.getAttribute('data-monthly-fee') || '0');
            sel.addEventListener('change', function () {
                var m = parseInt(this.value, 10);
                tot.textContent = (isFinite(m) && m > 0) ? fmtKz(m * monthlyFee) : '0 Kz';
            });
        }

        var trustForm = document.getElementById('trust-badge-request-form');
        if (trustForm) {
            if (!canSubmit || trustForm.getAttribute('data-can-submit') !== '1') {
                removeTrustRequestForm();
                trustForm = null;
            } else {
                trustForm.addEventListener('submit', function (ev) {
                    if (section.getAttribute('data-can-submit') !== '1') {
                        return blockTrustSubmit(ev);
                    }
                    if (isProcessingProof) {
                        ev.preventDefault();
                        setProofFeedback('Aguarde o processamento do comprovativo.', 'error');
                    }
                });
            }
        }

        if (!canSubmit || !proofInput || !proofWrap || !proofImg) {
            return;
        }

        proofInput.addEventListener('change', async function () {
            var file = proofInput.files && proofInput.files[0] ? proofInput.files[0] : null;
            hideProofPreview();

            if (!file) {
                setProofFeedback('JPG, PNG ou WebP, até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.', '');
                return;
            }
            if (!file.type || file.type.indexOf('image/') !== 0) {
                setProofFeedback('Selecione um ficheiro de imagem válido.', 'error');
                proofInput.value = '';
                return;
            }

            isProcessingProof = true;
            setProofFeedback('A processar...', '');

            try {
                var optimized = await convertProofToWebp(file);
                var baseName = (file.name || 'comprovativo').replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                var outFile = new File([optimized], baseName + '.webp', {
                    type: 'image/webp',
                    lastModified: Date.now()
                });

                syncProofInput(outFile);

                // Render preview via FileReader (avoids blob URL lifetime issues).
                var reader = new FileReader();
                reader.onload = function (ev) {
                    showProofPreview(outFile.name, outFile.size, String(ev.target && ev.target.result ? ev.target.result : ''));
                };
                reader.readAsDataURL(outFile);

                setProofFeedback('✓ ' + outFile.name + ' (' + Math.round(outFile.size / 1024) + ' KB)', 'success');
            } catch (err) {
                hideProofPreview();
                proofInput.value = '';
                setProofFeedback(String(err && err.message ? err.message : 'Falha ao processar imagem.'), 'error');
            } finally {
                isProcessingProof = false;
            }
        });
    })();

    // Boost request form: property select → update form action, days input → live total, proof upload canvas→WebP.
    (function () {
        var boostForm    = document.getElementById('boost-request-form');
        var propertySel  = document.getElementById('boost_property_id');
        var daysInput    = document.getElementById('boost_duration_days');
        var totalEl      = document.getElementById('boostTotalValue');
        var proofInput   = document.getElementById('boost_payment_proof');
        var proofWrap    = document.getElementById('boostProofPreviewWrap');
        var proofImg     = document.getElementById('boostProofPreview');
        var proofMeta    = document.getElementById('boostProofPreviewMeta');
        var proofFb      = document.getElementById('boostProofFeedback');
        var isProcessing = false;
        var maxBytes     = UPLOAD_TARGET_PROOF;
        var maxDim       = 1920;

        function fmtKz(v) {
            return Math.max(0, Math.round(v)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' Kz';
        }

        function setFeedback(msg, tone) {
            if (!proofFb) { return; }
            proofFb.textContent = msg || '';
            proofFb.style.color = tone === 'error' ? '#a11c2f' : tone === 'success' ? '#1e7e39' : '';
        }

        function hidePreview() {
            if (!proofWrap || !proofImg) { return; }
            proofWrap.style.display = 'none';
            proofImg.removeAttribute('src');
            if (proofMeta) { proofMeta.textContent = ''; }
        }

        function showPreview(name, sizeBytes, dataUrl) {
            if (!proofWrap || !proofImg) { return; }
            proofImg.src = dataUrl;
            proofWrap.style.display = 'block';
            if (proofMeta) { proofMeta.textContent = name + ' — ' + Math.round(sizeBytes / 1024) + ' KB'; }
        }

        function syncInput(file) {
            if (typeof DataTransfer !== 'function') { return; }
            try { var xfer = new DataTransfer(); xfer.items.add(file); proofInput.files = xfer.files; } catch (e) {}
        }

        function loadImg(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () { reject(new Error('Não foi possível ler a imagem.')); };
                reader.onload = function (ev) {
                    var img = new Image();
                    img.onerror = function () { reject(new Error('Formato não suportado.')); };
                    img.onload = function () { resolve(img); };
                    img.src = String(ev.target && ev.target.result ? ev.target.result : '');
                };
                reader.readAsDataURL(file);
            });
        }

        function toWebpBlob(canvas, q) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) { reject(new Error('Falha ao gerar WebP.')); return; }
                    resolve(blob);
                }, 'image/webp', q);
            });
        }

        async function convertToWebp(file) {
            var img = await loadImg(file);
            var w = img.naturalWidth || img.width;
            var h = img.naturalHeight || img.height;
            if (w <= 0 || h <= 0) { throw new Error('Dimensões inválidas.'); }
            if (w > maxDim || h > maxDim) {
                var sc = Math.min(maxDim / w, maxDim / h);
                w = Math.max(320, Math.floor(w * sc));
                h = Math.max(320, Math.floor(h * sc));
            }
            var qualities = [0.9, 0.84, 0.78, 0.72, 0.66, 0.6, 0.54, 0.48];
            var last = null;
            for (var round = 0; round < 4; round++) {
                var canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                var ctx = canvas.getContext('2d');
                if (!ctx) { throw new Error('Canvas não disponível.'); }
                ctx.drawImage(img, 0, 0, w, h);
                for (var qi = 0; qi < qualities.length; qi++) {
                    var blob = await toWebpBlob(canvas, qualities[qi]);
                    last = blob;
                    if (blob.size <= maxBytes) { return blob; }
                }
                if (!last) { break; }
                var ratio = Math.sqrt(maxBytes / last.size) * 0.95;
                if (!isFinite(ratio) || ratio >= 0.98) { break; }
                w = Math.max(320, Math.floor(w * ratio));
                h = Math.max(320, Math.floor(h * ratio));
            }
            if (last && last.size <= maxBytes) { return last; }
            throw new Error('Não foi possível reduzir o comprovativo para até ' + formatUploadTarget('imageProof') + '. Escolha outro ficheiro de até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.');
        }

        // Update form action when property changes.
        if (boostForm && propertySel) {
            var baseAction = boostForm.getAttribute('action').replace(/\/\d+$/, '');
            propertySel.addEventListener('change', function () {
                var pid = parseInt(this.value, 10);
                boostForm.setAttribute('action', baseAction + '/' + (isFinite(pid) && pid > 0 ? pid : 0));
            });
        }

        // Update total when days change.
        if (daysInput && totalEl) {
            var dailyFee = parseFloat(daysInput.getAttribute('data-daily-fee') || '0');
            daysInput.addEventListener('input', function () {
                var d = parseInt(this.value, 10);
                totalEl.textContent = (isFinite(d) && d > 0) ? fmtKz(d * dailyFee) : '0 Kz';
            });
        }

        if (!proofInput || !proofWrap || !proofImg) { return; }

        if (boostForm) {
            boostForm.addEventListener('submit', function (ev) {
                if (isProcessing) { ev.preventDefault(); setFeedback('Aguarde o processamento do comprovativo.', 'error'); return; }
                var pid = propertySel ? parseInt(propertySel.value, 10) : 0;
                if (!pid || pid <= 0) { ev.preventDefault(); setFeedback('Selecione um imóvel.', 'error'); }
            });
        }

        proofInput.addEventListener('change', async function () {
            var file = proofInput.files && proofInput.files[0] ? proofInput.files[0] : null;
            hidePreview();
            if (!file) { setFeedback('JPG, PNG ou WebP, até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.', ''); return; }
            if (!file.type || file.type.indexOf('image/') !== 0) { setFeedback('Selecione um ficheiro de imagem válido.', 'error'); proofInput.value = ''; return; }
            isProcessing = true;
            setFeedback('A processar...', '');
            try {
                var optimized = await convertToWebp(file);
                var baseName = (file.name || 'comprovativo').replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                var outFile = new File([optimized], baseName + '.webp', { type: 'image/webp', lastModified: Date.now() });
                syncInput(outFile);
                var reader = new FileReader();
                reader.onload = function (ev) {
                    showPreview(outFile.name, outFile.size, String(ev.target && ev.target.result ? ev.target.result : ''));
                };
                reader.readAsDataURL(outFile);
                setFeedback('✓ ' + outFile.name + ' (' + Math.round(outFile.size / 1024) + ' KB)', 'success');
            } catch (err) {
                hidePreview();
                proofInput.value = '';
                setFeedback(String(err && err.message ? err.message : 'Falha ao processar imagem.'), 'error');
            } finally {
                isProcessing = false;
            }
        });
    })();

    (function initMyPropertiesFilters() {
        var root = document.querySelector('.my-properties-dashboard-view');
        var filterBar = root ? root.querySelector('.my-properties-filter-bar') : null;
        var grid = document.getElementById('my-properties-grid');
        var emptyNote = document.getElementById('my-properties-empty-filter');
        if (!filterBar || !grid) {
            return;
        }

        var cards = grid.querySelectorAll('.my-properties-card[data-status]');

        function applyFilter(filter) {
            var visible = 0;
            cards.forEach(function (card) {
                var status = card.getAttribute('data-status') || '';
                var show = filter === 'all' || status === filter;
                card.toggleAttribute('data-hidden', !show);
                if (show) {
                    visible++;
                }
            });

            if (emptyNote) {
                emptyNote.hidden = visible > 0;
            }
        }

        filterBar.addEventListener('click', function (event) {
            var chip = event.target.closest('.my-properties-filter-chip');
            if (!chip) {
                return;
            }

            var filter = chip.getAttribute('data-filter') || 'all';
            filterBar.querySelectorAll('.my-properties-filter-chip').forEach(function (btn) {
                btn.classList.toggle('is-active', btn === chip);
            });
            applyFilter(filter);
        });
    })();

    // Copy link buttons (referrals, agency page, etc.).
    document.addEventListener('click', function (event) {
        var copyBtn = event.target.closest('button[data-copy-target]');
        if (!copyBtn) {
            return;
        }

        var targetId = copyBtn.getAttribute('data-copy-target');
        if (!targetId) {
            return;
        }

        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        var value = '';
        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
            value = (target.value || '').trim();
        } else {
            value = (target.textContent || '').trim();
        }
        if (!value) {
            return;
        }

        function markCopied() {
            copyBtn.classList.add('is-copied');
            var prevTitle = copyBtn.getAttribute('title') || '';
            var prevAria = copyBtn.getAttribute('aria-label') || '';
            copyBtn.setAttribute('title', 'Copiado');
            if (prevAria) {
                copyBtn.setAttribute('aria-label', 'Link copiado');
            }
            window.setTimeout(function () {
                copyBtn.classList.remove('is-copied');
                if (prevTitle) {
                    copyBtn.setAttribute('title', prevTitle);
                }
                if (prevAria) {
                    copyBtn.setAttribute('aria-label', prevAria);
                }
            }, 2000);
        }

        function fallbackCopy() {
            var ta = document.createElement('textarea');
            ta.value = value;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                markCopied();
            } finally {
                document.body.removeChild(ta);
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(markCopied).catch(fallbackCopy);
            return;
        }

        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
            target.select();
            try {
                document.execCommand('copy');
                markCopied();
            } catch (err) {
                fallbackCopy();
            }
            return;
        }

        fallbackCopy();
    });

    // Property detail gallery thumbnails.
    var mainImage = document.getElementById('property-main-image');
    var thumbsWrap = document.getElementById('property-gallery-thumbs');

    if (mainImage && thumbsWrap) {
        thumbsWrap.addEventListener('click', function (event) {
            var thumbBtn = event.target.closest('.property-gallery-thumb');
            if (!thumbBtn) {
                return;
            }

            var thumbImg = thumbBtn.querySelector('img');
            var nextImage = thumbBtn.getAttribute('data-gallery-image') || (thumbImg ? thumbImg.getAttribute('src') : '');
            if (!nextImage) {
                return;
            }

            mainImage.setAttribute('src', nextImage);

            var allThumbs = thumbsWrap.querySelectorAll('.property-gallery-thumb');
            Array.prototype.forEach.call(allThumbs, function (thumb) {
                thumb.classList.remove('is-active');
            });
            thumbBtn.classList.add('is-active');
        });
    }

    (function initPropertyVideoFacade() {
        var youtubeEmbeds = document.querySelectorAll('.property-video-embed-youtube[data-youtube-embed]');
        if (!youtubeEmbeds.length) {
            return;
        }

        Array.prototype.forEach.call(youtubeEmbeds, function (embed) {
            var embedUrl = embed.getAttribute('data-youtube-embed');
            var playButton = embed.querySelector('.property-video-play');
            if (!embedUrl || !playButton) {
                return;
            }

            playButton.addEventListener('click', function () {
                if (embed.classList.contains('is-playing')) {
                    return;
                }

                var iframe = document.createElement('iframe');
                iframe.className = 'property-video-iframe';
                iframe.src = embedUrl;
                iframe.title = 'Vídeo do imóvel';
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                iframe.setAttribute('allowfullscreen', '');
                iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

                embed.innerHTML = '';
                embed.classList.add('is-playing');
                embed.appendChild(iframe);
            });
        });
    }());

    function initCircularCardCarousel(config) {
        var viewport = document.getElementById(config.viewportId);
        var track = document.getElementById(config.trackId);
        var dotsWrap = document.getElementById(config.dotsId);
        var prev = document.getElementById(config.prevId);
        var next = document.getElementById(config.nextId);

        if (!viewport || !track || !dotsWrap) {
            return;
        }

        var cardSelector = config.cardSelector;
        var baseCards = Array.prototype.slice.call(track.querySelectorAll(cardSelector));
        if (!baseCards.length) {
            return;
        }

        var current = 0;
        var timer = null;
        var resizeTimer = null;
        var touchStartX = null;
        var isAnimating = false;

        function normalize(index) {
            var total = baseCards.length;
            return ((index % total) + total) % total;
        }

        function setControlsState() {
            var disabled = baseCards.length <= 1;
            [prev, next].forEach(function (btn) {
                if (!btn) {
                    return;
                }
                btn.disabled = disabled;
                btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            });
        }

        function gapSize() {
            var style = window.getComputedStyle(track);
            return parseFloat(style.columnGap || style.gap || '0') || 0;
        }

        function stepSize() {
            var firstCard = track.querySelector(cardSelector);
            if (!firstCard) {
                return 0;
            }
            return firstCard.getBoundingClientRect().width + gapSize();
        }

        function updateDots() {
            var dots = dotsWrap.querySelectorAll('.sales-carousel-dot');
            dots.forEach(function (dot, idx) {
                dot.classList.toggle('is-active', idx === current);
            });
        }

        function renderOrder(index) {
            current = normalize(index);
            var ordered = baseCards.slice(current).concat(baseCards.slice(0, current));
            ordered.forEach(function (card) {
                track.appendChild(card);
            });
            updateDots();
        }

        function buildDots() {
            dotsWrap.innerHTML = '';

            for (var i = 0; i < baseCards.length; i++) {
                (function (idx) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'sales-carousel-dot' + (idx === current ? ' is-active' : '');
                    dot.setAttribute('aria-label', 'Ir para destaque ' + (idx + 1));
                    dot.disabled = baseCards.length <= 1;
                    dot.addEventListener('click', function () {
                        renderOrder(idx);
                        restartAuto();
                    });
                    dotsWrap.appendChild(dot);
                })(i);
            }
        }

        function endAnimation() {
            track.style.transition = '';
            track.style.transform = 'translateX(0)';
            isAnimating = false;
        }

        function animateToNext() {
            if (baseCards.length <= 1 || isAnimating) {
                return;
            }

            var step = stepSize();
            if (step <= 0) {
                return;
            }

            isAnimating = true;
            track.style.transition = 'transform 620ms cubic-bezier(0.22, 0.61, 0.36, 1)';
            track.style.transform = 'translateX(-' + step + 'px)';

            var onEnd = function () {
                track.removeEventListener('transitionend', onEnd);
                renderOrder(current + 1);
                track.style.transition = 'none';
                track.style.transform = 'translateX(0)';
                // Force reflow before restoring transition.
                void track.offsetWidth;
                endAnimation();
            };
            track.addEventListener('transitionend', onEnd);
        }

        function animateToPrev() {
            if (baseCards.length <= 1 || isAnimating) {
                return;
            }

            var step = stepSize();
            if (step <= 0) {
                return;
            }

            isAnimating = true;
            var previousIndex = normalize(current - 1);
            renderOrder(previousIndex);

            track.style.transition = 'none';
            track.style.transform = 'translateX(-' + step + 'px)';
            void track.offsetWidth;

            track.style.transition = 'transform 620ms cubic-bezier(0.22, 0.61, 0.36, 1)';
            track.style.transform = 'translateX(0)';

            var onEnd = function () {
                track.removeEventListener('transitionend', onEnd);
                endAnimation();
            };
            track.addEventListener('transitionend', onEnd);
        }

        function stopAuto() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function startAuto() {
            if (baseCards.length <= 1) {
                return;
            }
            stopAuto();
            timer = window.setInterval(animateToNext, config.autoMs || 5000);
        }

        function restartAuto() {
            stopAuto();
            startAuto();
        }

        if (typeof config.applyLayout === 'function') {
            config.applyLayout(viewport, baseCards);
        }

        buildDots();
        renderOrder(0);
        setControlsState();
        startAuto();

        if (next) {
            next.addEventListener('click', function () {
                animateToNext();
                restartAuto();
            });
        }

        if (prev) {
            prev.addEventListener('click', function () {
                animateToPrev();
                restartAuto();
            });
        }

        viewport.addEventListener('mouseenter', stopAuto);
        viewport.addEventListener('mouseleave', startAuto);

        viewport.addEventListener('touchstart', function (event) {
            if (event.touches && event.touches[0]) {
                touchStartX = event.touches[0].clientX;
            }
        }, { passive: true });

        viewport.addEventListener('touchend', function (event) {
            if (touchStartX === null || !event.changedTouches || !event.changedTouches[0]) {
                touchStartX = null;
                return;
            }

            var delta = touchStartX - event.changedTouches[0].clientX;
            touchStartX = null;
            if (Math.abs(delta) < (config.swipeThreshold || 45)) {
                return;
            }

            if (delta > 0) {
                animateToNext();
            } else {
                animateToPrev();
            }
            restartAuto();
        }, { passive: true });

        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                if (typeof config.applyLayout === 'function') {
                    config.applyLayout(viewport, baseCards);
                }
                renderOrder(current);
                setControlsState();
            }, 140);
        });
    }

    initCircularCardCarousel({
        viewportId: 'homeSalesViewport',
        trackId: 'homeSalesTrack',
        dotsId: 'homeSalesDots',
        prevId: 'homeSalesPrev',
        nextId: 'homeSalesNext',
        cardSelector: '.sales-carousel-card',
        autoMs: 5000,
        swipeThreshold: 45,
        applyLayout: function (viewport, cards) {
            var gap = 18;
            var width = viewport.clientWidth;
            var visible = 1;
            if (width >= 1024) {
                visible = 3;
            } else if (width >= 680) {
                visible = 2;
            }

            var cardWidth = Math.floor((viewport.clientWidth - gap * (visible - 1)) / visible);
            cards.forEach(function (card) {
                card.style.width = cardWidth + 'px';
                card.style.flexBasis = cardWidth + 'px';
            });
        }
    });

    initCircularCardCarousel({
        viewportId: 'listPremiumViewport',
        trackId: 'listPremiumTrack',
        dotsId: 'listPremiumDots',
        prevId: 'listPremiumPrev',
        nextId: 'listPremiumNext',
        cardSelector: '.sales-premium-card',
        autoMs: 5000,
        swipeThreshold: 45,
        applyLayout: function (viewport, cards) {
            var gap = 18;
            var width = viewport.clientWidth;
            var visible = 1;
            if (width >= 1100) {
                visible = 2;
            }
            var cardWidth = Math.max(260, Math.floor((width - gap * (visible - 1)) / visible));
            cards.forEach(function (card) {
                card.style.width = cardWidth + 'px';
                card.style.flexBasis = cardWidth + 'px';
                card.style.minWidth = cardWidth + 'px';
                card.style.maxWidth = cardWidth + 'px';
            });
        }
    });

    // Notification polling (replaces Layout.php inline script).
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isMobileNotificationPanel() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    var notificationTypeIcons = {
        new_request: 'fa-inbox',
        request_status_updated: 'fa-exchange',
        request_cancelled: 'fa-ban',
        request_closing_confirmed: 'fa-check-circle',
        request_closing_contested: 'fa-exclamation-triangle',
        request_payment_declared: 'fa-credit-card',
        request_payment_receipt_confirmed: 'fa-check-circle',
        request_payment_contested: 'fa-exclamation-triangle',
        request_chat_message: 'fa-comments',
        request_sla_reminder: 'fa-clock-o',
        commission_paid: 'fa-money',
        trusted_badge_requested: 'fa-shield',
        trusted_badge_approved: 'fa-shield',
        trusted_badge_rejected: 'fa-shield',
        trusted_badge_payment_confirmed: 'fa-shield',
        user_approved: 'fa-user-circle',
        user_rejected: 'fa-user-times',
        user_blocked: 'fa-lock',
        user_unblocked: 'fa-unlock',
        admin_role_updated: 'fa-user',
        document_approved: 'fa-file-text-o',
        document_rejected: 'fa-file-text-o',
        document_resubmitted: 'fa-file-text-o',
        boost_request: 'fa-star',
        boost_approved: 'fa-star',
        boost_rejected: 'fa-star',
        affiliate_approved: 'fa-users',
        affiliate_rejected: 'fa-users',
        commission_created: 'fa-percent',
        commission_payment_due: 'fa-calendar-o',
        commission_owner_payment_submitted: 'fa-upload',
        commission_owner_payment_confirmed: 'fa-check',
        commission_owner_payment_rejected: 'fa-times',
        commission_payout_pending: 'fa-clock-o',
        subscription_renewed: 'fa-refresh',
        subscription_payment_failed: 'fa-exclamation-circle',
        subscription_downgraded: 'fa-level-down'
    };

    var notificationTypeTones = {
        request_chat_message: 'tone-chat',
        new_request: 'tone-request',
        request_status_updated: 'tone-request',
        request_cancelled: 'tone-request',
        request_closing_confirmed: 'tone-request',
        request_closing_contested: 'tone-alert',
        request_payment_declared: 'tone-payment',
        request_payment_receipt_confirmed: 'tone-payment',
        request_payment_contested: 'tone-alert',
        request_sla_reminder: 'tone-alert',
        commission_paid: 'tone-payment',
        commission_created: 'tone-payment',
        commission_payment_due: 'tone-payment',
        commission_owner_payment_submitted: 'tone-payment',
        commission_owner_payment_confirmed: 'tone-payment',
        commission_owner_payment_rejected: 'tone-alert',
        commission_payout_pending: 'tone-payment',
        trusted_badge_requested: 'tone-trust',
        trusted_badge_approved: 'tone-trust',
        trusted_badge_rejected: 'tone-trust',
        trusted_badge_payment_confirmed: 'tone-trust',
        user_approved: 'tone-account',
        user_rejected: 'tone-account',
        user_blocked: 'tone-account',
        user_unblocked: 'tone-account',
        admin_role_updated: 'tone-account',
        document_approved: 'tone-document',
        document_rejected: 'tone-document',
        document_resubmitted: 'tone-document',
        boost_request: 'tone-boost',
        boost_approved: 'tone-boost',
        boost_rejected: 'tone-boost',
        affiliate_approved: 'tone-affiliate',
        affiliate_rejected: 'tone-affiliate',
        subscription_renewed: 'tone-plan',
        subscription_payment_failed: 'tone-alert',
        subscription_downgraded: 'tone-plan'
    };

    function resolveNotificationIcon(item) {
        if (item.type_icon) {
            return item.type_icon;
        }
        return notificationTypeIcons[item.type] || 'fa-bell';
    }

    function resolveNotificationTone(item) {
        if (item.type_tone) {
            return item.type_tone;
        }
        return notificationTypeTones[item.type] || 'tone-default';
    }

    function buildNotificationItemHtml(item, dashboardUrl) {
        var cls = item.is_read ? 'is-read' : 'is-unread';
        var compactClass = isMobileNotificationPanel() ? ' is-compact' : ' is-compact';
        var targetUrl = item.target_url || dashboardUrl;
        var markReadUrl = item.mark_read_url || '';
        var markUnreadUrl = item.mark_unread_url || '';
        var typeLabel = item.type_label || 'Notificação';
        var typeIcon = resolveNotificationIcon(item);
        var typeTone = resolveNotificationTone(item);
        var timeLabel = item.relative_time || item.created_at_label || '';
        var absoluteTime = item.created_at_label || '';
        var unreadDot = item.is_read ? '' : '<span class="notification-feed-unread-dot" aria-label="Não lida"></span>';
        var messageHtml = item.message
            ? '<span class="notification-feed-message">' + escapeHtml(item.message) + '</span>'
            : '';

        return '<article class="notification-feed-item notification-item ' + cls + compactClass + '" data-notification-id="' + escapeHtml(item.id || '') + '">' +
            '<a href="' + escapeHtml(targetUrl) + '" class="notification-feed-link notification-item-main" data-notification-id="' + escapeHtml(item.id || '') + '" data-notification-read-url="' + escapeHtml(markReadUrl) + '">' +
            '<span class="notification-feed-icon ' + escapeHtml(typeTone) + '" aria-hidden="true"><i class="fa ' + escapeHtml(typeIcon) + '"></i></span>' +
            '<span class="notification-feed-body">' +
            '<span class="notification-feed-text"><strong>' + escapeHtml(item.title || '') + '</strong>' + messageHtml + '</span>' +
            '<span class="notification-feed-meta"><span class="notification-feed-type">' + escapeHtml(typeLabel) + '</span><span class="notification-feed-dot" aria-hidden="true">·</span>' +
            '<time class="notification-feed-time" title="' + escapeHtml(absoluteTime) + '">' + escapeHtml(timeLabel) + '</time></span>' +
            '</span>' +
            unreadDot +
            '</a>' +
            '<button type="button" class="notification-toggle-read-btn" data-notification-id="' + escapeHtml(item.id || '') + '" data-notification-unread-url="' + escapeHtml(markUnreadUrl) + '" hidden>Marcar não lida</button>' +
            '</article>';
    }

    function renderNotificationItems(items, dashboardUrl) {
        return items.map(function (item) {
            return buildNotificationItemHtml(item, dashboardUrl);
        }).join('');
    }

    var notificationSheetTrigger = null;
    var notificationFocusTrapHandler = null;

    function notificationSheetFocusableNodes(container) {
        if (!container) {
            return [];
        }

        return Array.prototype.slice.call(
            container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
        ).filter(function (node) {
            return node.offsetParent !== null || node === document.activeElement;
        });
    }

    function releaseNotificationFocusTrap() {
        if (notificationFocusTrapHandler) {
            document.removeEventListener('keydown', notificationFocusTrapHandler);
            notificationFocusTrapHandler = null;
        }
    }

    function bindNotificationFocusTrap(dropdown, trigger) {
        releaseNotificationFocusTrap();
        notificationSheetTrigger = trigger || null;

        if (!dropdown) {
            return;
        }

        notificationFocusTrapHandler = function (event) {
            if (event.key !== 'Tab') {
                return;
            }

            var nodes = notificationSheetFocusableNodes(dropdown);
            if (!nodes.length) {
                return;
            }

            var first = nodes[0];
            var last = nodes[nodes.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', notificationFocusTrapHandler);
        var closeButton = dropdown.querySelector('#notificationDropdownClose');
        if (closeButton) {
            closeButton.focus();
        } else {
            var nodes = notificationSheetFocusableNodes(dropdown);
            if (nodes.length) {
                nodes[0].focus();
            }
        }
    }

    function setNotificationMenuOpen(isOpen) {
        var notificationMenu = document.getElementById('notificationMenu');
        if (!notificationMenu) {
            return;
        }

        var trigger = notificationMenu.querySelector('.notification-trigger');
        var backdrop = document.getElementById('notificationDropdownBackdrop');
        var dropdown = document.getElementById('notificationDropdown');
        var portal = document.getElementById('notificationSheetPortal');

        notificationMenu.classList.toggle('is-open', isOpen);
        if (trigger) {
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        if (isMobileNotificationPanel()) {
            if (!portal) {
                portal = document.createElement('div');
                portal.id = 'notificationSheetPortal';
                portal.className = 'notification-sheet-portal';
                document.body.appendChild(portal);
            }

            if (backdrop && backdrop.parentElement !== portal) {
                portal.appendChild(backdrop);
            }
            if (dropdown && dropdown.parentElement !== portal) {
                portal.appendChild(dropdown);
            }

            portal.classList.toggle('is-active', isOpen);
            if (backdrop) {
                backdrop.hidden = !isOpen;
                backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            }
            if (dropdown) {
                dropdown.classList.toggle('is-mobile-sheet-open', isOpen);
                dropdown.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            }
        } else {
            if (portal) {
                portal.classList.remove('is-active');
            }

            if (dropdown && dropdown.parentElement !== notificationMenu) {
                notificationMenu.appendChild(dropdown);
            }
            if (backdrop && backdrop.parentElement !== notificationMenu) {
                notificationMenu.insertBefore(backdrop, dropdown);
            }

            if (backdrop) {
                backdrop.hidden = !isOpen;
                backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            }
            if (dropdown) {
                dropdown.classList.remove('is-mobile-sheet-open');
                dropdown.removeAttribute('aria-hidden');
            }
        }

        document.body.classList.toggle('notification-sheet-open', isOpen && isMobileNotificationPanel());

        if (isOpen && isMobileNotificationPanel() && dropdown) {
            bindNotificationFocusTrap(dropdown, trigger);
        } else {
            releaseNotificationFocusTrap();
            if (!isOpen && notificationSheetTrigger && typeof notificationSheetTrigger.focus === 'function') {
                notificationSheetTrigger.focus();
            }
            if (!isOpen) {
                notificationSheetTrigger = null;
            }
        }
    }

    (function bindNotificationSheetViewport() {
        var mobileQuery = window.matchMedia('(max-width: 768px)');
        var onChange = function () {
            if (!mobileQuery.matches) {
                setNotificationMenuOpen(false);
            }
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', onChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(onChange);
        }
    })();

    function closeNotificationFeedMenus() {
        document.querySelectorAll('.notification-feed-menu').forEach(function (menu) {
            menu.hidden = true;
            menu.classList.remove('is-open');
        });
        document.querySelectorAll('.notification-feed-menu-btn').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('.notification-feed-item.is-menu-open').forEach(function (item) {
            item.classList.remove('is-menu-open');
        });
    }

    function openNotificationFeedMenu(btn, menu, item) {
        closeNotificationFeedMenus();
        menu.hidden = false;
        menu.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        if (item) {
            item.classList.add('is-menu-open');
        }
    }

    (function initNotificationInboxFeedMenus() {
        if (!document.querySelector('.notification-inbox-view, .notification-archive-view, .dashboard-notifications-inbox')) {
            return;
        }

        function getNotificationPageCsrfToken() {
            var input = document.querySelector('.notification-inbox-view input[name="csrf_token"], .notification-archive-view input[name="csrf_token"], .dashboard-notifications-inbox input[name="csrf_token"]');
            if (input) {
                return input.value;
            }
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? (meta.getAttribute('content') || '') : '';
        }

        document.addEventListener('click', function (event) {
            var menuBtn = event.target.closest('.notification-feed-menu-btn');
            if (menuBtn) {
                event.preventDefault();
                event.stopPropagation();
                var wrap = menuBtn.closest('.notification-feed-menu-wrap');
                if (!wrap) {
                    return;
                }
                var menu = wrap.querySelector('.notification-feed-menu');
                if (!menu) {
                    return;
                }
                var item = menuBtn.closest('.notification-feed-item');
                var isOpen = !menu.hidden;
                if (isOpen) {
                    closeNotificationFeedMenus();
                    return;
                }
                openNotificationFeedMenu(menuBtn, menu, item);
                return;
            }

            var unreadBtn = event.target.closest('.notification-inbox-view .notification-feed-menu .notification-toggle-read-btn, .notification-archive-view .notification-feed-menu .notification-toggle-read-btn, .dashboard-notifications-inbox .notification-feed-menu .notification-toggle-read-btn');
            if (unreadBtn) {
                event.preventDefault();
                event.stopPropagation();
                var unreadUrl = unreadBtn.getAttribute('data-notification-unread-url');
                if (!unreadUrl || typeof fetch !== 'function') {
                    return;
                }
                fetch(unreadUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: 'csrf_token=' + encodeURIComponent(getNotificationPageCsrfToken())
                }).then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                }).then(function (payload) {
                    var notificationId = unreadBtn.getAttribute('data-notification-id') || '';
                    if (payload && payload.csrf_token) {
                        applyPageCsrfToken(payload.csrf_token);
                    }
                    updateNotificationReadState(notificationId, false, Number(payload && payload.unread_count));
                    closeNotificationFeedMenus();
                }).catch(function () {
                    // ignore
                });
                return;
            }

            var readBtn = event.target.closest('.notification-feed-menu .notification-mark-read-btn');
            if (readBtn) {
                event.preventDefault();
                event.stopPropagation();

                var readNotificationId = readBtn.getAttribute('data-notification-id') || '';
                var readUrl = readBtn.getAttribute('data-notification-read-url') || '';
                if (!readNotificationId || !readUrl || typeof fetch !== 'function') {
                    return;
                }

                fetch(readUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: 'csrf_token=' + encodeURIComponent(getNotificationPageCsrfToken())
                }).then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                }).then(function (payload) {
                    if (payload && payload.csrf_token) {
                        applyPageCsrfToken(payload.csrf_token);
                    }
                    updateNotificationReadState(readNotificationId, true, Number(payload && payload.unread_count));
                    closeNotificationFeedMenus();
                }).catch(function () {
                    // ignore
                });
                return;
            }

            if (event.target.closest('.notification-feed-menu-wrap')) {
                return;
            }

            closeNotificationFeedMenus();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNotificationFeedMenus();
            }
        });
    })();

    function syncNotificationUnreadCount(unreadCount) {
        var menu = document.getElementById('notificationMenu');
        if (!menu) {
            return;
        }

        var count = Number(unreadCount || 0);
        var badge = document.getElementById('notificationBadge');
        var unreadLabel = document.getElementById('notificationUnreadLabel');
        var readForm = document.getElementById('notificationReadForm');

        menu.setAttribute('data-unread-initial', String(count));
        updateNotificationPageTitle(count);

        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'notificationBadge';
                badge.className = 'notification-badge';
                var trigger = menu.querySelector('.notification-trigger');
                if (trigger) {
                    trigger.appendChild(badge);
                }
            }
            badge.textContent = count > 99 ? '99+' : String(count);

            if (!unreadLabel) {
                unreadLabel = document.createElement('span');
                unreadLabel.id = 'notificationUnreadLabel';
                unreadLabel.className = 'notification-dropdown-unread';
                var toolbar = menu.querySelector('.notification-dropdown-toolbar');
                if (toolbar) {
                    toolbar.insertBefore(unreadLabel, toolbar.firstChild);
                }
            }
            unreadLabel.textContent = count + ' não lidas';
            unreadLabel.hidden = false;
            if (readForm) {
                readForm.hidden = false;
            }
        } else {
            if (badge) {
                badge.remove();
            }
            if (unreadLabel) {
                unreadLabel.hidden = true;
            }
            if (readForm) {
                readForm.hidden = true;
            }
        }
    }

    function updateNotificationReadState(notificationId, isRead, unreadCount) {
        var normalizedId = String(notificationId || '');
        if (!normalizedId) {
            return;
        }

        Array.prototype.forEach.call(document.querySelectorAll('[data-notification-id]'), function (node) {
            if (String(node.getAttribute('data-notification-id') || '') !== normalizedId) {
                return;
            }

            var card = null;
            if (node.classList && node.classList.contains('notification-item')) {
                card = node;
            } else {
                card = node.closest('.notification-item');
            }

            if (!card) {
                return;
            }

            card.classList.toggle('is-read', !!isRead);
            card.classList.toggle('is-unread', !isRead);

            var link = card.querySelector('.notification-feed-link');
            var dot = card.querySelector('.notification-feed-unread-dot');
            if (isRead) {
                if (dot) {
                    dot.remove();
                }
            } else if (!dot && link && !card.classList.contains('is-archived')) {
                dot = document.createElement('span');
                dot.className = 'notification-feed-unread-dot';
                dot.setAttribute('aria-label', 'Não lida');
                link.appendChild(dot);
            }

            var unreadToggle = card.querySelector('.notification-toggle-read-btn[data-notification-unread-url]');
            if (unreadToggle) {
                unreadToggle.hidden = !isRead;
            }

            var menuWrap = card.querySelector('.notification-feed-menu-wrap');
            if (menuWrap) {
                menuWrap.hidden = false;
            }
        });

        if (isRead) {
            var ariaLive = document.getElementById('notificationAriaLive');
            if (ariaLive) {
                ariaLive.textContent = 'Notificação marcada como lida.';
            }
        }

        if (typeof unreadCount === 'number' && !Number.isNaN(unreadCount)) {
            syncNotificationUnreadCount(unreadCount);
            syncDashboardNotificationMeta(unreadCount);
        }
    }

    function syncDashboardNotificationMeta(unreadCount) {
        var section = document.querySelector('.dashboard-notifications-inbox');
        if (!section) {
            return;
        }

        var count = Number(unreadCount || 0);
        var meta = section.querySelector('.notification-inbox-hero-meta');
        var pill = section.querySelector('.notification-inbox-unread-pill');
        var markAllForm = section.querySelector('.notification-inbox-inline-form');

        if (count > 0) {
            if (!pill && meta) {
                pill = document.createElement('span');
                pill.className = 'notification-inbox-unread-pill';
                meta.insertBefore(pill, meta.firstChild);
            }
            if (pill) {
                pill.textContent = count + ' não lidas';
                pill.hidden = false;
            }
            if (markAllForm) {
                markAllForm.hidden = false;
            }
        } else {
            if (pill) {
                pill.remove();
            }
            if (markAllForm) {
                markAllForm.hidden = true;
            }
        }

        var overviewUnread = document.querySelector('.dashboard-overview-card.tone-red .dashboard-overview-body strong');
        if (overviewUnread) {
            overviewUnread.textContent = String(count);
        }
    }

    function readPageCsrfToken(preferredInput) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        return preferredInput ? preferredInput.value : '';
    }

    function applyPageCsrfToken(token) {
        if (!token) {
            return;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.content = token;
        }
        document.querySelectorAll('input[name="csrf_token"]').forEach(function (input) {
            input.value = token;
        });
    }

    function bindNotificationReadHandlers() {
        var menu = document.getElementById('notificationMenu');
        var readForm = document.getElementById('notificationReadForm');
        if (!menu || !readForm || typeof fetch !== 'function') {
            return;
        }

        var csrfInput = readForm.querySelector('input[name="csrf_token"]');
        if (!csrfInput) {
            return;
        }

        Array.prototype.forEach.call(document.querySelectorAll('a[data-notification-read-url]'), function (link) {
            if (link.getAttribute('data-notification-bound') === 'true') {
                return;
            }

            link.setAttribute('data-notification-bound', 'true');
            link.addEventListener('click', function (event) {
                var notificationId = link.getAttribute('data-notification-id') || '';
                var readUrl = link.getAttribute('data-notification-read-url') || '';
                var targetHref = link.getAttribute('href') || '';
                var card = link.closest('.notification-item');
                var isRead = !!(card && card.classList.contains('is-read'));

                if (!notificationId || !readUrl || !targetHref || isRead) {
                    return;
                }

                event.preventDefault();

                var formData = new FormData();
                formData.append('csrf_token', readPageCsrfToken(csrfInput));

                fetch(readUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                    credentials: 'same-origin',
                    keepalive: true
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Falha ao marcar notificação como lida');
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        if (payload && payload.csrf_token) {
                            applyPageCsrfToken(payload.csrf_token);
                        }
                        updateNotificationReadState(notificationId, true, Number(payload && payload.unread_count));
                    })
                    .catch(function () {
                        // Preserve navigation even if read marking fails.
                    })
                    .finally(function () {
                        window.location.href = targetHref;
                    });
            });
        });
    }

    function bindNotificationUnreadHandlers() {
        var menu = document.getElementById('notificationMenu');
        var readForm = document.getElementById('notificationReadForm');
        if (!menu || !readForm || typeof fetch !== 'function') {
            return;
        }

        var csrfInput = readForm.querySelector('input[name="csrf_token"]');
        if (!csrfInput) {
            return;
        }

        Array.prototype.forEach.call(document.querySelectorAll('.notification-toggle-read-btn[data-notification-unread-url]'), function (button) {
            if (button.getAttribute('data-notification-unread-bound') === 'true') {
                return;
            }

            button.setAttribute('data-notification-unread-bound', 'true');
            button.addEventListener('click', function (event) {
                event.preventDefault();

                var notificationId = button.getAttribute('data-notification-id') || '';
                var unreadUrl = button.getAttribute('data-notification-unread-url') || '';
                if (!notificationId || !unreadUrl) {
                    return;
                }

                var formData = new FormData();
                formData.append('csrf_token', readPageCsrfToken(csrfInput));

                fetch(unreadUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Falha ao marcar notificação como não lida');
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        if (payload && payload.csrf_token) {
                            applyPageCsrfToken(payload.csrf_token);
                        }
                        updateNotificationReadState(notificationId, false, Number(payload && payload.unread_count));
                    })
                    .catch(function () {
                        // Keep UI unchanged on transient failure.
                    });
            });
        });
    }

    function updateNotificationPageTitle(unreadCount) {
        var base = document.body.getAttribute('data-base-title');
        if (!base) {
            base = document.title;
            document.body.setAttribute('data-base-title', base);
        }
        document.title = unreadCount > 0 ? '(' + unreadCount + ') ' + base : base;
    }

    function pulseNotificationTrigger() {
        var menu = document.getElementById('notificationMenu');
        var trigger = menu ? menu.querySelector('.notification-trigger') : null;
        if (!trigger) { return; }
        trigger.classList.remove('notification-pulse');
        void trigger.offsetWidth;
        trigger.classList.add('notification-pulse');
        window.setTimeout(function () { trigger.classList.remove('notification-pulse'); }, 1500);
    }

    function updateNotificationUi(payload) {
        var menu = document.getElementById('notificationMenu');
        if (!menu || !payload) { return; }
        var unreadCount = Number(payload.unread_count || 0);
        var prev = Number(menu.getAttribute('data-unread-initial') || 0);
        var items = Array.isArray(payload.notifications) ? payload.notifications : [];
        var badge = document.getElementById('notificationBadge');
        var unreadLabel = document.getElementById('notificationUnreadLabel');
        var list = document.getElementById('notificationList');
        var empty = document.getElementById('notificationEmpty');
        var readForm = document.getElementById('notificationReadForm');
        var dashboardUrl = menu.getAttribute('data-dashboard-url') || '#';

        if (unreadCount > prev) { pulseNotificationTrigger(); }
        syncNotificationUnreadCount(unreadCount);

        if (list) {
            if (items.length > 0) {
                list.innerHTML = renderNotificationItems(items, dashboardUrl);
                list.hidden = false;
                if (empty) { empty.hidden = true; }
                bindNotificationReadHandlers();
                bindNotificationUnreadHandlers();
            } else {
                list.innerHTML = '';
                list.hidden = true;
                if (empty) { empty.hidden = false; }
            }
        }
    }

    (function startNotificationPolling() {
        var menu = document.getElementById('notificationMenu');
        if (!menu || typeof fetch !== 'function') { return; }
        var feedUrl = menu.getAttribute('data-feed-url');
        if (!feedUrl) { return; }

        var isPolling = false;
        function poll() {
            if (isPolling) { return; }
            isPolling = true;
            fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { throw new Error('Falha ao buscar notificações'); }
                    return r.json();
                })
                .then(updateNotificationUi)
                .catch(function () { /* transient error — keep existing UI */ })
                .finally(function () { isPolling = false; });
        }

        updateNotificationPageTitle(Number(menu.getAttribute('data-unread-initial') || 0));
        bindNotificationReadHandlers();
        bindNotificationUnreadHandlers();
        poll();
        window.setInterval(poll, 30000);
    }());

    // Deep-link focus helper: highlights a destination item when a URL includes a known target id.
    (function focusDeepLinkedItem() {
        if (typeof URLSearchParams === 'undefined') {
            return;
        }

        var params = new URLSearchParams(window.location.search || '');
        var focusMappings = [
            { param: 'highlight', selectorPrefix: '[data-focus-commission-id="', allowEmpty: false },
            { param: 'boost_id', selectorPrefix: '[data-focus-boost-id="', allowEmpty: false },
            { param: 'user', selectorPrefix: '[data-focus-user-id="', allowEmpty: false },
            { param: 'document', selectorPrefix: '[data-focus-document-id="', allowEmpty: false }
        ];

        var targetNode = null;
        for (var i = 0; i < focusMappings.length; i++) {
            var mapping = focusMappings[i];
            var value = String(params.get(mapping.param) || '').trim();
            if (!value) {
                continue;
            }

            var selector = mapping.selectorPrefix + value.replace(/"/g, '\\"') + '"]';
            targetNode = document.querySelector(selector);
            if (targetNode) {
                break;
            }
        }

        if (!targetNode) {
            return;
        }

        targetNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
        targetNode.classList.add('deep-link-focus');
        window.setTimeout(function () {
            targetNode.classList.remove('deep-link-focus');
        }, 2600);
    }());

    var isRegisterDocumentProcessing = false;

    // Registration page: user-type toggle and document upload optimisation.
    (function () {
        var userTypeSelect = document.getElementById('user_type');
        var docFileInput = document.getElementById('document_file');
        var maxDocumentBytes = UPLOAD_TARGET_DOCUMENT;
        var uploadServerMaxBytes = UPLOAD_SERVER_MAX;
        var maxDocumentDimension = 2400;
        var documentFeedback = document.getElementById('document_file_feedback');
        var registerForm = document.getElementById('registerForm');

        function setDocumentFeedback(message, tone) {
            if (!documentFeedback) {
                return;
            }

            documentFeedback.textContent = message || '';
            documentFeedback.classList.remove('is-match', 'is-mismatch');
            if (tone === 'success') {
                documentFeedback.classList.add('is-match');
            } else if (tone === 'error') {
                documentFeedback.classList.add('is-mismatch');
            }
        }

        function createDocumentTransfer() {
            if (typeof DataTransfer === 'function') {
                try {
                    return new DataTransfer();
                } catch (error) {
                    return null;
                }
            }

            return null;
        }

        function syncDocumentInputFile(file) {
            if (!docFileInput) {
                return false;
            }

            var transfer = createDocumentTransfer();
            if (!transfer || !transfer.items || typeof transfer.items.add !== 'function') {
                return false;
            }

            transfer.items.add(file);
            docFileInput.files = transfer.files;
            return true;
        }

        function loadDocumentImage(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () {
                    reject(new Error('Não foi possível ler a imagem seleccionada.'));
                };
                reader.onload = function (event) {
                    var image = new Image();
                    image.onerror = function () {
                        reject(new Error('Não foi possível processar o formato desta imagem.'));
                    };
                    image.onload = function () {
                        resolve(image);
                    };
                    image.src = String(event.target && event.target.result ? event.target.result : '');
                };
                reader.readAsDataURL(file);
            });
        }

        function canvasToDocumentJpegBlob(canvas, quality) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Falha ao gerar imagem JPG.'));
                        return;
                    }
                    resolve(blob);
                }, 'image/jpeg', quality);
            });
        }

        async function convertDocumentImageToJpeg(file) {
            var image = await loadDocumentImage(file);
            var width = image.naturalWidth || image.width;
            var height = image.naturalHeight || image.height;

            if (width <= 0 || height <= 0) {
                throw new Error('Dimensões da imagem inválidas.');
            }

            if (width > maxDocumentDimension || height > maxDocumentDimension) {
                var dimensionScale = Math.min(maxDocumentDimension / width, maxDocumentDimension / height);
                width = Math.max(320, Math.floor(width * dimensionScale));
                height = Math.max(320, Math.floor(height * dimensionScale));
            }

            var qualities = [0.92, 0.86, 0.8, 0.74, 0.68, 0.62, 0.56, 0.5, 0.44, 0.38];
            var bestBlob = null;

            for (var round = 0; round < 5; round++) {
                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('Não foi possível preparar o processamento da imagem.');
                }

                ctx.drawImage(image, 0, 0, width, height);

                for (var i = 0; i < qualities.length; i++) {
                    var candidate = await canvasToDocumentJpegBlob(canvas, qualities[i]);
                    bestBlob = candidate;
                    if (candidate.size <= maxDocumentBytes) {
                        return candidate;
                    }
                }

                if (!bestBlob) {
                    break;
                }

                var ratio = Math.sqrt(maxDocumentBytes / bestBlob.size) * 0.95;
                if (!isFinite(ratio) || ratio >= 0.98) {
                    break;
                }

                width = Math.max(320, Math.floor(width * ratio));
                height = Math.max(320, Math.floor(height * ratio));
            }

            if (bestBlob && bestBlob.size <= maxDocumentBytes) {
                return bestBlob;
            }

            throw new Error('Não foi possível reduzir a imagem para até ' + formatUploadTarget('document') + '. Escolha outro ficheiro de até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.');
        }

        function formatDocumentSize(bytes) {
            if (bytes >= 1024 * 1024) {
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            }

            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        function isPdfFile(file) {
            var name = String(file.name || '').toLowerCase();
            return file.type === 'application/pdf' || name.slice(-4) === '.pdf';
        }

        function isImageFile(file) {
            return file.type && file.type.indexOf('image/') === 0;
        }

        function syncRegisterDocumentFields(type) {
            var identificationSection = document.getElementById('register-identification');
            var lbl = document.getElementById('document_label');
            var hint = document.getElementById('document_hint');
            var numInput = document.getElementById('document_number');
            var fileLabel = document.getElementById('document_file_label');
            var docFileInput = document.getElementById('document_file');
            var fileNameEl = document.getElementById('document_file_name');

            if (!type) {
                if (identificationSection) {
                    identificationSection.classList.add('is-pending-type');
                    identificationSection.classList.remove('is-ready');
                }
                if (numInput) {
                    numInput.required = false;
                    numInput.disabled = true;
                    numInput.value = '';
                    numInput.placeholder = 'Seleccione o tipo de conta acima';
                    numInput.removeAttribute('pattern');
                    numInput.removeAttribute('maxlength');
                    numInput.removeAttribute('inputmode');
                }
                if (docFileInput) {
                    docFileInput.required = false;
                    docFileInput.disabled = true;
                    docFileInput.value = '';
                }
                if (fileNameEl) {
                    fileNameEl.textContent = 'Nenhum ficheiro seleccionado';
                }
                if (hint) {
                    hint.textContent = 'Escolha o tipo de conta para indicar o número e enviar o documento.';
                }
                if (fileLabel) {
                    fileLabel.textContent = 'Documento de identificação *';
                }
                return;
            }

            if (identificationSection) {
                identificationSection.classList.remove('is-pending-type');
                identificationSection.classList.add('is-ready');
            }
            if (numInput) {
                numInput.required = true;
                numInput.disabled = false;
                numInput.placeholder = '';
            }
            if (docFileInput) {
                docFileInput.required = true;
                docFileInput.disabled = false;
            }

            if (type === 'pessoa_fisica') {
                if (lbl) { lbl.textContent = 'BI / NIF *'; }
                if (hint) { hint.textContent = 'Como no documento — pode incluir letras e números.'; }
                if (numInput) {
                    numInput.removeAttribute('pattern');
                    numInput.maxLength = 30;
                    numInput.inputMode = 'text';
                    numInput.title = 'BI ou NIF com letras e números';
                    numInput.placeholder = 'Ex.: 00654321LA045';
                }
                if (fileLabel) { fileLabel.textContent = 'Cópia do BI *'; }
            } else {
                if (lbl) { lbl.textContent = 'NIF da empresa (10 dígitos) *'; }
                if (hint) { hint.textContent = 'Pessoa colectiva: NIF de 10 dígitos (AGT).'; }
                if (numInput) {
                    numInput.pattern = '\\d{10}';
                    numInput.maxLength = 10;
                    numInput.inputMode = 'numeric';
                    numInput.title = 'Deve ter exactamente 10 dígitos';
                    numInput.placeholder = 'Ex.: 5000123456';
                }
                if (fileLabel) { fileLabel.textContent = 'Cópia do NIF *'; }
            }
        }

        if (userTypeSelect) {
            userTypeSelect.addEventListener('change', function () {
                syncRegisterDocumentFields(userTypeSelect.value);
            });
            syncRegisterDocumentFields(userTypeSelect.value);
        }

        if (registerForm) {
            registerForm.addEventListener('submit', function (event) {
                if (!isRegisterDocumentProcessing) {
                    return;
                }

                event.preventDefault();
                setDocumentFeedback('Aguarde o processamento do documento terminar para concluir o envio.', 'error');
            });
        }

        if (docFileInput) {
            var fileNameEl = document.getElementById('document_file_name');
            docFileInput.addEventListener('change', async function () {
                var file = docFileInput.files && docFileInput.files[0] ? docFileInput.files[0] : null;

                if (!file) {
                    if (fileNameEl) {
                        fileNameEl.textContent = 'Nenhum ficheiro seleccionado';
                    }
                    setDocumentFeedback('PDF ou JPG/PNG, até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.', '');
                    return;
                }

                if (!isPdfFile(file) && !isImageFile(file)) {
                    docFileInput.value = '';
                    if (fileNameEl) {
                        fileNameEl.textContent = 'Nenhum ficheiro seleccionado';
                    }
                    setDocumentFeedback('Seleccione um PDF ou uma imagem JPG/PNG.', 'error');
                    return;
                }

                if (isPdfFile(file)) {
                    if (file.size > uploadServerMaxBytes) {
                        docFileInput.value = '';
                        if (fileNameEl) {
                            fileNameEl.textContent = 'Nenhum ficheiro seleccionado';
                        }
                        setDocumentFeedback('O PDF excede ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '. Escolha um ficheiro de até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.', 'error');
                        return;
                    }

                    if (fileNameEl) {
                        fileNameEl.textContent = file.name + ' (' + formatDocumentSize(file.size) + ')';
                    }
                    setDocumentFeedback('PDF pronto.', 'success');
                    return;
                }

                isRegisterDocumentProcessing = true;
                setDocumentFeedback('A processar...', '');

                try {
                    var optimizedBlob = await convertDocumentImageToJpeg(file);
                    var safeNameBase = (file.name || 'documento_identificacao').replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                    var optimizedFile = new File([optimizedBlob], safeNameBase + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });

                    if (!syncDocumentInputFile(optimizedFile)) {
                        throw new Error('O seu navegador não permite substituir o ficheiro antes do envio.');
                    }

                    if (fileNameEl) {
                        fileNameEl.textContent = optimizedFile.name + ' (' + formatDocumentSize(optimizedFile.size) + ')';
                    }
                    setDocumentFeedback('', 'success');
                } catch (error) {
                    docFileInput.value = '';
                    if (fileNameEl) {
                        fileNameEl.textContent = 'Nenhum ficheiro seleccionado';
                    }
                    setDocumentFeedback(String(error && error.message ? error.message : 'Falha ao processar o documento.'), 'error');
                } finally {
                    isRegisterDocumentProcessing = false;
                }
            });
        }
    }());

    // Review-documents page: filter queue and alert dismiss.
    (function () {
        var filterButtons = Array.prototype.slice.call(document.querySelectorAll('.doc-filter-btn'));
        var queueItems = Array.prototype.slice.call(document.querySelectorAll('.doc-queue-item[data-doc-priority]'));
        var emptyFilter = document.getElementById('docQueueEmptyFilter');
        var alertCloseButtons = Array.prototype.slice.call(document.querySelectorAll('[data-doc-alert-close]'));

        // Force a consistent initial hidden state even with stale CSS cache.
        Array.prototype.forEach.call(document.querySelectorAll('.doc-modal'), function (modal) {
            modal.hidden = true;
            modal.style.display = 'none';
        });

        alertCloseButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var alertBox = button.closest('.alert');
                if (alertBox) { alertBox.style.display = 'none'; }
            });
        });

        if (!filterButtons.length || !queueItems.length) { return; }

        function applyFilter(filter) {
            var visibleCount = 0;
            queueItems.forEach(function (item) {
                var priority = item.getAttribute('data-doc-priority');
                var show = filter === 'all' || priority === filter;
                item.classList.toggle('is-hidden', !show);
                if (show) { visibleCount += 1; }
            });
            if (emptyFilter) { emptyFilter.hidden = visibleCount > 0; }
        }

        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var filter = button.getAttribute('data-doc-filter') || 'all';
                filterButtons.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === button);
                });
                applyFilter(filter);
            });
        });
    }());

    // Requests dashboard: status filter + action detail fields + user action routing.
    (function () {
        var statusFilter = document.getElementById('statusFilter');
        var propertyStatusFilter = document.getElementById('propertyStatusFilter');
        var paymentStatusFilter = document.getElementById('paymentStatusFilter');
        var requestRows = Array.prototype.slice.call(document.querySelectorAll('.request-row'));
        var actionForms = Array.prototype.slice.call(document.querySelectorAll('.request-action-select-form--details'));
        var filterFeedback = document.getElementById('requestFilterFeedback');
        var requestsRoot = document.getElementById('requestsDashboardRoot');
        var requestsTable = document.querySelector('.requests-table');

        if (!statusFilter && !propertyStatusFilter && !paymentStatusFilter && !actionForms.length) {
            return;
        }

        function moveActionsColumnLast() {
            if (!requestsTable) {
                return;
            }

            var headerRow = requestsTable.querySelector('thead tr');
            if (headerRow) {
                var actionsHeader = headerRow.querySelector('th.col-actions');
                if (actionsHeader && headerRow.lastElementChild !== actionsHeader) {
                    headerRow.appendChild(actionsHeader);
                }
            }

            var bodyRows = Array.prototype.slice.call(requestsTable.querySelectorAll('tbody tr.request-row'));
            bodyRows.forEach(function (row) {
                var actionsCell = row.querySelector('td.col-actions');
                if (actionsCell && row.lastElementChild !== actionsCell) {
                    row.appendChild(actionsCell);
                }
            });
        }

        if (requestsTable) {
            moveActionsColumnLast();
        }

        function normalizeRequestStatus(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function filterRequestRows(status, propertyStatus, paymentStatus) {
            var selected = normalizeRequestStatus(status);
            var selectedPropertyStatus = normalizeRequestStatus(propertyStatus);
            var selectedPaymentStatus = normalizeRequestStatus(paymentStatus);
            var visibleCount = 0;
            requestRows.forEach(function (row) {
                var rowStatus = normalizeRequestStatus(row.getAttribute('data-status') || '');
                var rowPropertyStatus = normalizeRequestStatus(row.getAttribute('data-property-status') || '');
                var rowPaymentStatus = normalizeRequestStatus(row.getAttribute('data-payment-status') || 'none');
                var matchesRequest = selected === 'all' || rowStatus === selected;
                var matchesProperty = selectedPropertyStatus === 'all' || rowPropertyStatus === selectedPropertyStatus;
                var matchesPayment = selectedPaymentStatus === 'all' || rowPaymentStatus === selectedPaymentStatus;
                var isVisible = matchesRequest && matchesProperty && matchesPayment;
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (filterFeedback) {
                if (selected === 'all' && selectedPropertyStatus === 'all' && selectedPaymentStatus === 'all') {
                    filterFeedback.textContent = visibleCount + ' solicitações exibidas';
                } else if (visibleCount === 0) {
                    filterFeedback.textContent = 'Nenhuma solicitação encontrada para os filtros selecionados';
                } else {
                    filterFeedback.textContent = visibleCount + ' solicitações encontradas para os filtros selecionados';
                }
            }
        }

        function countActiveRequestFilters() {
            var active = 0;
            if (statusFilter && (statusFilter.value || 'all') !== 'all') {
                active += 1;
            }
            if (propertyStatusFilter && (propertyStatusFilter.value || 'all') !== 'all') {
                active += 1;
            }
            if (paymentStatusFilter && (paymentStatusFilter.value || 'all') !== 'all') {
                active += 1;
            }
            return active;
        }

        function updateRequestsFilterBadge() {
            var badge = document.getElementById('requestsFilterBadge');
            if (!badge) {
                return;
            }
            var activeCount = countActiveRequestFilters();
            if (activeCount > 0) {
                badge.hidden = false;
                badge.textContent = String(activeCount);
            } else {
                badge.hidden = true;
                badge.textContent = '';
            }
        }

        function applyRequestsFilters() {
            var selectedRequestStatus = statusFilter ? (statusFilter.value || 'all') : 'all';
            var selectedPropertyStatus = propertyStatusFilter ? (propertyStatusFilter.value || 'all') : 'all';
            var selectedPaymentStatus = paymentStatusFilter ? (paymentStatusFilter.value || 'all') : 'all';
            filterRequestRows(selectedRequestStatus, selectedPropertyStatus, selectedPaymentStatus);
            updateRequestsFilterBadge();
        }

        function syncRequestFeedActionsState(actionsRoot) {
            if (!actionsRoot || !actionsRoot.classList.contains('request-feed-actions')) {
                return;
            }

            var hasActive = !!actionsRoot.querySelector('.request-action-select-form.is-action-active');
            actionsRoot.classList.toggle('has-active-action', hasActive);

            var feedItem = actionsRoot.closest('.request-feed-item');
            if (feedItem) {
                feedItem.classList.toggle('has-active-action', hasActive);
            }
        }

        function updateRequestActionExtraFields(form, selectedAction) {
            var extraFields = form.querySelector('.request-action-extra-fields');
            var noteField = form.querySelector('textarea[name="action_note"]');
            var uploadLabel = form.querySelector('.request-action-upload-label')
                || form.querySelector('.request-action-upload span');
            var fileField = form.querySelector('input[name="action_image"]');
            var formButtons = form.querySelector('.request-form-buttons');
            var selectField = form.querySelector('.request-action-select');
            var uploadBlock = form.querySelector('.request-action-upload');
            if (!extraFields || !noteField) {
                return;
            }

            var requiredRaw = form.getAttribute('data-note-required-actions') || '';
            var requiredActions = requiredRaw.split(',').map(function (value) {
                return value.trim();
            }).filter(Boolean);

            var proofRequiredRaw = form.getAttribute('data-proof-required-actions') || '';
            var proofRequiredActions = proofRequiredRaw.split(',').map(function (value) {
                return value.trim();
            }).filter(Boolean);

            var requiresNote = selectedAction !== '' && requiredActions.indexOf(selectedAction) !== -1;
            var requiresProof = selectedAction !== '' && proofRequiredActions.indexOf(selectedAction) !== -1;
            var hasActionSelected = selectedAction !== '';
            var needsExtraFields = hasActionSelected && (requiresNote || requiresProof);
            extraFields.hidden = !needsExtraFields;
            noteField.hidden = !requiresNote;
            if (uploadBlock) {
                uploadBlock.hidden = !requiresProof;
            }
            form.classList.toggle('is-action-active', hasActionSelected);

            if (formButtons) {
                formButtons.hidden = !hasActionSelected;
            }

            if (selectField) {
                selectField.classList.toggle('is-action-selected', hasActionSelected);
            }

            noteField.required = requiresNote;
            noteField.placeholder = requiresNote
                ? 'Observação (obrigatória para esta ação)...'
                : 'Observação (opcional)...';

            if (uploadLabel) {
                uploadLabel.textContent = requiresProof
                    ? 'Comprovativo de pagamento (obrigatório)'
                    : 'Evidência (opcional)';
            }

            if (fileField) {
                fileField.required = requiresProof;
            }

            if (!hasActionSelected) {
                noteField.value = '';
                if (fileField) {
                    fileField.value = '';
                    fileField.required = false;
                }
            }

            syncRequestFeedActionsState(form.closest('.request-feed-actions'));
        }

        function resetRequestActionForm(form) {
            var selectField = form.querySelector('.request-action-select');
            if (selectField) {
                selectField.value = '';
            }
            updateRequestActionExtraFields(form, '');
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                applyRequestsFilters();
            });
        }

        if (propertyStatusFilter) {
            propertyStatusFilter.addEventListener('change', function () {
                applyRequestsFilters();
            });
        }

        if (paymentStatusFilter) {
            paymentStatusFilter.addEventListener('change', function () {
                applyRequestsFilters();
            });
        }

        if (statusFilter || propertyStatusFilter || paymentStatusFilter) {
            applyRequestsFilters();
        }

        actionForms.forEach(function (form) {
            var statusSelect = form.querySelector('select[name="status"]');
            var userSelect = form.querySelector('select[name="user_action"]');
            var select = statusSelect || userSelect;
            if (!select) {
                return;
            }

            updateRequestActionExtraFields(form, select.value || '');

            select.addEventListener('change', function () {
                var actionsRoot = form.closest('.request-feed-actions');
                if (actionsRoot && select.value) {
                    Array.prototype.slice.call(actionsRoot.querySelectorAll('.request-action-select-form')).forEach(function (otherForm) {
                        if (otherForm !== form) {
                            resetRequestActionForm(otherForm);
                        }
                    });
                }
                updateRequestActionExtraFields(form, select.value || '');
            });

            var cancelButton = form.querySelector('.request-action-cancel-btn');
            if (cancelButton) {
                cancelButton.addEventListener('click', function () {
                    resetRequestActionForm(form);
                });
            }

            if (!form.classList.contains('user-action-select-form')) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var action = select.value || '';
                if (!action) {
                    return;
                }

                var proofRequiredRaw = form.getAttribute('data-proof-required-actions') || '';
                var proofRequiredActions = proofRequiredRaw.split(',').map(function (value) {
                    return value.trim();
                }).filter(Boolean);
                if (proofRequiredActions.indexOf(action) !== -1) {
                    var proofField = form.querySelector('input[name="action_image"]');
                    if (!proofField || !proofField.files || proofField.files.length === 0) {
                        window.alert('Anexe o comprovativo de pagamento para declarar o pagamento.');
                        return;
                    }
                    var proofFile = proofField.files[0];
                    if (proofFile && (proofFile.size || 0) > UPLOAD_SERVER_MAX) {
                        window.alert('O comprovativo excede ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '. Aguarde o processamento ou escolha outra imagem.');
                        return;
                    }
                }

                var url = '';
                if (action === 'confirm_closing') {
                    url = form.getAttribute('data-confirm-url') || '';
                } else if (action === 'contest_closing') {
                    url = form.getAttribute('data-contest-url') || '';
                } else if (action === 'confirm_payment_receipt') {
                    url = form.getAttribute('data-confirm-payment-receipt-url') || '';
                } else if (action === 'contest_payment') {
                    url = form.getAttribute('data-contest-payment-url') || '';
                } else if (action === 'open_dispute') {
                    url = form.getAttribute('data-open-dispute-url') || '';
                } else if (action === 'cancel') {
                    url = form.getAttribute('data-cancel-url') || '';
                }

                if (!url) {
                    return;
                }

                form.setAttribute('action', url);
                form.submit();
            });
        });

        // Poll request chat summaries to keep unread badges and previews fresh.
        if (requestsRoot && typeof fetch === 'function') {
            var feedUrl = requestsRoot.getAttribute('data-chat-summaries-feed-url') || '';
            if (feedUrl) {
                var summaryNodes = Array.prototype.slice.call(document.querySelectorAll('[data-chat-summary-for-request]'));
                var summaryMap = {};

                summaryNodes.forEach(function (node) {
                    var requestId = Number(node.getAttribute('data-chat-summary-for-request') || 0);
                    if (requestId > 0) {
                        summaryMap[requestId] = node;
                    }
                });

                function truncateText(value, max) {
                    var text = String(value || '');
                    if (text.length <= max) {
                        return text;
                    }
                    return text.slice(0, max) + '...';
                }

                function applySummary(summary) {
                    var requestId = Number(summary.request_id || 0);
                    var node = summaryMap[requestId];
                    if (!node) {
                        return;
                    }

                    var count = Number(summary.total_messages || 0);
                    var unread = Number(summary.unread_count || 0);
                    var preview = truncateText(summary.last_message_text || '', 70);

                    node.hidden = count <= 0;
                    if (count <= 0) {
                        return;
                    }

                    var countNode = node.querySelector('.request-chat-summary-count');
                    if (countNode) {
                        countNode.textContent = String(count);
                    }

                    var unreadNode = node.querySelector('.request-chat-unread-badge');
                    if (unreadNode) {
                        unreadNode.hidden = unread <= 0;
                        unreadNode.textContent = unread > 0 ? (String(unread) + ' nova(s)') : '';
                    }

                    var previewNode = node.querySelector('.request-chat-summary-preview');
                    if (previewNode) {
                        previewNode.hidden = preview === '';
                        previewNode.textContent = preview;
                    }
                }

                var isPollingSummaries = false;
                function pollSummaries() {
                    if (isPollingSummaries) {
                        return;
                    }

                    isPollingSummaries = true;
                    fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Falha ao atualizar resumos de chat');
                            }
                            return response.json();
                        })
                        .then(function (payload) {
                            var summaries = Array.isArray(payload && payload.summaries) ? payload.summaries : [];
                            summaries.forEach(applySummary);
                        })
                        .catch(function () {
                            // Keep current summaries on transient errors.
                        })
                        .finally(function () {
                            isPollingSummaries = false;
                        });
                }

                pollSummaries();
                window.setInterval(pollSummaries, 15000);
            }
        }
    }());

    // Request chat: lightweight polling for near real-time updates.
    (function () {
        var messagesContainer = document.getElementById('requestChatMessages');
        if (!messagesContainer || typeof fetch !== 'function') {
            return;
        }

        var feedUrl = messagesContainer.getAttribute('data-chat-feed-url') || '';
        var currentUserId = Number(messagesContainer.getAttribute('data-chat-current-user-id') || 0);
        var profileUrlPrefix = messagesContainer.getAttribute('data-profile-url-prefix') || '';
        if (!feedUrl) {
            return;
        }

        function renderEmptyState() {
            messagesContainer.innerHTML = '' +
                '<div class="empty-state-content request-chat-empty">' +
                    '<i class="fa fa-comments"></i>' +
                    '<p>Nenhuma mensagem ainda. Use este canal para conduzir a negociação.</p>' +
                '</div>';
        }

        function renderMessages(items) {
            if (!Array.isArray(items) || items.length === 0) {
                renderEmptyState();
                return;
            }

            var html = items.map(function (message) {
                var isSystem = String(message.message_type || 'text') === 'system';
                var isOwn = Number(message.sender_user_id || 0) === currentUserId;
                var cls = isSystem ? 'is-system' : (isOwn ? 'is-own' : 'is-other');
                var senderLabel = isSystem ? 'Sistema' : String(message.sender_name || 'Utilizador');
                var senderUserId = Number(message.sender_user_id || 0);
                var senderHeading = escapeHtml(senderLabel);
                if (!isSystem && senderUserId > 0 && profileUrlPrefix) {
                    senderHeading = '<a href="' + escapeHtml(profileUrlPrefix + String(senderUserId)) + '" class="table-name-link">' + escapeHtml(senderLabel) + '</a>';
                }
                var createdLabel = String(message.created_at_label || '');
                var messageBody = String(message.message_text || '');
                var escapedBody = escapeHtml(messageBody).replace(/\n/g, '<br>');
                var attachmentPath = String(message.attachment_url || message.attachment_path || '');
                var attachmentHtml = '';

                if (attachmentPath) {
                    attachmentHtml = '' +
                        '<div class="request-chat-message-attachment">' +
                            '<a href="' + escapeHtml(attachmentPath) + '" class="attachment-link" target="_blank" rel="noopener noreferrer">' +
                                '<i class="fa fa-image"></i> Ver anexo' +
                            '</a>' +
                        '</div>';
                }

                return '' +
                    '<article class="request-chat-message ' + cls + '">' +
                        '<header class="request-chat-message-meta">' +
                            '<strong>' + senderHeading + '</strong>' +
                            '<small>' + escapeHtml(createdLabel) + '</small>' +
                        '</header>' +
                        '<div class="request-chat-message-body">' + escapedBody + '</div>' +
                        attachmentHtml +
                    '</article>';
            }).join('');

            messagesContainer.innerHTML = html;
        }

        var isPolling = false;
        function poll() {
            if (isPolling) {
                return;
            }

            isPolling = true;
            fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Falha ao atualizar chat');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    var messages = Array.isArray(payload && payload.messages) ? payload.messages : [];
                    renderMessages(messages);
                })
                .catch(function () {
                    // Keep existing UI on transient polling errors.
                })
                .finally(function () {
                    isPolling = false;
                });
        }

        poll();
        window.setInterval(poll, 10000);
    }());

    // Request chats inbox: mark read on open + keep sidebar in sync.
    (function () {
        var root = document.querySelector('.request-chats-dashboard-view');
        if (!root) {
            return;
        }

        var markBase = root.getAttribute('data-chat-mark-read-url') || '';
        var markUnreadBase = root.getAttribute('data-chat-mark-unread-url') || '';
        var summariesUrl = root.getAttribute('data-chat-summaries-feed-url') || '';

        function getChatCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? (meta.getAttribute('content') || '') : '';
        }

        function setChatItemReadState(item, unreadCount, options) {
            if (!item) {
                return;
            }

            options = options || {};
            var count = Number(unreadCount || 0);
            var isActive = item.classList.contains('is-active');
            var showUnread = count > 0 && (options.forceUnread || !isActive);

            item.classList.toggle('has-unread', showUnread);
            item.classList.toggle('is-unread', showUnread);
            item.classList.toggle('is-read', !showUnread);

            var meta = item.querySelector('.request-chats-panel-item-meta');
            var badge = item.querySelector('.request-chat-unread-badge');
            if (showUnread) {
                if (!badge && meta) {
                    var dotSep = document.createElement('span');
                    dotSep.className = 'notification-feed-dot';
                    dotSep.setAttribute('aria-hidden', 'true');
                    dotSep.textContent = '·';
                    badge = document.createElement('span');
                    badge.className = 'request-chat-unread-badge';
                    meta.appendChild(dotSep);
                    meta.appendChild(badge);
                }
                if (badge) {
                    badge.hidden = false;
                    badge.textContent = count + ' nova(s)';
                }
            } else if (badge) {
                var prev = badge.previousElementSibling;
                if (prev && prev.classList.contains('notification-feed-dot')) {
                    prev.remove();
                }
                badge.remove();
            }

            var link = item.querySelector('.request-chats-panel-item-link');
            var dot = item.querySelector('.notification-feed-unread-dot');
            if (showUnread) {
                if (!dot && link) {
                    dot = document.createElement('span');
                    dot.className = 'notification-feed-unread-dot';
                    dot.setAttribute('aria-label', count + ' mensagem(ns) nova(s)');
                    link.appendChild(dot);
                } else if (dot) {
                    dot.setAttribute('aria-label', count + ' mensagem(ns) nova(s)');
                }
            } else if (dot) {
                dot.remove();
            }

            var menuWrap = item.querySelector('.notification-feed-menu-wrap');
            if (menuWrap) {
                menuWrap.hidden = showUnread;
            }
        }

        function updateHeaderChatBadge(count) {
            var link = document.querySelector('.header-icon-link--chat');
            if (!link) {
                return;
            }

            var badge = link.querySelector('.notification-badge');
            var value = Number(count || 0);
            if (value > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-badge';
                    link.appendChild(badge);
                }
                badge.textContent = value > 99 ? '99+' : String(value);
                badge.hidden = false;
            } else if (badge) {
                badge.remove();
            }
        }

        function markRequestChatRead(requestId) {
            if (!markBase || requestId <= 0 || typeof fetch !== 'function') {
                return Promise.resolve(null);
            }

            return fetch(markBase + String(requestId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token=' + encodeURIComponent(getChatCsrfToken())
            })
                .then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                })
                .catch(function () {
                    return null;
                });
        }

        function markRequestChatUnread(requestId) {
            if (!markUnreadBase || requestId <= 0 || typeof fetch !== 'function') {
                return Promise.resolve(null);
            }

            return fetch(markUnreadBase + String(requestId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token=' + encodeURIComponent(getChatCsrfToken())
            })
                .then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                })
                .catch(function () {
                    return null;
                });
        }

        function applyChatUnreadPayload(payload, options) {
            if (!payload || !payload.ok) {
                return;
            }

            options = options || {};
            var requestId = Number(payload.request_id || 0);
            if (requestId <= 0) {
                return;
            }

            var item = root.querySelector('.request-chat-feed-item[data-request-id="' + String(requestId) + '"]');
            if (item) {
                setChatItemReadState(item, payload.unread_count, { forceUnread: !!options.forceUnread });
            }

            if (typeof payload.header_unread_chat_messages === 'number') {
                updateHeaderChatBadge(payload.header_unread_chat_messages);
            }

            var heroPill = root.querySelector('.notification-inbox-unread-pill');
            var heroMeta = root.querySelector('.notification-inbox-hero-meta');
            if (heroMeta) {
                var headerCount = Number(payload.header_unread_chat_messages || 0);
                if (headerCount > 0) {
                    if (!heroPill) {
                        var dot = document.createElement('span');
                        dot.className = 'notification-feed-dot';
                        dot.setAttribute('aria-hidden', 'true');
                        dot.textContent = '·';
                        heroPill = document.createElement('span');
                        heroPill.className = 'notification-inbox-unread-pill';
                        heroMeta.appendChild(dot);
                        heroMeta.appendChild(heroPill);
                    }
                    heroPill.textContent = headerCount + ' não lidas';
                    heroPill.hidden = false;
                } else if (heroPill) {
                    var prev = heroPill.previousElementSibling;
                    if (prev && prev.classList.contains('notification-feed-dot')) {
                        prev.remove();
                    }
                    heroPill.remove();
                }
            }
        }

        root.addEventListener('click', function (event) {
            var unreadBtn = event.target.closest('.request-chat-mark-unread-btn, .request-chats-mark-unread-btn');
            if (unreadBtn && root.contains(unreadBtn)) {
                event.preventDefault();
                event.stopPropagation();

                var unreadRequestId = Number(unreadBtn.getAttribute('data-request-id') || 0);
                if (unreadRequestId <= 0) {
                    return;
                }

                unreadBtn.disabled = true;
                markRequestChatUnread(unreadRequestId).then(function (payload) {
                    unreadBtn.disabled = false;
                    if (!payload || !payload.ok) {
                        return;
                    }

                    applyChatUnreadPayload(payload, { forceUnread: true });

                    if (typeof closeNotificationFeedMenus === 'function') {
                        closeNotificationFeedMenus();
                    }

                    if (unreadBtn.classList.contains('request-chats-mark-unread-btn')) {
                        unreadBtn.textContent = 'Marcada como não lida';
                        window.setTimeout(function () {
                            unreadBtn.textContent = 'Marcar como não lida';
                        }, 2000);
                    }
                });
                return;
            }

            var link = event.target.closest('.request-chats-panel-item-link');
            if (!link || !root.contains(link)) {
                return;
            }

            var item = link.closest('.request-chat-feed-item');
            var requestId = Number(item && item.getAttribute('data-request-id') || 0);
            if (requestId <= 0) {
                return;
            }

            event.preventDefault();
            var targetUrl = link.getAttribute('href') || '';

            markRequestChatRead(requestId).then(function (payload) {
                if (item) {
                    setChatItemReadState(item, payload ? payload.unread_count : 0);
                }
                if (payload && typeof payload.header_unread_chat_messages === 'number') {
                    updateHeaderChatBadge(payload.header_unread_chat_messages);
                }
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });
        });

        if (summariesUrl && root.classList.contains('has-chat-selected')) {
            function applySummary(summary) {
                var requestId = Number(summary.request_id || 0);
                if (requestId <= 0) {
                    return;
                }
                var item = root.querySelector('.request-chat-feed-item[data-request-id="' + String(requestId) + '"]');
                if (!item) {
                    return;
                }
                setChatItemReadState(item, summary.unread_count);
            }

            function pollChatSummaries() {
                fetch(summariesUrl, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('summaries failed');
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        var summaries = payload && Array.isArray(payload.summaries) ? payload.summaries : [];
                        summaries.forEach(applySummary);
                    })
                    .catch(function () {
                        // Keep UI on transient errors.
                    });
            }

            pollChatSummaries();
            window.setInterval(pollChatSummaries, 12000);
        }

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    }());

    // Property create/edit: rental period fields + rent terms visibility toggle.
    (function () {
        var purpose = document.getElementById('purpose');
        var rentalPeriodsBlock = document.getElementById('rental-periods-block');
        var rentalDaysField    = document.getElementById('rental-days-field');
        var rentalMonthsField  = document.getElementById('rental-months-field');
        var rentTermsBlock     = document.getElementById('rent-terms-block');
        var rentalDaysInput    = document.getElementById('rental_days');
        var rentalMonthsInput  = document.getElementById('rental_months');
        if (!purpose) { return; }

        function syncRentalVisibility() {
            var val = purpose.value;
            var isShortRent = val === 'aluguer_curto';
            var isLongRent  = val === 'aluguer_longo';
            var isAnyRent   = isShortRent || isLongRent;

            if (rentalPeriodsBlock) { rentalPeriodsBlock.style.display = isAnyRent ? '' : 'none'; }
            if (rentalDaysField)    { rentalDaysField.style.display    = isShortRent ? '' : 'none'; }
            if (rentalMonthsField)  { rentalMonthsField.style.display  = isLongRent  ? '' : 'none'; }
            if (rentalDaysInput)    { rentalDaysInput.disabled = !isShortRent; }
            if (rentalMonthsInput)  { rentalMonthsInput.disabled = !isLongRent; }
            if (rentTermsBlock) {
                rentTermsBlock.style.display = isLongRent ? 'block' : 'none';
                if (!isLongRent) {
                    var checks = rentTermsBlock.querySelectorAll('input[type="checkbox"]');
                    for (var i = 0; i < checks.length; i++) {
                        checks[i].checked = false;
                        checks[i].disabled = true;
                    }
                } else {
                    var enabledChecks = rentTermsBlock.querySelectorAll('input[type="checkbox"]');
                    for (var j = 0; j < enabledChecks.length; j++) {
                        enabledChecks[j].disabled = false;
                    }
                }
            }
        }

        purpose.addEventListener('change', syncRentalVisibility);
        syncRentalVisibility();
    }());

    // Property create/edit: filter regions by selected country.
    (function () {
        var form = document.querySelector('.property-create-form');
        if (!form) {
            return;
        }

        var countrySelect = form.querySelector('#country_id');
        var regionSelect = form.querySelector('#region_id');
        if (!countrySelect || !regionSelect) {
            return;
        }

        var allRegionOptions = Array.prototype.slice.call(regionSelect.querySelectorAll('option'));
        if (allRegionOptions.length <= 1) {
            return;
        }

        var placeholderOption = allRegionOptions[0];
        var previousRegionValue = regionSelect.value;

        function repopulateRegions() {
            var selectedCountryId = countrySelect.value;
            var nextSelectedValue = previousRegionValue;

            regionSelect.innerHTML = '';
            var placeholderClone = placeholderOption.cloneNode(true);
            placeholderClone.textContent = selectedCountryId === '' ? 'Selecione um país primeiro' : 'Selecione uma região';
            regionSelect.appendChild(placeholderClone);

            var hasCurrentSelection = false;
            if (selectedCountryId !== '') {
                for (var i = 1; i < allRegionOptions.length; i++) {
                    var option = allRegionOptions[i];
                    var optionCountryId = option.getAttribute('data-country-id') || '';
                    if (optionCountryId === selectedCountryId) {
                        var clone = option.cloneNode(true);
                        if (clone.value === nextSelectedValue) {
                            clone.selected = true;
                            hasCurrentSelection = true;
                        }
                        regionSelect.appendChild(clone);
                    }
                }
            }

            if (!hasCurrentSelection) {
                regionSelect.value = '';
                previousRegionValue = '';
            }

            regionSelect.disabled = selectedCountryId === '';
        }

        countrySelect.addEventListener('change', function () {
            previousRegionValue = regionSelect.value;
            repopulateRegions();
        });

        regionSelect.addEventListener('change', function () {
            previousRegionValue = regionSelect.value;
        });

        form.addEventListener('submit', function () {
            if (countrySelect.value !== '') {
                regionSelect.disabled = false;
            }
        });

        repopulateRegions();
    }());

    // Properties listing filter: country -> region dependency.
    (function () {
        var filterForm = document.querySelector('.sales-filter-form');
        if (!filterForm) {
            return;
        }

        var countrySelect = filterForm.querySelector('select[name="country_id"]');
        var regionSelect = filterForm.querySelector('select[name="region_id"]');
        if (!countrySelect || !regionSelect) {
            return;
        }

        var allRegionOptions = Array.prototype.slice.call(regionSelect.querySelectorAll('option'));
        if (allRegionOptions.length <= 1) {
            return;
        }

        var placeholderOption = allRegionOptions[0];
        var previousRegionValue = regionSelect.value;

        function repopulateRegions() {
            var selectedCountryId = countrySelect.value;
            var nextSelectedValue = previousRegionValue;

            regionSelect.innerHTML = '';
            var placeholderClone = placeholderOption.cloneNode(true);
            placeholderClone.textContent = selectedCountryId === '' ? 'Selecione um país primeiro' : 'Todas';
            regionSelect.appendChild(placeholderClone);

            var hasCurrentSelection = false;
            if (selectedCountryId !== '') {
                for (var i = 1; i < allRegionOptions.length; i++) {
                    var option = allRegionOptions[i];
                    var optionCountryId = option.getAttribute('data-country-id') || '';
                    if (optionCountryId === selectedCountryId) {
                        var clone = option.cloneNode(true);
                        if (clone.value === nextSelectedValue) {
                            clone.selected = true;
                            hasCurrentSelection = true;
                        }
                        regionSelect.appendChild(clone);
                    }
                }
            }

            if (!hasCurrentSelection) {
                regionSelect.value = '';
                previousRegionValue = '';
            }

            regionSelect.disabled = selectedCountryId === '';
        }

        countrySelect.addEventListener('change', function () {
            previousRegionValue = regionSelect.value;
            repopulateRegions();
        });

        regionSelect.addEventListener('change', function () {
            previousRegionValue = regionSelect.value;
        });

        repopulateRegions();
    }());

    // Profile: promoter program terms before activating affiliate profile.
    (function setupPromoterTermsModal() {
        var activateBtn = document.getElementById('promoter-activate-btn');
        var modal = document.getElementById('promoter-terms-modal');
        if (!activateBtn || !modal) { return; }

        var termsBody = document.getElementById('promoter-terms-body');
        var closeBtn = document.querySelector('.promoter-terms-modal-close');
        var cancelBtn = document.querySelector('.promoter-terms-modal-cancel');
        var submitBtn = document.getElementById('promoter-terms-submit');
        var checkbox = document.getElementById('promoter-terms-checkbox');
        var form = document.getElementById('promoter-activate-form');
        var termsUrl = modal.getAttribute('data-terms-url') || '';

        function closeModal() {
            modal.hidden = true;
        }

        function loadTerms() {
            if (!termsBody) { return; }
            if (!termsUrl) {
                termsBody.innerHTML = '<p style="color: red;">URL dos termos indisponível.</p>';
                return;
            }
            termsBody.innerHTML = '<p>A carregar termos...</p>';
            fetch(termsUrl, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) { throw new Error('Falha ao carregar termos'); }
                    return response.json();
                })
                .then(function (data) {
                    var modalTitle = document.getElementById('promoter-terms-modal-title');
                    if (modalTitle && data.title) {
                        modalTitle.textContent = data.title;
                    }
                    var html = '';
                    if (data.user_type_label) {
                        html += '<p class="dashboard-inline-note">Documento para conta de <strong>' + escapeHtml(data.user_type_label) + '</strong>.</p>';
                    }
                    if (Array.isArray(data.sections)) {
                        data.sections.forEach(function (section) {
                            html += '<div class="affiliate-terms-section">' +
                                '<h3>' + escapeHtml(section.heading || '') + '</h3>' +
                                '<p>' + escapeHtml(section.content || '') + '</p>' +
                                '</div>';
                        });
                    }
                    if (data.last_updated) {
                        html += '<p class="dashboard-inline-note">Última actualização: ' + escapeHtml(String(data.last_updated)) + '</p>';
                    }
                    termsBody.innerHTML = html;
                })
                .catch(function () {
                    termsBody.innerHTML = '<p style="color: red;">Erro ao carregar os termos. Tente novamente.</p>';
                });
        }

        activateBtn.addEventListener('click', function () {
            if (checkbox) { checkbox.checked = false; }
            if (submitBtn) { submitBtn.disabled = true; }
            loadTerms();
            modal.hidden = false;
        });

        if (checkbox && submitBtn) {
            checkbox.addEventListener('change', function () {
                submitBtn.disabled = !checkbox.checked;
            });
        }

        if (submitBtn && form) {
            submitBtn.addEventListener('click', function () {
                if (!checkbox || !checkbox.checked) { return; }
                form.submit();
            });
        }

        if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
        if (cancelBtn) { cancelBtn.addEventListener('click', closeModal); }
        modal.addEventListener('click', function (event) {
            if (event.target === modal) { closeModal(); }
        });
    }());

    // Property affiliation: Modal for terms and conditions.
    (function setupAffiliationModal() {
        var requestBtns = Array.prototype.slice.call(document.querySelectorAll('.affiliation-request-btn'));
        var modal = document.getElementById('affiliation-terms-modal');
        var closeBtn = document.querySelector('.affiliation-modal-close');
        var cancelBtn = document.querySelector('.affiliation-modal-cancel');
        var submitBtn = document.querySelector('.affiliation-submit-btn');

        if (requestBtns.length === 0 || !modal) { return; }

        function getBaseUrl() {
            return window.location.origin + window.location.pathname.split('property')[0];
        }

        function showTermsModal(button) {
            var termsBody = document.getElementById('affiliation-terms-body');
            if (!termsBody) { return; }
            if (submitBtn && button) {
                var propertyId = button.getAttribute('data-affiliate-property-id') || '';
                if (propertyId) {
                    submitBtn.setAttribute('data-property-id', propertyId);
                }
            }

            fetch(getBaseUrl() + 'property/getAffiliationTerms', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) { throw new Error('Falha ao carregar termos'); }
                    return response.json();
                })
                .then(function (data) {
                    var html = '<h3>' + escapeHtml(data.title || 'Termos e Condições') + '</h3>';
                    if (Array.isArray(data.sections)) {
                        data.sections.forEach(function (section) {
                            html += '<div class="affiliate-terms-section">' +
                                '<h3>' + escapeHtml(section.heading || '') + '</h3>' +
                                '<p>' + escapeHtml(section.content || '') + '</p>' +
                                '</div>';
                        });
                    }
                    termsBody.innerHTML = html;
                    modal.hidden = false;
                })
                .catch(function () {
                    termsBody.innerHTML = '<p style="color: red;">Erro ao carregar os termos. Tente novamente.</p>';
                    modal.hidden = false;
                });
        }

        function submitRequest() {
            var propertyId = submitBtn.getAttribute('data-property-id');
            if (!propertyId) { return; }

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = (csrfMeta && csrfMeta.content)
                ? csrfMeta.content
                : ((document.querySelector('input[name="csrf_token"]') || {}).value || '');

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = getBaseUrl() + 'property/affiliateRequest/' + encodeURIComponent(propertyId);
            form.innerHTML = '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">';
            document.body.appendChild(form);
            form.submit();
        }

        function closeModal() {
            modal.hidden = true;
        }

        requestBtns.forEach(function (requestBtn) {
            requestBtn.addEventListener('click', function () {
                showTermsModal(requestBtn);
            });
        });
        if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
        if (cancelBtn) { cancelBtn.addEventListener('click', closeModal); }
        if (submitBtn) { submitBtn.addEventListener('click', submitRequest); }

        modal.addEventListener('click', function (event) {
            if (event.target === modal) { closeModal(); }
        });
    }());

    // Display simplified affiliate terms (for already approved affiliates).
    (function displayAffiliateTermsForApproved() {
        var termsDisplay = document.getElementById('affiliate-terms-display');
        if (!termsDisplay) { return; }

        var baseUrl = window.location.origin + window.location.pathname.split('property')[0];

        fetch(baseUrl + 'property/getAffiliationTerms', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) { throw new Error('Falha'); }
                return response.json();
            })
            .then(function (data) {
                var html = '';
                if (Array.isArray(data.sections)) {
                    data.sections.forEach(function (section) {
                        html += '<div class="affiliate-terms-section">' +
                            '<h3>' + escapeHtml(section.heading || '') + '</h3>' +
                            '<p>' + escapeHtml(section.content || '') + '</p>' +
                            '</div>';
                    });
                }
                termsDisplay.innerHTML = html;
            })
            .catch(function () {
                // Silent fail for read-only display
            });
    }());

    // Doc-modal: close active modal on Escape key (page-agnostic).
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        var affiliateModal = document.getElementById('affiliation-terms-modal');
        if (affiliateModal && !affiliateModal.hidden) {
            affiliateModal.hidden = true;
        }
        var promoterModal = document.getElementById('promoter-terms-modal');
        if (promoterModal && !promoterModal.hidden) {
            promoterModal.hidden = true;
        }
        var activeModalId = document.body.getAttribute('data-active-doc-modal');
        if (activeModalId) { window.docModalClose(activeModalId); }
    });

    // Request chat / solicitações: otimizar anexos de imagem para WebP, igual ao pipeline do chat.
    (function initRequestAttachmentWebpOptimizer() {
        var maxBytes = UPLOAD_TARGET_CHAT;
        var maxDim = 1920;
        var defaultHint = 'JPG, PNG, GIF ou WebP, até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.';

        function setFeedback(el, msg, tone) {
            if (!el) {
                return;
            }
            el.textContent = msg || '';
            if (tone === 'error') {
                el.style.color = '#a11c2f';
            } else if (tone === 'success') {
                el.style.color = '#1e7e39';
            } else {
                el.style.color = '';
            }
        }

        function syncInputFile(input, file) {
            if (!input || typeof DataTransfer !== 'function') {
                return false;
            }
            try {
                var xfer = new DataTransfer();
                xfer.items.add(file);
                input.files = xfer.files;
                return true;
            } catch (e) {
                return false;
            }
        }

        function loadImageFromFile(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () {
                    reject(new Error('Não foi possível ler a imagem.'));
                };
                reader.onload = function (ev) {
                    var img = new Image();
                    img.onerror = function () {
                        reject(new Error('Formato de imagem não suportado.'));
                    };
                    img.onload = function () {
                        resolve(img);
                    };
                    img.src = String(ev.target && ev.target.result ? ev.target.result : '');
                };
                reader.readAsDataURL(file);
            });
        }

        function canvasToWebpBlob(canvas, quality) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Falha ao gerar WebP.'));
                        return;
                    }
                    resolve(blob);
                }, 'image/webp', quality);
            });
        }

        async function convertAttachmentToWebp(file) {
            var img = await loadImageFromFile(file);
            var w = img.naturalWidth || img.width;
            var h = img.naturalHeight || img.height;
            if (w <= 0 || h <= 0) {
                throw new Error('Dimensões inválidas.');
            }

            if (w > maxDim || h > maxDim) {
                var scale = Math.min(maxDim / w, maxDim / h);
                w = Math.max(320, Math.floor(w * scale));
                h = Math.max(320, Math.floor(h * scale));
            }

            var qualities = [0.9, 0.84, 0.78, 0.72, 0.66, 0.6, 0.54, 0.48];
            var last = null;

            for (var round = 0; round < 4; round++) {
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('Canvas não disponível.');
                }
                ctx.drawImage(img, 0, 0, w, h);

                for (var qi = 0; qi < qualities.length; qi++) {
                    var blob = await canvasToWebpBlob(canvas, qualities[qi]);
                    last = blob;
                    if (blob.size <= maxBytes) {
                        return blob;
                    }
                }

                if (!last) {
                    break;
                }
                var ratio = Math.sqrt(maxBytes / last.size) * 0.95;
                if (!isFinite(ratio) || ratio >= 0.98) {
                    break;
                }
                w = Math.max(320, Math.floor(w * ratio));
                h = Math.max(320, Math.floor(h * ratio));
            }

            if (last && last.size <= maxBytes) {
                return last;
            }

            throw new Error('Não foi possível reduzir a imagem para até ' + formatUploadTarget('chatAttachment') + '. Escolha outro ficheiro de até ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.');
        }

        function bindRequestAttachmentOptimizer(input, feedbackEl) {
            if (!input || input.dataset.requestAttachmentOptimizerBound === '1') {
                return;
            }
            input.dataset.requestAttachmentOptimizerBound = '1';

            var form = input.form;
            var isProcessing = false;
            var defaultName = (file) => (file && file.name ? file.name.replace(/\.[^.]+$/, '') : 'anexo');

            if (form) {
                form.addEventListener('submit', function (ev) {
                    if (!isProcessing) {
                        return;
                    }
                    ev.preventDefault();
                    setFeedback(feedbackEl, 'Aguarde o processamento.', 'error');
                });
            }

            input.addEventListener('change', async function () {
                var file = input.files && input.files[0] ? input.files[0] : null;

                if (!file) {
                    setFeedback(feedbackEl, defaultHint, '');
                    return;
                }

                if (!file.type || file.type.indexOf('image/') !== 0) {
                    input.value = '';
                    setFeedback(feedbackEl, 'Selecione um ficheiro de imagem válido.', 'error');
                    return;
                }

                isProcessing = true;
                setFeedback(feedbackEl, 'A processar...', '');

                try {
                    var optimized = await convertAttachmentToWebp(file);
                    var baseName = defaultName(file).replace(/[^a-zA-Z0-9_-]/g, '_') || 'anexo';
                    var outFile = new File([optimized], baseName + '.webp', {
                        type: 'image/webp',
                        lastModified: Date.now()
                    });

                    if (!syncInputFile(input, outFile)) {
                        throw new Error('O navegador não permite substituir o ficheiro antes do envio.');
                    }

                    setFeedback(
                        feedbackEl,
                        '✓ ' + outFile.name + ' (' + Math.round(outFile.size / 1024) + ' KB)',
                        'success'
                    );
                } catch (err) {
                    input.value = '';
                    setFeedback(
                        feedbackEl,
                        String(err && err.message ? err.message : 'Falha ao processar imagem.'),
                        'error'
                    );
                } finally {
                    isProcessing = false;
                }
            });

            setFeedback(feedbackEl, defaultHint, '');
        }

        document.querySelectorAll('.js-request-attachment-input').forEach(function (input) {
            var feedbackEl = null;
            if (input.id === 'message-attachment') {
                feedbackEl = document.getElementById('attachment-name');
            } else if (input.id === 'commission_payment_proof') {
                feedbackEl = document.getElementById('commission-payment-proof-feedback');
            } else if (input.classList && input.classList.contains('js-affiliate-payout-proof')) {
                feedbackEl = document.getElementById('payout_proof_feedback_' + String(input.id || '').replace('payout_proof_', ''))
                    || (input.closest('label') ? input.closest('label').querySelector('.request-attachment-feedback') : null);
            } else if (input.id === '' && input.name === 'action_image') {
                var label = input.closest('label');
                feedbackEl = label
                    ? label.querySelector('.request-attachment-feedback')
                    : input.parentElement && input.parentElement.querySelector('.request-attachment-feedback');
            }
            bindRequestAttachmentOptimizer(input, feedbackEl);
        });
    })();

    // Requests legend sheet (mobile)
    (function () {
        var legendSheetBtn = document.getElementById('requestsLegendSheetBtn');
        var legendSheet = document.getElementById('requestsLegendSheet');
        if (!legendSheetBtn || !legendSheet) {
            return;
        }

        legendSheetBtn.setAttribute('data-sheet-open', 'requestsLegendSheet');
        legendSheetBtn.addEventListener('click', function () {
            legendSheetBtn.setAttribute('aria-expanded', 'true');
        });

        legendSheet.querySelectorAll('[data-sheet-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                legendSheetBtn.setAttribute('aria-expanded', 'false');
            });
        });
    })();

    // Legend toggle functionality
    (function() {
        var legendToggle = document.getElementById('legendToggle');
        var legendGrid = document.getElementById('legendGrid');
        if (!legendToggle || !legendGrid) {
            return;
        }

        var legendIcon = legendToggle.querySelector('i');
        var mobileLegendQuery = window.matchMedia('(max-width: 768px)');

        function setLegendCollapsed(collapsed) {
            legendGrid.classList.toggle('collapsed', collapsed);
            legendToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (legendIcon) {
                legendIcon.className = collapsed ? 'fa fa-chevron-down' : 'fa fa-chevron-up';
            }
        }

        function loadLegendState() {
            var stored = localStorage.getItem('legendCollapsed');
            var collapsed;
            if (stored === null) {
                collapsed = mobileLegendQuery.matches;
            } else {
                collapsed = stored !== 'false';
            }
            setLegendCollapsed(collapsed);
        }

        loadLegendState();

        legendToggle.addEventListener('click', function() {
            var collapsed = legendGrid.classList.toggle('collapsed');
            legendToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (legendIcon) {
                legendIcon.className = collapsed ? 'fa fa-chevron-down' : 'fa fa-chevron-up';
            }
            localStorage.setItem('legendCollapsed', collapsed ? 'true' : 'false');
        });

        if (typeof mobileLegendQuery.addEventListener === 'function') {
            mobileLegendQuery.addEventListener('change', loadLegendState);
        } else if (typeof mobileLegendQuery.addListener === 'function') {
            mobileLegendQuery.addListener(loadLegendState);
        }
    })();

    // Requests inbox: mobile filters + collapsible card actions
    (function () {
        var root = document.getElementById('requestsDashboardRoot');
        if (!root) {
            return;
        }

        var filterToggle = document.getElementById('requestsFilterToggle');
        var filterPanel = document.getElementById('requestsFiltersPanel');
        var mobileFiltersQuery = window.matchMedia('(max-width: 768px)');

        function isMobileRequestsInbox() {
            return mobileFiltersQuery.matches;
        }

        function setFiltersPanelOpen(isOpen) {
            if (!filterPanel) {
                return;
            }
            filterPanel.classList.toggle('is-open', isOpen);
            if (filterToggle) {
                filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
            if (isMobileRequestsInbox()) {
                localStorage.setItem('requestsFiltersOpen', isOpen ? 'true' : 'false');
            }
        }

        function syncRequestsFiltersPanel() {
            if (!filterPanel) {
                return;
            }
            if (!isMobileRequestsInbox()) {
                setFiltersPanelOpen(true);
                return;
            }
            var stored = localStorage.getItem('requestsFiltersOpen');
            var shouldOpen = stored === 'true';
            setFiltersPanelOpen(shouldOpen);
        }

        if (filterToggle && filterPanel) {
            syncRequestsFiltersPanel();

            filterToggle.addEventListener('click', function () {
                var willOpen = !filterPanel.classList.contains('is-open');
                setFiltersPanelOpen(willOpen);
            });

            if (typeof mobileFiltersQuery.addEventListener === 'function') {
                mobileFiltersQuery.addEventListener('change', syncRequestsFiltersPanel);
            } else if (typeof mobileFiltersQuery.addListener === 'function') {
                mobileFiltersQuery.addListener(syncRequestsFiltersPanel);
            }
        }

        root.addEventListener('click', function (event) {
            var toggleBtn = event.target.closest('.request-feed-actions-toggle');
            if (!toggleBtn || !root.contains(toggleBtn)) {
                return;
            }
            if (!isMobileRequestsInbox()) {
                return;
            }

            var item = toggleBtn.closest('.request-feed-item');
            if (!item) {
                return;
            }

            var willOpen = !item.classList.contains('is-actions-open');
            item.classList.toggle('is-actions-open', willOpen);
            toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    })();

    // Request chats: mobile context panel toggle
    (function () {
        var contextToggle = document.getElementById('requestChatsContextToggle');
        var contextCard = document.getElementById('requestChatsContextCard');
        if (!contextToggle || !contextCard) {
            return;
        }

        var mobileContextQuery = window.matchMedia('(max-width: 768px)');

        function isMobileContext() {
            return mobileContextQuery.matches;
        }

        function setContextOpen(isOpen) {
            contextCard.classList.toggle('is-open', isOpen);
            contextToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        function syncContextPanel() {
            if (!isMobileContext()) {
                contextCard.classList.add('is-open');
                contextToggle.setAttribute('aria-expanded', 'true');
                return;
            }
            setContextOpen(false);
        }

        syncContextPanel();

        contextToggle.addEventListener('click', function () {
            if (!isMobileContext()) {
                return;
            }
            setContextOpen(!contextCard.classList.contains('is-open'));
        });

        if (typeof mobileContextQuery.addEventListener === 'function') {
            mobileContextQuery.addEventListener('change', syncContextPanel);
        } else if (typeof mobileContextQuery.addListener === 'function') {
            mobileContextQuery.addListener(syncContextPanel);
        }
    })();

    // Footer accordion (mobile)
    (function () {
        var footer = document.querySelector('.site-footer');
        if (!footer) {
            return;
        }

        var columns = footer.querySelectorAll('[data-footer-accordion]');
        if (!columns.length) {
            return;
        }

        var mobileQuery = window.matchMedia('(max-width: 768px)');

        function setColumnState(column, collapsed) {
            var toggle = column.querySelector('.footer-column-toggle');
            var panel = column.querySelector('.footer-column-panel');
            var icon = toggle ? toggle.querySelector('i') : null;
            if (!toggle || !panel) {
                return;
            }

            panel.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (icon) {
                icon.className = collapsed ? 'fa fa-chevron-down' : 'fa fa-chevron-up';
            }
        }

        function syncFooterAccordion() {
            columns.forEach(function (column) {
                setColumnState(column, mobileQuery.matches);
            });
        }

        columns.forEach(function (column) {
            var toggle = column.querySelector('.footer-column-toggle');
            if (!toggle) {
                return;
            }

            toggle.addEventListener('click', function () {
                if (!mobileQuery.matches) {
                    return;
                }

                var panel = column.querySelector('.footer-column-panel');
                if (!panel) {
                    return;
                }

                setColumnState(column, !panel.classList.contains('is-collapsed'));
            });
        });

        syncFooterAccordion();

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', syncFooterAccordion);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(syncFooterAccordion);
        }
    })();

    document.querySelectorAll('form.affiliate-payout-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var submitBtn = form.querySelector('.js-affiliate-payout-submit');
            if (submitBtn && submitBtn.disabled) {
                event.preventDefault();
                return;
            }

            var fileInput = form.querySelector('input[name="payout_proof"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                event.preventDefault();
                window.alert('O comprovativo é obrigatório. Selecione a imagem do pagamento antes de confirmar.');
                if (fileInput && typeof fileInput.focus === 'function') {
                    fileInput.focus();
                }
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'A enviar...';
            }
        });
    });

    // Generic data-confirm: intercept form submit for buttons with data-confirm attribute.
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('button[data-confirm], input[data-confirm]');
        if (!btn) { return; }
        var message = btn.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    function formatAoa(value) {
        var amount = Math.max(0, Math.round(Number(value) || 0));
        return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' Kz';
    }

    function collapseFilterPanel(panel, toggleBtn) {
        if (!panel) {
            return;
        }
        panel.open = false;
        panel.classList.remove('is-open');
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.classList.remove('is-open');
        }
        var form = panel.closest('form');
        if (form) {
            var hidden = form.querySelector('input[name="filters_open"]');
            if (hidden) {
                hidden.remove();
            }
        }
    }

    function initFilterToolbars() {
        document.querySelectorAll('[data-filter-toggle]').forEach(function (toggleBtn) {
            var panelId = toggleBtn.getAttribute('aria-controls');
            var panel = panelId ? document.getElementById(panelId) : null;
            if (!panel || panel.tagName !== 'DETAILS') {
                return;
            }

            function syncState() {
                var isOpen = panel.open;
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                toggleBtn.classList.toggle('is-open', isOpen);
                panel.classList.toggle('is-open', isOpen);
            }

            toggleBtn.addEventListener('click', function () {
                panel.open = !panel.open;
                syncState();
            });

            panel.addEventListener('toggle', syncState);
            syncState();
        });

        document.querySelectorAll('form[data-filter-collapse-on-submit]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var panel = form.querySelector('.filter-advanced-panel');
                var toggleBtn = form.querySelector('[data-filter-toggle]');
                collapseFilterPanel(panel, toggleBtn);
            });
        });
    }

    initFilterToolbars();

    function initSubscriptionCheckout() {
        var form = document.getElementById('sub-checkout-form');
        if (!form) {
            return;
        }

        var durationSelect = document.getElementById('duration_months');
        var totalEl = document.querySelector('[data-sub-total]');
        var dueEl = document.querySelector('[data-sub-due]');
        var dueRows = document.querySelectorAll('.sub-checkout-due-row');
        var methodSelect = document.getElementById('payment_method_id');
        var channelSelect = document.getElementById('system_channel_id');
        var isPaid = form.getAttribute('data-is-paid') === '1';
        var monthlyPrice = parseFloat(form.getAttribute('data-monthly-price') || '0') || 0;

        function syncDuration(duration) {
            var months = parseInt(duration, 10);
            if (!months) {
                return;
            }

            var matchedRow = null;
            dueRows.forEach(function (row) {
                var rowDuration = parseInt(row.getAttribute('data-duration'), 10);
                var isMatch = rowDuration === months;
                row.classList.toggle('is-selected', isMatch);
                if (isMatch) {
                    matchedRow = row;
                }
            });

            if (matchedRow) {
                if (totalEl) {
                    var total = parseInt(matchedRow.getAttribute('data-total'), 10) || 0;
                    totalEl.textContent = isPaid ? formatAoa(total) : 'Grátis';
                }
                if (dueEl) {
                    dueEl.textContent = matchedRow.getAttribute('data-due') || '';
                }
            } else if (totalEl) {
                var computed = monthlyPrice * months;
                totalEl.textContent = isPaid ? formatAoa(computed) : 'Grátis';
            }
        }

        function syncChannels() {
            if (!methodSelect || !channelSelect) {
                return;
            }

            var methodId = methodSelect.value;
            var hasVisible = false;

            Array.prototype.forEach.call(channelSelect.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                var optionMethodId = option.getAttribute('data-method-id');
                var visible = !methodId || optionMethodId === methodId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) {
                    hasVisible = true;
                }
            });

            if (!hasVisible || !methodId) {
                channelSelect.value = '';
            } else {
                var current = channelSelect.options[channelSelect.selectedIndex];
                if (current && (current.hidden || current.disabled)) {
                    channelSelect.value = '';
                }
            }
        }

        if (durationSelect) {
            durationSelect.addEventListener('change', function () {
                syncDuration(durationSelect.value);
            });
            syncDuration(durationSelect.value);
        }

        dueRows.forEach(function (row) {
            row.addEventListener('click', function () {
                var duration = row.getAttribute('data-duration');
                if (!duration || !durationSelect) {
                    return;
                }
                durationSelect.value = duration;
                syncDuration(duration);
            });
        });

        if (methodSelect) {
            methodSelect.addEventListener('change', syncChannels);
            syncChannels();
        }
    }

    initSubscriptionCheckout();

    function initLoginForm() {
        var form = document.getElementById('loginForm');
        if (!form) {
            return;
        }

        var loginInput = document.getElementById('login');
        var passwordInput = document.getElementById('password');

        function isEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        function isPhone(value) {
            var digits = value.replace(/\D/g, '');
            return digits.length >= 9;
        }

        form.addEventListener('submit', function (event) {
            var loginValue = loginInput ? loginInput.value.trim() : '';
            var passwordValue = passwordInput ? passwordInput.value : '';
            var firstInvalid = null;

            if (loginInput) {
                loginInput.setCustomValidity('');
            }
            if (passwordInput) {
                passwordInput.setCustomValidity('');
            }

            if (!loginValue) {
                if (loginInput) {
                    loginInput.setCustomValidity('Indique o email ou telefone.');
                    firstInvalid = firstInvalid || loginInput;
                }
            } else if (!isEmail(loginValue) && !isPhone(loginValue)) {
                if (loginInput) {
                    loginInput.setCustomValidity('Use um email válido ou um telefone com pelo menos 9 dígitos.');
                    firstInvalid = firstInvalid || loginInput;
                }
            }

            if (!passwordValue) {
                if (passwordInput) {
                    passwordInput.setCustomValidity('Indique a senha.');
                    firstInvalid = firstInvalid || passwordInput;
                }
            } else if (passwordValue.length < 6) {
                if (passwordInput) {
                    passwordInput.setCustomValidity('A senha deve ter pelo menos 6 caracteres.');
                    firstInvalid = firstInvalid || passwordInput;
                }
            }

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.reportValidity();
            }
        });
    }

    initLoginForm();

    function initRegisterForm() {
        var form = document.getElementById('registerForm');
        if (!form) {
            return;
        }

        var userTypeSelect = document.getElementById('user_type');
        var nameInput = document.getElementById('name');
        var emailInput = document.getElementById('email');
        var phoneInput = document.getElementById('phone');
        var passwordInput = document.getElementById('password');
        var passwordConfirmInput = document.getElementById('password_confirm');
        var documentNumberInput = document.getElementById('document_number');
        var documentFileInput = document.getElementById('document_file');
        var acceptTermsInput = document.getElementById('accept_terms');
        var submitBtn = document.getElementById('registerSubmitBtn');

        function syncRegisterSubmitState() {
            if (!submitBtn || !acceptTermsInput) {
                return;
            }
            var accepted = acceptTermsInput.checked;
            submitBtn.disabled = !accepted;
            submitBtn.setAttribute('aria-disabled', accepted ? 'false' : 'true');
            var hintWait = form.querySelector('.auth-register-submit-hint--wait');
            var hintOk = form.querySelector('.auth-register-submit-hint--ok');
            if (hintWait) {
                hintWait.hidden = accepted;
            }
            if (hintOk) {
                hintOk.hidden = !accepted;
            }
        }

        if (acceptTermsInput) {
            acceptTermsInput.addEventListener('change', syncRegisterSubmitState);
            syncRegisterSubmitState();
        }

        form.querySelectorAll('.auth-agreement-link').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        });

        function isEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        function isPhone(value) {
            return value.replace(/\D/g, '').length >= 9;
        }

        form.addEventListener('submit', function (event) {
            var firstInvalid = null;
            if (isRegisterDocumentProcessing) {
                event.preventDefault();
                var documentFeedback = document.getElementById('document_file_feedback');
                if (documentFeedback) {
                    documentFeedback.textContent = 'Aguarde o processamento do documento terminar para concluir o envio.';
                    documentFeedback.classList.add('is-mismatch');
                }
                return;
            }

            [nameInput, emailInput, phoneInput, passwordInput, passwordConfirmInput, userTypeSelect].forEach(function (field) {
                if (field) {
                    field.setCustomValidity('');
                }
            });
            if (documentNumberInput) {
                documentNumberInput.setCustomValidity('');
            }
            if (documentFileInput) {
                documentFileInput.setCustomValidity('');
            }
            if (acceptTermsInput) {
                acceptTermsInput.setCustomValidity('');
            }

            if (nameInput && !nameInput.value.trim()) {
                nameInput.setCustomValidity('Indique o seu nome ou razão social.');
                firstInvalid = firstInvalid || nameInput;
            }

            if (userTypeSelect && !userTypeSelect.value) {
                userTypeSelect.setCustomValidity('Seleccione pessoa física ou jurídica.');
                firstInvalid = firstInvalid || userTypeSelect;
            }

            if (userTypeSelect && userTypeSelect.value && documentNumberInput) {
                var docRaw = documentNumberInput.value.trim();
                var docDigits = docRaw.replace(/\D/g, '');
                var docAlnum = docRaw.replace(/[^A-Za-z0-9]/g, '');
                if (!docRaw) {
                    documentNumberInput.setCustomValidity('Indique o número de BI ou NIF.');
                    firstInvalid = firstInvalid || documentNumberInput;
                } else if (userTypeSelect.value === 'pessoa_fisica' && (docAlnum.length < 5 || docAlnum.length > 24 || !/^[A-Za-z0-9\s.\-\/]+$/.test(docRaw))) {
                    documentNumberInput.setCustomValidity('Indique um BI ou NIF válido (letras e números).');
                    firstInvalid = firstInvalid || documentNumberInput;
                } else if (userTypeSelect.value === 'pessoa_juridica' && docDigits.length !== 10) {
                    documentNumberInput.setCustomValidity('O NIF deve ter exactamente 10 dígitos.');
                    firstInvalid = firstInvalid || documentNumberInput;
                }
            }

            if (documentFileInput && documentFileInput.required && !documentFileInput.files.length) {
                documentFileInput.setCustomValidity('Envie o documento de identificação.');
                firstInvalid = firstInvalid || documentFileInput;
            }

            if (documentFileInput && documentFileInput.files.length) {
                var documentFile = documentFileInput.files[0];
                if ((documentFile.size || 0) > UPLOAD_SERVER_MAX) {
                    documentFileInput.setCustomValidity('O documento deve ter no máximo ' + formatUploadBytes(UPLOAD_SERVER_MAX) + '.');
                    firstInvalid = firstInvalid || documentFileInput;
                }
            }

            if (emailInput) {
                var email = emailInput.value.trim();
                if (!email) {
                    emailInput.setCustomValidity('Indique o seu email.');
                    firstInvalid = firstInvalid || emailInput;
                } else if (!isEmail(email)) {
                    emailInput.setCustomValidity('Email inválido.');
                    firstInvalid = firstInvalid || emailInput;
                }
            }

            if (phoneInput) {
                var phone = phoneInput.value.trim();
                if (!phone) {
                    phoneInput.setCustomValidity('Indique o seu telefone.');
                    firstInvalid = firstInvalid || phoneInput;
                } else if (!isPhone(phone)) {
                    phoneInput.setCustomValidity('Use um telefone com pelo menos 9 dígitos.');
                    firstInvalid = firstInvalid || phoneInput;
                }
            }

            if (passwordInput) {
                if (!passwordInput.value) {
                    passwordInput.setCustomValidity('Crie uma senha.');
                    firstInvalid = firstInvalid || passwordInput;
                } else if (passwordInput.value.length < 6) {
                    passwordInput.setCustomValidity('A senha deve ter pelo menos 6 caracteres.');
                    firstInvalid = firstInvalid || passwordInput;
                }
            }

            if (passwordConfirmInput && passwordInput) {
                if (!passwordConfirmInput.value) {
                    passwordConfirmInput.setCustomValidity('Confirme a senha.');
                    firstInvalid = firstInvalid || passwordConfirmInput;
                } else if (passwordConfirmInput.value !== passwordInput.value) {
                    passwordConfirmInput.setCustomValidity('A confirmação de senha não coincide.');
                    firstInvalid = firstInvalid || passwordConfirmInput;
                }
            }

            if (acceptTermsInput && !acceptTermsInput.checked) {
                acceptTermsInput.setCustomValidity('Deve aceitar os Termos e Condições e a Política de Privacidade.');
                firstInvalid = firstInvalid || acceptTermsInput;
            }

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.reportValidity();
            }
        });
    }

    initRegisterForm();

})();