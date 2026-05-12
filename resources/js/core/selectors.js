/**
 * Canonical DOM selector constants for the SPA layer.
 *
 * Every selector that spa.js, app.js, or unified-sidebar.js depends on
 * is declared here. Never hardcode these strings elsewhere — a rename
 * in a layout or component PHP file requires only a single change here.
 *
 * NOTE: Selectors marked [SCOPED] are always used as relative queries
 * (e.g. sidebar.querySelectorAll(SEL.NAV_LINKS)) — never document-level.
 */
export const SEL = {
    // ── Layout ────────────────────────────────────────────────────────
    SPA_CONTENT:            '#spa-content',
    HEADER_CONTROLS_SLOT:   '#header-controls-slot',
    HEADER_BAR:             '.xs-header',

    // ── Sidebar ───────────────────────────────────────────────────────
    SIDEBAR:                '#main-sidebar',
    SIDEBAR_OVERLAY:        '#sidebar-overlay',
    MENU_TOGGLE:            '#menu-toggle',
    SIDEBAR_CLOSE:          '#sidebar-close-btn',
    ROLE_SWITCHER:          '#roleSwitcher',
    NAV_LINKS:              '.nav-link',   // [SCOPED] always from sidebar root

    // ── Header UI ─────────────────────────────────────────────────────
    HEADER_TITLE:           '#header-title',
    HEADER_SUBTITLE:        '#header-subtitle',
    HEADER_PRIMARY_ACTION:  '#header-primary-action',
    HEADER_PRIMARY_ACTION_MOBILE: '#header-primary-action-mobile',

    // ── Search ────────────────────────────────────────────────────────
    SEARCH_DESKTOP:         '#global-search',
    SEARCH_RESULTS:         '#global-search-results',
    SEARCH_MOBILE:          '#global-search-mobile',

    // ── User menu ─────────────────────────────────────────────────────
    USER_MENU_BTN:          '#user-menu-btn',
    USER_MENU:              '#user-menu',

    // ── Scheduler ─────────────────────────────────────────────────────
    SCHEDULER_CONTAINER:    '#appointments-inline-calendar',

    // ── Flash messages ────────────────────────────────────────────────
    FLASH_CONTAINER:        '[data-flash-messages]',
    FLASH_CLOSE:            '[data-flash-close="true"]',
};
