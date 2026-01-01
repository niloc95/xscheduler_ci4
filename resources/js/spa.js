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
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    // Try to extract only the content inside #spa-content if rendered server-side
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    return doc.querySelector('#spa-content')?.innerHTML ?? text;
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
      const html = await fetchPage(url);
      el.innerHTML = html;
      // Execute any inline or external scripts in the newly injected SPA content
      // This allows per-view scripts (e.g., user management role cards) to initialize after SPA navigation.
      const scripts = Array.from(el.querySelectorAll('script'));
      scripts.forEach(orig => {
        // Skip if already processed
        if (orig.dataset.spaExecuted === 'true') return;
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
    window.addEventListener('popstate', popstateHandler);
    // On load, mark current history entry as SPA-aware
    history.replaceState({ spa: true }, '', window.location.href);
    // Initialize tabs for server-rendered initial content
    initTabsInSpaContent();
  };

  return { init, navigate };
})();

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', SPA.init);
} else {
  SPA.init();
}

// Optional: expose for programmatic navigations
window.xsSPA = SPA;
