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
      if (!res.ok) throw new Error('Failed to load services');
      const result = await res.json();
      const services = result.data || [];

      if (services.length === 0) {
        serviceSelect.innerHTML = '<option value="">No services available for this provider</option>';
        serviceSelect.disabled = false;
        return;
      }

      serviceSelect.innerHTML = '<option value="">Select a service...</option>';
      services.forEach(svc => {
        const opt = document.createElement('option');
        opt.value = svc.id;
        opt.textContent = `${svc.name} - $${parseFloat(svc.price).toFixed(2)}`;
        opt.dataset.duration = svc.durationMin || svc.duration_min;
        opt.dataset.price = svc.price;
        if (preselectServiceId && String(preselectServiceId) === String(svc.id)) {
          opt.selected = true;
        }
        serviceSelect.appendChild(opt);
      });
      serviceSelect.disabled = false;
    } catch (e) {
      console.error('[time-slots-ui] Error loading services:', e);
      serviceSelect.innerHTML = '<option value="">Error loading services. Please try again.</option>';
      serviceSelect.disabled = false;
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

  async function loadSlots() {
    hideAllStates();
    const providerId = providerSelect.value;
    const serviceId = serviceSelect.value;
    const date = dateInput.value;

    if (!providerId || !serviceId || !date) {
      el.prompt?.classList.remove('hidden');
      timeInput.value = '';
      return;
    }

    el.loading?.classList.remove('hidden');
    try {
      const qs = new URLSearchParams({ provider_id: providerId, service_id: serviceId, date });
      if (excludeAppointmentId) qs.append('exclude_appointment_id', String(excludeAppointmentId));
      const res = await fetch(`/api/availability/slots?${qs.toString()}`);
      if (!res.ok) throw new Error('Failed to load time slots');
      const result = await res.json();
      const slots = result?.data?.slots || [];

      el.loading?.classList.add('hidden');

      el.grid.innerHTML = '';
      el.grid.className = 'grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2';

      let matchedInitial = false;
      slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'time-slot-btn px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-500 dark:hover:border-blue-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500';
        btn.textContent = slot.start;
        btn.dataset.time = slot.start;
        btn.dataset.startTime = slot.startTime;
        btn.dataset.endTime = slot.endTime;
        attachSlotHandlers(btn);

        if (initialTime && initialTime === slot.start) {
          matchedInitial = true;
          selectButton(btn);
          timeInput.value = initialTime;
        }
        el.grid.appendChild(btn);
      });

      // Inject current time if editing and not available
      if (initialTime && !matchedInitial && excludeAppointmentId) {
        const currentBtn = document.createElement('button');
        currentBtn.type = 'button';
        currentBtn.className = 'time-slot-btn px-3 py-2 text-sm font-medium text-white bg-amber-600 border border-amber-600 rounded-lg hover:bg-amber-700 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500';
        currentBtn.textContent = `Current: ${initialTime}`;
        currentBtn.dataset.time = initialTime;
        currentBtn.title = 'Currently scheduled time';
        currentBtn.addEventListener('click', function() {
          document.querySelectorAll('.time-slot-btn').forEach(b => {
            b.classList.remove('bg-blue-600','text-white','border-blue-600','dark:bg-blue-600','dark:border-blue-600');
            b.classList.add('bg-white','dark:bg-gray-700','text-gray-700','dark:text-gray-300','border-gray-300','dark:border-gray-600');
          });
          this.classList.add('bg-amber-600','text-white','border-amber-600');
          timeInput.value = initialTime;
          if (typeof onTimeSelected === 'function') onTimeSelected(initialTime);
        });
        // Preselect current
        timeInput.value = initialTime;
        el.grid.prepend(currentBtn);
      }

      if (el.grid.children.length === 0) {
        el.empty?.classList.remove('hidden');
        timeInput.value = '';
      } else {
        el.grid.classList.remove('hidden');
      }
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
    // After services change, clear time selection and prompt user
    timeInput.value = '';
    hideAllStates();
    el.prompt?.classList.remove('hidden');
    // If a service is preselected after loading, attempt loading slots when date present
    if (serviceSelect.value && dateInput.value) loadSlots();
  });

  serviceSelect.addEventListener('change', () => {
    timeInput.value = '';
    loadSlots();
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
      // Need to check again after services are loaded and potentially preselected
      if (serviceSelect.value && dateInput.value) {
        await loadSlots();
      }
    }
  })();
}
