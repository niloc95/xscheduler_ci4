// Clean FullCalendar Implementation (refactored to static imports for reliability)
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
// Supports Day, Week, Month views with Tailwind styling and dark mode
// Added: Robust error + empty state handling, loading indicator, fade-in, CSS imports

// Import FullCalendar core + plugin styles (Vite will bundle these)
// NOTE: FullCalendar CSS imports removed due to package export map restrictions in this environment.
// Minimal essential styling will be injected dynamically so build succeeds.
function injectCalendarBaseStyles() {
  if (document.getElementById('fc-lite-styles')) return;
  const css = `
    .fc { font-family: inherit; font-size: 0.8125rem; }
    .fc table { border-collapse: collapse; width: 100%; }
    .fc th { text-align: center; font-weight: 600; padding: 4px 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .fc-daygrid-day { border: 1px solid var(--fc-border, #e5e7eb); background: var(--fc-bg, #fff); }
    html.dark .fc-daygrid-day { --fc-bg: #1f2937; --fc-border: #374151; }
    .fc-daygrid-day-top { display: flex; justify-content: flex-end; padding: 2px 4px; font-size: 11px; }
    .fc-day-today { background: rgba(59,130,246,.15); }
    html.dark .fc-day-today { background: rgba(96,165,250,.15); }
    .fc .fc-timegrid-slot { height: 32px; }
    .fc-timegrid-slot, .fc-timegrid-col { border: 1px solid var(--fc-border, #e5e7eb); }
    html.dark .fc-timegrid-slot, html.dark .fc-timegrid-col { --fc-border: #374151; }
    .fc-timegrid-axis { background: var(--fc-axis-bg,#f9fafb); font-size: 10px; color: #6b7280; }
    html.dark .fc-timegrid-axis { --fc-axis-bg:#111827; color:#9ca3af; }
    .fc-xs-pill { line-height: 1.1; }
    .fc .fc-scrollgrid, .fc .fc-scrollgrid-section > td { border: 0; }
  `;
  const style = document.createElement('style');
  style.id = 'fc-lite-styles';
  style.textContent = css;
  document.head.appendChild(style);
}

console.log('🔄 Calendar clean script starting...');

// ===== Deep Diagnostic Mode =====
const CAL_DEBUG = (() => {
  try {
    return /caldebug=1/.test(window.location.search) || localStorage.getItem('xs:cal:debug') === '1';
  } catch (_) { return false; }
})();

function dbg(...args) {
  if (CAL_DEBUG) console.log('[CalDbg]', ...args);
}

// Allow toggle from console: localStorage.setItem('xs:cal:debug','1'); location.reload();

const diag = { steps: [], errors: [] };
function recordStep(name, data) {
  const entry = { ts: Date.now(), name, data };
  diag.steps.push(entry);
  dbg('STEP', name, data || '');
  if (CAL_DEBUG && window.__CAL_DBG_PANEL__) {
    const line = document.createElement('div');
    line.className = 'text-xs font-mono py-0.5';
    line.textContent = `${new Date().toISOString().split('T')[1].slice(0,8)} · ${name}`;
    window.__CAL_DBG_PANEL__.appendChild(line);
  }
}
function recordError(err, ctx) {
  const e = { ts: Date.now(), message: err?.message || String(err), stack: err?.stack, ctx };
  diag.errors.push(e);
  console.error('[CalDbg] ERROR', ctx, err);
  if (CAL_DEBUG) {
    try { window.__CAL_LAST_ERROR__ = e; } catch (_) {}
  }
}

function mountDebugPanel() {
  if (!CAL_DEBUG || window.__CAL_DBG_PANEL__) return;
  const host = document.createElement('div');
  host.style.cssText = 'position:fixed;bottom:0;right:0;z-index:9999;max-width:340px;max-height:40vh;overflow:auto;background:#111827cc;color:#f3f4f6;font:11px/1.3 monospace;padding:6px 8px;border:1px solid #374151;border-radius:6px;backdrop-filter:blur(4px);';
  host.innerHTML = '<div style="font-weight:600;margin-bottom:4px">Calendar Debug</div>';
  document.body.appendChild(host);
  window.__CAL_DBG_PANEL__ = host;
  recordStep('debug-panel-mounted');
}

