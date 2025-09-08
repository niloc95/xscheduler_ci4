// Clean FullCalendar Implementation with dynamic imports
// Supports Day, Week, Month views with Tailwind styling and dark mode

console.log('🔄 Calendar clean script starting...');

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

async function loadAppointments() {
  try {
    const view = state.calendar?.view;
    const start = view ? view.activeStart : new Date(state.currentDate.getFullYear(), state.currentDate.getMonth(), 1);
    const end = view ? view.activeEnd : new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + 1, 0);

    const params = new URLSearchParams({
      start: start.toISOString().split('T')[0],
      end: end.toISOString().split('T')[0]
    });

    if (state.filters.providerId) params.set('providerId', state.filters.providerId);
    if (state.filters.serviceId) params.set('serviceId', state.filters.serviceId);

    const url = `${apiBase()}/appointments?${params.toString()}`;
    const appointments = await fetchJSON(url);

    return appointments.map(apt => ({
      id: String(apt.id || ''),
      title: apt.title || 'Appointment',
      start: apt.start,
      end: apt.end,
      extendedProps: {
        status: (apt.status || '').toLowerCase(),
        raw: apt
      }
    }));
  } catch (error) {
    console.error('Failed to load appointments:', error);
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
    return;
  }

  console.log('📅 Calendar container found, clearing content...');
  container.innerHTML = '';

  console.log('📅 Container visibility:', getComputedStyle(container).display, getComputedStyle(container).visibility);
  console.log('📅 Container dimensions:', container.offsetWidth, 'x', container.offsetHeight);

  try {
    console.log('� Loading FullCalendar modules...');

    // Use dynamic imports to avoid build issues
    const [{ Calendar }, dayGridPlugin, timeGridPlugin, interactionPlugin] = await Promise.all([
      import('@fullcalendar/core'),
      import('@fullcalendar/daygrid'),
      import('@fullcalendar/timegrid'),
      import('@fullcalendar/interaction')
    ]);

    console.log('✅ FullCalendar modules loaded successfully');
    console.log('📦 Calendar:', Calendar);
    console.log('📦 DayGrid Plugin:', dayGridPlugin);
    console.log('📦 TimeGrid Plugin:', timeGridPlugin);
    console.log('📦 Interaction Plugin:', interactionPlugin);

    // Create calendar
    console.log('🏗️ Creating calendar instance...');
    state.calendar = new Calendar(container, {
      plugins: [dayGridPlugin.default, timeGridPlugin.default, interactionPlugin.default],
      initialView: state.view === 'week' ? 'timeGridWeek' :
                   state.view === 'day' ? 'timeGridDay' : 'dayGridMonth',
      initialDate: state.currentDate,
      headerToolbar: false, // We use custom header
      height: 'auto',
      expandRows: true,

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

      // Month view settings
      fixedWeekCount: false,
      showNonCurrentDates: true,
      dayMaxEventRows: 3,

      // Interaction
      selectable: true,
      selectMirror: true,

      // Load events
      events: loadAppointments,

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
    });

    console.log('🎨 Rendering calendar...');
    state.calendar.render();
    console.log('✅ Calendar rendered successfully!');
    console.log('📅 Calendar instance:', state.calendar);

  } catch (error) {
    console.error('❌ Failed to initialize calendar:', error);
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
    try {
      state.view = newView;
      state.calendar.changeView(fcView);
      // UI update right away to reflect active tab
      updateViewTabs();
    } finally {
      state.isSwitching = false;
    }
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
  // Navigation
  $('calPrev')?.addEventListener('click', () => navigate(-1));
  $('calNext')?.addEventListener('click', () => navigate(1));

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

    if (savedService !== null) state.filters.serviceId = savedService;
    if (savedProvider !== null) state.filters.providerId = savedProvider;

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
      return;
    }

    const calendarRoot = $('calendarRoot');
    if (!calendarRoot) {
      console.error('❌ Calendar container #calendarRoot not found in DOM!');
      return;
    }

    console.log('🔄 Initializing calendar...');
    state.initialized = true;

    // Restore saved state
    restoreFilters();

    // Setup event listeners
    setupEventListeners();

  // Load business hours before initializing calendar
  await loadBusinessHours();

    // Initialize calendar
    await initCalendar();

  } catch (error) {
    console.error('❌ Calendar initialization failed:', error);
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
    // SPA replaced content; always rebuild a fresh calendar binding to new DOM
    if (state.calendar) {
      try { state.calendar.destroy(); } catch (_) {}
      state.calendar = null;
    }
    state.initialized = false;
    // small delay to allow DOM to settle
    setTimeout(init, 0);
    return;
  }

  // Navigating away: destroy calendar and reset state
  if (state.calendar) {
    try { state.calendar.destroy(); } catch (_) {}
    state.calendar = null;
  }
  state.initialized = false;
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
