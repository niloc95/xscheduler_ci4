/**
 * Shared Time Slots UI module
 * Unifies service loading and slot grid rendering for create/edit flows.
 */

/**
 * Initialize the time slots UI
 * @param {Object} options
 * @param {string} options.providerSelectId
 * @param {string} options.serviceSelectId
 * @param {string} options.dateInputId
 * @param {string} options.timeInputId
 * @param {string} [options.gridId="time-slots-grid"]
 * @param {string} [options.loadingId="time-slots-loading"]
 * @param {string} [options.emptyId="time-slots-empty"]
 * @param {string} [options.errorId="time-slots-error"]
 * @param {string} [options.errorMsgId="time-slots-error-message"]
 * @param {string} [options.promptId="time-slots-prompt"]
 * @param {string|number} [options.excludeAppointmentId]
 * @param {string|number} [options.preselectServiceId]
 * @param {string} [options.initialTime]
 * @param {(time:string)=>void} [options.onTimeSelected]
 */
export function initTimeSlotsUI(options) {
  const {
    providerSelectId,
    serviceSelectId,
    dateInputId,
    timeInputId,
    gridId = 'time-slots-grid',
    loadingId = 'time-slots-loading',
    emptyId = 'time-slots-empty',
    errorId = 'time-slots-error',
    errorMsgId = 'time-slots-error-message',
    promptId = 'time-slots-prompt',
    excludeAppointmentId,
    preselectServiceId,
    initialTime,
    onTimeSelected
  } = options || {};

  const providerSelect = document.getElementById(providerSelectId);
  const serviceSelect = document.getElementById(serviceSelectId);
  const dateInput = document.getElementById(dateInputId);
  const timeInput = document.getElementById(timeInputId);

  if (!providerSelect || !serviceSelect || !dateInput || !timeInput) {
    console.warn('[time-slots-ui] Missing required elements');
    return;
  }

  const el = {
    grid: document.getElementById(gridId),
    loading: document.getElementById(loadingId),
    empty: document.getElementById(emptyId),
    error: document.getElementById(errorId),
    errorMsg: document.getElementById(errorMsgId),
    prompt: document.getElementById(promptId),
  };

  const CALENDAR_CACHE_TTL = 60 * 1000; // 1 minute client-side cache to avoid hammering API
  const calendarCache = new Map();
  let calendarFetchToken = 0;

  function buildCalendarKey(providerId, serviceId, startDate) {
    const keyParts = [providerId, serviceId, startDate || 'auto'];
    if (excludeAppointmentId) {
      keyParts.push(`exclude:${excludeAppointmentId}`);
    }
    return keyParts.join('|');
  }

  function normalizeCalendar(payload) {
    if (!payload || typeof payload !== 'object') {
      return {
        availableDates: [],
        slotsByDate: {},
        defaultDate: null,
        startDate: null,
        endDate: null,
        timezone: null,
        generatedAt: null,
      };
    }

    const availableDates = Array.isArray(payload.availableDates) ? [...payload.availableDates] : [];
    const rawSlots = (payload.slotsByDate && typeof payload.slotsByDate === 'object') ? payload.slotsByDate : {};
    const slotsByDate = Object.keys(rawSlots).reduce((carry, date) => {
      const slotList = Array.isArray(rawSlots[date]) ? rawSlots[date].map(slot => ({ ...slot })) : [];
      carry[date] = slotList;
      return carry;
    }, {});

    return {
      availableDates,
      slotsByDate,
      defaultDate: payload.default_date ?? payload.defaultDate ?? (availableDates[0] ?? null),
      startDate: payload.start_date ?? payload.startDate ?? null,
      endDate: payload.end_date ?? payload.endDate ?? null,
      timezone: payload.timezone ?? null,
      generatedAt: payload.generated_at ?? payload.generatedAt ?? null,
    };
  }

  function getCacheEntry(key) {
    const cached = calendarCache.get(key);
    if (!cached) {
      return null;
    }
    if (Date.now() - cached.fetchedAt > CALENDAR_CACHE_TTL) {
      calendarCache.delete(key);
      return null;
    }
    return cached;
  }

  async function fetchCalendar(providerId, serviceId, startDate, forceRefresh = false) {
    const key = buildCalendarKey(providerId, serviceId, startDate);
    const cached = forceRefresh ? null : getCacheEntry(key);
    if (cached) {
      return cached.data;
    }

    const token = ++calendarFetchToken;
    const params = new URLSearchParams({
      provider_id: providerId,
      service_id: serviceId,
      days: '60',
    });
    if (startDate) {
      params.append('start_date', startDate);
    }
    if (excludeAppointmentId) {
      params.append('exclude_appointment_id', String(excludeAppointmentId));
    }

    let response;
    try {
      response = await fetch(`/api/availability/calendar?${params.toString()}`, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
    } catch (networkError) {
      throw new Error('Unable to reach availability service. Check your connection.');
    }

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = payload?.error?.message || payload?.error || 'Failed to load availability calendar';
      throw new Error(message);
    }

    const normalized = normalizeCalendar(payload?.data ?? payload ?? {});
    if (token === calendarFetchToken) {
      calendarCache.set(key, { data: normalized, fetchedAt: Date.now() });
    }
    return normalized;
  }

  function ensureDateFromCalendar(calendar, desiredDate) {
    if (!calendar || !Array.isArray(calendar.availableDates) || calendar.availableDates.length === 0) {
      return { date: desiredDate || '', updated: false };
    }

    if (desiredDate && calendar.availableDates.includes(desiredDate)) {
      return { date: desiredDate, updated: false };
    }

    const fallback = calendar.defaultDate || calendar.availableDates[0];
    if (fallback) {
      dateInput.value = fallback;
      return { date: fallback, updated: true };
    }

    return { date: desiredDate || '', updated: false };
  }

  function slotValue(slot) {
    if (slot?.startFormatted) {
      return slot.startFormatted;
    }
    if (slot?.start) {
      try {
        const date = new Date(slot.start);
        if (!Number.isNaN(date.getTime())) {
          return date.toISOString().slice(11, 16);
        }
      } catch (_) {
        // ignore parsing error
      }
    }
    return '';
  }

  function slotLabel(slot) {
    if (slot?.label) {
      return slot.label;
    }
    const value = slotValue(slot);
    if (slot?.endFormatted) {
      return `${value} - ${slot.endFormatted}`;
    }
    return value || 'Available slot';
  }

  function getSlotsForDate(calendar, date) {
    if (!calendar || !calendar.slotsByDate) {
      return [];
    }
    return Array.isArray(calendar.slotsByDate[date]) ? calendar.slotsByDate[date] : [];
  }

  async function loadServices(providerId) {
    serviceSelect.innerHTML = '<option value="">Loading services...</option>';
    serviceSelect.disabled = true;

    if (!providerId) {
      serviceSelect.innerHTML = '<option value="">Select a provider first...</option>';
      serviceSelect.disabled = false;
      return;
    }

    try {
      const res = await fetch(`/api/v1/providers/${providerId}/services`);
      if (!res.ok) {
        console.error('[time-slots-ui] Service API error:', res.status);
        throw new Error('Failed to load services');
      }
      const result = await res.json();
      const services = result.data || [];

      if (services.length === 0) {
        serviceSelect.innerHTML = '<option value="">No services available for this provider</option>';
        serviceSelect.disabled = false;
        return;
      }

      serviceSelect.innerHTML = '<option value="">Select a service...</option>';
      let preselectFound = false;
      services.forEach(svc => {
        const opt = document.createElement('option');
        opt.value = svc.id;
        opt.textContent = `${svc.name} - $${parseFloat(svc.price).toFixed(2)}`;
        opt.dataset.duration = svc.durationMin || svc.duration_min;
        opt.dataset.price = svc.price;
        if (preselectServiceId && String(preselectServiceId) === String(svc.id)) {
          opt.selected = true;
          preselectFound = true;
        }
        serviceSelect.appendChild(opt);
      });
      serviceSelect.disabled = false;
      
      return preselectFound;
    } catch (e) {
      console.error('[time-slots-ui] Error loading services:', e);
      serviceSelect.innerHTML = '<option value="">Error loading services. Please try again.</option>';
      return false;
    }
  }

  function hideAllStates() {
    el.grid?.classList.add('hidden');
    el.loading?.classList.add('hidden');
    el.empty?.classList.add('hidden');
    el.error?.classList.add('hidden');
    el.prompt?.classList.add('hidden');
  }

  function selectButton(btn) {
    document.querySelectorAll('.time-slot-btn').forEach(b => {
      b.classList.remove('bg-blue-600','text-white','border-blue-600','dark:bg-blue-600','dark:border-blue-600');
      b.classList.add('bg-white','dark:bg-gray-700','text-gray-700','dark:text-gray-300','border-gray-300','dark:border-gray-600');
    });
    btn.classList.remove('bg-white','dark:bg-gray-700','text-gray-700','dark:text-gray-300','border-gray-300','dark:border-gray-600');
    btn.classList.add('bg-blue-600','text-white','border-blue-600','dark:bg-blue-600','dark:border-blue-600');
  }

  function attachSlotHandlers(btn) {
    btn.addEventListener('click', function() {
      selectButton(this);
      timeInput.value = this.dataset.time;
      if (typeof onTimeSelected === 'function') onTimeSelected(this.dataset.time);
      // small feedback toast
      const feedback = document.createElement('div');
      feedback.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center gap-2';
      feedback.innerHTML = '<span class="material-symbols-outlined text-sm">check_circle</span><span>Time slot selected: ' + this.dataset.time + '</span>';
      document.body.appendChild(feedback);
      setTimeout(() => feedback.remove(), 1500);
    });
  }

  function renderSlotGrid(slots) {
    el.grid.innerHTML = '';
    el.grid.className = 'grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2';

    const activeValue = timeInput.value || initialTime;
    let matchedInitial = false;
    slots.forEach(slot => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'time-slot-btn px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-500 dark:hover:border-blue-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500';
      btn.textContent = slotLabel(slot);
      const formattedValue = slotValue(slot);
      btn.dataset.time = formattedValue;
      btn.dataset.startTime = slot.start ?? slot.startTime ?? '';
      btn.dataset.endTime = slot.end ?? slot.endTime ?? '';
      attachSlotHandlers(btn);

      if (activeValue && formattedValue && activeValue === formattedValue) {
        matchedInitial = true;
        selectButton(btn);
        timeInput.value = formattedValue;
      }

      el.grid.appendChild(btn);
    });

    if (initialTime && !matchedInitial && excludeAppointmentId) {
      const currentBtn = document.createElement('button');
      currentBtn.type = 'button';
      currentBtn.className = 'time-slot-btn px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500';
      currentBtn.textContent = `${initialTime} (current)`;
      currentBtn.dataset.time = initialTime;
      currentBtn.title = 'Currently scheduled time (keep this time)';
      attachSlotHandlers(currentBtn);
      timeInput.value = initialTime;
      el.grid.prepend(currentBtn);
    }
  }

  async function loadSlots(forceRefresh = false) {
    hideAllStates();
    const providerId = providerSelect.value;
    const serviceId = serviceSelect.value;
    let date = dateInput.value;

    if (!providerId || !serviceId) {
      el.prompt?.classList.remove('hidden');
      timeInput.value = '';
      return;
    }

    const fetchStartDate = date || new Date().toISOString().slice(0, 10);
    el.loading?.classList.remove('hidden');

    try {
      let calendar = await fetchCalendar(providerId, serviceId, fetchStartDate, forceRefresh);
      const needsWindowExpansion = Boolean(date) && calendar.availableDates.length && !calendar.availableDates.includes(date);
      if (needsWindowExpansion) {
        calendar = await fetchCalendar(providerId, serviceId, date, true);
      }

      if (!calendar.availableDates.length) {
        el.loading?.classList.add('hidden');
        el.empty?.classList.remove('hidden');
        timeInput.value = '';
        return;
      }

      const { date: resolvedDate } = ensureDateFromCalendar(calendar, date);
      if (resolvedDate) {
        date = resolvedDate;
      }

      const slots = getSlotsForDate(calendar, date);
      renderSlotGrid(slots);

      if (!el.grid.children.length) {
        el.loading?.classList.add('hidden');
        el.empty?.classList.remove('hidden');
        timeInput.value = '';
        return;
      }

      el.loading?.classList.add('hidden');
      el.grid.classList.remove('hidden');
    } catch (err) {
      console.error('[time-slots-ui] Error loading time slots:', err);
      el.loading?.classList.add('hidden');
      el.error?.classList.remove('hidden');
      if (el.errorMsg) el.errorMsg.textContent = err.message || 'Failed to load time slots';
      timeInput.value = '';
    }
  }

  // Wire events
  providerSelect.addEventListener('change', async () => {
    await loadServices(providerSelect.value);
    timeInput.value = '';
    hideAllStates();
    el.prompt?.classList.remove('hidden');
    if (serviceSelect.value) {
      loadSlots(true);
    }
  });

  serviceSelect.addEventListener('change', () => {
    timeInput.value = '';
    loadSlots(true);
  });

  dateInput.addEventListener('change', () => {
    timeInput.value = '';
    loadSlots();
  });

  // Initial boot
  (async () => {
    // If provider is already chosen, load services and respect preselect
    if (providerSelect.value) {
      await loadServices(providerSelect.value);
      
      // After loading services, check if we should load slots
      // Small delay to allow DOM to update with service selection
      setTimeout(() => {
        if (serviceSelect.value) {
          loadSlots(true);
        }
      }, 100);
    } else {
      hideAllStates();
      el.prompt?.classList.remove('hidden');
    }
  })();
}
