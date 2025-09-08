// Modern TailwindCSS Calendar with Material Design
// Features: Day, Week, Month views | API Integration | Material Icons | Responsive

// FullCalendar (All views integration)
import { Calendar as FullCalendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import '../css/fullcalendar-overrides.css';

// Date utilities
const dayjs = (d) => ({
  d: d ? new Date(d) : new Date(),
  startOf(unit) {
    const x = new Date(this.d);
    if (unit === 'month') { x.setDate(1); x.setHours(0,0,0,0); }
    if (unit === 'week') {
      const wd = x.getDay();
      x.setDate(x.getDate() - wd);
      x.setHours(0,0,0,0);
    }
    if (unit === 'day') { x.setHours(0,0,0,0); }
    return dayjs(x);
  },
  endOf(unit) {
    const x = new Date(this.d);
    if (unit === 'month') { x.setMonth(x.getMonth()+1,0); x.setHours(23,59,59,999); }
    if (unit === 'week') { const s = this.startOf('week').d; x.setTime(s.getTime() + 6*86400000 + 86399999); }
    if (unit === 'day') { x.setHours(23,59,59,999); }
    return dayjs(x);
  },
  add(n, unit) {
    const x = new Date(this.d);
    if (unit === 'month') { x.setMonth(x.getMonth()+n); }
    if (unit === 'week') { x.setDate(x.getDate()+n*7); }
    if (unit === 'day') { x.setDate(x.getDate()+n); }
    return dayjs(x);
  },
  format(fmt) {
    const pad = (v) => String(v).padStart(2,'0');
    const y = this.d.getFullYear();
    const m = pad(this.d.getMonth()+1);
    const d = pad(this.d.getDate());
    if (fmt === 'YYYY-MM-DD') return `${y}-${m}-${d}`;
    if (fmt === 'MMM YYYY') return this.d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
    if (fmt === 'MMMM YYYY') return this.d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    return this.d.toISOString();
  },
  isSame(other, unit) {
    if (unit === 'day') return this.format('YYYY-MM-DD') === dayjs(other).format('YYYY-MM-DD');
    return false;
  }
});

// Application state
const state = {
  view: 'month',
  cursor: new Date(),
  appointments: [],
  today: new Date(),
  filters: {
    serviceId: '',
    providerId: ''
  }
};

// FullCalendar instance (all views)
let __fc = null;
let __refreshing = false;
let __refreshQueued = false;

// Utility functions
const $ = (id) => document.getElementById(id);
const apiBase = () => (window.__BASE_URL__ || '') + '/api';

function parseDate(value) {
  if (!value) return new Date(NaN);
  const s = typeof value === 'string' ? value.replace(' ', 'T') : value;
  return new Date(s);
}

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

// Status styling functions
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
async function loadAppointments() {
  try {
    let start, end;
    if (state.view === 'month') {
      start = dayjs(state.cursor).startOf('month').format('YYYY-MM-DD');
      end = dayjs(state.cursor).endOf('month').format('YYYY-MM-DD');
    } else if (state.view === 'week') {
      start = dayjs(state.cursor).startOf('week').format('YYYY-MM-DD');
      end = dayjs(state.cursor).endOf('week').format('YYYY-MM-DD');
    } else {
      start = dayjs(state.cursor).startOf('day').format('YYYY-MM-DD');
      end = dayjs(state.cursor).endOf('day').format('YYYY-MM-DD');
    }

    const params = new URLSearchParams({ start, end });
    if (state.filters.providerId) params.set('providerId', state.filters.providerId);
    if (state.filters.serviceId) params.set('serviceId', state.filters.serviceId);
    const url = `${apiBase()}/appointments?${params.toString()}`;
    state.appointments = await fetchJSON(url);
  } catch (error) {
    console.error('Failed to load appointments:', error);
    state.appointments = [];
  }
}

// View helpers
function formatHeaderTitle() {
  const d = dayjs(state.cursor);
  const pad2 = (n) => String(n).padStart(2, '0');
  const weekday = d.d.toLocaleDateString(undefined, { weekday: 'short' });
  const day = pad2(d.d.getDate());
  const monthShort = d.d.toLocaleDateString(undefined, { month: 'short' });
  const monthLong = d.d.toLocaleDateString(undefined, { month: 'long' });
  const year = d.d.getFullYear();

  if (state.view === 'day') {
    return `${weekday}, ${day} ${monthShort} ${year}`;
  }
  if (state.view === 'week') {
    const start = d.startOf('week');
    const end = d.endOf('week');
    const isoWeek = (date) => {
      const tmp = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
      const dayNum = tmp.getUTCDay() || 7;
      tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
      const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
      const weekNo = Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7);
      return weekNo;
    };
    const wk = isoWeek(d.d);
    const startDay = String(start.d.getDate()).padStart(2, '0');
    const endDay = String(end.d.getDate()).padStart(2, '0');
    const monthShortEnd = end.d.toLocaleDateString(undefined, { month: 'short' });
    const yr = d.d.getFullYear();
    return `Week ${wk} (${startDay} – ${endDay} ${monthShortEnd} ${yr})`;
  }
  return `${monthLong} ${year}`;
}

