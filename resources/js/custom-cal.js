// Modern TailwindCSS Calendar with Material Design
// Features: Day, Week, Month views | API Integration | Material Icons | Responsive

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
// Theme-based status classes (no hardcoded colors)
function getStatusClasses(status) {
  switch ((status || '').toLowerCase()) {
    case 'confirmed':
      // Success pill: light background, readable text, dark-mode tint
      return 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-300 border border-success-200 dark:border-success-700';
    case 'cancelled':
      // Error pill
      return 'bg-error-100 text-error-800 dark:bg-error-900/30 dark:text-error-300 border border-error-200 dark:border-error-700';
    case 'booked':
    case 'pending':
      // Warning pill
      return 'bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-300 border border-warning-200 dark:border-warning-700';
    default:
      // Neutral/primary accent as fallback
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

// View rendering functions
function updateTitle() {
  const titleEl = $('calTitle');
  if (!titleEl) return;
  
  const d = dayjs(state.cursor);
  if (state.view === 'month') {
    titleEl.textContent = d.format('MMMM YYYY');
    titleEl.setAttribute('datetime', d.format('YYYY-MM'));
  } else if (state.view === 'week') {
    const start = d.startOf('week');
    const end = d.endOf('week');
    titleEl.textContent = `${start.format('MMM')} ${start.d.getDate()} – ${end.format('MMM')} ${end.d.getDate()}, ${d.d.getFullYear()}`;
  } else {
    titleEl.textContent = d.d.toLocaleDateString(undefined, { 
      weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' 
    });
  }
}

function updateViewTabs() {
  ['viewDay', 'viewWeek', 'viewMonth'].forEach(id => {
    const btn = $(id);
    if (!btn) return;
    
    const view = btn.getAttribute('data-view') || id.replace('view', '').toLowerCase();
    const isActive = state.view === view;

  // Toggle default button styles
  const base = 'btn btn-sm';
  const active = 'btn-primary';
  const inactive = 'btn-outline';
  btn.className = `${base} ${isActive ? active : inactive}`;
  });
}

function renderMonthView(root) {
  const start = dayjs(state.cursor).startOf('month').startOf('week');
  const today = dayjs(state.today);
  
  // Build calendar grid
  const weeks = [];
  for (let week = 0; week < 6; week++) {
    const days = [];
    for (let day = 0; day < 7; day++) {
      const current = dayjs(start.d.getTime() + (week * 7 + day) * 86400000);
      const isToday = current.isSame(today, 'day');
      const isCurrentMonth = current.d.getMonth() === state.cursor.getMonth();
      const dayAppts = state.appointments.filter(a => 
        a.start?.slice(0, 10) === current.format('YYYY-MM-DD')
      );
      
      // Today highlight using primary palette
      const todayClasses = isToday 
        ? 'bg-primary-50 dark:bg-primary-900/20 border-primary-200 dark:border-primary-700'
        : 'border-gray-200 dark:border-gray-700';
      
      // Current month styling
      const monthClasses = isCurrentMonth 
        ? 'text-gray-700 dark:text-gray-300' 
        : 'text-gray-400 dark:text-gray-500';
      
      // Date number (top-right aligned)
      const dateNumber = `<span class="text-sm font-semibold ${isToday ? 'text-primary-700 dark:text-primary-300' : ''}">${current.d.getDate()}</span>`;
      
      // Events list (max 2 visible)
      const visibleEvents = dayAppts.slice(0, 2);
      const hiddenCount = Math.max(0, dayAppts.length - 2);
      const eventsList = visibleEvents.map(apt => {
        const time = parseDate(apt.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return `
          <div class="group cursor-pointer" data-event-id="${apt.id}">
            <div class="flex items-center space-x-2 px-2 py-1 rounded-md text-xs hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${getStatusClasses(apt.status)}">
              <div class="w-1.5 h-1.5 rounded-full ${getStatusDot(apt.status)}"></div>
              <span class="font-medium truncate">${apt.title || 'Appointment'}</span>
              <span class="text-xs opacity-80">${time}</span>
            </div>
          </div>`;
      }).join('');
      
      const moreIndicator = hiddenCount > 0 
        ? `<div class="text-xs text-primary-600 dark:text-primary-400 font-medium cursor-pointer hover:text-primary-700 dark:hover:text-primary-300">+${hiddenCount} more</div>`
        : '';
      
      days.push(`
        <div class="group bg-white/90 dark:bg-gray-800/90 backdrop-blur-[1px] rounded-xl shadow-brand border ${todayClasses} ${monthClasses} min-h-[130px] p-3 hover:shadow-brand-lg hover:border-gray-300 dark:hover:border-gray-600 transition-all">
          <div class="flex items-start justify-between mb-2">
            <!-- Add appointment button -->
            <button class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Add appointment" data-add-date="${current.format('YYYY-MM-DD')}">
              <span class="material-symbols-rounded text-gray-500 dark:text-gray-400 text-sm">add</span>
            </button>
            <div class="ml-2"></div>
            <!-- Date number top-right -->
            ${dateNumber}
          </div>
          <div class="space-y-1.5">
            ${eventsList}
            ${moreIndicator}
          </div>
        </div>
      `);
    }
    weeks.push(`<div class="grid grid-cols-7 gap-1">${days.join('')}</div>`);
  }
  
  // Week day headers
  const headers = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => 
    `<div class="text-center text-sm font-semibold text-gray-600 dark:text-gray-400 py-2">${day}</div>`
  ).join('');
  
  root.innerHTML = `
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-brand border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700 bg-transparent">
        ${headers}
      </div>
      <div class="space-y-1 p-1">
        ${weeks.join('')}
      </div>
    </div>
  `;
}

function renderWeekView(root) {
  const weekStart = dayjs(state.cursor).startOf('week');
  const today = dayjs(state.today);
  const hours = Array.from({ length: 24 }, (_, i) => i);
  
  // Time column
  const timeColumn = hours.map(hour => `
    <div class="h-16 border-b border-gray-100 dark:border-gray-700 flex items-start justify-end pr-2 text-xs text-gray-500 dark:text-gray-400">
      ${hour.toString().padStart(2, '0')}:00
    </div>
  `).join('');
  
  // Day columns
  const dayColumns = Array.from({ length: 7 }, (_, dayIndex) => {
    const current = dayjs(weekStart.d.getTime() + dayIndex * 86400000);
    const isToday = current.isSame(today, 'day');
    const dayAppts = state.appointments.filter(a => 
      a.start?.slice(0, 10) === current.format('YYYY-MM-DD')
    );
    
    // Day header
    const headerClasses = isToday 
      ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-semibold'
      : 'text-gray-700 dark:text-gray-300';
    
    const dayHeader = `
      <div class="h-16 border-b border-gray-200 dark:border-gray-700 ${headerClasses} flex flex-col items-center justify-center">
        <div class="text-xs font-medium">${current.d.toLocaleDateString(undefined, { weekday: 'short' })}</div>
        <div class="text-lg font-semibold">${current.d.getDate()}</div>
      </div>
    `;
    
    // Hour slots
    const hourSlots = hours.map(hour => {
      const slotAppts = dayAppts.filter(a => {
        const startHour = parseDate(a.start).getHours();
        return startHour === hour;
      });
      
      const appointments = slotAppts.map(apt => {
        const start = parseDate(apt.start);
        const end = parseDate(apt.end);
        const duration = (end - start) / (1000 * 60); // minutes
        const height = Math.max(24, duration * 1.2); // minimum 24px height
        
        return `
          <div class="absolute left-1 right-1 ${getStatusClasses(apt.status)} rounded-lg p-2 shadow-sm cursor-pointer hover:shadow-md transition-all transform hover:scale-105 z-10"
               style="height: ${height}px; top: 2px;"
               data-event-id="${apt.id}">
            <div class="text-xs font-medium truncate">${apt.title || 'Appointment'}</div>
            <div class="text-xs opacity-90">${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
          </div>
        `;
      }).join('');
      
      return `
        <div class="relative h-16 border-b border-gray-100 dark:border-gray-700 bg-transparent hover:bg-gray-50/40 dark:hover:bg-gray-800/40 transition-colors">
          ${appointments}
        </div>
      `;
    }).join('');
    
    return `
      <div class="border-r border-gray-200 dark:border-gray-700 last:border-r-0">
        ${dayHeader}
        ${hourSlots}
      </div>
    `;
  }).join('');
  
  root.innerHTML = `
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-brand border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="flex">
        <!-- Time column -->
        <div class="w-16 bg-transparent border-r border-gray-200 dark:border-gray-700">
          <div class="h-16 border-b border-gray-200 dark:border-gray-700"></div>
          ${timeColumn}
        </div>
        <!-- Days grid -->
        <div class="flex-1 grid grid-cols-7">
          ${dayColumns}
        </div>
      </div>
    </div>
  `;
}

function renderDayView(root) {
  const current = dayjs(state.cursor);
  const today = dayjs(state.today);
  const isToday = current.isSame(today, 'day');
  const hours = Array.from({ length: 24 }, (_, i) => i);
  const dayAppts = state.appointments.filter(a => 
    a.start?.slice(0, 10) === current.format('YYYY-MM-DD')
  );
  
  // Hour slots
  const hourSlots = hours.map(hour => {
    const slotAppts = dayAppts.filter(a => {
      const startHour = parseDate(a.start).getHours();
      return startHour === hour;
    });
    
    const appointments = slotAppts.map(apt => {
      const start = parseDate(apt.start);
      const end = parseDate(apt.end);
      const duration = (end - start) / (1000 * 60); // minutes
      
      return `
        <div class="${getStatusClasses(apt.status)} rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition-all transform hover:scale-105 mb-2"
             data-event-id="${apt.id}">
          <div class="font-medium">${apt.title || 'Appointment'}</div>
          <div class="text-sm opacity-90">
            ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - 
            ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
          </div>
        </div>
      `;
    }).join('');
    
    return `
      <div class="flex border-b border-gray-100 dark:border-gray-700 min-h-[4rem] hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
        <div class="w-16 bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex items-start justify-end pr-2 pt-2">
          <span class="text-xs text-gray-500 dark:text-gray-400">${hour.toString().padStart(2, '0')}:00</span>
        </div>
        <div class="flex-1 p-2">
          ${appointments}
        </div>
      </div>
    `;
  }).join('');
  
  const todayIndicator = isToday ? 'border-l-4 border-primary-500' : '';
  
  root.innerHTML = `
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-brand border border-gray-200 dark:border-gray-700 overflow-hidden ${todayIndicator}">
      <div class="bg-transparent border-b border-gray-200 dark:border-gray-700 p-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          ${current.d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}
        </h2>
      </div>
      <div class="max-h-[600px] overflow-y-auto">
        ${hourSlots}
      </div>
    </div>
  `;
  
  // Auto-scroll to current time if viewing today
  if (isToday) {
    const container = root.querySelector('.overflow-y-auto');
    const currentHour = new Date().getHours();
    const scrollTarget = container.children[Math.max(0, currentHour - 1)];
    if (scrollTarget) {
      scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
}

// Main render function
function render() {
  const root = $('calendarRoot');
  if (!root) return;
  
  root.innerHTML = '<div class="flex items-center justify-center h-64"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
  
  updateTitle();
  updateViewTabs();
  
  if (state.view === 'month') {
    renderMonthView(root);
  } else if (state.view === 'week') {
    renderWeekView(root);
  } else {
    renderDayView(root);
  }
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
  
  // Event handlers
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
  // Placeholder for edit functionality
  console.log('Edit appointment:', id);
  hideEventModal();
}

// Navigation and initialization
function changeView(newView) {
  if (state.view === newView) return;
  state.view = newView;
  refresh();
}

function navigate(direction) {
  const unit = state.view === 'day' ? 'day' : state.view === 'week' ? 'week' : 'month';
  state.cursor = dayjs(state.cursor).add(direction, unit).d;
  refresh();
}

function goToToday() {
  state.cursor = new Date();
  refresh();
}

async function refresh() {
  await loadAppointments();
  render();
}

// Event listeners
function setupEventListeners() {
  // Navigation
  $('calPrev')?.addEventListener('click', () => navigate(-1));
  $('calToday')?.addEventListener('click', goToToday);
  $('calNext')?.addEventListener('click', () => navigate(1));
  
  // View switching
  $('viewDay')?.addEventListener('click', () => changeView('day'));
  $('viewWeek')?.addEventListener('click', () => changeView('week'));
  $('viewMonth')?.addEventListener('click', () => changeView('month'));
  
  // Add button
  $('calAdd')?.addEventListener('click', () => {
    console.log('Add new appointment');
    // Placeholder for add functionality
  });

  // Filters (desktop + mobile)
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
    // Persist to localStorage
    try {
      localStorage.setItem('xs:cal:serviceId', state.filters.serviceId || '');
      localStorage.setItem('xs:cal:providerId', state.filters.providerId || '');
    } catch (_) {}
    refresh();
  };
  const sync = (from, to) => {
    if (from && to) to.value = from.value;
  };
  svc?.addEventListener('change', () => { sync(svc, svcM); applyFilters(); });
  prov?.addEventListener('change', () => { sync(prov, provM); applyFilters(); });
  svcM?.addEventListener('change', () => { sync(svcM, svc); applyFilters(); });
  provM?.addEventListener('change', () => { sync(provM, prov); applyFilters(); });
  
  // Modal close
  $('closeModal')?.addEventListener('click', hideEventModal);
  $('eventModal')?.addEventListener('click', (e) => {
    // Only close when clicking the overlay background, not any inner content
    if (e.currentTarget === e.target) hideEventModal();
  });
  
  // Event clicks (delegated)
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

    // Per-day add button
    const addBtn = e.target.closest('[data-add-date]');
    if (addBtn) {
      const date = addBtn.getAttribute('data-add-date');
      console.log('Add appointment on', date);
      // TODO: Replace with real add flow (open modal with date prefilled)
    }
  });
}

// SPA-safe bootstrapping
function bootCalendarIfPresent(force = false) {
  const root = $('calendarRoot');
  if (!root) return;
  // Prevent duplicate global listeners across SPA navigations
  if (!window.__XS_CAL_WIRED__ || force || !root.dataset.wired) {
    setupEventListeners();
    root.dataset.wired = '1';
    window.__XS_CAL_WIRED__ = true;
  // Initialize filters from dataset if provided
    state.filters.serviceId = root.dataset.service || '';
    state.filters.providerId = root.dataset.provider || '';
    // Restore from localStorage if present
    try {
      const lsSvc = localStorage.getItem('xs:cal:serviceId');
      const lsProv = localStorage.getItem('xs:cal:providerId');
      if (lsSvc !== null) state.filters.serviceId = lsSvc;
      if (lsProv !== null) state.filters.providerId = lsProv;
    } catch (_) {}
    // Reflect into selects
    const svc = $('filterService');
    const prov = $('filterProvider');
    const svcM = $('filterServiceMobile');
    const provM = $('filterProviderMobile');
    if (svc) svc.value = state.filters.serviceId;
    if (svcM) svcM.value = state.filters.serviceId;
    if (prov) prov.value = state.filters.providerId;
    if (provM) provM.value = state.filters.providerId;
  }
  // If root is empty or force specified, (re)load appointments and render
  if (force || !root.hasChildNodes()) {
    refresh();
  }
}

// Initialize on first load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => bootCalendarIfPresent());
} else {
  bootCalendarIfPresent();
}

// Reinitialize on SPA navigations
document.addEventListener('spa:navigated', () => {
  // Defer a tick to allow DOM swap to complete
  setTimeout(() => bootCalendarIfPresent(true), 0);
});

// Observe DOM for dynamic insertion of #calendarRoot
if (!window.__XS_CAL_OBS__) {
  const target = document.getElementById('spa-content') || document.body;
  try {
    const mo = new MutationObserver(() => bootCalendarIfPresent());
    mo.observe(target, { childList: true, subtree: true });
    window.__XS_CAL_OBS__ = true;
  } catch (_) {
    // no-op if MutationObserver not available
  }
}