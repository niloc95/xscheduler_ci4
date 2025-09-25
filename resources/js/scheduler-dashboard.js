import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import '../css/fullcalendar-overrides.css';
import createSchedulerService from './services/scheduler-service';

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
  if (value instanceof Date) {
    return value.toISOString();
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) return null;
    return trimmed.includes('T') ? trimmed : trimmed.replace(' ', 'T');
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
  return {
    id: String(item.id ?? ''),
    title: item.title || 'Appointment',
    start: normalizedStart ?? item.start ?? null,
    end: normalizedEnd ?? item.end ?? null,
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

function bootSchedulerDashboard() {
  const root = document.getElementById('scheduler-dashboard');
  if (!root) return;

  // Avoid double-boot in SPA transitions
  if (root.dataset.booted === '1') {
    return;
  }
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
  let currentStatusVariant = null;

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

  if (!elements.calendarRoot) {
    console.warn('[scheduler] Missing calendar root');
    return;
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

    try {
      const response = await service.getSlots({
        providerId,
        serviceId,
        date: elements.focusDateInput?.value,
      });
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
      console.warn('[scheduler] Failed loading slots', error);
      if (elements.slotsCaption) elements.slotsCaption.textContent = 'Unable to load slot data.';
      if (elements.slotsEmpty) {
        elements.slotsEmpty.classList.remove('hidden');
        elements.slotsEmpty.textContent = 'Something went wrong fetching availability. Try refreshing.';
      }
    } finally {
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
    elements.modal.classList.add('hidden');
    elements.modalBody.textContent = '';
    elements.modalFooter.textContent = '';
  }

  function openModal({ title, body, actions = [] }) {
    if (!elements.modal) return;
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
    elements.modal.classList.remove('hidden');
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

  setFocusDate(new Date());

  const calendar = new Calendar(elements.calendarRoot, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: false,
    selectable: true,
    height: 'auto',
  expandRows: false,
    views: {
      dayGridMonth: {
        expandRows: true,
        dayMaxEventRows: 6,
      },
    },
    eventTimeFormat: { hour: '2-digit', minute: '2-digit' },
    events: async (info, success, failure) => {
      if (eventsAbortController) {
        eventsAbortController.abort();
      }
      const controller = new AbortController();
      eventsAbortController = controller;
      const requestId = ++eventsFetchToken;

      setStatus('Loading appointments…');

      try {
        const items = await service.getAppointments({
          start: toDateOnly(info.startStr),
          end: toDateOnly(info.endStr),
        }, state.filters, { signal: controller.signal });

        if (requestId !== eventsFetchToken) {
          return;
        }

        const events = Array.isArray(items) ? items.map(mapAppointmentToEvent) : [];
        success(events);
        setStatus(`Showing ${events.length} appointment${events.length === 1 ? '' : 's'}.`);
      } catch (error) {
        if (controller.signal.aborted || error.name === 'AbortError') {
          return;
        }
        if (requestId !== eventsFetchToken) {
          return;
        }
        console.error('[scheduler] Failed loading appointments', error);
        setStatus('Unable to load appointments.', 'error');
        failure(error);
      } finally {
        if (requestId === eventsFetchToken && eventsAbortController === controller) {
          eventsAbortController = null;
        }
      }
    },
    eventClassNames(arg) {
      const status = (arg.event.extendedProps?.status || '').toLowerCase();
      return [`inline-flex w-full items-center gap-2 rounded-xl border px-2 py-1 text-xs font-medium ${classForStatus(status)}`];
    },
    eventContent(arg) {
      const status = (arg.event.extendedProps?.status || '').toLowerCase();
      const dot = dotClass(status);
      const start = arg.timeText ? `<span class="text-[10px] opacity-80">${arg.timeText}</span>` : '';
      const wrapper = document.createElement('div');
      wrapper.className = 'flex w-full items-center gap-2';
      wrapper.innerHTML = `
        <span class="h-2 w-2 rounded-full ${dot}"></span>
        <span class="flex-1 truncate">${arg.event.title}</span>
        ${start}`;
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
      refreshCounts();
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
  });

  elements.prevButton?.addEventListener('click', () => {
    calendar.prev();
  });

  elements.nextButton?.addEventListener('click', () => {
    calendar.next();
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
