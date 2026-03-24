// Lightweight SPA navigation: preserve header/sidebar/footer, swap #spa-content
//
// USAGE FOR VIEW-SPECIFIC JAVASCRIPT:
// If your view needs to initialize JavaScript (e.g., event listeners, search functionality),
// wrap your initialization in a function and register it with xsRegisterViewInit():
//
// Example:
//   function initMyView() {
//     const btn = document.getElementById('myButton');
//     if (!btn || btn.dataset.initialized === 'true') return;
//     btn.dataset.initialized = 'true';
//     btn.addEventListener('click', () => console.log('clicked'));
//   }
//   xsRegisterViewInit(initMyView);
//
// This ensures your init function runs on:
// 1. Initial page load
// 2. Every SPA navigation
//
// IMPORTANT: Always check for duplicate initialization using dataset flags!

// Global registry for view-specific initializer functions
// Views can register functions that should run on initial load AND after SPA navigation
window.xsViewInitializers = window.xsViewInitializers || [];
window.xsRegisterViewInit = function(initFn) {
  if (typeof initFn === 'function' && !window.xsViewInitializers.includes(initFn)) {
    window.xsViewInitializers.push(initFn);
    // Also run immediately if DOM is already ready
    if (document.readyState !== 'loading') {
      try { initFn(); } catch (e) { console.error('View init error:', e); }
    }
  }
};

import { getBaseUrl } from './utils/url-helpers.js';