if (CAL_DEBUG) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountDebugPanel, { once: true });
  } else {
    mountDebugPanel();
  }
}

// Application state
const state = {
  view: 'month',
  currentDate: new Date(),
  calendar: null,
  filters: {
    serviceId: '',
    providerId: ''
  },
  initialized: false,
  // prevent re-entrant view switching
  isSwitching: false,
  switchToken: 0,
  // business hours loaded from settings (defaults)
  businessHours: {
    start: '09:00:00',
    end: '17:00:00'
  }
};

// Utility functions
const $ = (id) => document.getElementById(id);
const apiBase = () => (window.__BASE_URL__ || '') + '/api';

// Status styling functions (preserved from original)
function getStatusClasses(status) {
  switch ((status || '').toLowerCase()) {
    case 'confirmed':
      return 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-300 border border-success-200 dark:border-success-700';
    case 'cancelled':
      return 'bg-error-100 text-error-800 dark:bg-error-900/30 dark:text-error-300 border border-error-200 dark:border-error-700';
    case 'booked':
    case 'pending':
      return 'bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-300 border border-warning-200 dark:border-warning-700';
    default:
      return 'bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-300 border border-primary-200 dark:border-primary-700';
  }
}

function getStatusDot(status) {
  switch ((status || '').toLowerCase()) {
    case 'confirmed': return 'bg-success-500 dark:bg-success-400';
    case 'cancelled': return 'bg-error-500 dark:bg-error-400';
    case 'booked':
    case 'pending': return 'bg-warning-500 dark:bg-warning-400';
    default: return 'bg-primary-500 dark:bg-primary-400';
  }
}

// API functions
async function fetchJSON(url, opts = {}) {
  const res = await fetch(url, opts);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const json = await res.json();
  if (json && typeof json === 'object') {
    if (json.error) throw new Error(json.error.message || 'API error');
    if ('data' in json) return json.data;
  }
  return json;
}

let firstLoadAttempted = false;
async function loadAppointments() {
  try {
    const view = state.calendar?.view;
    const start = view ? view.activeStart : new Date(state.currentDate.getFullYear(), state.currentDate.getMonth(), 1);
    const end = view ? view.activeEnd : new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + 1, 0);

    const params = new URLSearchParams({
      start: start.toISOString().split('T')[0],
      end: end.toISOString().split('T')[0]
    });

    // Request a large page size so we don't miss events in busy ranges
    params.set('length', '1000');

    if (state.filters.providerId) params.set('providerId', state.filters.providerId);
    if (state.filters.serviceId) params.set('serviceId', state.filters.serviceId);

    const url = `${apiBase()}/appointments?${params.toString()}`;
    console.log('[Calendar] Fetching appointments:', url);
    const appointments = await fetchJSON(url);
    console.log('[Calendar] Appointments loaded:', appointments?.length ?? 0);

    return appointments.map(apt => ({
      id: String(apt.id || ''),
      title: apt.title || 'Appointment',
      // Normalize to ISO-8601 (Safari-safe) by inserting 'T' between date and time
      start: typeof apt.start === 'string' ? apt.start.replace(' ', 'T') : apt.start,
      end: typeof apt.end === 'string' ? apt.end.replace(' ', 'T') : apt.end,
      extendedProps: {
        status: (apt.status || '').toLowerCase(),
        raw: apt
      }
    }));
  } catch (error) {
    console.error('Failed to load appointments:', error);
    if (!firstLoadAttempted) {
      // First load failed -> show stronger feedback (modal if available)
      try {
        window.XSNotify?.show({
          title: 'Appointments Load Failed',
          message: 'We could not load appointments from the server. The calendar will appear empty until this is resolved.',
          type: 'error',
          actions: [{ text: 'Dismiss' }]
        });
      } catch(_) {
        // Inject banner fallback
        const banner = document.createElement('div');
        banner.className = 'm-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm';
        banner.textContent = 'Error loading appointments. Please retry or check your connection.';
        document.getElementById('calendarRoot')?.prepend(banner);
      }
    }
    firstLoadAttempted = true;
    try {
      window.XSNotify?.toast({
        title: 'Calendar',
        message: 'Could not load appointments. Showing empty calendar.',
        type: 'warning',
        duration: 4000
      });
    } catch (_) {}
    return [];
  }
}

