// Lightweight SPA navigation: preserve header/sidebar/footer, swap #spa-content

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
    };

    tabs.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const name = btn.dataset.tab;
        if (!name) return;
        showTab(name);
      });
    });

    // Initialize first active or first tab
    const active = tabs.find(t => t.classList.contains('active')) || tabs[0];
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
      setBusy(true);
      const html = await fetchPage(url);
      el.innerHTML = html;
  // Initialize any tabs rendered in the new content
  initTabsInSpaContent();
      if (push) history.pushState({ spa: true }, '', url);
      // re-run per-view initializers if needed
      document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url } }));
      focusMain();
    } catch (e) {
      console.error('SPA navigation failed:', e);
      window.location.href = url; // graceful fallback
    } finally {
      setBusy(false);
    }
  };

  const clickHandler = (e) => {
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

  const focusMain = () => {
    const el = content();
    if (!el) return;
    el.setAttribute('tabindex', '-1');
    el.focus({ preventScroll: true });
    // Gentle scroll to top for new content, but don't interfere with fixed elements
    // Only scroll if we're significantly below the content area
    const currentScroll = window.scrollY;
    const contentTop = el.getBoundingClientRect().top + currentScroll;
    const headerOffset = 80; // rough header height
    
    // Only scroll if user is way down the page (more than 200px below content)
    if (currentScroll > contentTop + 200) {
      window.scrollTo({ top: Math.max(0, contentTop - headerOffset), behavior: 'smooth' });
    }
  };

  const init = () => {
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