const SPA = (() => {
  const content = () => document.getElementById('spa-content');
  const header = () => document.querySelector('.xs-header');
  const headerControlsSlot = () => document.getElementById('header-controls-slot');

  const normalizePathname = (url) => {
    try {
      return new URL(url, window.location.origin).pathname;
    } catch {
      return window.location.pathname;
    }
  };

  const getAppBasePath = () => {
    try {
      const baseUrl = getBaseUrl();
      return new URL(baseUrl, window.location.origin).pathname.replace(/\/+$/, '');
    } catch {
      return '';
    }
  };

  const getAppRelativePath = (url) => {
    const pathname = normalizePathname(url);
    const appBasePath = getAppBasePath();

    if (!appBasePath || appBasePath === '/') {
      return pathname;
    }

    if (pathname === appBasePath) {
      return '/';
    }

    if (pathname.startsWith(`${appBasePath}/`)) {
      return pathname.slice(appBasePath.length);
    }

    return pathname;
  };

  const isAppointmentControlsMarkup = (controlsHtml) => {
    if (!controlsHtml) return false;
    return (
      controlsHtml.includes('data-calendar-action=') ||
      controlsHtml.includes('scheduler-date-display') ||
      controlsHtml.includes('scheduler-view-selector')
    );
  };

  const shouldRenderHeaderControlsForPath = (targetPath, controlsHtml) => {
    if (!isAppointmentControlsMarkup(controlsHtml)) {
      return true;
    }

    const relativePath = getAppRelativePath(targetPath);
    return relativePath === '/appointments' || relativePath.startsWith('/appointments/');
  };

  const syncHeaderControls = (controlsHtml, hasControls) => {
    const headerEl = header();
    if (!headerEl) return;

    let slot = headerControlsSlot();

    if (hasControls) {
      if (!slot) {
        slot = document.createElement('div');
        slot.id = 'header-controls-slot';
        slot.className = 'xs-header-controls mt-3 pt-3 border-t border-gray-200 dark:border-gray-700';
        headerEl.appendChild(slot);
      }
      slot.innerHTML = controlsHtml || '';
      return;
    }

    if (slot) {
      slot.remove();
    }
  };

  // Initialize simple tab UI inside current SPA content if present
  const initTabsInSpaContent = () => {
    const el = content();
    if (!el) return;
    const tablist = el.querySelector('[role="tablist"]');
    if (!tablist || tablist.dataset.tabsInitialized === 'true') return;
    const tabs = Array.from(tablist.querySelectorAll('.tab-btn'));
    const panels = Array.from(el.querySelectorAll('.tab-panel'));
    if (!tabs.length || !panels.length) return;

    const showTab = (name) => {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
      panels.forEach(p => p.classList.toggle('hidden', p.id !== `panel-${name}`));
      // Update URL hash without scrolling
      if (history.replaceState) {
        history.replaceState(null, null, '#' + name);
      }
    };

    tabs.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const name = btn.dataset.tab;
        if (!name) return;
        showTab(name);
      });
    });

    // Check URL hash for tab to show, otherwise use first active or first tab
    const hashTab = window.location.hash ? window.location.hash.substring(1) : null;
    const tabFromHash = hashTab ? tabs.find(t => t.dataset.tab === hashTab) : null;
    const active = tabFromHash || tabs.find(t => t.classList.contains('active')) || tabs[0];
    if (active) showTab(active.dataset.tab);
    tablist.dataset.tabsInitialized = 'true';
  };
  const sameOrigin = (url) => {
    try { const u = new URL(url, window.location.origin); return u.origin === window.location.origin; } catch { return false; }
  };

  const shouldIntercept = (a) => {
    if (!a || a.target || a.hasAttribute('download')) return false;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;
    if (!sameOrigin(href)) return false;
    // Do not intercept FullCalendar internal navigation links
    if (a.hasAttribute('data-navlink')) return false;
    if (a.closest('.fc')) return false;
    // opt-out
    if (a.dataset?.noSpa === 'true' || a.classList.contains('no-spa')) return false;
    return true;
  };

  const fetchPage = async (url) => {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (res.status === 401) {
      // Session expired — full reload to trigger login redirect
      window.location.href = url;
      throw new Error('Session expired');
    }
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    // Try to extract only the content inside #spa-content if rendered server-side
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    const spaContentEl = doc.querySelector('#spa-content');
    
    if (!spaContentEl) {
      throw new Error('Server response missing #spa-content element');
    }
    
    // Get page title from multiple sources
    let pageTitle = spaContentEl.getAttribute('data-page-title');
    
    // Also check for child element with data-page-title (dashboard layout uses this)
    if (!pageTitle) {
      const titleEl = spaContentEl.querySelector('[data-page-title]');
      pageTitle = titleEl?.getAttribute('data-page-title');
    }
    const headerControlsEl = doc.querySelector('#header-controls-slot');

    // Return object with SPA content, page title, and optional header controls
    return {
      html: spaContentEl.innerHTML,
      pageTitle: pageTitle || null,
      headerControlsHtml: headerControlsEl?.innerHTML ?? null,
      hasHeaderControls: Boolean(headerControlsEl)
    };
  };

  const setBusy = (busy) => {
    const el = content();
    if (!el) return;
    el.setAttribute('aria-busy', busy ? 'true' : 'false');
    el.classList.toggle('opacity-50', busy);
  };

  const runViewInitializers = () => {
    if (window.xsViewInitializers) {
      window.xsViewInitializers.forEach(fn => {
        try { fn(); } catch (e) { console.error('View initializer error:', e); }
      });
    }
  };

  const navigate = async (url, push = true) => {
    const el = content();
    if (!el) return;
    try {
      const dest = new URL(url, window.location.href);
      const cur = new URL(window.location.href);
      const targetPath = dest.pathname;
      if (dest.pathname === cur.pathname && dest.search === cur.search) {
        if (push && dest.hash !== cur.hash) {
          history.pushState({ spa: true }, '', dest.href);
        }
        return;
      }
      setBusy(true);
      const { html, pageTitle, headerControlsHtml, hasHeaderControls } = await fetchPage(url);
      el.innerHTML = html;

      // Keep fixed header controls in sync with the destination view
      const shouldRenderHeaderControls = hasHeaderControls && shouldRenderHeaderControlsForPath(targetPath, headerControlsHtml);
      syncHeaderControls(headerControlsHtml, shouldRenderHeaderControls);
      
      // Update the data-page-title attribute on spa-content for header sync
      if (pageTitle) {
        el.setAttribute('data-page-title', pageTitle);
      } else {
        el.removeAttribute('data-page-title');
      }
      
      // Execute any inline or external scripts in the newly injected SPA content
      // This allows per-view scripts (e.g., user management role cards) to initialize after SPA navigation.
      const scripts = Array.from(el.querySelectorAll('script'));
      scripts.forEach(orig => {
        // Skip if already processed
        if (orig.dataset.spaExecuted === 'true') return;
        try {
          const s = document.createElement('script');
          // Copy attributes (src, type, etc.)
          for (const attr of orig.attributes) {
            s.setAttribute(attr.name, attr.value);
          }
          // Inline code
          if (!orig.src) {
            s.textContent = orig.textContent;
          }
          s.dataset.spaExecuted = 'true';
          orig.replaceWith(s);
        } catch (scriptErr) {
          console.error('SPA script execution error:', scriptErr);
        }
      });
      // Push state BEFORE tab init so window.location.hash reflects target URL
      if (push) history.pushState({ spa: true }, '', url);
      // Initialize any tabs rendered in the new content (reads hash)
      initTabsInSpaContent();
      // re-run per-view initializers if needed
      document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url } }));
      // Also trigger per-view init functions if registered
      runViewInitializers();
      // Always reset scroll position to top when switching views
      focusMain(true);
    } catch (e) {
      console.error('SPA navigation failed:', e);
      window.location.href = url; // graceful fallback
    } finally {
      setBusy(false);
    }
  };

  const submitHandler = (e) => {
    if (e.defaultPrevented) return;
    const form = e.target.closest('form');
    if (!form) return;
    if (form.dataset.noSpa === 'true' || form.classList.contains('no-spa')) {
      return;
    }
    if (form.method.toUpperCase() !== 'POST' && form.method.toUpperCase() !== 'PUT') return;
    if (!sameOrigin(form.action)) return;

    e.preventDefault();
    submitForm(form);
  };

  const submitForm = async (form) => {
    const el = content();
    if (!el) { console.error('[XS-SPA] submitForm: #spa-content not found!'); return; }

    try {
      setBusy(true);
      const formData = new FormData(form);
      const method = form.method.toUpperCase() || 'POST';
      const action = form.action || window.location.href;

      const res = await fetch(action, {
        method: method,
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      // Read body once for both OK and error responses
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (_) {
        data = null;
      }

      if (!res.ok) {
        // Use parsed JSON error details when available
        const errMsg = (data && (data.message || data.error)) || `HTTP ${res.status}`;
        const flashEvent = new CustomEvent('xs:flash', {
          detail: { type: 'error', message: errMsg }
        });
        document.dispatchEvent(flashEvent);

        // Inject validation errors into the form if present
        if (data && data.errors && typeof data.errors === 'object') {
          injectValidationErrors(form, data.errors);
        }
        return;
      }

      if (!data) {
        // Non-JSON OK response — treat as HTML and navigate
        await navigate(res.url || action, false);
        return;
      }

      // Handle JSON response from controller
      if (data.success) {
        // Navigate first, then show flash so it isn't wiped by content swap
        if (data.redirect) {
          await navigate(data.redirect);
        } else {
          await navigate(window.location.pathname + window.location.search);
        }

        // Show success message AFTER navigation so the toast survives
        if (data.message) {
          document.dispatchEvent(new CustomEvent('xs:flash', {
            detail: { type: 'success', message: data.message }
          }));
        }
      } else {
        // Handle error response
        document.dispatchEvent(new CustomEvent('xs:flash', {
          detail: { type: 'error', message: data.message || 'An error occurred' }
        }));

        // Inject validation errors into the form
        if (data.errors && typeof data.errors === 'object') {
          injectValidationErrors(form, data.errors);
        }
      }
    } catch (e) {
      console.error('Form submission failed:', e);
      document.dispatchEvent(new CustomEvent('xs:flash', {
        detail: { type: 'error', message: 'Form submission failed: ' + e.message }
      }));
    } finally {
      setBusy(false);
    }
  };

  const injectValidationErrors = (form, errors) => {
    // Clear any previous validation markers
    form.querySelectorAll('.spa-validation-error').forEach(el => el.remove());
    form.querySelectorAll('.border-red-500').forEach(el => {
      el.classList.remove('border-red-500', 'dark:border-red-400');
    });

    Object.entries(errors).forEach(([field, msgs]) => {
      const input = form.querySelector(`[name="${field}"]`);
      if (input) {
        input.classList.add('border-red-500', 'dark:border-red-400');
        const errorMsg = Array.isArray(msgs) ? msgs[0] : msgs;
        const errorEl = document.createElement('p');
        errorEl.className = 'spa-validation-error text-red-500 dark:text-red-400 text-sm mt-1';
        errorEl.textContent = errorMsg;
        input.parentElement?.appendChild(errorEl);
      }
    });
  };

  const clickHandler = (e) => {
    // Only left-clicks without modifier keys and not already handled
    if (e.defaultPrevented) return;
    if (e.button !== 0) return; // left click only
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a');
    if (!a) return;
    if (!shouldIntercept(a)) return;
    e.preventDefault();
    navigate(a.getAttribute('href'));
  };

  const popstateHandler = (e) => {
    if (e.state?.spa) {
      navigate(window.location.href, false);
    }
  };

  const focusMain = (resetScroll = false) => {
    const el = content();
    if (!el) return;
    el.setAttribute('tabindex', '-1');
    el.focus({ preventScroll: true });
    if (resetScroll) {
      // Reset to page top; header is sticky so 0 is appropriate
      window.scrollTo({ top: 0, behavior: 'auto' });
    }
  };

  const init = () => {
    // Disable browser automatic scroll restoration; SPA manages scroll explicitly
    if ('scrollRestoration' in history) {
      try { history.scrollRestoration = 'manual'; } catch (_) {}
    }

    // Bypass HTML5 client-side validation on all forms. When a <button type="submit">
    // is clicked, the browser checks validity BEFORE dispatching the submit event.
    // If any constraint fails (required, min, pattern, etc.) the submit event never
    // fires and our handlers are silently skipped. Setting noValidate in the capture
    // phase of the click ensures the subsequent native submit step skips validation.
    // Server-side validation handles all constraint checking.
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button[type="submit"]');
      if (!btn) return;
      const form = btn.closest('form');
      if (!form) return;
      if (form.dataset.noSpa === 'true' || form.classList.contains('no-spa')) {
        return;
      }
      form.noValidate = true;
    }, true);

    document.addEventListener('click', clickHandler);
    document.addEventListener('submit', submitHandler);
    window.addEventListener('popstate', popstateHandler);
    // On load, mark current history entry as SPA-aware
    history.replaceState({ spa: true }, '', window.location.href);
    // Initialize tabs for server-rendered initial content
    initTabsInSpaContent();
  };

  return { init, navigate, submitForm };
})();

