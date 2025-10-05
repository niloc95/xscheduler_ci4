/**
 * ⚠️ DEPRECATED: Legacy Scheduler Module (FullCalendar-based)
 * 
 * This module is being phased out in favor of the new Appointment View.
 * Status: Active but will be replaced
 * Timeline: Maintained until new Appointment View reaches feature parity
 * 
 * DO NOT add new features to this file.
 * Only bug fixes and security updates will be applied.
 * 
 * See: docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md
 * Replacement: resources/js/modules/appointments/appointments-dashboard.js (In Development)
 * 
 * Last Updated: October 5, 2025
 */

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import ptBrLocale from '@fullcalendar/core/locales/pt-br';
import '../../../css/fullcalendar-overrides.css';
import createSchedulerService from '../../services/scheduler-legacy/scheduler-service';

// Color palette for services and providers (extend as needed)
const COLOR_PALETTE = [
  'bg-cyan-100 text-cyan-800 border-cyan-200 dark:bg-cyan-900/40 dark:text-cyan-200 dark:border-cyan-700',
  'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-200 dark:bg-fuchsia-900/40 dark:text-fuchsia-200 dark:border-fuchsia-700',
  'bg-lime-100 text-lime-800 border-lime-200 dark:bg-lime-900/40 dark:text-lime-200 dark:border-lime-700',
  'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-200 dark:border-indigo-700',
  'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/40 dark:text-orange-200 dark:border-orange-700',
  'bg-pink-100 text-pink-800 border-pink-200 dark:bg-pink-900/40 dark:text-pink-200 dark:border-pink-700',
  'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-900/40 dark:text-teal-200 dark:border-teal-700',
  'bg-violet-100 text-violet-800 border-violet-200 dark:bg-violet-900/40 dark:text-violet-200 dark:border-violet-700',
];

function colorClassForService(serviceId) {
  if (!serviceId) return '';
  const idx = parseInt(serviceId, 10) % COLOR_PALETTE.length;
  return COLOR_PALETTE[idx];
}

function colorClassForProvider(providerId) {
  if (!providerId) return '';
  const idx = parseInt(providerId, 10) % COLOR_PALETTE.length;
  return COLOR_PALETTE[(idx + 3) % COLOR_PALETTE.length]; // offset for variety
}

// Returns color class: status > service > provider
function eventColorClass({ status, serviceId, providerId }) {
  if (status && STATUS_CLASSNAME[status]) return STATUS_CLASSNAME[status];
  if (serviceId) return colorClassForService(serviceId);
  if (providerId) return colorClassForProvider(providerId);
  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 border-blue-200 dark:border-blue-700';
}

const STATUS_CLASSNAME = {
  confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200 border-emerald-200 dark:border-emerald-700',
  cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200 border-rose-200 dark:border-rose-700',
  booked: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200 border-amber-200 dark:border-amber-700',
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200 border-amber-200 dark:border-amber-700',
  completed: 'bg-slate-200 text-slate-700 dark:bg-slate-800/60 dark:text-slate-200 border-slate-300 dark:border-slate-700',
};

const dotForStatus = {
  confirmed: 'bg-emerald-500 dark:bg-emerald-300',
  cancelled: 'bg-rose-500 dark:bg-rose-300',
  booked: 'bg-amber-500 dark:bg-amber-300',
  pending: 'bg-amber-500 dark:bg-amber-300',
  completed: 'bg-slate-400 dark:bg-slate-300',
  default: 'bg-blue-500 dark:bg-blue-300',
};

const STATUS_VARIANT_STYLES = {
  info: {
    container: ['border-blue-200', 'bg-blue-50', 'dark:border-blue-600/60', 'dark:bg-blue-900/30'],
    icon: ['text-blue-500', 'dark:text-blue-300'],
    label: ['text-blue-700', 'dark:text-blue-200'],
    message: ['text-blue-800', 'dark:text-blue-100'],
  },
  success: {
    container: ['border-emerald-200', 'bg-emerald-50', 'dark:border-emerald-600/50', 'dark:bg-emerald-900/25'],
    icon: ['text-emerald-500', 'dark:text-emerald-300'],
    label: ['text-emerald-700', 'dark:text-emerald-200'],
    message: ['text-emerald-800', 'dark:text-emerald-100'],
  },
  warning: {
    container: ['border-amber-200', 'bg-amber-50', 'dark:border-amber-500/60', 'dark:bg-amber-900/25'],
    icon: ['text-amber-500', 'dark:text-amber-300'],
    label: ['text-amber-700', 'dark:text-amber-200'],
    message: ['text-amber-800', 'dark:text-amber-100'],
  },
  error: {
    container: ['border-rose-200', 'bg-rose-50', 'dark:border-rose-500/60', 'dark:bg-rose-900/25'],
    icon: ['text-rose-500', 'dark:text-rose-300'],
    label: ['text-rose-700', 'dark:text-rose-200'],
    message: ['text-rose-800', 'dark:text-rose-100'],
  },
};

const STATUS_VARIANT_ICONS = {
  info: 'info',
  success: 'check_circle',
  warning: 'warning',
  error: 'error',
};

const VIEW_TYPE_MAP = {
  day: 'timeGridDay',
  week: 'timeGridWeek',
  month: 'dayGridMonth',
};

