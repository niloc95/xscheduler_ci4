/**
 * Scheduler side-panel drawer / bottom-sheet controller.
 *
 * At >=1300px the provider/availability panel (#scheduler-right-panel) is a static
 * right column and this controller is inert (the toggle is hidden via CSS). Below
 * 1300px the panel becomes an off-canvas surface — a right drawer on tablet, a
 * bottom sheet on phones (positioning handled in SCSS) — opened by
 * #scheduler-panel-toggle.
 *
 * Follows the UnifiedSidebar overlay conventions: `.is-open` class toggle, a
 * backdrop overlay, `body.xs-no-scroll`, Escape-to-close, and self-teardown on
 * SPA navigation. Idempotent per the SPA lifecycle contract.
 */

const DESKTOP_QUERY = '(min-width: 1300px)';

class SchedulerPanelDrawer {
    constructor() {
        this.toggle = document.getElementById('scheduler-panel-toggle');
        this.panel = document.getElementById('scheduler-right-panel');
        this.overlay = document.getElementById('scheduler-panel-overlay');
        this.closeBtn = document.getElementById('scheduler-panel-close');

        this.isOpen = false;
        this.onKeydown = this.onKeydown.bind(this);
        this.onResize = this.onResize.bind(this);
        this.onSpaNavigated = this.onSpaNavigated.bind(this);
    }

    init() {
        if (!this.toggle || !this.panel || !this.overlay) return;
        // Idempotency guard — re-init on SPA return should not double-bind.
        if (this.toggle.dataset.drawerBound === 'true') return;
        this.toggle.dataset.drawerBound = 'true';

        this.toggle.addEventListener('click', () => this.open());
        this.overlay.addEventListener('click', () => this.close());
        if (this.closeBtn) this.closeBtn.addEventListener('click', () => this.close());
        document.addEventListener('keydown', this.onKeydown);
        window.addEventListener('resize', this.onResize);
        document.addEventListener('spa:navigated', this.onSpaNavigated);
    }

    isDesktop() {
        return window.matchMedia(DESKTOP_QUERY).matches;
    }

    open() {
        if (this.isDesktop()) return; // panel is always visible on desktop
        this.isOpen = true;
        this.panel.classList.add('is-open');
        this.overlay.hidden = false;
        // Force reflow so the overlay transition runs from hidden → visible.
        void this.overlay.offsetWidth;
        this.overlay.classList.add('is-open');
        document.body.classList.add('xs-no-scroll');
        this.toggle.setAttribute('aria-expanded', 'true');
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.panel.classList.remove('is-open');
        this.overlay.classList.remove('is-open');
        this.overlay.hidden = true;
        document.body.classList.remove('xs-no-scroll');
        this.toggle.setAttribute('aria-expanded', 'false');
    }

    onKeydown(event) {
        if (event.key === 'Escape' && this.isOpen) {
            this.close();
        }
    }

    onResize() {
        // Growing into the desktop breakpoint dissolves the drawer — unlock scroll.
        if (this.isOpen && this.isDesktop()) {
            this.close();
        }
    }

    onSpaNavigated() {
        // Panel gone after navigating away from the scheduler → tear down.
        if (!document.getElementById('scheduler-right-panel')) {
            this.destroy();
        } else {
            this.close();
        }
    }

    destroy() {
        this.close();
        document.removeEventListener('keydown', this.onKeydown);
        window.removeEventListener('resize', this.onResize);
        document.removeEventListener('spa:navigated', this.onSpaNavigated);
        if (this.toggle) delete this.toggle.dataset.drawerBound;
    }
}

export function initSchedulerPanelDrawer() {
    const drawer = new SchedulerPanelDrawer();
    drawer.init();
    return drawer;
}