// XSNotify — global toast API that bridges to xs:flash events
window.XSNotify = {
  toast({ type = 'info', title = '', message = '', autoClose = true, duration = 5000 } = {}) {
    // Combine title and message if both provided
    const text = message ? (title ? `${title}: ${message}` : message) : title;
    if (!text) return;
    document.dispatchEvent(new CustomEvent('xs:flash', {
      detail: { type, message: text, autoClose, duration }
    }));
  }
};

// Flash message handler - listen for xs:flash events
document.addEventListener('xs:flash', (e) => {
  const { type = 'info', message = '', autoClose = true, duration = 5000 } = e.detail;
  if (!message) return;

  // Find or create flash messages container
  let container = document.querySelector('[data-flash-messages]');
  if (!container) {
    container = document.createElement('div');
    container.setAttribute('data-flash-messages', '');
    container.className = 'fixed top-4 right-4 z-[9999] w-full max-w-sm space-y-2';
    document.body.appendChild(container);
  }

  // Create message element
  const alert = document.createElement('div');
  alert.className = `xs-alert xs-alert-${type} mb-4 p-4 rounded-lg border flex items-start gap-3`;
  
  const colors = {
    success: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200',
    error: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
    info: 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
    warning: 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200'
  };
  
  const icons = {
    success: 'check_circle',
    error: 'error',
    info: 'info',
    warning: 'warning'
  };

  alert.className += ' ' + (colors[type] || colors.info);

  // Escape message to prevent XSS
  const safeMessage = window.xsEscapeHtml ? window.xsEscapeHtml(message) : message;

  alert.innerHTML = `
    <span class="material-symbols-outlined flex-shrink-0 text-lg">${icons[type] || icons.info}</span>
    <span class="flex-1">${safeMessage}</span>
    <button type="button" onclick="this.parentElement.remove()" class="flex-shrink-0 text-lg opacity-50 hover:opacity-100">close</button>
  `;

  container.appendChild(alert);

  // Auto-dismiss if enabled
  if (autoClose) {
    setTimeout(() => {
      alert.classList.add('xs-fade-out');
      setTimeout(() => alert.remove(), 300);
    }, duration);
  }
});

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', SPA.init);
} else {
  SPA.init();
}

// Optional: expose for programmatic navigations
window.xsSPA = SPA;