function classForStatus(status) {
  return STATUS_CLASSNAME[status] || 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 border-blue-200 dark:border-blue-700';
}

function dotClass(status) {
  return dotForStatus[status] || dotForStatus.default;
}

function parseISO(value) {
  if (!value) return new Date(NaN);
  return new Date(value);
}

function normalizeEventDateTime(value) {
  if (!value) return null;
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) return null;

    // Convert MySQL datetime format (YYYY-MM-DD HH:MM:SS) to ISO-8601 (YYYY-MM-DDTHH:MM:SS)
    const candidate = trimmed.includes(' ') && !trimmed.includes('T')
      ? trimmed.replace(' ', 'T')
      : trimmed;

    const parsed = new Date(candidate);
    
    // Enhanced debugging for datetime parsing
    if (Number.isNaN(parsed.getTime())) {
      console.error('[scheduler] Failed to parse datetime:', {
        original: value,
        trimmed,
        candidate,
        parsed
      });
      return null;
    }
    
    // Verify the parsed date is what we expect
    console.log('[scheduler] Parsed datetime:', {
      original: value,
      iso: candidate,
      parsed: parsed.toISOString(),
      localTime: parsed.toLocaleTimeString()
    });
    
    return parsed;
  }
  if (typeof value === 'number') {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }
  return null;
}

function formatRangeTitle(viewType, start, end) {
  const intlDate = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' });
  if (viewType === 'dayGridMonth') {
    return intlDate.format(start);
  }
  if (viewType === 'timeGridWeek') {
    const opts = { month: 'short', day: 'numeric' };
    const startFmt = new Intl.DateTimeFormat(undefined, opts).format(start);
    const endFmt = new Intl.DateTimeFormat(undefined, opts).format(end);
    return `${startFmt} – ${endFmt}`;
  }
  if (viewType === 'timeGridDay') {
    const opts = { weekday: 'long', month: 'long', day: 'numeric' };
    return new Intl.DateTimeFormat(undefined, opts).format(start);
  }
  return intlDate.format(start);
}

function getInitialState() {
  return {
    calendar: null,
    view: 'month',
    viewType: 'dayGridMonth',
    focusDate: new Date(),
    filters: {
      providerId: '',
      serviceId: '',
    },
    isLoadingCounts: false,
    isLoadingSlots: false,
    hasInitialFocusSync: false,
  };
}

function toDateOnly(value) {
  if (!value) return '';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function mapAppointmentToEvent(item) {
  const normalizedStart = normalizeEventDateTime(item.start);
  const normalizedEnd = normalizeEventDateTime(item.end);
  
  // Debug logging to identify the issue
  if (!normalizedStart || !normalizedEnd) {
    console.warn('[scheduler] Invalid datetime for appointment:', {
      id: item.id,
      raw_start: item.start,
      raw_end: item.end,
      normalized_start: normalizedStart,
      normalized_end: normalizedEnd
    });
  }
  
  return {
    id: String(item.id ?? ''),
    title: item.title || 'Appointment',
    start: normalizedStart ?? normalizeEventDateTime(item.start ?? item.start_time ?? null),
    end: normalizedEnd ?? normalizeEventDateTime(item.end ?? item.end_time ?? null),
    extendedProps: {
      status: (item.status || '').toLowerCase(),
      raw: item,
    },
  };
}

function ready(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
    return;
  }
  callback();
}