function renderQuickSelect() {
  const hosts = Array.from(document.querySelectorAll('[data-quick-select]'));
  if (!hosts.length) return;

  const d = dayjs(state.cursor);
  const chip = (label, selected = false) => `
    <button class="inline-flex items-center whitespace-nowrap px-3 py-1.5 mr-2 mb-2 rounded-full text-sm border transition-colors ${selected ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'}" data-chip="1">
      ${label}
    </button>`;

  let html = '';
  if (state.view === 'day') {
    const start = d.add(-7, 'day');
    const days = Array.from({ length: 21 }, (_, i) => dayjs(start.d.getTime() + i * 86400000));
    html = days.map(x => {
      const label = `${x.d.toLocaleDateString(undefined, { weekday: 'short' })} ${String(x.d.getDate()).padStart(2, '0')}`;
      const sel = x.isSame(d.d, 'day');
      return `<span data-date="${x.format('YYYY-MM-DD')}" class="qs-item">${chip(label, sel)}</span>`;
    }).join('');
  } else if (state.view === 'week') {
    const center = d.startOf('week');
    const start = center.add(-4, 'week');
    const weeks = Array.from({ length: 12 }, (_, i) => start.add(i, 'week'));
    const isoWeek = (date) => {
      const tmp = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
      const dayNum = tmp.getUTCDay() || 7;
      tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
      const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
      return Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7);
    };
    html = weeks.map(x => {
      const wk = isoWeek(x.d);
      const label = `Week ${wk}`;
      const sel = x.isSame(center.d, 'day');
      return `<span data-week-start="${x.format('YYYY-MM-DD')}" class="qs-item">${chip(label, sel)}</span>`;
    }).join('');
  } else if (state.view === 'month') {
    const year = d.d.getFullYear();
    const startYear = year - 2;
    const years = Array.from({ length: 5 }, (_, i) => startYear + i);
    html = years.map(y => {
      const sel = y === year;
      return `<span data-year="${y}" class="qs-item">${chip(String(y), sel)}</span>`;
    }).join('');
  }

  hosts.forEach(host => {
    host.innerHTML = html;
    host.querySelectorAll('.qs-item').forEach(item => {
      item.addEventListener('click', (e) => {
        const el = e.currentTarget;
        const ymd = el.getAttribute('data-date');
        const weekStart = el.getAttribute('data-week-start');
        const yr = el.getAttribute('data-year');
        if (ymd) {
          state.cursor = new Date(ymd);
        } else if (weekStart) {
          state.cursor = new Date(weekStart);
        } else if (yr) {
          const cur = dayjs(state.cursor);
          state.cursor = new Date(Number(yr), cur.d.getMonth(), Math.min(cur.d.getDate(), 28));
        }
        refresh();
      });
    });
  });
}

// Title/tabs
function updateTitle() {
  const titleEl = $('calTitle');
  if (!titleEl) return;
  const txt = formatHeaderTitle();
  titleEl.textContent = txt;
  if (state.view === 'month') {
    titleEl.setAttribute('datetime', dayjs(state.cursor).format('YYYY-MM'));
  } else {
    titleEl.setAttribute('datetime', dayjs(state.cursor).format('YYYY-MM-DD'));
  }
}

function updateViewTabs() {
  ['viewDay', 'viewWeek', 'viewMonth'].forEach(id => {
    const btn = $(id);
    if (!btn) return;
    const view = btn.getAttribute('data-view') || id.replace('view', '').toLowerCase();
    const isActive = state.view === view;
    const base = 'btn btn-sm';
    const active = 'btn-primary';
    const inactive = 'btn-outline';
    btn.className = `${base} ${isActive ? active : inactive}`;
  });
}

