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
  if (typeof initFn === 'function') {
    window.xsViewInitializers.push(initFn);
    // Also run immediately if DOM is already ready
    if (document.readyState !== 'loading') {
      try { initFn(); } catch (e) { console.error('View init error:', e); }
    }
  }
};

const SPA = (() => {
  const content = () => document.getElementById('spa-content');
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
    
    // Get page title from multiple sources
    let pageTitle = spaContentEl?.getAttribute('data-page-title');
    
    // Also check for child element with data-page-title (dashboard layout uses this)
    if (!pageTitle && spaContentEl) {
      const titleEl = spaContentEl.querySelector('[data-page-title]');
      pageTitle = titleEl?.getAttribute('data-page-title');
    }
    // Return object with both HTML and page title attribute
    return {
      html: spaContentEl?.innerHTML ?? text,
      pageTitle: pageTitle || null
    };
  };

  const setBusy = (busy) => {
    const el = content();
    if (!el) return;
    el.setAttribute('aria-busy', busy ? 'true' : 'false');
    el.classList.toggle('opacity-50', busy);
  };

  const navigate = async (url, push = true) => {
    const el = content();
    if (!el) return;
    try {
      // If navigating to the same path (ignoring hash), do nothing
      const dest = new URL(url, window.location.href);
      const cur = new URL(window.location.href);
      if (dest.pathname === cur.pathname && dest.search === cur.search) {
        if (push && dest.hash !== cur.hash) {
          history.pushState({ spa: true }, '', dest.href);
        }
        return;
      }
      setBusy(true);
      const { html, pageTitle } = await fetchPage(url);
      el.innerHTML = html;
      
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
  // Initialize any tabs rendered in the new content
  initTabsInSpaContent();
      if (push) history.pushState({ spa: true }, '', url);
      // re-run per-view initializers if needed
      document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url } }));
      // Also trigger per-view init functions if registered
      if (window.xsViewInitializers) {
        window.xsViewInitializers.forEach(fn => {
          try { fn(); } catch (e) { console.error('View initializer error:', e); }
        });
      }
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
    if (form.dataset.noSpa === 'true' || form.classList.contains('no-spa')) return;
    if (form.method.toUpperCase() !== 'POST' && form.method.toUpperCase() !== 'PUT') return;
    if (!sameOrigin(form.action)) return;

    e.preventDefault();
    submitForm(form);
  };

  const submitForm = async (form) => {
    const el = content();
    if (!el) return;

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

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      // Try to parse JSON response
      let data;
      const text = await res.text();
      try {
        data = JSON.parse(text);
      } catch (e) {
        // If not JSON, treat as HTML content and navigate
        el.innerHTML = text;
        document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url: action } }));
        if (window.xsViewInitializers) {
          window.xsViewInitializers.forEach(fn => {
            try { fn(); } catch (e) { console.error('View initializer error:', e); }
          });
        }
        focusMain(true);
        return;
      }

      // Handle JSON response from controller
      if (data.success) {
        // Show success message if provided
        if (data.message) {
          const flashEvent = new CustomEvent('xs:flash', {
            detail: { type: 'success', message: data.message }
          });
          document.dispatchEvent(flashEvent);
        }

        // Navigate to redirect URL if provided
        if (data.redirect) {
          await navigate(data.redirect);
        } else {
          // If no redirect, refresh current page
          await navigate(window.location.pathname + window.location.search);
        }
      } else {
        // Handle error response
        const flashEvent = new CustomEvent('xs:flash', {
          detail: { type: 'error', message: data.message || 'An error occurred' }
        });
        document.dispatchEvent(flashEvent);

        // If validation errors, inject them into the form
        if (data.errors && typeof data.errors === 'object') {
          Object.entries(data.errors).forEach(([field, errors]) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
              input.classList.add('border-red-500', 'dark:border-red-400');
              const errorMsg = Array.isArray(errors) ? errors[0] : errors;
              const errorEl = document.createElement('p');
              errorEl.className = 'text-red-500 dark:text-red-400 text-sm mt-1';
              errorEl.textContent = errorMsg;
              input.parentElement?.appendChild(errorEl);
            }
          });
        }
      }
    } catch (e) {
      console.error('Form submission failed:', e);
      const flashEvent = new CustomEvent('xs:flash', {
        detail: { type: 'error', message: 'Form submission failed: ' + e.message }
      });
      document.dispatchEvent(flashEvent);
    } finally {
      setBusy(false);
    }
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
    const text = message || title;
    if (!text) return;
    document.dispatchEvent(new CustomEvent('xs:flash', {
      detail: { type, message: text }
    }));
  }
};

// Flash message handler - listen for xs:flash events
document.addEventListener('xs:flash', (e) => {
  const { type = 'info', message = '' } = e.detail;
  if (!message) return;

  // Find or create flash messages container
  let container = document.querySelector('[data-flash-messages]');
  if (!container) {
    container = document.createElement('div');
    container.setAttribute('data-flash-messages', '');
    const spaContent = document.getElementById('spa-content');
    if (spaContent) {
      spaContent.insertAdjacentElement('afterbegin', container);
    } else {
      document.body.insertAdjacentElement('afterbegin', container);
    }
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
  alert.innerHTML = `
    <span class="material-symbols-outlined flex-shrink-0 text-lg">${icons[type] || icons.info}</span>
    <span class="flex-1">${message}</span>
    <button type="button" onclick="this.parentElement.remove()" class="flex-shrink-0 text-lg opacity-50 hover:opacity-100">close</button>
  `;

  container.appendChild(alert);

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    alert.style.transition = 'opacity 0.3s ease';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 300);
  }, 5000);
});

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', SPA.init);
} else {
  SPA.init();
}

// Optional: expose for programmatic navigations
window.xsSPA = SPA;
