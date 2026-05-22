import { onDomReady } from '../core/lifecycle.js';

function syncWindowConfigFromBody() {
    const body = document.body;
    if (!body) {
        return;
    }

    const baseUrl = body.dataset.baseUrl || '';
    const csrfToken = body.dataset.csrfToken || '';
    const businessName = body.dataset.businessName || 'WebScheduler';
    const defaultPhoneCountryCode = body.dataset.defaultPhoneCountryCode || '+27';

    if (baseUrl) {
        window.__BASE_URL__ = baseUrl;
    }
    if (csrfToken) {
        window.__CSRF_TOKEN__ = csrfToken;
    }

    window.__BUSINESS_NAME__ = businessName;
    window.__DEFAULT_PHONE_COUNTRY_CODE__ = defaultPhoneCountryCode;
}

function initHeaderClock() {
    const clockEl = document.getElementById('header-live-clock');
    const dateEl = document.getElementById('header-live-date');

    if (!clockEl && !dateEl) {
        return;
    }

    const formatHeaderDateTime = () => {
        const timezone = window.appTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
        const now = new Date();

        if (clockEl) {
            clockEl.textContent = now.toLocaleTimeString('en-GB', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone: timezone,
            });
        }

        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-GB', {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
                timeZone: timezone,
            });
        }
    };

    formatHeaderDateTime();
    window.setInterval(formatHeaderDateTime, 1000);
}

function initLayoutBehavior() {
    const headerElement = document.querySelector('.xs-header');
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userMenu = document.getElementById('user-menu');

    function syncHeaderHeight() {
        if (!headerElement) {
            return;
        }

        const height = headerElement.offsetHeight;
        if (height > 0) {
            document.documentElement.style.setProperty('--xs-header-height', `${height}px`);
        }
    }

    function getPageMetadata() {
        const spaContent = document.getElementById('spa-content');
        if (!spaContent) {
            return { pageTitle: '', pageSubtitle: '' };
        }

        let pageTitle = spaContent.getAttribute('data-page-title');
        let pageSubtitle = spaContent.getAttribute('data-page-subtitle');

        if (!pageTitle) {
            const titleEl = spaContent.querySelector('[data-page-title]');
            pageTitle = titleEl?.getAttribute('data-page-title');
        }

        if (!pageSubtitle) {
            const subtitleEl = spaContent.querySelector('[data-page-subtitle]');
            pageSubtitle = subtitleEl?.getAttribute('data-page-subtitle');
        }

        if (!pageTitle) {
            const heading = spaContent.querySelector('h1:not(#header-title), h2.text-xl, h2.text-2xl');
            if (heading) {
                pageTitle = heading.textContent.trim();
            }
        }

        return {
            pageTitle: pageTitle?.trim?.() || '',
            pageSubtitle: pageSubtitle?.trim?.() || '',
        };
    }

    function syncHeaderIdentity() {
        const headerTitleEl = document.getElementById('header-title');
        const headerSubtitleEl = document.getElementById('header-subtitle');
        if (!headerTitleEl) {
            return;
        }

        const { pageTitle, pageSubtitle } = getPageMetadata();

        if (pageTitle && pageTitle !== headerTitleEl.textContent.trim()) {
            headerTitleEl.textContent = pageTitle;
            document.title = `${pageTitle} • WebScheduler`;
        }

        if (headerSubtitleEl) {
            if (pageSubtitle) {
                headerSubtitleEl.textContent = pageSubtitle;
                headerSubtitleEl.hidden = false;
            } else {
                headerSubtitleEl.textContent = '';
                headerSubtitleEl.hidden = true;
            }
        }
    }

    function syncHeaderState() {
        const hasControls = document.getElementById('header-controls-slot');
        if (!headerElement) {
            return;
        }

        headerElement.classList.toggle('xs-header--with-controls', Boolean(hasControls));
    }

    function closeUserMenu() {
        if (!userMenu || !userMenuBtn) {
            return;
        }

        userMenu.classList.add('hidden');
        userMenuBtn.setAttribute('aria-expanded', 'false');
    }

    function toggleUserMenu(event) {
        if (!userMenu || !userMenuBtn) {
            return;
        }

        event.stopPropagation();
        const willOpen = userMenu.classList.contains('hidden');
        userMenu.classList.toggle('hidden', !willOpen);
        userMenuBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }

    function bindMediaQueryChange(query, handler) {
        if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', handler);
            return;
        }

        if (typeof query.addListener === 'function') {
            query.addListener(handler);
        }
    }

    // Defer offsetHeight read until after the browser has applied stylesheets and painted.
    // Reading offsetHeight at DOMContentLoaded can trigger a premature reflow warning when
    // CSS is still being applied. spa:navigated already uses rAF for the same reason.
    requestAnimationFrame(() => syncHeaderHeight());
    syncHeaderIdentity();
    syncHeaderState();

    window.addEventListener('resize', syncHeaderHeight);

    if (window.ResizeObserver && headerElement) {
        const headerObserver = new ResizeObserver(() => syncHeaderHeight());
        headerObserver.observe(headerElement);
    }

    document.addEventListener('spa:navigated', () => {
        requestAnimationFrame(() => {
            syncHeaderIdentity();
            syncHeaderState();
            syncHeaderHeight();
            closeUserMenu();
        });
    });

    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', toggleUserMenu);

        document.addEventListener('click', (event) => {
            if (!userMenu.contains(event.target) && !userMenuBtn.contains(event.target)) {
                closeUserMenu();
            }
        });

        [window.matchMedia('(max-width: 1023px)'), window.matchMedia('(max-width: 1279px)')].forEach((query) => {
            bindMediaQueryChange(query, () => closeUserMenu());
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeUserMenu();

            if (typeof window.closeSidebar === 'function') {
                window.closeSidebar();
            }
        }
    });
}

onDomReady(() => {
    syncWindowConfigFromBody();
    initHeaderClock();
    initLayoutBehavior();

    // Remove the load-time FOUC guards set by the inline blocking script in <head>:
    //   - xs-no-transition: re-enables transition-colors on <body> for user theme switches
    //   - backgroundColor / colorScheme inline styles: <body>'s dark:bg-gray-900 now owns the bg
    // Double rAF: first frame commits style recalculations, second confirms the browser has painted.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.documentElement.classList.remove('xs-no-transition');
            document.documentElement.style.backgroundColor = '';
            document.documentElement.style.colorScheme = '';
        });
    });
});