// FC helpers
function toFullCalendarEvents(appts) {
  return (appts || []).map(a => ({
    id: String(a.id ?? ''),
    title: a.title || 'Appointment',
    start: a.start,
    end: a.end,
    extendedProps: { status: (a.status || '').toLowerCase(), raw: a },
  }));
}

function ensureFullCalendar(root) {
  let mount = document.getElementById('fcMonth');
  // Ensure a mount element exists and is inside root without blowing away existing FC DOM
  if (!mount) {
    mount = document.createElement('div');
    mount.id = 'fcMonth';
  mount.className = 'fc';
    root.innerHTML = '';
    root.appendChild(mount);
  } else if (!root.contains(mount)) {
    // Move existing mount under root if it's not there already
    root.innerHTML = '';
    root.appendChild(mount);
  }

  if (!__fc) {
    __fc = new FullCalendar(mount, {
      plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
      initialView: state.view === 'week' ? 'timeGridWeek' : (state.view === 'day' ? 'timeGridDay' : 'dayGridMonth'),
      headerToolbar: false,
      firstDay: 0,
      fixedWeekCount: false,
      showNonCurrentDates: true,
      dayMaxEventRows: 3,
      selectable: true,
      selectMirror: true,
      initialDate: state.cursor,
  height: 'auto',
  contentHeight: 'auto',
  handleWindowResize: true,
      allDaySlot: true,
      slotMinTime: '00:00:00',
      slotMaxTime: '24:00:00',
  slotDuration: '00:30:00',
      expandRows: true,
      nowIndicator: true,
      events: toFullCalendarEvents(state.appointments),
      datesSet(info) {
        // Sync state.view and state.cursor with the current FullCalendar view
        const vType = info.view?.type || 'dayGridMonth';
        state.view = vType === 'timeGridWeek' ? 'week' : (vType === 'timeGridDay' ? 'day' : 'month');
        // Anchor cursor to start of period for header formatting/filters
        const curStart = info.view?.currentStart || info.start;
        if (state.view === 'month') {
          state.cursor = new Date(curStart.getFullYear(), curStart.getMonth(), 1);
        } else {
          state.cursor = new Date(curStart);
        }
        updateTitle();
        renderQuickSelect();
        updateViewTabs();
      },
      eventClick: async (info) => {
        const id = info?.event?.id;
        if (!id) return;
        try {
          const appointment = await fetchJSON(`${apiBase()}/appointments/${id}`);
          showEventModal(appointment);
        } catch (err) {
          console.error('Failed to load appointment', err);
        }
      },
      dateClick: (info) => {
        // Placeholder for add flow
      },
      eventContent(arg) {
        const raw = arg.event.extendedProps?.raw || {};
        const status = (raw.status || '').toLowerCase();
        const classes = getStatusClasses(status);
        const dot = getStatusDot(status);
        const start = parseDate(raw.start);
        const time = isNaN(start) ? '' : start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const wrapper = document.createElement('div');
        wrapper.className = `fc-xs-pill ${classes} flex items-center space-x-2 px-2 py-1 rounded-md text-xs border`;
        const parts = [
          `<div class="w-1.5 h-1.5 rounded-full ${dot}"></div>`,
          `<span class="font-medium truncate">${raw.title || arg.event.title || 'Appointment'}</span>`
        ];
        if (time) parts.push(`<span class="text-[10px] opacity-80">${time}</span>`);
        wrapper.innerHTML = parts.join('');
        return { domNodes: [wrapper] };
      },
    });
    __fc.render();
  } else {
    // Ensure the correct view
    const targetView = state.view === 'week' ? 'timeGridWeek' : (state.view === 'day' ? 'timeGridDay' : 'dayGridMonth');
    if (__fc.view?.type !== targetView) {
      __fc.changeView(targetView, state.cursor);
    } else {
      __fc.gotoDate(state.cursor);
    }
    __fc.removeAllEvents();
    __fc.addEventSource(toFullCalendarEvents(state.appointments));
  }
}

function renderMonthView(root) { ensureFullCalendar(root); }
function renderWeekView(root) { ensureFullCalendar(root); }
function renderDayView(root) { ensureFullCalendar(root); }