// Load business hours from settings API (prefix business.)
async function loadBusinessHours() {
  try {
    const url = `${apiBase()}/v1/settings?prefix=business.`;
    const data = await fetchJSON(url);

    const rawStart = (data['business.work_start'] || '09:00').toString();
    const rawEnd = (data['business.work_end'] || '17:00').toString();

    const normalize = (t) => {
      // Accept HH:MM or HH:MM:SS
      const m = t.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
      if (!m) return null;
      let hh = Math.max(0, Math.min(24, parseInt(m[1], 10)));
      let mm = Math.max(0, Math.min(59, parseInt(m[2], 10)));
      let ss = m[3] ? Math.max(0, Math.min(59, parseInt(m[3], 10))) : 0;
      // Special case: 24:00 -> allow for slotMaxTime
      if (hh === 24) { mm = 0; ss = 0; }
      return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`;
    };

    const start = normalize(rawStart) || '09:00:00';
    const end = normalize(rawEnd) || '17:00:00';

    // Ensure end is after start; if not, default to start + 1h
    const toSec = (s) => {
      const [h, m, sec] = s.split(':').map((x) => parseInt(x, 10) || 0);
      return h * 3600 + m * 60 + sec;
    };
    let ns = toSec(start);
    let ne = toSec(end);
    if (ne <= ns) {
      ne = Math.min(ns + 3600, 24 * 3600); // +1h, cap at 24:00
      const h = Math.floor(ne / 3600);
      const m = Math.floor((ne % 3600) / 60);
      const s = ne % 60;
      state.businessHours.end = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    } else {
      state.businessHours.end = end;
    }
    state.businessHours.start = start;
  } catch (e) {
    console.warn('Failed to load business hours, using defaults', e);
    // Keep defaults
  }
}

// Header title formatting
function formatTitle(date, viewType) {
  const d = new Date(date);
  const options = { timeZone: 'local' };

  switch (viewType) {
    case 'timeGridDay':
      return d.toLocaleDateString(undefined, {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      });
    case 'timeGridWeek':
      const weekStart = new Date(d);
      weekStart.setDate(d.getDate() - d.getDay());
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 6);

      const startDay = weekStart.getDate().toString().padStart(2, '0');
      const endDay = weekEnd.getDate().toString().padStart(2, '0');
      const month = weekEnd.toLocaleDateString(undefined, { month: 'short' });
      const year = weekEnd.getFullYear();

      // Calculate ISO week number
      const yearStart = new Date(year, 0, 1);
      const weekNum = Math.ceil((((weekEnd - yearStart) / 86400000) + yearStart.getDay() + 1) / 7);

      return `Week ${weekNum} (${startDay} – ${endDay} ${month} ${year})`;
    default: // dayGridMonth
      return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  }
}

// Update UI components
function updateTitle() {
  const titleEl = $('calTitle');
  if (!titleEl || !state.calendar) return;

  const title = formatTitle(state.currentDate, state.calendar.view.type);
  titleEl.textContent = title;
  titleEl.setAttribute('datetime', state.currentDate.toISOString().split('T')[0]);
}

function updateViewTabs() {
  const viewMap = {
    'dayGridMonth': 'month',
    'timeGridWeek': 'week',
    'timeGridDay': 'day'
  };

  const currentView = viewMap[state.calendar?.view?.type] || state.view;

  ['viewDay', 'viewWeek', 'viewMonth'].forEach(id => {
    const btn = $(id);
    if (!btn) return;

    const view = btn.getAttribute('data-view');
    const isActive = currentView === view;
    const base = 'btn btn-sm';
    const active = 'btn-primary';
    const inactive = 'btn-outline';
    btn.className = `${base} ${isActive ? active : inactive}`;
  });

  // Update mobile select
  const mobileSelect = $('viewSelectMobile');
  if (mobileSelect) {
    mobileSelect.value = currentView;
  }
}

// Event modal functions
function showEventModal(appointment) {
  const modal = $('eventModal');
  const content = $('modalContent');
  if (!modal || !content) return;

  const start = new Date(appointment.start);
  const end = new Date(appointment.end);

  content.innerHTML = `
    <div class="space-y-4">
      <div>
        <h4 class="font-medium text-gray-900 dark:text-gray-100">${appointment.title || 'Appointment'}</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          ${start.toLocaleDateString()} at ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} -
          ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
        </p>
      </div>
      <div class="flex space-x-3">
        <button id="rescheduleBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Reschedule
        </button>
        <button id="cancelBtn" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Cancel
        </button>
        <button id="editBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Edit
        </button>
      </div>
    </div>
  `;

  modal.classList.remove('hidden');

  // Add event listeners (simplified)
  $('rescheduleBtn')?.addEventListener('click', () => console.log('Reschedule:', appointment.id));
  $('cancelBtn')?.addEventListener('click', () => console.log('Cancel:', appointment.id));
  $('editBtn')?.addEventListener('click', () => console.log('Edit:', appointment.id));
}

function hideEventModal() {
  const modal = $('eventModal');
  if (modal) modal.classList.add('hidden');
}

// Calendar initialization
async function initCalendar() {
  const container = $('calendarRoot');
  if (!container) {
    console.error('❌ Calendar container #calendarRoot not found!');
    recordError(new Error('#calendarRoot missing'), 'initCalendar:container-missing');
    return;
  }
  recordStep('initCalendar:container-found', { visible: getComputedStyle(container).display, children: container.childElementCount });

  console.log('📅 Calendar container found, clearing content...');
  container.innerHTML = '';

  console.log('📅 Container visibility:', getComputedStyle(container).display, getComputedStyle(container).visibility);
  console.log('📅 Container dimensions:', container.offsetWidth, 'x', container.offsetHeight);

  try {
    console.log('� Loading FullCalendar modules...');

    // Use dynamic imports to avoid build issues
  // Static import path already performed at top; verify presence
  recordStep('modules-loaded', { haveCalendar: !!Calendar, dayGrid: !!dayGridPlugin, timeGrid: !!timeGridPlugin, interaction: !!interactionPlugin });

    console.log('✅ FullCalendar modules loaded successfully');
    console.log('📦 Calendar:', Calendar);
    console.log('📦 DayGrid Plugin:', dayGridPlugin);
    console.log('📦 TimeGrid Plugin:', timeGridPlugin);
    console.log('📦 Interaction Plugin:', interactionPlugin);

    // Create calendar
    console.log('🏗️ Creating calendar instance...');
  // Prepare fade-in to avoid flashing
  container.style.opacity = '0';

    let optionsObject = {
      plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
      initialView: state.view === 'week' ? 'timeGridWeek' :
                   state.view === 'day' ? 'timeGridDay' : 'dayGridMonth',
      initialDate: state.currentDate,
      headerToolbar: false, // We use custom header
      height: 'auto',
      contentHeight: 'auto',
      expandRows: false,
      handleWindowResize: false,
      stickyHeaderDates: false,
      navLinks: false,
      lazyFetching: true,

      // Day/Week view settings
      allDaySlot: true,
      slotMinTime: state.businessHours.start,
      slotMaxTime: state.businessHours.end,
      slotDuration: '00:30:00',
  nowIndicator: true,
      scrollTime: state.businessHours.start,
      businessHours: {
        // show business hours background on grid views
        daysOfWeek: [0,1,2,3,4,5,6],
        startTime: state.businessHours.start,
        endTime: state.businessHours.end,
      },

      // Time display format
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },

      // Month view settings
      fixedWeekCount: false,
      showNonCurrentDates: true,
  dayMaxEventRows: 3,
  progressiveEventRendering: true,
  eventOverlap: false,
  slotEventOverlap: false,
  forceEventDuration: true,

      // Events set hook will handle empty-state overlay manually (option removed for compatibility)
      eventsSet() {
        requestAnimationFrame(() => ensureEmptyStateOverlay(container));
      },

      // Interaction
      selectable: true,
      selectMirror: true,

      // Load events
      events: loadAppointments,

      // Loading indicator hook
      loading(isLoading) {
        try {
          container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
          if (isLoading) {
            if (!container.__spinner) {
              const sp = document.createElement('div');
              sp.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
              sp.innerHTML = '<div class="animate-spin h-6 w-6 border-2 border-gray-300 dark:border-gray-600 border-t-transparent rounded-full"></div>';
              container.style.position = container.style.position || 'relative';
              container.appendChild(sp);
              container.__spinner = sp;
            }
            container.__spinner.style.display = '';
          } else if (container.__spinner) {
            container.__spinner.style.display = 'none';
          }
        } catch (_) {}
      },

      // Event handlers
      datesSet(info) {
        console.log('📅 Dates set:', info.view.type, info.start, info.end);
        state.currentDate = new Date(info.start);
        updateTitle();
        updateViewTabs();
      },

      eventClick(info) {
        console.log('📅 Event clicked:', info.event.title);
        const appointment = info.event.extendedProps.raw;
        showEventModal(appointment);
      },

      dateClick(info) {
        console.log('📅 Date clicked:', info.dateStr);
      },

      // Custom event rendering
  eventContent(arg) {
        const raw = arg.event.extendedProps?.raw || {};
        const status = (raw.status || '').toLowerCase();
        const classes = getStatusClasses(status);
        const dot = getStatusDot(status);

        const start = new Date(raw.start);
        const time = isNaN(start) ? '' : start.toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit'
        });

        const wrapper = document.createElement('div');
        wrapper.className = `fc-xs-pill ${classes} flex items-center space-x-2 px-2 py-1 rounded-md text-xs border`;

        const parts = [
          `<div class="w-1.5 h-1.5 rounded-full ${dot}"></div>`,
          `<span class="font-medium truncate">${raw.title || arg.event.title || 'Appointment'}</span>`
        ];

        if (time && arg.view.type !== 'dayGridMonth') {
          parts.push(`<span class="text-[10px] opacity-80">${time}</span>`);
        }

        wrapper.innerHTML = parts.join('');
        return { domNodes: [wrapper] };
      }
    };

    // Diagnostics: list option keys
    recordStep('calendar-options-prepared', { keys: Object.keys(optionsObject) });

  state.calendar = new Calendar(container, optionsObject);
    recordStep('calendar-instance-created');

    console.log('🎨 Rendering calendar...');
  state.calendar.render();
  recordStep('calendar-render-called', { view: state.calendar.view?.type });
    console.log('✅ Calendar rendered successfully!');
    // Smooth fade-in after initial render
    requestAnimationFrame(() => {
      container.style.transition = 'opacity .25s ease';
      container.style.opacity = '1';
      const fcEl = container.querySelector('.fc');
      recordStep('post-render-check', { hasFC: !!fcEl, childCount: container.childElementCount, htmlSample: container.innerHTML.slice(0,120) });
      if (!fcEl) {
        // Attempt forced manual build (rarely required but diagnostic)
        try {
          recordStep('attempt-manual-rerender');
          state.calendar?.updateSize();
        } catch (e) {
          recordError(e, 'manual-rerender');
        }
        setTimeout(() => {
          const again = container.querySelector('.fc');
          if (!again) {
            const fallback = document.createElement('div');
            fallback.className = 'p-6 text-sm text-red-600 dark:text-red-300';
            fallback.innerHTML = '<div class="font-semibold mb-1">Calendar failed to mount</div><div>Diag log captured (no .fc). Static imports in use. Check bundle duplication or CSS hiding.</div>';
            container.appendChild(fallback);
            recordError(new Error('No .fc element after forced update'), 'post-render-retry');
          } else {
            recordStep('post-render-retry-success');
          }
        }, 30);
      }
    });
    console.log('📅 Calendar instance:', state.calendar);

  } catch (error) {
    console.error('❌ Failed to initialize calendar:', error);
    recordError(error, 'initCalendar:exception');
    container.innerHTML = `
      <div class="flex items-center justify-center h-64 text-red-500">
        <div class="text-center">
          <div class="text-lg font-semibold mb-2">Calendar Failed to Load</div>
          <div class="text-sm">${error.message}</div>
        </div>
      </div>
    `;
  }
}

// Create or update a manual empty state overlay when no events visible in current view
function ensureEmptyStateOverlay(container){
  try {
    if (!state.calendar) return;
    const viewEl = container.querySelector('.fc-view-harness, .fc-view');
    if (!viewEl) return;
    const events = container.querySelectorAll('.fc-event');
    let overlay = container.querySelector('#fc-empty-overlay');
    if (events.length === 0) {
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'fc-empty-overlay';
        overlay.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
        overlay.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">No appointments available for this period.</div>';
        container.style.position = container.style.position || 'relative';
        container.appendChild(overlay);
      }
      overlay.style.display = 'flex';
    } else if (overlay) {
      overlay.style.display = 'none';
    }
  } catch (e) {
    console.warn('Empty overlay check failed', e);
  }
}

// Navigation functions
function navigate(direction) {
  if (!state.calendar) return;

  if (direction > 0) {
    state.calendar.next();
  } else {
    state.calendar.prev();
  }
}

function goToToday() {
  if (!state.calendar) return;
  state.calendar.today();
}

function changeView(newView) {
  if (!state.calendar) return;
  if (state.isSwitching) return;

  const viewMap = {
    'day': 'timeGridDay',
    'week': 'timeGridWeek',
    'month': 'dayGridMonth'
  };

  const fcView = viewMap[newView];
  if (fcView && state.view !== newView) {
    console.log(`🔄 Switching to ${newView} view`);
    state.isSwitching = true;
    const token = ++state.switchToken;
    // Defer actual view change to next microtask to coalesce multiple clicks
    Promise.resolve().then(() => {
      if (token !== state.switchToken) return; // a newer switch superseded this
      try {
        state.view = newView;
  // persist view preference
  try { localStorage.setItem('xs:cal:view', state.view); } catch(_) {}
        state.calendar.changeView(fcView);
        // UI update right away to reflect active tab
        updateViewTabs();
      } finally {
        if (token === state.switchToken) state.isSwitching = false;
      }
    });
  }
}

// Filter handling
async function applyFilters() {
  const svc = $('filterService');
  const prov = $('filterProvider');
  const svcM = $('filterServiceMobile');
  const provM = $('filterProviderMobile');

  state.filters.serviceId = (svc?.value || svcM?.value || '').trim();
  state.filters.providerId = (prov?.value || provM?.value || '').trim();

  // Sync filter values
  if (svc && svcM) { svc.value = svcM.value = state.filters.serviceId; }
  if (prov && provM) { prov.value = provM.value = state.filters.providerId; }

  // Update data attributes
  const root = $('calendarRoot');
  if (root) {
    root.dataset.service = state.filters.serviceId;
    root.dataset.provider = state.filters.providerId;
  }

  // Save to localStorage
  try {
    localStorage.setItem('xs:cal:serviceId', state.filters.serviceId);
    localStorage.setItem('xs:cal:providerId', state.filters.providerId);
  } catch (_) {}

  // Refresh events
  if (state.calendar) {
    state.calendar.refetchEvents();
  }
}

// Event listeners
function setupEventListeners() {
  if (state.__eventsWired) return;
  state.__eventsWired = true;
  // Navigation
  $('calPrev')?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); navigate(-1); });
  $('calNext')?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); navigate(1); });
  $('calToday')?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); goToToday(); });

  // View switching
  $('viewDay')?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    console.log('Day view clicked');
    changeView('day');
  });
  $('viewWeek')?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    console.log('Week view clicked');
    changeView('week');
  });
  $('viewMonth')?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    console.log('Month view clicked');
    changeView('month');
  });

  // Mobile view select
  const viewSelectMobile = $('viewSelectMobile');
  if (viewSelectMobile) {
    viewSelectMobile.addEventListener('change', (e) => {
      e.preventDefault();
      const view = viewSelectMobile.value;
      console.log(`Mobile view changed to: ${view}`);
      if (['day', 'week', 'month'].includes(view)) {
        changeView(view);
      }
    });
  }

  // Filters
  const filterElements = [
    $('filterService'),
    $('filterProvider'),
    $('filterServiceMobile'),
    $('filterProviderMobile')
  ];

  filterElements.forEach(el => {
    if (el) {
      el.addEventListener('change', applyFilters);
    }
  });

  // Modal
  $('closeModal')?.addEventListener('click', hideEventModal);
  $('eventModal')?.addEventListener('click', (e) => {
    if (e.currentTarget === e.target) hideEventModal();
  });

  // Add appointment button
  $('calAdd')?.addEventListener('click', () => {
    console.log('Add new appointment');
  });
}

// Restore saved filters
function restoreFilters() {
  try {
    const savedService = localStorage.getItem('xs:cal:serviceId');
    const savedProvider = localStorage.getItem('xs:cal:providerId');
  const savedView = localStorage.getItem('xs:cal:view');

    if (savedService !== null) state.filters.serviceId = savedService;
    if (savedProvider !== null) state.filters.providerId = savedProvider;
  if (savedView && ['day','week','month'].includes(savedView)) state.view = savedView;

    // Apply to UI elements
    const svc = $('filterService');
    const prov = $('filterProvider');
    const svcM = $('filterServiceMobile');
    const provM = $('filterProviderMobile');

    if (svc) svc.value = state.filters.serviceId;
    if (prov) prov.value = state.filters.providerId;
    if (svcM) svcM.value = state.filters.serviceId;
    if (provM) provM.value = state.filters.providerId;
  } catch (_) {}
}

// Initialize application
async function init() {
  try {
    // Prevent multiple initializations
    if (state.initialized) {
      console.log('Calendar already initialized, skipping...');
  recordStep('init:skip-already-initialized');
      return;
    }

    const calendarRoot = $('calendarRoot');
    if (!calendarRoot) {
      console.error('❌ Calendar container #calendarRoot not found in DOM!');
      recordError(new Error('#calendarRoot missing at init()'), 'init:container-missing');
      return;
    }
    recordStep('init:container-present');

    // Dropdown diagnostics
    const filterService = $('filterService');
    const filterProvider = $('filterProvider');
    const filterServiceMobile = $('filterServiceMobile');
    const filterProviderMobile = $('filterProviderMobile');
    recordStep('init:dropdown-presence', {
      filterService: !!filterService,
      filterProvider: !!filterProvider,
      filterServiceMobile: !!filterServiceMobile,
      filterProviderMobile: !!filterProviderMobile
    });

  console.log('🔄 Initializing calendar...');
  injectCalendarBaseStyles();
    state.initialized = true;

    // Restore saved state
  restoreFilters();
  recordStep('filters-restored', { filters: { ...state.filters } });

    // Setup event listeners
  setupEventListeners();
  recordStep('event-listeners-wired');

  // Load business hours before initializing calendar
  await loadBusinessHours();
    recordStep('business-hours-loaded', { ...state.businessHours });

    // Initialize calendar
  await initCalendar();
  recordStep('initCalendar-finished');

  } catch (error) {
    console.error('❌ Calendar initialization failed:', error);
  recordError(error, 'init:exception');
    const container = $('calendarRoot');
    if (container) {
      container.innerHTML = `
        <div class="flex items-center justify-center h-64 text-red-500">
          <div class="text-center">
            <div class="text-lg font-semibold mb-2">Calendar Initialization Failed</div>
            <div class="text-sm">${error.message}</div>
          </div>
        </div>
      `;
    }
  }
}

// SPA navigation handler
function handleSPANavigation() {
  const isScheduler = window.location.pathname.includes('/schedule') || window.location.pathname.includes('/scheduler');
  if (isScheduler) {
    // SPA replaced content; rebuild once after DOM settles
    if (state.__spaNavPending) return;
    state.__spaNavPending = true;
    setTimeout(() => {
      state.__spaNavPending = false;
      if (state.calendar) {
        try { state.calendar.destroy(); } catch (_) {}
        state.calendar = null;
      }
      state.initialized = false;
  state.__eventsWired = false; // allow re-binding listeners for new DOM
      init();
    }, 0);
    return;
  }

  // Navigating away: destroy calendar and reset state
  if (state.calendar) {
    try { state.calendar.destroy(); } catch (_) {}
    state.calendar = null;
  }
  state.initialized = false;
  state.__eventsWired = false;
}

// Bootstrap - SINGLE INITIALIZATION ONLY
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}

// SPA support - ensure single handler
if (!window.__xsCalendarSpaBound) {
  document.addEventListener('spa:navigated', handleSPANavigation);
  window.__xsCalendarSpaBound = true;
}

// Export for debugging
window.calendarState = state;
// Expose diagnostics
window.__CAL_DIAG__ = diag;

// Force style baseline if missing (some builds may purge our minimal styles)
function ensureBaseStructureStyles() {
  if (document.getElementById('fc-structure-fix')) return;
  const style = document.createElement('style');
  style.id = 'fc-structure-fix';
  style.textContent = `
    #calendarRoot { position: relative; }
    #calendarRoot .fc { font-family: inherit; font-size: 12px; }
    #calendarRoot .fc .fc-daygrid-day { min-height: 100px; }
    #calendarRoot .fc .fc-scrollgrid { border: 0 !important; }
  `;
  document.head.appendChild(style);
  recordStep('structure-styles-injected');
}
ensureBaseStructureStyles();

// ---- Visibility Watchdog (prevents calendar disappearing after view switches) ----
function startCalendarWatchdog() {
  const root = document.getElementById('calendarRoot');
  if (!root || root.__watchdog) return;
  const observer = new MutationObserver(() => {
    if (!root._fc) return; // calendar destroyed intentionally
    const style = getComputedStyle(root);
    const hidden = style.display === 'none' || style.visibility === 'hidden';
    if (hidden || root.offsetHeight === 0) {
      console.warn('[Calendar] Detected hidden/collapsed container – forcing re-render');
      try {
        root.style.display = 'block';
        root.style.visibility = 'visible';
        root.style.minHeight = '600px';
        // FullCalendar sometimes needs a rerender when container was 0 height
        state.calendar?.updateSize();
      } catch (_) {}
    }
  });
  observer.observe(root, { attributes: true, attributeFilter: ['style', 'class'] });
  root.__watchdog = observer;
}

// Defer watchdog start until next tick after initial render
setTimeout(startCalendarWatchdog, 500);