async function bootSchedulerDashboard() {
  const root = document.getElementById('scheduler-dashboard');
  if (!root) return;
  if (root.dataset.booted === '1') return;
  root.dataset.booted = '1';

  const apiBase = root.dataset.apiBase || '';
  const slotsUrl = root.dataset.slotsUrl || '';
  let service;
  try {
    service = createSchedulerService({ baseUrl: apiBase, slotsUrl });
  } catch (error) {
    console.error('[scheduler] Failed to initialise service', error);
    return;
  }

  const state = getInitialState();
  let eventsAbortController = null;
  let eventsFetchToken = 0;
  let slotsAbortController = null;
  let slotsFetchToken = 0;
  let currentStatusVariant = null;
  let modalReturnFocus = null;

  const elements = {
    countToday: document.getElementById('scheduler-count-today'),
    countWeek: document.getElementById('scheduler-count-week'),
    countMonth: document.getElementById('scheduler-count-month'),
    summaryButtons: Array.from(root.querySelectorAll('#scheduler-summary [data-target-view]')),
    providerSelect: document.getElementById('scheduler-filter-provider'),
    serviceSelect: document.getElementById('scheduler-filter-service'),
    focusDateInput: document.getElementById('scheduler-focus-date'),
    clearButton: document.getElementById('scheduler-clear'),
    applyButton: document.getElementById('scheduler-apply'),
    refreshButton: document.getElementById('scheduler-refresh'),
    newButton: document.getElementById('scheduler-new'),
    todayButton: document.getElementById('scheduler-today'),
    prevButton: document.getElementById('scheduler-prev'),
    nextButton: document.getElementById('scheduler-next'),
    viewButtons: Array.from(root.querySelectorAll('.scheduler-view')),
    activeRange: document.getElementById('scheduler-active-range'),
    status: document.getElementById('scheduler-status'),
    statusContainer: document.getElementById('scheduler-status-alert'),
    statusIcon: root.querySelector('[data-status-icon]'),
    statusLabel: root.querySelector('[data-status-label]'),
    statusMessage: document.getElementById('scheduler-status-message'),
    slotsContainer: document.getElementById('scheduler-slots'),
    slotsEmpty: document.getElementById('scheduler-slots-empty'),
    slotsCaption: document.getElementById('scheduler-slots-caption'),
    slotTemplate: document.getElementById('scheduler-slot-template'),
    filterFeedback: document.getElementById('scheduler-filter-feedback'),
    modal: document.getElementById('scheduler-modal'),
    modalTitle: document.getElementById('scheduler-modal-title'),
    modalBody: document.getElementById('scheduler-modal-body'),
    modalFooter: document.getElementById('scheduler-modal-footer'),
    modalClose: document.getElementById('scheduler-modal-close'),
    calendarRoot: document.getElementById('scheduler-calendar'),
  };

  const FOCUSABLE_SELECTOR = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
  if (!elements.calendarRoot) {
    console.warn('[scheduler] Missing calendar root');
    return;
  }

  // --- Fetch business settings (working hours, breaks, blocks) ---
  let businessSettings = {};
  try {
    businessSettings = await service.getBusinessSettings();
  } catch (error) {
    console.warn('[scheduler] Failed to fetch business settings, using defaults', error);
    businessSettings = {};
  }
  
  // Parse times for FullCalendar
  const slotMinTime = businessSettings.work_start || '08:00:00';
  const slotMaxTime = businessSettings.work_end || '17:00:00';
  // Parse breaks and block periods
  let breakEvents = [];
  if (businessSettings.break_start && businessSettings.break_end) {
    breakEvents.push({
      startTime: businessSettings.break_start,
      endTime: businessSettings.break_end,
      daysOfWeek: [1,2,3,4,5,6,0], // all days
      display: 'background',
      overlap: false,
      className: 'bg-yellow-200/60',
      groupId: 'break',
      title: 'Break',
    });
  }
  let blockEvents = [];
  if (Array.isArray(businessSettings.blocked_periods)) {
    blockEvents = businessSettings.blocked_periods.map(b => ({
      start: b.start,
      end: b.end,
      display: 'background',
      overlap: false,
      className: 'bg-rose-200/60',
      groupId: 'block',
      title: b.notes || 'Blocked',
    }));
  }

  function setStatus(message, type = 'info') {
    const wrapper = elements.status;
    const messageEl = elements.statusMessage;
    const container = elements.statusContainer;
    if (!wrapper || !messageEl) return;

    const removeVariantClasses = (variant) => {
      if (!variant || !STATUS_VARIANT_STYLES[variant]) return;
      const styles = STATUS_VARIANT_STYLES[variant];
      if (container) container.classList.remove(...styles.container);
      if (elements.statusIcon) elements.statusIcon.classList.remove(...styles.icon);
      if (elements.statusLabel) elements.statusLabel.classList.remove(...styles.label);
      messageEl.classList.remove(...styles.message);
    };

    const applyVariantClasses = (variant) => {
      const styles = STATUS_VARIANT_STYLES[variant];
      if (container) container.classList.add(...styles.container);
      if (elements.statusIcon) elements.statusIcon.classList.add(...styles.icon);
      if (elements.statusLabel) elements.statusLabel.classList.add(...styles.label);
      messageEl.classList.add(...styles.message);
    };

    if (!message) {
      removeVariantClasses(currentStatusVariant);
      currentStatusVariant = null;
      messageEl.textContent = '';
      wrapper.classList.add('hidden');
      return;
    }

    const variant = STATUS_VARIANT_STYLES[type] ? type : 'info';
    wrapper.classList.remove('hidden');
    messageEl.textContent = message;

    if (elements.statusIcon) {
      elements.statusIcon.textContent = STATUS_VARIANT_ICONS[variant] || STATUS_VARIANT_ICONS.info;
    }

    if (variant !== currentStatusVariant) {
      removeVariantClasses(currentStatusVariant);
      applyVariantClasses(variant);
      currentStatusVariant = variant;
    } else if (currentStatusVariant === null) {
      applyVariantClasses(variant);
      currentStatusVariant = variant;
    }
  }

  function updateViewButtons(activeView) {
    elements.viewButtons.forEach((btn) => {
      const view = btn.dataset.view;
      const isActive = view === activeView;
      btn.classList.toggle('bg-blue-600', isActive);
      btn.classList.toggle('text-white', isActive);
      btn.classList.toggle('shadow-sm', isActive);
      btn.classList.toggle('bg-white', !isActive);
      btn.classList.toggle('text-gray-600', !isActive);
      btn.classList.toggle('dark:bg-gray-900', !isActive);
      btn.classList.toggle('dark:text-gray-300', !isActive);
    });
  }

  function updateSummaryCounts(counts) {
    const { today = 0, week = 0, month = 0 } = counts || {};
    if (elements.countToday) elements.countToday.textContent = String(today);
    if (elements.countWeek) elements.countWeek.textContent = String(week);
    if (elements.countMonth) elements.countMonth.textContent = String(month);
  }

  function updateFilterFeedback() {
    const applied = [];
    if (state.filters.serviceId) applied.push('service');
    if (state.filters.providerId) applied.push('provider');
    if (elements.filterFeedback) {
      if (!applied.length) {
        elements.filterFeedback.classList.add('hidden');
        elements.filterFeedback.textContent = '';
      } else {
        const formatted = applied.join(' & ');
        elements.filterFeedback.classList.remove('hidden');
        elements.filterFeedback.textContent = `Filters active: ${formatted}.`;
      }
    }
  }

  async function refreshCounts() {
    state.isLoadingCounts = true;
    if (elements.countToday) elements.countToday.textContent = '—';
    if (elements.countWeek) elements.countWeek.textContent = '—';
    if (elements.countMonth) elements.countMonth.textContent = '—';
    try {
      let data = await service.getCounts(state.filters);
      if (!data || typeof data !== 'object') {
        data = await service.getSummary(state.filters);
      }
      updateSummaryCounts(data);
    } catch (error) {
      console.warn('[scheduler] Failed fetching counts', error);
      try {
        const data = await service.getSummary(state.filters);
        updateSummaryCounts(data);
      } catch (fallbackError) {
        console.error('[scheduler] Summary fallback failed', fallbackError);
        updateSummaryCounts({ today: 0, week: 0, month: 0 });
        setStatus('Unable to load appointment counts.', 'error');
      }
    } finally {
      state.isLoadingCounts = false;
    }
  }

  async function refreshSlots() {
    if (!elements.slotsContainer || !elements.slotTemplate) return;
    const providerId = state.filters.providerId;
    const serviceId = state.filters.serviceId;

    if (!providerId || !serviceId) {
      // Abort any inflight slots request since preconditions are not met
      if (slotsAbortController) {
        slotsAbortController.abort();
        slotsAbortController = null;
      }
      elements.slotsContainer.innerHTML = '';
      if (elements.slotsEmpty) {
        elements.slotsEmpty.classList.remove('hidden');
        elements.slotsEmpty.textContent = 'Choose a provider and service to preview availability.';
      }
      if (elements.slotsCaption) elements.slotsCaption.textContent = 'Awaiting filters…';
      return;
    }

    if (elements.slotsEmpty) elements.slotsEmpty.classList.add('hidden');
    if (elements.slotsCaption) elements.slotsCaption.textContent = 'Loading slots…';
    elements.slotsContainer.innerHTML = '';
    state.isLoadingSlots = true;

    // Abort previous request and create a new controller/token
    if (slotsAbortController) {
      slotsAbortController.abort();
    }
    const controller = new AbortController();
    slotsAbortController = controller;
    const requestId = ++slotsFetchToken;

    try {
      const response = await service.getSlots({
        providerId,
        serviceId,
        date: elements.focusDateInput?.value,
      }, { signal: controller.signal });

      // If a newer request started, ignore this result
      if (requestId !== slotsFetchToken) {
        return;
      }
      const slots = Array.isArray(response?.slots) ? response.slots : [];
      if (!slots.length) {
        if (elements.slotsCaption) {
          elements.slotsCaption.textContent = `No availability for ${response?.date || 'selected criteria'}.`;
        }
        if (elements.slotsEmpty) {
          elements.slotsEmpty.classList.remove('hidden');
          elements.slotsEmpty.textContent = 'No open slots match the current filters.';
        }
        return;
      }

      if (elements.slotsCaption) {
        elements.slotsCaption.textContent = `Showing ${slots.length} slot${slots.length === 1 ? '' : 's'} for ${response?.date || elements.focusDateInput?.value || ''}.`;
      }

      const frag = document.createDocumentFragment();
      slots.slice(0, 12).forEach((slot) => {
        const node = elements.slotTemplate.content.cloneNode(true);
        const timeEl = node.querySelector('[data-slot-time]');
        const notesEl = node.querySelector('[data-slot-notes]');
        const buttonEl = node.querySelector('[data-slot-book]');
        if (timeEl) timeEl.textContent = `${slot.start} – ${slot.end}`;
        if (notesEl) notesEl.textContent = slot.capacity ? `${slot.capacity} open` : 'Available';
        if (buttonEl) {
          buttonEl.addEventListener('click', () => {
            openCreateModal({
              start: `${response?.date || elements.focusDateInput?.value || ''}T${slot.start}:00`,
              end: `${response?.date || elements.focusDateInput?.value || ''}T${slot.end}:00`,
            });
          });
        }
        frag.appendChild(node);
      });
      elements.slotsContainer.appendChild(frag);
    } catch (error) {
      if (controller.signal.aborted || error?.name === 'AbortError') {
        // Clear loading caption on abort of older request
        if (requestId === slotsFetchToken) {
          if (elements.slotsCaption) elements.slotsCaption.textContent = '';
        }
        return;
      }
      console.warn('[scheduler] Failed loading slots', error);
      if (elements.slotsCaption) elements.slotsCaption.textContent = 'Unable to load slot data.';
      if (elements.slotsEmpty) {
        elements.slotsEmpty.classList.remove('hidden');
        elements.slotsEmpty.textContent = 'Something went wrong fetching availability. Try refreshing.';
      }
    } finally {
      // Only clear controller if it belongs to latest request
      if (requestId === slotsFetchToken && slotsAbortController === controller) {
        slotsAbortController = null;
      }
      state.isLoadingSlots = false;
    }
  }

  function setFocusDate(date) {
    state.focusDate = date instanceof Date ? date : new Date(date);
    if (elements.focusDateInput) {
      elements.focusDateInput.value = toDateOnly(state.focusDate) || '';
    }
  }

  function applyFilters({ refresh = true } = {}) {
    state.filters.providerId = elements.providerSelect?.value || '';
    state.filters.serviceId = elements.serviceSelect?.value || '';
    updateFilterFeedback();
    if (refresh) {
      refreshCounts();
      refreshSlots();
      state.calendar?.refetchEvents();
    }
  }

  function clearFilters() {
    if (elements.providerSelect) elements.providerSelect.value = '';
    if (elements.serviceSelect) elements.serviceSelect.value = '';
    applyFilters({ refresh: true });
  }

  function closeModal() {
    if (!elements.modal) return;
    elements.modal.classList.remove('flex');
    elements.modal.classList.add('hidden');
    elements.modal.setAttribute('aria-hidden', 'true');
    elements.modalBody.textContent = '';
    elements.modalFooter.textContent = '';
    if (document.body) {
      document.body.classList.remove('overflow-hidden');
    }
    if (modalReturnFocus && typeof modalReturnFocus.focus === 'function') {
      modalReturnFocus.focus({ preventScroll: true });
    }
    modalReturnFocus = null;
  }

  function openModal({ title, body, actions = [], focusSelector } = {}) {
    if (!elements.modal) return;
    modalReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    elements.modalTitle.textContent = title;
    elements.modalBody.innerHTML = '';
    if (typeof body === 'string') {
      elements.modalBody.innerHTML = body;
    } else if (body instanceof Node) {
      elements.modalBody.appendChild(body);
    }
    elements.modalFooter.innerHTML = '';
    actions.forEach((action) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = action.label;
      btn.className = action.className || 'inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700';
      btn.addEventListener('click', async () => {
        if (action.onClick) {
          btn.disabled = true;
          try {
            await action.onClick();
          } finally {
            btn.disabled = false;
          }
        }
      });
      elements.modalFooter.appendChild(btn);
    });
    elements.modal.classList.add('flex');
    elements.modal.classList.remove('hidden');
    elements.modal.setAttribute('aria-hidden', 'false');
    if (document.body) {
      document.body.classList.add('overflow-hidden');
    }

    const focusTarget =
      (focusSelector ? elements.modal.querySelector(focusSelector) : null) ||
      elements.modal.querySelector('[data-initial-focus]') ||
      elements.modal.querySelector(FOCUSABLE_SELECTOR) ||
      elements.modal;

    if (focusTarget && typeof focusTarget.focus === 'function') {
      focusTarget.focus({ preventScroll: true });
    }
  }

  function broadcastChange() {
    document.dispatchEvent(new CustomEvent('appointment:changed'));
  }

  async function showAppointment(id) {
    try {
      setStatus('Loading appointment details…');
      const appointment = await service.getAppointment(id);
      const start = parseISO(appointment.start);
      const end = parseISO(appointment.end);
      const body = document.createElement('div');
      body.className = 'space-y-4';
      body.innerHTML = `
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">${start.toLocaleString()} – ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
          <h4 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">${appointment.title || 'Appointment'}</h4>
          <span class="mt-2 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs ${classForStatus((appointment.status || '').toLowerCase())}">
            <span class="material-symbols-outlined text-sm">circle</span>
            ${appointment.status || 'booked'}
          </span>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div class="rounded-2xl border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Provider</p>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${appointment.providerId ? `#${appointment.providerId}` : '—'}</p>
          </div>
          <div class="rounded-2xl border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Service</p>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${appointment.serviceId ? `#${appointment.serviceId}` : '—'}</p>
          </div>
        </div>`;
      const actions = [
        {
          label: 'Cancel Appointment',
          className: 'inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700',
          onClick: async () => {
            if (!confirm('Cancel this appointment?')) return;
            await service.cancelAppointment(id);
            closeModal();
            setStatus('Appointment cancelled', 'success');
            state.calendar?.refetchEvents();
            refreshCounts();
            refreshSlots();
            broadcastChange();
          },
        },
        {
          label: 'Reschedule',
          className: 'inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800',
          onClick: async () => {
            closeModal();
            openRescheduleModal(appointment);
          },
        },
        {
          label: 'Close',
          className: 'inline-flex items-center gap-2 rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100',
          onClick: async () => {
            closeModal();
          },
        },
      ];
      openModal({ title: 'Appointment Details', body, actions });
    } catch (error) {
      console.error('[scheduler] Failed to load appointment', error);
      setStatus('Could not load appointment details.', 'error');
    }
  }

  function openCreateModal(prefill = {}) {
    const body = document.createElement('form');
    body.className = 'space-y-4';
    body.innerHTML = `
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Full Name
          <input name="name" type="text" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" required />
        </label>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Email
          <input name="email" type="email" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" required />
        </label>
      </div>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Provider ID
          <input name="providerId" type="number" min="1" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" value="${state.filters.providerId || ''}" required />
        </label>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Service ID
          <input name="serviceId" type="number" min="1" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" value="${state.filters.serviceId || ''}" required />
        </label>
      </div>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Date
          <input name="date" type="date" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" value="${prefill.start ? prefill.start.slice(0, 10) : (elements.focusDateInput?.value || '')}" required />
        </label>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Start Time
          <input name="start" type="time" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" value="${prefill.start ? prefill.start.slice(11, 16) : ''}" required />
        </label>
      </div>
      <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Notes
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
      </label>`;

    function getFormPayload() {
      const formData = new FormData(body);
      return {
        name: formData.get('name'),
        email: formData.get('email'),
        providerId: formData.get('providerId'),
        serviceId: formData.get('serviceId'),
        date: formData.get('date'),
        start: formData.get('start'),
        notes: formData.get('notes'),
      };
    }

    openModal({
      title: 'Book Appointment',
      body,
      actions: [
        {
          label: 'Create Appointment',
          onClick: async () => {
            const payload = getFormPayload();
            await service.createAppointment(payload);
            closeModal();
            setStatus('Appointment created', 'success');
            state.calendar?.refetchEvents();
            refreshCounts();
            refreshSlots();
            broadcastChange();
          },
        },
        {
          label: 'Cancel',
          className: 'inline-flex items-center gap-2 rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100',
          onClick: async () => closeModal(),
        },
      ],
    });
  }

  function openRescheduleModal(appointment) {
    const body = document.createElement('form');
    body.className = 'space-y-4';
    const startDate = appointment.start?.slice(0, 10) || '';
    const startTime = appointment.start?.slice(11, 16) || '';
    body.innerHTML = `
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Date
          <input name="date" type="date" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" value="${startDate}" required />
        </label>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Time
          <input name="time" type="time" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" value="${startTime}" required />
        </label>
      </div>`;

    openModal({
      title: 'Reschedule Appointment',
      body,
      actions: [
        {
          label: 'Save Changes',
          onClick: async () => {
            const formData = new FormData(body);
            const date = formData.get('date');
            const time = formData.get('time');
            if (!date || !time) return;
            const nextStart = `${date} ${time}:00`;
            await service.updateAppointment(appointment.id, { start: nextStart });
            closeModal();
            setStatus('Appointment rescheduled', 'success');
            state.calendar?.refetchEvents();
            refreshCounts();
            refreshSlots();
            broadcastChange();
          },
        },
        {
          label: 'Cancel',
          className: 'inline-flex items-center gap-2 rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100',
          onClick: async () => closeModal(),
        },
      ],
    });
  }

  elements.modalClose?.addEventListener('click', closeModal);
  elements.modal?.addEventListener('click', (event) => {
    if (event.target === elements.modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !elements.modal?.classList.contains('hidden')) {
      closeModal();
    }
  });

  setFocusDate(new Date());

  // Fetch localization settings before initializing calendar
  let localizationSettings = {
    time_format: '24h',
    first_day: 'Monday',
    language: 'English',
    timezone: 'local'
  };
  
  try {
    const settings = await service.getBusinessSettings();
    localizationSettings = {
      time_format: settings.time_format || '24h',
      first_day: settings.first_day || 'Monday',
      language: settings.language || 'English',
      timezone: settings.timezone || 'local'
    };
    console.log('[scheduler] Localization settings loaded:', localizationSettings);
  } catch (error) {
    console.warn('[scheduler] Failed to load localization settings, using defaults:', error);
  }

  // Map first day of week to FullCalendar value (0 = Sunday, 1 = Monday)
  const firstDay = localizationSettings.first_day === 'Sunday' ? 0 : 1;
  
  // Map time format to FullCalendar settings
  const hour12 = localizationSettings.time_format === '12h';
  const hourFormat = hour12 ? 'numeric' : '2-digit';
  const meridiem = hour12 ? 'short' : false;

  // Map language label to FullCalendar locale code
  const languageKey = (localizationSettings.language || '').toString().trim().toLowerCase();
  const localeMap = {
    'english': 'en',
    'portuguese-br': 'pt-br',
    'spanish': 'es',
  };
  const fcLocale = localeMap[languageKey] || 'en';
  const timezone = (localizationSettings.timezone && localizationSettings.timezone !== 'Automatic')
    ? localizationSettings.timezone
    : 'local';

  const calendar = new Calendar(elements.calendarRoot, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: false,
    selectable: true,
    height: 'auto',
    expandRows: false,
    locales: [esLocale, ptBrLocale],
    // Localization settings
    firstDay: firstDay,
    timeZone: timezone,
    locale: fcLocale,
    
    // Time slot configuration
    slotMinTime,
    slotMaxTime,
    slotDuration: '00:30:00', // 30-minute slots
    slotLabelInterval: '01:00:00', // Show hour labels
    snapDuration: '00:15:00', // Snap to 15-minute increments
    slotLabelFormat: {
      hour: hourFormat,
      minute: '2-digit',
      omitZeroMinute: false,
      meridiem: meridiem,
      hour12: hour12
    },
    
    // Event display configuration
    eventMinHeight: 100, // Minimum event height in pixels for better readability
    eventShortHeight: 50, // Height threshold for compact display
    slotEventOverlap: false, // Prevent overlapping - display side-by-side
    eventMaxStack: 3, // Maximum number of events to stack before showing "+more"
    eventTimeFormat: {
      hour: hourFormat,
      minute: '2-digit',
      meridiem: meridiem,
      hour12: hour12
    },
    
    views: {
      dayGridMonth: {
        expandRows: true,
        dayMaxEventRows: 6,
      },
      timeGridWeek: {
        slotLabelInterval: '01:00:00',
        slotDuration: '00:30:00',
        eventMinHeight: 100,
        dayHeaderFormat: { weekday: 'short', month: 'numeric', day: 'numeric' },
        slotEventOverlap: false, // Side-by-side for week view
      },
      timeGridDay: {
        slotLabelInterval: '01:00:00',
        slotDuration: '00:30:00',
        eventMinHeight: 100,
        slotEventOverlap: false, // Side-by-side for day view
      },
    },
    eventSources: [
      // Appointments
      async (info, success, failure) => {
        if (eventsAbortController) eventsAbortController.abort();
        const controller = new AbortController();
        eventsAbortController = controller;
        const requestId = ++eventsFetchToken;
        setStatus('Loading appointments…');
        try {
          const items = await service.getAppointments({
            start: toDateOnly(info.startStr),
            end: toDateOnly(info.endStr),
          }, state.filters, { signal: controller.signal });
          if (requestId !== eventsFetchToken) return;
          
          // Debug: Log first appointment to inspect data
          if (Array.isArray(items) && items.length > 0) {
            console.log('[scheduler] Sample appointment data:', {
              raw: items[0],
              startStr: info.startStr,
              endStr: info.endStr
            });
          }
          
          const events = Array.isArray(items) ? items.map(mapAppointmentToEvent) : [];
          
          // Debug: Log first mapped event
          if (events.length > 0) {
            console.log('[scheduler] Sample mapped event:', events[0]);
          }
          
          success(events);
          setStatus(`Showing ${events.length} appointment${events.length === 1 ? '' : 's'}.`);
          setTimeout(() => { if (!eventsAbortController) setStatus(''); }, 800);
        } catch (error) {
          if (controller.signal.aborted || error.name === 'AbortError') { setStatus(''); return; }
          if (requestId !== eventsFetchToken) return;
          console.error('[scheduler] Failed loading appointments', error);
          setStatus('Unable to load appointments.', 'error');
          failure(error);
        } finally {
          if (requestId === eventsFetchToken && eventsAbortController === controller) {
            eventsAbortController = null;
          }
        }
      },
      // Breaks as background events
      ...breakEvents.map(b => ({
        ...b,
        rendering: 'background',
        editable: false,
        allDay: false,
      })),
      // Block periods as background events
      ...blockEvents.map(b => ({
        ...b,
        rendering: 'background',
        editable: false,
        allDay: false,
      })),
    ],
    eventDidMount(arg) {
      const start = arg.event.start;
      const end = arg.event.end;
      console.log('[scheduler] Event mounted:', {
        id: arg.event.id,
        title: arg.event.title,
        startISO: start ? start.toISOString() : null,
        endISO: end ? end.toISOString() : null,
        timeText: arg.timeText,
        position: arg.el?.style?.top ?? null
      });
    },
    selectAllow: function(selectInfo) {
      // Prevent selection in breaks or block periods
      const start = selectInfo.start;
      const end = selectInfo.end;
      // Check breaks
      for (const b of breakEvents) {
        if (start && end && b.startTime && b.endTime) {
          const s = new Date(start);
          const e = new Date(end);
          const [bh, bm] = b.startTime.split(':');
          const [eh, em] = b.endTime.split(':');
          const breakStart = new Date(s); breakStart.setHours(bh, bm, 0, 0);
          const breakEnd = new Date(s); breakEnd.setHours(eh, em, 0, 0);
          if ((s < breakEnd && e > breakStart)) return false;
        }
      }
      // Check block periods
      for (const b of blockEvents) {
        if (start && end && b.start && b.end) {
          const s = new Date(start);
          const e = new Date(end);
          const blockStart = new Date(b.start);
          const blockEnd = new Date(b.end);
          if ((s < blockEnd && e > blockStart)) return false;
        }
      }
      return true;
    },
    eventClassNames(arg) {
      const { status, raw } = arg.event.extendedProps || {};
      const serviceId = raw?.serviceId;
      const providerId = raw?.providerId;
      return [
        `fc-xs-pill ${eventColorClass({ status, serviceId, providerId })}`
      ];
    },
    eventContent(arg) {
      const { status, raw } = arg.event.extendedProps || {};
      const dot = dotClass(status);
      
      // Build the event content with improved spacing and hierarchy
      const wrapper = document.createElement('div');
      wrapper.className = 'fc-event-details flex flex-col w-full';
      
      // Header row: status dot, title, and time
      const headerRow = document.createElement('div');
      headerRow.className = 'flex items-start justify-between gap-2 mb-2';
      
      const leftSide = document.createElement('div');
      leftSide.className = 'flex items-center gap-2 flex-1 min-w-0';
      
      const statusDot = document.createElement('span');
      statusDot.className = `fc-event-status-dot ${dot}`;
      leftSide.appendChild(statusDot);
      
      const titleEl = document.createElement('span');
      titleEl.className = 'font-bold truncate flex-1 text-sm';
      titleEl.textContent = arg.event.title || 'Appointment';
      leftSide.appendChild(titleEl);
      
      headerRow.appendChild(leftSide);
      
      if (arg.timeText) {
        const timeEl = document.createElement('span');
        timeEl.className = 'fc-event-time-text flex-shrink-0';
        timeEl.textContent = arg.timeText;
        headerRow.appendChild(timeEl);
      }
      
      wrapper.appendChild(headerRow);
      
      // Client name (prominent)
      if (raw?.name) {
        const clientEl = document.createElement('div');
        clientEl.className = 'fc-event-client';
        clientEl.textContent = raw.name;
        wrapper.appendChild(clientEl);
      }
      
      // Service name
      if (raw?.serviceName) {
        const serviceEl = document.createElement('div');
        serviceEl.className = 'fc-event-service';
        serviceEl.textContent = raw.serviceName;
        wrapper.appendChild(serviceEl);
      }
      
      // Provider name
      if (raw?.providerName) {
        const providerEl = document.createElement('div');
        providerEl.className = 'fc-event-provider';
        providerEl.textContent = `with ${raw.providerName}`;
        wrapper.appendChild(providerEl);
      }
      
      // Status badge (if present)
      if (status) {
        const statusBadge = document.createElement('div');
        statusBadge.className = 'fc-event-status-badge mt-2';
        statusBadge.innerHTML = `
          <span class="inline-block h-1.5 w-1.5 rounded-full ${dot}"></span>
          <span>${status}</span>
        `;
        wrapper.appendChild(statusBadge);
      }
      
      // Comprehensive tooltip for hover
      const tooltipParts = [
        arg.event.title,
        raw?.name ? `Client: ${raw.name}` : null,
        raw?.serviceName ? `Service: ${raw.serviceName}` : null,
        raw?.providerName ? `Provider: ${raw.providerName}` : null,
        status ? `Status: ${status}` : null,
        arg.timeText ? `Time: ${arg.timeText}` : null,
      ].filter(Boolean);
      
      wrapper.title = tooltipParts.join('\n');
      
      return { domNodes: [wrapper] };
    },
    datesSet(info) {
      state.viewType = info.view.type;
      state.view = state.viewType === 'timeGridDay' ? 'day' : (state.viewType === 'timeGridWeek' ? 'week' : 'month');
      const rangeTitle = formatRangeTitle(info.view.type, info.view.currentStart, info.view.currentEnd);
      if (elements.activeRange) elements.activeRange.textContent = rangeTitle;
      updateViewButtons(state.view);
      if (!state.hasInitialFocusSync) {
        state.hasInitialFocusSync = true;
        setFocusDate(new Date());
      } else {
        setFocusDate(info.view.currentStart);
      }
      // Keep counts, slots, and events in sync when navigating between dates/views
      refreshCounts();
      refreshSlots();
      state.calendar?.refetchEvents();
    },
    eventClick(info) {
      info.jsEvent.preventDefault();
      const id = info.event.id;
      if (id) showAppointment(id);
    },
    select(info) {
      calendar.unselect();
      openCreateModal({ start: info.startStr, end: info.endStr });
    },
  });

  calendar.render();
  state.calendar = calendar;
  updateViewButtons(state.view);

  elements.summaryButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.targetView;
      if (!target) return;
      const viewType = VIEW_TYPE_MAP[target] || 'dayGridMonth';
      const needsChange = calendar.view?.type !== viewType;
      if (needsChange) {
        calendar.changeView(viewType, new Date());
      } else {
        calendar.gotoDate(new Date());
      }
      // Keep everything in sync
      refreshCounts();
      refreshSlots();
      calendar.refetchEvents();
    });

    btn.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        btn.click();
      }
    });
  });

  elements.viewButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const view = btn.dataset.view;
      if (!view) return;
      const viewType = VIEW_TYPE_MAP[view] || 'dayGridMonth';
      if (calendar.view?.type === viewType) return;
      calendar.changeView(viewType);
    });
  });

  elements.todayButton?.addEventListener('click', () => {
    calendar.today();
    // datesSet will trigger, but also refresh immediately to avoid UI lag
    refreshCounts();
    refreshSlots();
    calendar.refetchEvents();
  });

  elements.prevButton?.addEventListener('click', () => {
    calendar.prev();
    refreshCounts();
    refreshSlots();
    calendar.refetchEvents();
  });

  elements.nextButton?.addEventListener('click', () => {
    calendar.next();
    refreshCounts();
    refreshSlots();
    calendar.refetchEvents();
  });

  elements.applyButton?.addEventListener('click', () => {
    const focusDate = elements.focusDateInput?.value;
    if (focusDate) {
      calendar.gotoDate(focusDate);
    }
    applyFilters({ refresh: true });
  });

  elements.clearButton?.addEventListener('click', () => {
    setFocusDate(new Date());
    clearFilters();
  });

  // Live filter changes (without requiring Apply) should also refresh
  elements.providerSelect?.addEventListener('change', () => applyFilters({ refresh: true }));
  elements.serviceSelect?.addEventListener('change', () => applyFilters({ refresh: true }));
  elements.focusDateInput?.addEventListener('change', (e) => {
    const value = e.target?.value;
    if (value) {
      calendar.gotoDate(value);
    }
    // datesSet handler will ensure refetch & counts/slots refresh
  });

  elements.refreshButton?.addEventListener('click', () => {
    refreshCounts();
    refreshSlots();
    calendar.refetchEvents();
  });

  elements.newButton?.addEventListener('click', () => openCreateModal());

  applyFilters({ refresh: false });
  refreshCounts();
  refreshSlots();
  calendar.refetchEvents();
}

ready(() => {
  bootSchedulerDashboard();
});

document.addEventListener('spa:navigated', () => {
  setTimeout(() => bootSchedulerDashboard(), 0);
});