// Main render
function render() {
  const root = $('calendarRoot');
  if (!root) return;

  // Show a loader only until FullCalendar is mounted
  const showLoader = !__fc;
  if (showLoader) {
    root.innerHTML = '<div class="flex items-center justify-center h-64"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
  }

  updateTitle();
  renderQuickSelect();
  updateViewTabs();

  // Always ensure/mount FullCalendar for current view
  if (state.view === 'month') {
    renderMonthView(root);
  } else if (state.view === 'week') {
    renderWeekView(root);
  } else {
    renderDayView(root);
  }
  // When FC is mounted, ensureFullCalendar manages DOM; render() should only update UI chrome
}

// Event handling
function showEventModal(appointment) {
  const modal = $('eventModal');
  const content = $('modalContent');
  if (!modal || !content) return;

  const start = parseDate(appointment.start);
  const end = parseDate(appointment.end);

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

  $('rescheduleBtn')?.addEventListener('click', () => rescheduleAppointment(appointment.id));
  $('cancelBtn')?.addEventListener('click', () => cancelAppointment(appointment.id));
  $('editBtn')?.addEventListener('click', () => editAppointment(appointment.id));
}

function hideEventModal() {
  const modal = $('eventModal');
  if (modal) modal.classList.add('hidden');
}

async function rescheduleAppointment(id) {
  try {
    const newTime = new Date(parseDate(state.appointments.find(a => a.id == id)?.start).getTime() + 3600000);
    await fetchJSON(`${apiBase()}/appointments/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ start: newTime.toISOString() })
    });
    hideEventModal();
    await refresh();
  } catch (error) {
    console.error('Failed to reschedule:', error);
  }
}

async function cancelAppointment(id) {
  try {
    await fetchJSON(`${apiBase()}/appointments/${id}`, { method: 'DELETE' });
    hideEventModal();
    await refresh();
  } catch (error) {
    console.error('Failed to cancel:', error);
  }
}

function editAppointment(id) {
  console.log('Edit appointment:', id);
  hideEventModal();
}

// Navigation & init
function changeView(newView) {
  if (state.view === newView) return;
  state.view = newView;
  // Switch FullCalendar view directly if mounted
  if (__fc) {
    const viewName = newView === 'week' ? 'timeGridWeek' : (newView === 'day' ? 'timeGridDay' : 'dayGridMonth');
    if (newView === 'month') {
      const d = state.cursor instanceof Date ? state.cursor : new Date(state.cursor);
      state.cursor = new Date(d.getFullYear(), d.getMonth(), 1);
    }
  __fc.changeView(viewName, state.cursor);
  }
  refresh();
}

function navigate(direction) {
  if (__fc) {
    if (direction < 0) __fc.prev(); else __fc.next();
    // state will sync in datesSet, but still refresh events
    refresh();
  } else {
    // Fallback (shouldn’t happen once FC is primary)
    const unit = state.view === 'month' ? 'month' : (state.view === 'day' ? 'day' : 'week');
    state.cursor = dayjs(state.cursor).add(direction, unit).d;
    refresh();
  }
}

function goToToday() {
  const now = new Date();
  if (__fc) {
    __fc.today();
  }
  state.cursor = now;
  refresh();
}

async function refresh() {
  if (__refreshing) { __refreshQueued = true; return; }
  __refreshing = true;
  try {
    await loadAppointments();
    if (__fc) {
      __fc.removeAllEvents();
      __fc.addEventSource(toFullCalendarEvents(state.appointments));
      // datesSet will update UI; avoid re-rendering FC container
    } else {
      render();
    }
  } catch (e) {
    console.error('Calendar refresh failed:', e);
  } finally {
    __refreshing = false;
    if (__refreshQueued) {
      __refreshQueued = false;
      // Run one more refresh to include any missed updates
      setTimeout(() => refresh(), 0);
    }
  }
}

// Event listeners
function setupEventListeners() {
  $('calPrev')?.addEventListener('click', () => navigate(-1));
  $('calNext')?.addEventListener('click', () => navigate(1));

  $('viewDay')?.addEventListener('click', () => changeView('day'));
  $('viewWeek')?.addEventListener('click', () => changeView('week'));
  $('viewMonth')?.addEventListener('click', () => changeView('month'));

  const viewSelectMobile = $('viewSelectMobile');
  if (viewSelectMobile) {
    viewSelectMobile.value = state.view;
    viewSelectMobile.addEventListener('change', () => {
      const v = viewSelectMobile.value;
      if (v === 'day' || v === 'week' || v === 'month') changeView(v);
    });
  }

  $('calAdd')?.addEventListener('click', () => {
    console.log('Add new appointment');
  });

  const svc = $('filterService');
  const prov = $('filterProvider');
  const svcM = $('filterServiceMobile');
  const provM = $('filterProviderMobile');
  const root = $('calendarRoot');
  const applyFilters = () => {
    state.filters.serviceId = (svc?.value || svcM?.value || '').trim();
    state.filters.providerId = (prov?.value || provM?.value || '').trim();
    if (root) {
      root.dataset.service = state.filters.serviceId;
      root.dataset.provider = state.filters.providerId;
    }
    try {
      localStorage.setItem('xs:cal:serviceId', state.filters.serviceId || '');
      localStorage.setItem('xs:cal:providerId', state.filters.providerId || '');
    } catch (_) {}
    refresh();
  };
  const sync = (from, to) => { if (from && to) to.value = from.value; };
  svc?.addEventListener('change', () => { sync(svc, svcM); applyFilters(); });
  prov?.addEventListener('change', () => { sync(prov, provM); applyFilters(); });
  svcM?.addEventListener('change', () => { sync(svcM, svc); applyFilters(); });
  provM?.addEventListener('change', () => { sync(provM, prov); applyFilters(); });

  $('closeModal')?.addEventListener('click', hideEventModal);
  $('eventModal')?.addEventListener('click', (e) => {
    if (e.currentTarget === e.target) hideEventModal();
  });

  document.addEventListener('click', async (e) => {
    const eventEl = e.target.closest('[data-event-id]');
    if (eventEl) {
      const id = eventEl.getAttribute('data-event-id');
      try {
        const appointment = await fetchJSON(`${apiBase()}/appointments/${id}`);
        showEventModal(appointment);
      } catch (error) {
        console.error('Failed to load appointment:', error);
      }
    }

    const addBtn = e.target.closest('[data-add-date]');
    if (addBtn) {
      const date = addBtn.getAttribute('data-add-date');
      console.log('Add appointment on', date);
    }
  });
}

// SPA-safe bootstrapping
function bootCalendarIfPresent(force = false) {
  const root = $('calendarRoot');
  if (!root) return;
  // Only wire once; ignore `force` for wiring to avoid duplicate handlers
  if (!window.__XS_CAL_WIRED__ || !root.dataset.wired) {
    setupEventListeners();
    root.dataset.wired = '1';
    window.__XS_CAL_WIRED__ = true;
    state.filters.serviceId = root.dataset.service || '';
    state.filters.providerId = root.dataset.provider || '';
    try {
      const lsSvc = localStorage.getItem('xs:cal:serviceId');
      const lsProv = localStorage.getItem('xs:cal:providerId');
      if (lsSvc !== null) state.filters.serviceId = lsSvc;
      if (lsProv !== null) state.filters.providerId = lsProv;
    } catch (_) {}
    const svc = $('filterService');
    const prov = $('filterProvider');
    const svcM = $('filterServiceMobile');
    const provM = $('filterProviderMobile');
    if (svc) svc.value = state.filters.serviceId;
    if (svcM) svcM.value = state.filters.serviceId;
    if (prov) prov.value = state.filters.providerId;
    if (provM) provM.value = state.filters.providerId;
  }
  if (force || !root.hasChildNodes()) {
    refresh();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => bootCalendarIfPresent());
} else {
  bootCalendarIfPresent();
}

document.addEventListener('spa:navigated', () => {
  // Force a refresh of data/calendar without re-wiring listeners
  setTimeout(() => bootCalendarIfPresent(true), 0);
});

// Avoid observing the entire document subtree which causes loops with FullCalendar DOM changes.
// Instead, watch only #spa-content for top-level additions that include #calendarRoot.
if (!window.__XS_CAL_OBS__) {
  const target = document.getElementById('spa-content');
  try {
    if (target) {
    const mo = new MutationObserver((mutations) => {
        const addedCalendar = mutations.some(m =>
          Array.from(m.addedNodes || []).some(n => {
            if (!(n instanceof Element)) return false;
            return n.id === 'calendarRoot' || !!n.querySelector?.('#calendarRoot');
          })
        );
        if (addedCalendar) {
          // Debounce to group multiple micro-mutations into a single boot
          clearTimeout(window.__XS_CAL_OBS_T);
      window.__XS_CAL_OBS_T = setTimeout(() => bootCalendarIfPresent(true), 50);
        }
      });
      mo.observe(target, { childList: true });
    }
    window.__XS_CAL_OBS__ = true;
  } catch (_) {}
}