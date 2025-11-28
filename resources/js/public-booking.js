const root = document.getElementById('public-booking-root');

const FIELD_LABELS = {
  first_name: 'First name',
  last_name: 'Last name',
  email: 'Email address',
  phone: 'Phone number',
  address: 'Address',
  notes: 'Notes',
};

if (!root) {
  console.warn('[public-booking] Root element not found.');
} else {
  bootstrapPublicBooking();
}

function bootstrapPublicBooking() {
  const context = parseContext();
  const today = new Date();
  const initialAvailability = context.initialAvailability ?? null;
  const initialCalendar = context.initialCalendar ?? null;
  const calendarDates = Array.isArray(initialCalendar?.availableDates) ? initialCalendar.availableDates : [];
    const calendarDefaultDate = initialCalendar?.defaultDate ?? calendarDates[0] ?? null;
  const defaultDate = initialAvailability?.date ?? calendarDefaultDate ?? today.toISOString().slice(0, 10);
  const initialCalendarSlots = initialCalendar?.slotsByDate?.[defaultDate] ?? [];
  const hasInitialCalendar = calendarDates.length > 0 && initialCalendarSlots.length > 0;

  let state = {
    view: 'book',
    booking: createBookingDraft(context, defaultDate, initialAvailability, initialCalendar),
    manage: createManageDraft(context, defaultDate),
    csrf: {
      header: root.dataset.csrfHeader || 'X-CSRF-TOKEN',
      value: root.dataset.csrfValue || '',
      name: root.dataset.csrfName || 'csrf_token',
    },
  };

  render();
  if (state.booking.providerId && state.booking.serviceId) {
    if (hasInitialCalendar) {
      syncSlotsFromCalendar('booking');
    } else {
      fetchCalendar('booking');
    }
  }

  function setState(updater) {
    const focusSnapshot = snapshotFocus();
    state = typeof updater === 'function' ? updater(cloneState(state)) : { ...state, ...updater };
    render();
    restoreFocus(focusSnapshot);
  }

  function updateBooking(updater) {
    setState(prev => ({
      ...prev,
      booking: typeof updater === 'function'
        ? updater(prev.booking)
        : { ...prev.booking, ...updater },
    }));
  }

  function updateManage(updater) {
    setState(prev => ({
      ...prev,
      manage: typeof updater === 'function'
        ? updater(prev.manage)
        : { ...prev.manage, ...updater },
    }));
  }

  function updateManageForm(updater) {
    setState(prev => ({
      ...prev,
      manage: {
        ...prev.manage,
        formState: typeof updater === 'function'
          ? updater(prev.manage.formState)
          : { ...prev.manage.formState, ...updater },
      },
    }));
  }

  function getDraft(target) {
    return target === 'manage' ? state.manage.formState : state.booking;
  }

  function updateDraft(target, updater) {
    if (target === 'manage') {
      updateManageForm(updater);
      return;
    }
    updateBooking(updater);
  }

  function render() {
    const hero = renderHero(context);
    const tabs = renderTabs(state.view);
    const body = state.view === 'book'
      ? (state.booking.success
        ? renderSuccess(state.booking.success, context)
        : renderForm(state.booking, context))
      : renderManageSection(state.manage, context);

    root.innerHTML = `
      <div class="px-4 py-10 sm:px-6 lg:px-0">
        <div class="mx-auto w-full max-w-4xl space-y-6">
          ${hero}
          ${tabs}
          ${body}
        </div>
      </div>
    `;
    bindEvents();
  }

  function bindEvents() {
    bindViewToggles();

    if (state.view === 'book') {
      if (state.booking.success) {
        root.querySelector('[data-start-over]')?.addEventListener('click', resetBookingFlow);
        return;
      }
      bindFormControls('booking');
      return;
    }

    if (state.manage.stage === 'lookup') {
      bindLookupEvents();
      return;
    }

    if (state.manage.stage === 'reschedule') {
      bindFormControls('manage');
      root.querySelector('[data-manage-reset]')?.addEventListener('click', resetManageFlow);
      return;
    }

    if (state.manage.stage === 'success') {
      root.querySelector('[data-manage-start-over]')?.addEventListener('click', resetManageFlow);
    }
  }

  function bindViewToggles() {
    root.querySelectorAll('[data-view-toggle]').forEach(button => {
      button.addEventListener('click', () => {
        const nextView = button.getAttribute('data-view-toggle');
        if (!nextView || nextView === state.view) {
          return;
        }
        setState(prev => ({ ...prev, view: nextView }));
      });
    });
  }

  function bindFormControls(target) {
    const formSelector = target === 'booking' ? '#public-booking-form' : '#public-reschedule-form';
    const form = root.querySelector(formSelector);
    if (!form) {
      return;
    }

    const providerSelect = form.querySelector('[data-provider-select]');
    const serviceSelect = form.querySelector('[data-service-select]');
    const dateInput = form.querySelector('[data-date-input]');
    const dateSelect = form.querySelector('[data-date-select]');

    providerSelect?.addEventListener('change', (event) => handleProviderChange(event.target.value, target));
    serviceSelect?.addEventListener('change', (event) => handleServiceChange(event.target.value, target));
    dateInput?.addEventListener('change', (event) => handleDateChange(event.target.value, target));
    dateSelect?.addEventListener('change', (event) => handleDateChange(event.target.value, target));
    form.querySelectorAll('[data-date-pill]').forEach(button => {
      button.addEventListener('click', () => {
        const dateValue = button.getAttribute('data-date-pill');
        handleDateChange(dateValue, target);
      });
    });

    form.addEventListener('input', (event) => handleFormInput(event, target));
    form.addEventListener('change', (event) => handleFormInput(event, target));
    form.addEventListener('submit', (event) => handleSubmit(event, target));

    form.querySelectorAll('[data-slot-option]').forEach(button => {
      button.addEventListener('click', () => {
        const slotStart = button.getAttribute('data-slot-option');
        handleSlotSelection(slotStart, target);
      });
    });
  }

  function bindLookupEvents() {
    const form = root.querySelector('#booking-lookup-form');
    if (!form) {
      return;
    }
    form.addEventListener('input', handleLookupInput);
    form.addEventListener('submit', handleLookupSubmit);
  }

  function resetBookingFlow() {
    updateBooking(() => createBookingDraft(context, defaultDate, initialAvailability, initialCalendar));
    fetchCalendar('booking');
  }

  function resetManageFlow() {
    updateManage(() => createManageDraft(context, defaultDate));
  }

  function handleProviderChange(value, target = 'booking') {
    updateDraft(target, prev => ({
      ...prev,
      providerId: value,
      serviceId: '',
      services: [],
      servicesLoading: true,
      selectedSlot: null,
      slots: [],
      slotsError: '',
      prefetched: null,
      calendar: createCalendarState(),
      errors: { ...prev.errors, provider_id: undefined, service_id: undefined, slot_start: undefined },
    }));
    if (value) {
      fetchProviderServices(value, target);
    }
  }

  function handleServiceChange(value, target = 'booking') {
    updateDraft(target, prev => ({
      ...prev,
      serviceId: value,
      selectedSlot: null,
      slots: [],
      slotsError: '',
      prefetched: null,
      calendar: { ...createCalendarState(), loading: true, error: '' },
      errors: { ...prev.errors, service_id: undefined, slot_start: undefined },
    }));
    if (value) {
      fetchCalendar(target);
    }
  }

  async function fetchProviderServices(providerId, target = 'booking') {
    if (!providerId) {
      updateDraft(target, prev => ({
        ...prev,
        services: [],
        servicesLoading: false,
      }));
      return;
    }

    try {
      const response = await fetch(`/api/v1/providers/${providerId}/services`, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      updateCsrfFromHeaders(response.headers);
      const payload = await safeJson(response);

      if (!response.ok) {
        throw new Error(payload?.error ?? 'Unable to load services.');
      }

      const services = (payload?.data ?? payload ?? []).map(svc => ({
        id: svc.id,
        name: svc.name,
        duration: svc.duration_min ?? svc.durationMin ?? svc.duration,
        durationMinutes: svc.duration_min ?? svc.durationMin ?? svc.duration,
        price: svc.price,
        formattedPrice: svc.price ? `$${parseFloat(svc.price).toFixed(2)}` : '',
      }));

      updateDraft(target, prev => ({
        ...prev,
        services,
        servicesLoading: false,
      }));
    } catch (error) {
      console.error('[public-booking] Failed to fetch services:', error);
      updateDraft(target, prev => ({
        ...prev,
        services: [],
        servicesLoading: false,
      }));
    }
  }

  function handleDateChange(value, target = 'booking') {
    if (!value) {
      return;
    }
    updateDraft(target, prev => ({
      ...prev,
      errors: { ...prev.errors, slot_start: undefined },
    }));

    const draft = getDraft(target);
    const availableDates = draft.calendar?.availableDates ?? [];
    if (availableDates.includes(value)) {
      syncSlotsFromCalendar(target, value);
      return;
    }

    updateDraft(target, prev => ({
      ...prev,
      appointmentDate: value,
      selectedSlot: null,
      slots: [],
      slotsError: '',
      prefetched: null,
    }));
    fetchSlots(target, { forceRemote: true });
  }

  function handleSlotSelection(slotStart, target = 'booking') {
    if (!slotStart) {
      return;
    }
    const draft = getDraft(target);
    const slot = draft.slots.find(item => item.start === slotStart);
    if (!slot || draft.submitting) {
      return;
    }
    updateDraft(target, prev => ({
      ...prev,
      selectedSlot: slot,
      errors: { ...prev.errors, slot_start: undefined },
    }));
  }

  function handleLookupInput(event) {
    const { name } = event.target;
    if (!name) {
      return;
    }
    updateManage(prev => ({
      ...prev,
      lookupForm: {
        ...prev.lookupForm,
        [name]: event.target.value,
      },
      lookupErrors: { ...prev.lookupErrors, [name]: undefined, contact: undefined },
      lookupError: '',
    }));
  }

  async function handleLookupSubmit(event) {
    event.preventDefault();
    if (state.manage.lookupLoading) {
      return;
    }

    const form = state.manage.lookupForm;
    const token = (form.token ?? '').trim();
    const email = (form.email ?? '').trim();
    const phone = (form.phone ?? '').trim();
    const errors = {};

    if (!token) {
      errors.token = 'Enter your confirmation token';
    }
    if (!email && !phone) {
      errors.contact = 'Provide the email or phone used on the booking.';
    }

    if (Object.keys(errors).length > 0) {
      updateManage(prev => ({ ...prev, lookupErrors: { ...prev.lookupErrors, ...errors } }));
      return;
    }

    updateManage(prev => ({ ...prev, lookupLoading: true, lookupError: '', lookupErrors: {} }));

    try {
      const params = new URLSearchParams();
      if (email) {
        params.set('email', email);
      }
      if (phone) {
        params.set('phone', phone);
      }
      const query = params.toString();
      const url = query ? `/public/booking/${encodeURIComponent(token)}?${query}` : `/public/booking/${encodeURIComponent(token)}`;

      const response = await fetch(url, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      updateCsrfFromHeaders(response.headers);
      const data = await safeJson(response);

      if (!response.ok) {
        const details = data?.details ?? {};
        throw new SubmissionError(data?.error ?? 'Unable to locate that booking.', details);
      }

      updateManage(prev => ({ ...prev, lookupLoading: false }));
      enterRescheduleStage(data?.data, { email, phone });
    } catch (error) {
      if (error instanceof SubmissionError) {
        updateManage(prev => ({
          ...prev,
          lookupLoading: false,
          lookupError: error.message,
          lookupErrors: { ...prev.lookupErrors, ...error.details },
        }));
        return;
      }

      updateManage(prev => ({
        ...prev,
        lookupLoading: false,
        lookupError: error.message ?? 'Unable to locate that booking.',
      }));
    }
  }

  function enterRescheduleStage(appointment, contact = {}) {
    if (!appointment) {
      updateManage(prev => ({ ...prev, lookupError: 'We could not load that booking. Please try again.' }));
      return;
    }

    const startDate = appointment.start ? new Date(appointment.start) : null;
    const appointmentDate = startDate && !Number.isNaN(startDate.getTime())
      ? startDate.toISOString().slice(0, 10)
      : defaultDate;
    const slotLabel = appointment.start ? formatSlotSummary({ start: appointment.start, end: appointment.end }) : '';

    updateManage(prev => ({
      ...prev,
      stage: 'reschedule',
      appointment,
      contact: {
        email: contact.email ?? prev.contact?.email ?? '',
        phone: contact.phone ?? prev.contact?.phone ?? '',
      },
      lookupError: '',
      lookupErrors: {},
      success: null,
    }));

    updateManageForm(prev => ({
      ...prev,
      providerId: String(appointment.provider_id ?? ''),
      serviceId: String(appointment.service_id ?? ''),
      appointmentDate,
      selectedSlot: appointment.start ? { start: appointment.start, end: appointment.end, label: slotLabel } : null,
      form: {
        ...prev.form,
        notes: appointment.notes ?? prev.form.notes ?? '',
        email: prev.form.email || contact.email || '',
        phone: prev.form.phone || contact.phone || '',
      },
      slots: [],
      slotsError: '',
      errors: {},
      globalError: '',
      submitting: false,
      calendar: createCalendarState(),
    }));

    if (state.view !== 'manage') {
      setState(prev => ({ ...prev, view: 'manage' }));
    }

    fetchCalendar('manage', { preferredDate: appointmentDate });
  }

  function handleFormInput(event, target = 'booking') {
    const { name, type } = event.target;
    if (!name || ['provider_id', 'service_id', 'appointment_date'].includes(name)) {
      return;
    }
    const value = type === 'checkbox' ? (event.target.checked ? '1' : '0') : event.target.value;
    updateDraft(target, prev => ({
      ...prev,
      form: {
        ...prev.form,
        [name]: value,
      },
      errors: { ...prev.errors, [name]: undefined },
    }));
  }

  async function handleSubmit(event, target = 'booking') {
    event.preventDefault();
    const draft = getDraft(target);

    if (draft.submitting) {
      return;
    }

    if (!draft.providerId || !draft.serviceId) {
      updateDraft(target, prev => ({
        ...prev,
        errors: {
          ...prev.errors,
          provider_id: draft.providerId ? undefined : 'Select a provider',
          service_id: draft.serviceId ? undefined : 'Select a service',
        },
      }));
      return;
    }

    if (!draft.selectedSlot) {
      updateDraft(target, prev => ({
        ...prev,
        errors: { ...prev.errors, slot_start: 'Choose an available time before continuing.' },
      }));
      return;
    }

    updateDraft(target, prev => ({ ...prev, submitting: true, globalError: '', errors: { ...prev.errors } }));

    try {
      const contactExtras = target === 'manage' ? state.manage.contact ?? {} : {};
      const payload = buildPayload(draft, contactExtras);
      let endpoint = '/public/booking';
      let method = 'POST';

      if (target === 'manage') {
        const token = state.manage.appointment?.token;
        if (!token) {
          throw new Error('Missing appointment token.');
        }
        endpoint = `/public/booking/${encodeURIComponent(token)}`;
        method = 'PATCH';
      }

      const response = await fetch(endpoint, {
        method,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(state.csrf.value ? { [state.csrf.header]: state.csrf.value } : {}),
        },
        body: JSON.stringify(payload),
      });

      updateCsrfFromHeaders(response.headers);
      const data = await safeJson(response);

      if (!response.ok) {
        const details = data?.details ?? {};
        throw new SubmissionError(
          data?.error ?? (target === 'booking' ? 'Unable to save your booking.' : 'Unable to update the booking.'),
          details
        );
      }

      if (target === 'booking') {
        updateBooking(prev => ({
          ...prev,
          submitting: false,
          success: data?.data ?? null,
          globalError: '',
        }));
      } else {
        updateManageForm(prev => ({
          ...prev,
          submitting: false,
          globalError: '',
        }));
        updateManage(prev => ({
          ...prev,
          stage: 'success',
          success: data?.data ?? null,
          appointment: data?.data ?? prev.appointment,
          contact: {
            email: draft.form.email ?? prev.contact?.email ?? '',
            phone: draft.form.phone ?? prev.contact?.phone ?? '',
          },
        }));
      }
    } catch (error) {
      if (error instanceof SubmissionError) {
        updateDraft(target, prev => ({
          ...prev,
          submitting: false,
          globalError: error.message,
          errors: { ...prev.errors, ...error.details },
        }));
        return;
      }

      updateDraft(target, prev => ({
        ...prev,
        submitting: false,
        globalError: error.message ?? 'Something went wrong. Please try again.',
      }));
    }
  }

  async function fetchCalendar(target = 'booking', options = {}) {
    const { preferredDate = null } = options;
    const draft = getDraft(target);

    if (!draft.providerId || !draft.serviceId) {
      updateDraft(target, prev => ({
        ...prev,
        calendar: createCalendarState(),
        slots: [],
        slotsError: '',
        selectedSlot: null,
        prefetched: null,
      }));
      return;
    }

    updateDraft(target, prev => ({
      ...prev,
      calendar: { ...createCalendarState(), loading: true, error: '' },
      slots: [],
      slotsError: '',
      selectedSlot: null,
      prefetched: null,
      slotsLoading: true,
    }));

    const query = new URLSearchParams({
      provider_id: draft.providerId,
      service_id: draft.serviceId,
      days: '60',
    });

    try {
      const response = await fetch(`/public/booking/calendar?${query.toString()}`, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      updateCsrfFromHeaders(response.headers);
      const payload = await safeJson(response);

      if (!response.ok) {
        throw new Error(payload?.error ?? 'Unable to load availability.');
      }

      const calendarPayload = payload?.data ?? payload ?? {};
      updateDraft(target, prev => {
        const normalizedCalendar = { ...createCalendarState(calendarPayload), loading: false, error: '' };
        const selection = syncCalendarSelection(prev, normalizedCalendar, preferredDate);
        return {
          ...prev,
          ...selection,
          calendar: normalizedCalendar,
          slotsLoading: false,
        };
      });
    } catch (error) {
      updateDraft(target, prev => ({
        ...prev,
        calendar: { ...prev.calendar, loading: false, error: error.message ?? 'Unable to load availability.' },
        slotsLoading: false,
      }));
    }
  }

  function syncSlotsFromCalendar(target = 'booking', preferredDate = null) {
    const draft = getDraft(target);
    if (!draft.calendar || !Array.isArray(draft.calendar.availableDates) || !draft.calendar.availableDates.length) {
      return;
    }

    updateDraft(target, prev => {
      const normalizedCalendar = {
        ...createCalendarState(prev.calendar),
        loading: prev.calendar?.loading ?? false,
        error: prev.calendar?.error ?? '',
      };
      const selection = syncCalendarSelection(prev, normalizedCalendar, preferredDate);
      return {
        ...prev,
        ...selection,
        calendar: normalizedCalendar,
      };
    });
  }

  async function fetchSlots(target = 'booking', options = {}) {
    const { forceRemote = false } = options;
    const draft = getDraft(target);
    if (!draft.providerId || !draft.serviceId || !draft.appointmentDate) {
      updateDraft(target, prev => ({ ...prev, slots: [], slotsError: '' }));
      return;
    }

    const calendarSlots = draft.calendar?.slotsByDate?.[draft.appointmentDate];
    if (!forceRemote && Array.isArray(calendarSlots)) {
      updateDraft(target, prev => ({
        ...prev,
        slots: calendarSlots,
        slotsLoading: false,
        slotsError: calendarSlots.length === 0 ? 'No slots available for this date. Try another day.' : '',
        selectedSlot: calendarSlots.find(slot => slot.start === prev.selectedSlot?.start) ?? null,
      }));
      return;
    }

    updateDraft(target, prev => ({ ...prev, slotsLoading: true, slotsError: '', slots: [] }));

    const query = new URLSearchParams({
      provider_id: draft.providerId,
      service_id: draft.serviceId,
      date: draft.appointmentDate,
    });

    try {
      const response = await fetch(`/public/booking/slots?${query.toString()}`, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      updateCsrfFromHeaders(response.headers);
      const data = await safeJson(response);

      if (!response.ok) {
        throw new Error(data?.error ?? 'Unable to load availability.');
      }

      const slotList = Array.isArray(data?.data) ? data.data : [];
      const previous = draft.selectedSlot?.start;
      const stillValid = slotList.find(slot => slot.start === previous) ?? null;

      updateDraft(target, prev => ({
        ...prev,
        slotsLoading: false,
        slots: slotList,
        selectedSlot: stillValid,
        slotsError: slotList.length === 0 ? 'No slots available for this date. Try another day.' : '',
      }));
    } catch (error) {
      updateDraft(target, prev => ({ ...prev, slotsLoading: false, slotsError: error.message ?? 'Unable to load availability.' }));
    }
  }

  function buildPayload(draft, extras = {}) {
    const payload = {
      provider_id: Number(draft.providerId),
      service_id: Number(draft.serviceId),
      slot_start: draft.selectedSlot?.start ?? null,
      notes: draft.form.notes ?? '',
    };

    Object.entries(draft.form).forEach(([key, value]) => {
      payload[key] = value;
    });

    return { ...payload, ...extras };
  }

  function updateCsrfFromHeaders(headers) {
    if (!headers || !state.csrf.header) {
      return;
    }
    const headerName = state.csrf.header;
    const newValue = headers.get(headerName) || headers.get(headerName.toLowerCase());
    if (newValue && newValue !== state.csrf.value) {
      state.csrf = { ...state.csrf, value: newValue };
      root.dataset.csrfValue = newValue;
    }
  }

  function renderHero(ctx) {
    const timezone = ctx.timezone ?? 'local timezone';
    return `
      <header class="text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Secure Self-Service Booking</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Reserve an appointment</h1>
        <p class="mt-3 text-base text-slate-600">Pick a provider, choose a service, and lock in a time that works for you. All times are shown in <span class="font-semibold">${escapeHtml(timezone)}</span>.</p>
      </header>
    `;
  }

  function renderTabs(activeView) {
    const tabs = [
      { key: 'book', label: 'Book a visit', description: 'Plan a new appointment' },
      { key: 'manage', label: 'Manage booking', description: 'Look up or reschedule' },
    ];

    return `
      <div class="rounded-3xl border border-slate-200 bg-white p-1 shadow-sm">
        <nav class="grid gap-1 sm:flex" role="tablist">
          ${tabs.map(tab => {
            const isActive = tab.key === activeView;
            const base = 'w-full rounded-2xl px-5 py-3 text-left text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200';
            const stateClass = isActive
              ? 'bg-slate-900 text-white shadow'
              : 'text-slate-500 hover:text-slate-900';
            const detailClass = isActive ? 'text-slate-200' : 'text-slate-400';
            return `
              <button type="button" data-view-toggle="${tab.key}" class="${base} ${stateClass}" role="tab" aria-selected="${isActive}">
                <span class="block">${escapeHtml(tab.label)}</span>
                <span class="text-xs font-normal ${detailClass}">${escapeHtml(tab.description)}</span>
              </button>
            `;
          }).join('')}
        </nav>
      </div>
    `;
  }

  function renderManageSection(manageState, ctx) {
    if (manageState.stage === 'success' && manageState.success) {
      return renderSuccess(manageState.success, ctx, {
        title: 'Appointment updated',
        subtitle: 'We emailed your updated confirmation. Use the new token for any future changes.',
        primaryButton: { label: 'Look up another booking', attr: 'data-manage-start-over' },
        footerText: 'Need to adjust again? Submit your new confirmation token to reopen this booking.',
      });
    }

    if (manageState.stage === 'reschedule') {
      return renderRescheduleStage(manageState, ctx);
    }

    return renderLookupStage(manageState, ctx);
  }

  function renderLookupStage(manageState, ctx) {
    const info = manageState.lookupError
      ? `<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${escapeHtml(manageState.lookupError)}</div>`
      : '';
    const contactError = manageState.lookupErrors?.contact
      ? `<p class="text-sm text-red-600">${escapeHtml(manageState.lookupErrors.contact)}</p>`
      : '';
    const policy = ctx.reschedulePolicy ?? { enabled: true, label: '24 hours' };
    const policyMessage = policy.enabled
      ? `You can reschedule online up to ${escapeHtml(policy.label ?? '24 hours')} before the appointment.`
      : 'Online changes are disabled. Contact the office for assistance.';
    const policyClass = policy.enabled ? 'text-slate-600' : 'text-amber-600';

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="booking-lookup-form" class="space-y-5" novalidate>
          <div>
            <h2 class="text-xl font-semibold text-slate-900">Already booked?</h2>
            <p class="mt-1 text-sm text-slate-600">Enter your confirmation token plus the email or phone used when booking. We will pull up your appointment instantly.</p>
          </div>
          ${info}
          <label class="block text-sm font-medium text-slate-700">
            Confirmation token
            <input name="token" value="${escapeHtml(manageState.lookupForm.token ?? '')}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="abcd-1234-efgh" required>
            ${renderFieldError('token', manageState.lookupErrors)}
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">
              Email address
              <input type="email" name="email" value="${escapeHtml(manageState.lookupForm.email ?? '')}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="you@example.com">
              ${renderFieldError('email', manageState.lookupErrors)}
            </label>
            <label class="block text-sm font-medium text-slate-700">
              Phone number
              <input type="tel" name="phone" value="${escapeHtml(manageState.lookupForm.phone ?? '')}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="(555) 555-1234">
              ${renderFieldError('phone', manageState.lookupErrors)}
            </label>
          </div>
          <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-600">
            Provide the contact method used on the booking so we can verify ownership. Email or phone is sufficient.
            ${contactError}
          </div>
          <p class="text-xs font-medium ${policyClass}">${policyMessage}</p>
          <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-300" ${manageState.lookupLoading ? 'disabled' : ''}>${manageState.lookupLoading ? 'Finding your booking...' : 'Find my booking'}</button>
        </form>
      </section>
    `;
  }

  function renderRescheduleStage(manageState, ctx) {
    const helper = 'We will send your updated confirmation immediately after you save.';
    const form = renderForm(manageState.formState, ctx, {
      formId: 'public-reschedule-form',
      actionOptions: {
        submitLabel: 'Save new time',
        pendingLabel: 'Updating booking...',
        helperText: helper,
      },
    });

    return `
      <div class="space-y-6">
        ${renderManageSummary(manageState, ctx)}
        ${form}
      </div>
    `;
  }

  function renderManageSummary(manageState, ctx) {
    const appointment = manageState.appointment;
    if (!appointment) {
      return '';
    }
      const providerLabel = resolveAppointmentProvider(appointment, ctx);
      const serviceLabel = resolveAppointmentService(appointment, ctx);
      const slotSummary = formatAppointmentRange(appointment);
      const contactEmail = manageState.contact?.email || appointment.customer?.email;
      const contactPhone = manageState.contact?.phone || appointment.customer?.phone;
      const contactLine = contactEmail
        ? `Verified via <span class="font-semibold">${escapeHtml(contactEmail)}</span>`
        : (contactPhone ? `Verified via <span class="font-semibold">${escapeHtml(contactPhone)}</span>` : 'Contact verified');

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Booking token</p>
            <p class="mt-0.5 font-mono text-base text-slate-900">${escapeHtml(appointment.token ?? '')}</p>
            <p class="mt-2 text-sm text-slate-600">${contactLine}</p>
          </div>
          <button type="button" data-manage-reset class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-600">Use a different token</button>
        </div>
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Current time</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(slotSummary)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(providerLabel)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(serviceLabel)}</dd>
          </div>
        </dl>
      </section>
    `;
  }

  function renderForm(currentState, ctx, options = {}) {
    const generalError = currentState.globalError
      ? `<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${escapeHtml(currentState.globalError)}</div>`
      : '';
    const formId = options.formId ?? 'public-booking-form';

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="${formId}" class="space-y-6" novalidate>
          ${generalError}
          ${renderSelections(currentState, ctx)}
          ${renderSlotSection(currentState)}
          ${renderCustomerSection(currentState, ctx)}
          ${renderCustomFields(currentState, ctx)}
          ${renderNotesField(currentState, ctx)}
          ${renderPolicy(ctx)}
          ${renderActions(currentState, options.actionOptions)}
        </form>
      </section>
    `;
  }

  function renderSelections(currentState, ctx) {
    const providerOptions = (ctx.providers ?? []).map(provider => {
      const optionValue = escapeHtml(String(provider.id ?? ''));
      const isSelected = String(provider.id) === String(currentState.providerId) ? 'selected' : '';
      return `
        <option value="${optionValue}" ${isSelected}>
          ${escapeHtml(provider.name ?? provider.displayName ?? 'Provider')}
        </option>
      `;
    }).join('');

    const availableServices = currentState.services?.length ? currentState.services : (ctx.services ?? []);
    const serviceOptions = availableServices.map(service => {
      const optionValue = escapeHtml(String(service.id ?? ''));
      const isSelected = String(service.id) === String(currentState.serviceId) ? 'selected' : '';
      return `
        <option value="${optionValue}" ${isSelected}>
          ${escapeHtml(service.name ?? 'Service')}${service.formattedPrice ? ` &middot; ${escapeHtml(service.formattedPrice)}` : ''}
        </option>
      `;
    }).join('');

    const selectedService = availableServices.find(service => String(service.id) === String(currentState.serviceId));
    const serviceSummary = selectedService
      ? `<p class="text-sm text-slate-500">${escapeHtml(selectedService.name ?? 'Service')} &middot; ${(selectedService.duration ?? selectedService.durationMinutes ?? 0) || 0} min${selectedService.formattedPrice ? ` &middot; ${escapeHtml(selectedService.formattedPrice)}` : ''}</p>`
      : '';

    return `
      <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">
          Provider
          <select name="provider_id" data-provider-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${ctx.providers?.length ? '' : 'disabled'}>
            <option value="" ${currentState.providerId ? '' : 'selected'}>Choose a provider</option>
            ${providerOptions}
          </select>
          ${renderFieldError('provider_id', currentState.errors)}
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Service
          <select name="service_id" data-service-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${currentState.servicesLoading || !currentState.providerId ? 'disabled' : ''}>
            <option value="" ${currentState.serviceId ? '' : 'selected'}>${currentState.servicesLoading ? 'Loading services...' : (currentState.providerId ? 'Choose a service' : 'Select a provider first')}</option>
            ${serviceOptions}
          </select>
          ${serviceSummary}
          ${renderFieldError('service_id', currentState.errors)}
        </label>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        ${renderDatePickerField(currentState)}
        ${renderSchedulingTips()}
      </div>
    `;
  }

  function renderSlotSection(currentState) {
    const loading = currentState.slotsLoading
      ? '<p class="text-sm text-slate-500">Checking availability...</p>'
      : '';

    const slotButtons = currentState.slots.map(slot => {
      const isSelected = slot.start === currentState.selectedSlot?.start;
      const baseClasses = 'w-full rounded-2xl border px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200';
      const stateClasses = isSelected
        ? 'border-blue-600 bg-blue-50 text-blue-900 shadow-sm'
        : 'border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700';
      return `<button type="button" data-slot-option="${slot.start}" class="${baseClasses} ${stateClasses}">${escapeHtml(slot.label ?? formatSlotLabel(slot))}</button>`;
    }).join('');

    const grid = slotButtons
      ? `<div class="grid gap-2 sm:grid-cols-2">${slotButtons}</div>`
      : ''; 

    const emptyMessage = !currentState.slotsLoading && !currentState.slots.length
      ? `<p class="text-sm text-slate-500">${currentState.providerId && currentState.serviceId ? escapeHtml(currentState.slotsError || 'No open times for this day. Try another date.') : 'Select a provider and service to view appointments.'}</p>`
      : '';

    return `
      <div>
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-900">Pick an available time</h2>
          ${currentState.selectedSlot ? `<span class="text-sm text-slate-600">Selected: ${escapeHtml(formatSlotSummary(currentState.selectedSlot))}</span>` : ''}
        </div>
        <div class="mt-3 space-y-3">
          ${loading}
          ${grid}
          ${emptyMessage}
          ${renderFieldError('slot_start', currentState.errors)}
        </div>
      </div>
    `;
  }

  function renderSchedulingTips() {
    return `
      <div class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <span class="font-semibold text-slate-700">Scheduling tips</span>
        <span>Only days with openings are shown. Need another time? Try a different provider or service.</span>
      </div>
    `;
  }

  function renderDatePickerField(state) {
    const calendar = state.calendar ?? createCalendarState();
    const dates = calendar.availableDates ?? [];
    const disabled = calendar.loading;
    const hasSelections = Boolean(state.providerId && state.serviceId);

    if (!dates.length && calendar.loading) {
      return `
        <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm">
          <p class="font-medium text-slate-600">Preparing availability…</p>
          <p class="text-slate-500">We are checking the next 60 days for openings.</p>
        </div>
      `;
    }

    if (!dates.length && calendar.error) {
      return `
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          <p class="font-semibold">Availability unavailable</p>
          <p>${escapeHtml(calendar.error)}</p>
        </div>
      `;
    }

    if (!dates.length) {
      if (!hasSelections) {
        return `
          <div class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
            <p class="font-semibold text-slate-700">Select provider & service</p>
            <p>Pick your provider and service to see available days.</p>
          </div>
        `;
      }
      return `
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
          <p class="font-semibold">No open days</p>
          <p>We could not find any openings in the next 60 days. Try another provider or service.</p>
        </div>
      `;
    }

    const options = dates.map(date => {
      const selected = date === state.appointmentDate ? 'selected' : '';
      return `<option value="${escapeHtml(date)}" ${selected}>${escapeHtml(formatDateSelectLabel(date))}</option>`;
    }).join('');

    const pills = dates.slice(0, 6).map(date => {
      const isSelected = date === state.appointmentDate;
      const base = 'rounded-2xl border px-3 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200';
      const stateClass = isSelected
        ? 'border-blue-600 bg-blue-50 text-blue-900'
        : 'border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700';
      return `<button type="button" data-date-pill="${escapeHtml(date)}" class="${base} ${stateClass}">${escapeHtml(formatDatePillLabel(date))}</button>`;
    }).join('');

    const moreCount = Math.max(0, dates.length - 6);

    return `
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Choose a day</label>
        <div class="flex flex-wrap gap-2 mb-3" role="listbox">
          ${pills || '<span class="text-sm text-slate-500">No available days found.</span>'}
          ${moreCount > 0 ? `<span class="rounded-2xl border border-dashed border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500">+${moreCount} more days</span>` : ''}
        </div>
        <label class="block text-sm font-medium text-slate-600">Browse all available days
          <select data-date-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${disabled ? 'disabled' : ''}>
            ${options}
          </select>
        </label>
        ${calendar.error ? `<p class="mt-2 text-sm text-red-600">${escapeHtml(calendar.error)}</p>` : ''}
      </div>
    `;
  }

  function renderCustomerSection(currentState, ctx) {
    const fieldConfig = ctx.fieldConfig ?? {};
    const sectionFields = ['first_name', 'last_name', 'email', 'phone', 'address'];
    const visibleFields = sectionFields.filter(field => fieldConfig[field]?.display !== false);

    if (!visibleFields.length) {
      return '';
    }

    const inputs = visibleFields.map(field => renderField(field, fieldConfig[field] ?? {}, currentState)).join('');

    return `
      <div>
        <h2 class="text-base font-semibold text-slate-900">Your details</h2>
        <p class="text-sm text-slate-500">We will use this information to confirm your appointment and send reminders.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          ${inputs}
        </div>
      </div>
    `;
  }

  function renderCustomFields(currentState, ctx) {
    const customConfig = ctx.customFieldConfig ?? {};
    const keys = Object.keys(customConfig);

    if (!keys.length) {
      return '';
    }

    const inputs = keys.map(key => renderCustomField(key, customConfig[key], currentState)).filter(Boolean).join('');

    if (!inputs) {
      return '';
    }

    return `
      <div>
        <h2 class="text-base font-semibold text-slate-900">Additional information</h2>
        <div class="mt-4 grid gap-4">
          ${inputs}
        </div>
      </div>
    `;
  }

  function renderNotesField(currentState, ctx) {
    const fieldConfig = ctx.fieldConfig ?? {};
    if (fieldConfig.notes?.display === false) {
      return '';
    }

    const required = fieldConfig.notes?.required;
    return `
      <div>
        <label class="block text-sm font-medium text-slate-700">
          Notes for your provider ${required ? '<span class="text-red-500">*</span>' : ''}
          <textarea name="notes" rows="4" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">${escapeHtml(currentState.form.notes ?? '')}</textarea>
        </label>
        ${renderFieldError('notes', currentState.errors)}
      </div>
    `;
  }

  function renderPolicy(ctx) {
    const policy = ctx.reschedulePolicy ?? { enabled: true, label: '24 hours' };
    const label = policy.enabled ? `Need to make a change? You can reschedule online up to ${escapeHtml(policy.label ?? '24 hours')} before your appointment.` : 'Contact the office directly if you need to make a change.';
    return `<p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">${label}</p>`;
  }

  function renderActions(currentState, options = {}) {
    const submitLabel = options?.submitLabel ?? 'Confirm appointment';
    const pendingLabel = options?.pendingLabel ?? 'Booking your appointment...';
    const helperText = options?.helperText ?? 'We respect your privacy. Your confirmation token will be displayed and emailed immediately.';
    const disabled = currentState.submitting ? 'disabled' : '';
    const text = currentState.submitting ? pendingLabel : submitLabel;
    return `
      <div class="flex flex-col gap-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-300" ${disabled}>${escapeHtml(text)}</button>
        <p class="text-center text-xs text-slate-400">${escapeHtml(helperText)}</p>
      </div>
    `;
  }

  function renderSuccess(appointment, ctx, options = {}) {
    if (!appointment) {
      return '';
    }

    const providerLabel = resolveAppointmentProvider(appointment, ctx);
    const serviceLabel = resolveAppointmentService(appointment, ctx);
    const slotSummary = formatAppointmentRange(appointment);
    const title = options.title ?? "You're booked!";
    const subtitle = options.subtitle ?? 'We\'ll send a confirmation email shortly. Keep your token handy if you need to make changes.';
    const footerText = options.footerText ?? 'Need to reschedule? Use your token and contact email to pull up this booking anytime.';
    const primaryButton = options.primaryButton ?? { label: 'Book another appointment', attr: 'data-start-over' };
    const secondaryButton = options.secondaryButton;
    const primaryAttr = primaryButton?.attr ?? 'data-start-over';
    const secondaryAttr = secondaryButton?.attr ?? '';

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
          <span class="text-2xl">&#10003;</span>
        </div>
        <h2 class="mt-4 text-2xl font-semibold text-slate-900">${escapeHtml(title)}</h2>
        <p class="mt-2 text-sm text-slate-600">${escapeHtml(subtitle)}</p>
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Date & time</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(slotSummary)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(providerLabel)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(serviceLabel)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Confirmation token</dt>
            <dd class="text-base font-mono text-slate-900">${escapeHtml(appointment.token ?? '')}</dd>
          </div>
        </dl>
        <div class="mt-6 flex flex-col gap-3">
          ${primaryButton ? `<button type="button" ${primaryAttr} class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600">${escapeHtml(primaryButton.label)}</button>` : ''}
          ${secondaryButton ? `<button type="button" ${secondaryAttr} class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-600">${escapeHtml(secondaryButton.label)}</button>` : ''}
          <p class="text-xs text-slate-500">${escapeHtml(footerText)}</p>
        </div>
      </section>
    `;
  }

  function renderField(name, config, currentState) {
    const label = config.label ?? FIELD_LABELS[name] ?? name;
    const required = config.required;
    return `
      <label class="block text-sm font-medium text-slate-700">
        ${escapeHtml(label)} ${required ? '<span class="text-red-500">*</span>' : ''}
        <input name="${name}" value="${escapeHtml(currentState.form[name] ?? '')}" type="${name === 'email' ? 'email' : (name === 'phone' ? 'tel' : 'text')}" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${required ? 'aria-required="true"' : ''}>
        ${renderFieldError(name, currentState.errors)}
      </label>
    `;
  }

  function renderCustomField(key, config, currentState) {
    if (!config || config.display === false) {
      return '';
    }

    const label = config.title ?? `Custom field ${config.index}`;
    if (config.type === 'textarea') {
      return `
        <label class="block text-sm font-medium text-slate-700">
          ${escapeHtml(label)} ${config.required ? '<span class="text-red-500">*</span>' : ''}
          <textarea name="${key}" rows="3" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">${escapeHtml(currentState.form[key] ?? '')}</textarea>
          ${renderFieldError(key, currentState.errors)}
        </label>
      `;
    }

    if (config.type === 'checkbox') {
      const checked = (currentState.form[key] ?? '') === '1' ? 'checked' : '';
      return `
        <label class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
          <span>${escapeHtml(label)}</span>
          <input type="checkbox" name="${key}" class="h-4 w-4" ${checked}>
        </label>
        ${renderFieldError(key, currentState.errors)}
      `;
    }

    return `
      <label class="block text-sm font-medium text-slate-700">
        ${escapeHtml(label)} ${config.required ? '<span class="text-red-500">*</span>' : ''}
        <input name="${key}" value="${escapeHtml(currentState.form[key] ?? '')}" type="text" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        ${renderFieldError(key, currentState.errors)}
      </label>
    `;
  }

  function renderFieldError(name, errors) {
    if (!errors || !errors[name]) {
      return '';
    }
    return `<p class="mt-1 text-sm text-red-600">${escapeHtml(errors[name])}</p>`;
  }

  function formatSlotLabel(slot) {
    const start = slot?.start ? new Date(slot.start) : null;
    const end = slot?.end ? new Date(slot.end) : null;
    if (!start || !end) {
      return 'Selected time';
    }
    const timeFormatter = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
    return `${timeFormatter.format(start)} - ${timeFormatter.format(end)}`;
  }

  function formatSlotSummary(slot) {
    if (!slot?.start) {
      return '';
    }
    const start = new Date(slot.start);
    const end = slot.end ? new Date(slot.end) : null;
    const dateFormatter = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    const timeFormatter = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
    const dateLabel = dateFormatter.format(start);
    const rangeLabel = end ? `${timeFormatter.format(start)} - ${timeFormatter.format(end)}` : timeFormatter.format(start);
    return `${dateLabel}, ${rangeLabel}`;
  }

  function formatAppointmentRange(appointment) {
    if (!appointment) {
      return '';
    }
    if (appointment.display_range) {
      return appointment.display_range;
    }
    return formatSlotSummary({ start: appointment.start, end: appointment.end });
  }

  function resolveAppointmentProvider(appointment, ctx) {
    if (appointment?.provider?.name) {
      return appointment.provider.name;
    }
    const provider = (ctx.providers ?? []).find(item => Number(item.id) === Number(appointment?.provider_id));
    return provider?.name ?? provider?.displayName ?? 'Assigned provider';
  }

  function resolveAppointmentService(appointment, ctx) {
    if (appointment?.service?.name) {
      return appointment.service.name;
    }
    const service = (ctx.services ?? []).find(item => Number(item.id) === Number(appointment?.service_id));
    return service?.name ?? 'Selected service';
  }

  function parseContext() {
    try {
      return JSON.parse(root.dataset.context ?? '{}') || window.__PUBLIC_BOOKING__ || {};
    } catch (error) {
      console.error('[public-booking] Failed to parse context payload.', error);
      return window.__PUBLIC_BOOKING__ || {};
    }
  }

  function createCalendarState(source = null) {
    const base = {
      availableDates: [],
      slotsByDate: {},
      startDate: null,
      endDate: null,
      timezone: null,
      generatedAt: null,
      defaultDate: null,
      loading: false,
      error: '',
    };

    if (!source || typeof source !== 'object') {
      return { ...base };
    }

    const availableDates = Array.isArray(source.availableDates) ? [...source.availableDates] : [];
    const rawSlots = (source.slotsByDate && typeof source.slotsByDate === 'object') ? source.slotsByDate : {};
    const slotsByDate = Object.keys(rawSlots).reduce((acc, date) => {
      const slots = Array.isArray(rawSlots[date]) ? rawSlots[date].map(slot => ({ ...slot })) : [];
      acc[date] = slots;
      return acc;
    }, {});

    return {
      ...base,
      availableDates,
      slotsByDate,
      startDate: source.start_date ?? source.startDate ?? null,
      endDate: source.end_date ?? source.endDate ?? null,
      timezone: source.timezone ?? null,
      generatedAt: source.generated_at ?? source.generatedAt ?? null,
      defaultDate: source.default_date ?? source.defaultDate ?? (availableDates[0] ?? null),
    };
  }

  function syncCalendarSelection(prevState, calendar, preferredDate = null) {
    const availableDates = Array.isArray(calendar.availableDates) ? calendar.availableDates : [];

    if (!availableDates.length) {
      return {
        appointmentDate: prevState.appointmentDate,
        slots: [],
        selectedSlot: null,
        slotsError: calendar.error || 'No availability found in the next 60 days.',
        prefetched: null,
      };
    }

    let appointmentDate = preferredDate || prevState.appointmentDate;
    if (!availableDates.includes(appointmentDate)) {
      appointmentDate = availableDates[0];
    }

    const slotList = Array.isArray(calendar.slotsByDate?.[appointmentDate])
      ? calendar.slotsByDate[appointmentDate]
      : [];
    const selectedSlotStart = prevState.selectedSlot?.start;
    const selectedSlot = slotList.find(slot => slot.start === selectedSlotStart) ?? null;

    return {
      appointmentDate,
      slots: slotList,
      selectedSlot,
      slotsError: slotList.length === 0 ? 'No slots available for this date. Try another day.' : '',
      prefetched: slotList.length ? { date: appointmentDate } : null,
    };
  }

  function formatDateDisplay(dateStr, options) {
    if (!dateStr) {
      return '';
    }
    const date = new Date(`${dateStr}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return dateStr;
    }
    try {
      return new Intl.DateTimeFormat(undefined, options).format(date);
    } catch (error) {
      return date.toLocaleDateString();
    }
  }

  function formatDateSelectLabel(dateStr) {
    return formatDateDisplay(dateStr, { weekday: 'short', month: 'short', day: 'numeric' });
  }

  function formatDatePillLabel(dateStr) {
    return formatDateDisplay(dateStr, { weekday: 'short', month: 'short', day: 'numeric' });
  }

  function createInitialFormState(ctx) {
    const base = {
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      address: '',
      notes: '',
    };
    const fieldConfig = ctx.fieldConfig ?? {};
    Object.keys(fieldConfig).forEach(key => {
      if (base[key] === undefined) {
        base[key] = '';
      }
    });
    const customConfig = ctx.customFieldConfig ?? {};
    Object.keys(customConfig).forEach(key => {
      base[key] = customConfig[key].type === 'checkbox' ? '0' : '';
    });
    return base;
  }

  function createBookingDraft(ctx, defaultDate, initialAvailability = null, initialCalendar = null) {
    const providerId = ctx.providers?.[0]?.id?.toString() ?? '';
    const serviceId = ctx.services?.[0]?.id?.toString() ?? '';
    const calendarState = createCalendarState(initialCalendar);
    const matchesPrefetch = initialAvailability
      && String(initialAvailability.provider_id ?? '') === providerId
      && String(initialAvailability.service_id ?? '') === serviceId
      && Array.isArray(initialAvailability.slots)
      && initialAvailability.slots.length > 0;
    const calendarDate = calendarState.availableDates[0] ?? null;
    const fallbackDate = matchesPrefetch ? initialAvailability.date : null;
    const selectedDate = calendarDate || fallbackDate || defaultDate;
    const calendarSlots = (calendarState.slotsByDate?.[selectedDate]) ?? [];
    const slots = calendarSlots.length ? calendarSlots : (matchesPrefetch ? initialAvailability.slots : []);
    if (!calendarState.availableDates.length && matchesPrefetch && initialAvailability?.date) {
      calendarState.availableDates = [initialAvailability.date];
      calendarState.slotsByDate = { ...calendarState.slotsByDate, [initialAvailability.date]: initialAvailability.slots ?? [] };
    }
    return {
      providerId,
      serviceId,
      services: ctx.services ?? [],
      servicesLoading: false,
      appointmentDate: selectedDate,
      slots,
      slotsLoading: false,
      slotsError: '',
      selectedSlot: null,
      prefetched: slots.length ? { date: selectedDate } : null,
      calendar: calendarState,
      form: createInitialFormState(ctx),
      errors: {},
      globalError: '',
      submitting: false,
      success: null,
    };
  }

  function createManageDraft(ctx, defaultDate) {
    return {
      stage: 'lookup',
      lookupForm: { token: '', email: '', phone: '' },
      lookupErrors: {},
      lookupError: '',
      lookupLoading: false,
      appointment: null,
      success: null,
      contact: { email: '', phone: '' },
      formState: {
        providerId: '',
        serviceId: '',
        services: ctx.services ?? [],
        servicesLoading: false,
        appointmentDate: defaultDate,
        slots: [],
        slotsLoading: false,
        slotsError: '',
        selectedSlot: null,
        calendar: createCalendarState(),
        form: createInitialFormState(ctx),
        errors: {},
        globalError: '',
        submitting: false,
      },
    };
  }

  async function safeJson(response) {
    try {
      return await response.json();
    } catch (error) {
      return null;
    }
  }

  function cloneState(value) {
    if (typeof structuredClone === 'function') {
      return structuredClone(value);
    }
    return JSON.parse(JSON.stringify(value));
  }

  function snapshotFocus() {
    const active = document.activeElement;
    if (!active || !root.contains(active)) {
      return null;
    }
    if (active instanceof HTMLInputElement || active instanceof HTMLTextAreaElement) {
      return {
        name: active.name || null,
        selectionStart: active.selectionStart,
        selectionEnd: active.selectionEnd,
      };
    }
    return null;
  }

  function restoreFocus(snapshot) {
    if (!snapshot?.name) {
      return;
    }
    const selector = `input[name="${snapshot.name}"]`;
    const input = root.querySelector(selector) || root.querySelector(`textarea[name="${snapshot.name}"]`);
    if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
      input.focus({ preventScroll: true });
      const hasRange = typeof snapshot.selectionStart === 'number' && typeof snapshot.selectionEnd === 'number';
      try {
        if (hasRange) {
          input.setSelectionRange(snapshot.selectionStart, snapshot.selectionEnd);
        } else {
          const end = input.value.length;
          input.setSelectionRange(end, end);
        }
      } catch (error) {
        // ignore selection errors
      }
    }
  }
}

class SubmissionError extends Error {
  constructor(message, details = {}) {
    super(message);
    this.details = details;
  }
}

function escapeHtml(value) {
  if (value === undefined || value === null) {
    return '';
  }
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
