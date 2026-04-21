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
    if (!clockEl) {
        return;
    }

    const formatTime = () => {
        const timezone = window.appTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: timezone,
        });
    };

    formatTime();
    window.setInterval(formatTime, 1000);
}

function initLayoutBehavior() {
    const headerElement = document.querySelector('.xs-header');

    function syncHeaderHeight() {
        if (!headerElement) {
            return;
        }

        const height = headerElement.offsetHeight;
        if (height > 0) {
            document.documentElement.style.setProperty('--xs-header-height', `${height}px`);
        }
    }

    function syncHeaderTitle() {
        const headerTitleEl = document.getElementById('header-title');
        const spaContent = document.getElementById('spa-content');
        if (!headerTitleEl || !spaContent) {
            return;
        }

        let pageTitle = spaContent.getAttribute('data-page-title');

        if (!pageTitle) {
            const titleEl = spaContent.querySelector('[data-page-title]');
            pageTitle = titleEl?.getAttribute('data-page-title');
        }

        if (!pageTitle) {
            const heading = spaContent.querySelector('h1:not(#header-title), h2.text-xl, h2.text-2xl');
            if (heading) {
                pageTitle = heading.textContent.trim();
            }
        }

        if (pageTitle && pageTitle !== headerTitleEl.textContent.trim()) {
            headerTitleEl.textContent = pageTitle;
            document.title = `${pageTitle} • WebScheduler`;
        }
    }

    function syncGreetingVisibility() {
        const greetingEl = document.getElementById('header-greeting');
        const greetingSep = document.getElementById('header-greeting-sep');
        if (!greetingEl) {
            return;
        }

        const hasControls = document.getElementById('header-controls-slot');
        greetingEl.style.display = hasControls ? 'none' : '';
        if (greetingSep) {
            greetingSep.style.display = hasControls ? 'none' : '';
        }
    }

    syncHeaderHeight();
    syncHeaderTitle();
    syncGreetingVisibility();

    window.addEventListener('resize', syncHeaderHeight);

    if (window.ResizeObserver && headerElement) {
        const headerObserver = new ResizeObserver(() => syncHeaderHeight());
        headerObserver.observe(headerElement);
    }

    document.addEventListener('spa:navigated', () => {
        requestAnimationFrame(() => {
            syncHeaderTitle();
            syncGreetingVisibility();
            syncHeaderHeight();
        });
    });

    const userMenuBtn = document.getElementById('user-menu-btn');
    const userMenu = document.getElementById('user-menu');

    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            userMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', (event) => {
            if (!userMenu.contains(event.target) && !userMenuBtn.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && typeof window.closeSidebar === 'function') {
            window.closeSidebar();
        }
    });
}

onDomReady(() => {
    syncWindowConfigFromBody();
    initHeaderClock();
    initLayoutBehavior();
});
