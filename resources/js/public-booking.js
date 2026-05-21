import { formatCurrency } from './currency.js';
import { escapeHtml } from './utils/html.js';
import { initPhoneCountrySelectors } from './utils/phone-country-selector.js';
import { apiRequest } from './core/api.js';
import { rotateCsrfFromResponse } from './core/csrf.js';
import {
  FIELD_LABELS,
  UI_CLASSES,
  createCalendarState,
  createBookingDraft,
  createManageDraft,
} from './modules/public-booking/state.js';

const root = document.getElementById('public-booking-root');

const MSG_NO_SLOTS = 'No slots available for this date. Try another day.';
const MSG_NO_AVAILABILITY = 'Unable to load availability.';


if (!root) {
  console.warn('[public-booking] Root element not found.');
} else {
  bootstrapPublicBooking();
}

function bootstrapPublicBooking() {
  const context = parseContext();
  const appBase = resolveAppBaseUrl(context);
  const bookingBase = context.bookingBaseUrl || '/booking';
  const defaultDate = new Date().toISOString().slice(0, 10);

  let state = {
    view: context.manageReference ? 'manage' : 'book',
    booking: createBookingDraft(context, defaultDate),
    manage: createManageDraft(context, defaultDate),
  };

  function formatLocalizedCurrency(amount) {
    return formatCurrency(amount, {
      currencySymbol: context.currencySymbol || context.currency_symbol || null,
    });
  }

  render();
  if (state.booking.providerId && state.booking.serviceId) {
    fetchCalendar('booking');
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
    initPhoneCountrySelectors(root, { defaultCountryCode: context.defaultPhoneCountryCode || '+27' });
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

    if (state.manage.stage === 'select') {
      root.querySelectorAll('[data-select-appointment]').forEach(button => {
        button.addEventListener('click', () => {
          const index = parseInt(button.getAttribute('data-select-appointment'), 10);
          handleSelectAppointment(index);
        });
      });
      root.querySelector('[data-manage-start-over]')?.addEventListener('click', resetManageFlow);
      return;
    }

    if (state.manage.stage === 'reschedule') {
      bindFormControls('manage');
      root.querySelector('[data-manage-reset]')?.addEventListener('click', resetManageFlow);
      root.querySelector('[data-manage-cancel]')?.addEventListener('click', handleManageCancel);
      return;
    }

    if (state.manage.stage === 'success') {
      root.querySelector('[data-manage-start-over]')?.addEventListener('click', resetManageFlow);
    }
  }

  async function handleSelectAppointment(index) {
    const results = state.manage.searchResults ?? [];
    const appt = results[index];
    if (!appt?.token) return;
    const { email, phone, phone_country_code: phoneCountryCode } = state.manage.contact ?? {};
    await selectAppointmentFromSearch(appt.token, email ?? '', phone ?? '', phoneCountryCode ?? '');
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
    const locationSelect = form.querySelector('[data-location-select]');
    const dateSelect = form.querySelector('[data-date-select]');

    providerSelect?.addEventListener('change', (event) => handleProviderChange(event.target.value, target));
    serviceSelect?.addEventListener('change', (event) => handleServiceChange(event.target.value, target));
    locationSelect?.addEventListener('change', (event) => handleLocationChange(event.target.value, target));
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

    form.querySelectorAll('[name="public_delivery_mode"]').forEach(radio => {
      radio.addEventListener('change', e => {
        updateDraft(target, prev => ({ ...prev, deliveryMode: e.target.value }));
      });
    });

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

  function extractErrorDetails(data) {
    return data?.error?.details ?? data?.details ?? {};
  }

  function refreshCsrf(response, data) {
    rotateCsrfFromResponse(response, 'public');
    updateCsrfFromBody(data);
  }

  function handleLookupError(error, fallbackMessage) {
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
      lookupError: error.message ?? fallbackMessage,
    }));
  }

  function renderDashedCard(text) {
    return `<div class="flex min-h-[80px] items-center rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 transition-all duration-200">${escapeHtml(text)}</div>`;
  }

  function getFutureLimitDays() {
    const days = Number(context.futureLimitDays ?? 60);
    return days > 0 ? days : 60;
  }

  function resetBookingFlow() {
    updateBooking(() => createBookingDraft(context, defaultDate));
    fetchCalendar('booking');
  }

  function resetManageFlow() {
    updateManage(() => createManageDraft(context, defaultDate));
  }

  async function handleManageCancel() {
    const appointment = state.manage.appointment;
    const reference = appointment?.reference;

    if (!reference || state.manage.cancelSubmitting) {
      return;
    }

    if (appointment?.can_cancel === false) {
      updateManage(prev => ({
        ...prev,
        cancelError: 'This appointment is too close to cancel online. Please contact us directly.',
      }));
      return;
    }

    const confirmed = window.confirm('Cancel this appointment? This action cannot be undone.');
    if (!confirmed) {
      return;
    }

    updateManage(prev => ({ ...prev, cancelSubmitting: true, cancelError: '' }));

    try {
      const payload = {
        email: state.manage.contact?.email || state.manage.formState?.form?.email || '',
        phone: state.manage.contact?.phone || state.manage.formState?.form?.phone || '',
        phone_country_code: state.manage.contact?.phone_country_code || state.manage.lookupForm?.phone_country_code || '',
      };

      const { response, payload: data } = await apiRequest(`${bookingBase}/${encodeURIComponent(reference)}/cancel`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: payload,
        authContext: 'public',
      });

      refreshCsrf(response, data);

      if (!response.ok) {
        const details = extractErrorDetails(data);
        throw new SubmissionError(data?.error?.message ?? data?.error ?? 'Unable to cancel the booking.', details);
      }

      updateManage(prev => ({
        ...prev,
        cancelSubmitting: false,
        cancelError: '',
        stage: 'success',
        success: data?.data ?? null,
        appointment: data?.data ?? prev.appointment,
      }));
    } catch (error) {
      updateManage(prev => ({
        ...prev,
        cancelSubmitting: false,
        cancelError: error.message ?? 'Unable to cancel the booking.',
      }));
    }
  }

  function handleProviderChange(value, target = 'booking') {
    // Auto-resolve location: if provider has exactly 1 location, pre-select it
    const provider = (context.providers ?? []).find(p => String(p.slug ?? '') === String(value));
    const locations = provider?.locations ?? [];
    const autoLocationId = locations.length === 1 ? String(locations[0].id) : '';

    updateDraft(target, prev => ({
      ...prev,
      providerId: value,
      serviceId: '',
      services: [],
      servicesLoading: true,
      selectedSlot: null,
      slots: [],
      slotsError: '',
      resolvedLocation: null,
      selectedLocationId: autoLocationId,
      calendar: createCalendarState(),
      errors: { ...prev.errors, provider_id: undefined, service_id: undefined, slot_start: undefined, location_id: undefined },
    }));
    if (value) {
      fetchProviderServices(value, target);
    }
  }

  function getAvailableModes(serviceId, services, ctx) {
    const svc = (services || []).find(s => String(s.id) === String(serviceId));
    const modes = Array.isArray(svc?.deliveryModes) ? svc.deliveryModes : ['onsite'];
    const filtered = modes.filter(m =>
      m === 'onsite' ||
      (m === 'online_zoom'  && ctx.zoomConnected) ||
      (m === 'online_jitsi' && ctx.jitsiConnected)
    );
    return filtered.length > 0 ? filtered : ['onsite'];
  }

  function handleServiceChange(value, target = 'booking') {
    const currentServices = getDraft(target).services;
    const availableModes  = getAvailableModes(value, currentServices, context);
    updateDraft(target, prev => ({
      ...prev,
      serviceId: value,
      deliveryMode: availableModes[0] ?? 'onsite',
      selectedSlot: null,
      slots: [],
      slotsError: '',
      calendar: { ...createCalendarState(), loading: true, error: '' },
      errors: { ...prev.errors, service_id: undefined, slot_start: undefined },
    }));
    if (value) {
      fetchCalendar(target);
    }
  }

  /**
   * Handle location selection change.
   * Stores the selected location ID and re-fetches calendar/slots scoped to that location.
   */
  function handleLocationChange(value, target = 'booking') {
    const draft = getDraft(target);
    const provider = (context.providers ?? []).find(p => String(p.slug ?? '') === String(draft.providerId));
    const loc = (provider?.locations ?? []).find(l => String(l.id) === String(value)) ?? null;

    updateDraft(target, prev => ({
      ...prev,
      selectedLocationId: value,
      resolvedLocation: loc,
      selectedSlot: null,
      slots: [],
      slotsError: '',
      calendar: { ...createCalendarState(), loading: Boolean(prev.serviceId), error: '' },
      errors: { ...prev.errors, location_id: undefined, slot_start: undefined },
    }));
    if (draft.serviceId) {
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
      const encodedProviderSlug = encodeURIComponent(String(providerId));
      const { response, payload } = await apiRequest(`${appBase}/api/v1/providers/slug/${encodedProviderSlug}/services`, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        authContext: 'public',
      });

      rotateCsrfFromResponse(response, 'public');
      updateCsrfFromBody(payload);

      if (!response.ok) {
        throw new Error(payload?.error?.message ?? payload?.error ?? 'Unable to load services.');
      }

      const services = (payload?.data ?? payload ?? []).map(svc => ({
        id: svc.id,
        name: svc.name,
        description: svc.description,
        duration: svc.durationMin ?? svc.duration_min ?? svc.duration,
        price: svc.price,
        formattedPrice: svc.price != null && svc.price !== '' ? formatLocalizedCurrency(svc.price) : '',
        deliveryModes: Array.isArray(svc.deliveryModes) ? svc.deliveryModes : ['onsite'],
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
    const draft = getDraft(target);

    // Resolve location: if user explicitly selected one, use it; otherwise auto-resolve from date
    let loc;
    if (draft.selectedLocationId) {
      const provider = (context.providers ?? []).find(p => String(p.slug ?? '') === String(draft.providerId));
      loc = (provider?.locations ?? []).find(l => String(l.id) === String(draft.selectedLocationId)) ?? null;
    } else {
      loc = resolveLocationForDate(draft.providerId, value);
    }

    const availableDates = draft.calendar?.availableDates ?? [];
    if (availableDates.includes(value)) {
      syncSlotsFromCalendar(target, value);
      return;
    }

    updateDraft(target, prev => ({
      ...prev,
      resolvedLocation: loc,
      appointmentDate: value,
      selectedSlot: null,
      slots: [],
      slotsError: '',
      errors: { ...prev.errors, slot_start: undefined },
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

    // Update state silently (no re-render) to preserve cursor position
    state.manage.lookupForm[name] = event.target.value;
    if (state.manage.lookupErrors[name]) {
      delete state.manage.lookupErrors[name];
      const errEl = event.target.closest('label')?.querySelector('.text-red-600');
      if (errEl) errEl.remove();
    }
    if (state.manage.lookupErrors.contact) {
      delete state.manage.lookupErrors.contact;
      const contactErr = root.querySelector('[data-contact-error]');
      if (contactErr) contactErr.remove();
    }
    state.manage.lookupError = '';
  }

  async function handleLookupSubmit(event) {
    event.preventDefault();
    if (state.manage.lookupLoading) return;

    const { hasPrefilledReference } = state.manage;
    const formState = state.manage.lookupForm;
    const submittedForm = event.target;
    const reference = (formState.reference ?? '').trim() || String(context.manageReference ?? '').trim();
    const email = (formState.email ?? '').trim();
    const phone = (formState.phone ?? '').trim();
    const phoneCountryCode = (submittedForm?.querySelector('[name="phone_country_code"]')?.value || formState.phone_country_code || '').trim();
    const errors = {};

    if (hasPrefilledReference && !reference) {
      errors.token = 'Secure reference not found. Please use your original booking link.';
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
      if (email) params.set('email', email);
      if (phone) params.set('phone', phone);
      if (phoneCountryCode) params.set('phone_country_code', phoneCountryCode);

      let url;
      if (hasPrefilledReference && reference) {
        const query = params.toString();
        url = query ? `${bookingBase}/${encodeURIComponent(reference)}?${query}` : `${bookingBase}/${encodeURIComponent(reference)}`;
      } else {
        url = `${bookingBase}/search?${params.toString()}`;
      }

      const { response, payload: data } = await apiRequest(url, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        authContext: 'public',
      });

      refreshCsrf(response, data);

      if (!response.ok) {
        const details = extractErrorDetails(data);
        throw new SubmissionError(data?.error?.message ?? data?.error ?? 'Unable to locate that booking.', details);
      }

      updateManage(prev => ({ ...prev, lookupLoading: false }));

      if (hasPrefilledReference) {
        // Direct token lookup — single result, proceed straight to reschedule
        enterRescheduleStage(data?.data, { email, phone, phone_country_code: phoneCountryCode });
      } else {
        // Contact-based search — may return multiple appointments
        const results = Array.isArray(data?.data) ? data.data : [];
        if (results.length === 0) {
          throw new SubmissionError("We couldn't find any upcoming bookings for that contact.");
        }
        if (results.length === 1) {
          await selectAppointmentFromSearch(results[0].token, email, phone, phoneCountryCode);
        } else {
          updateManage(prev => ({
            ...prev,
            stage: 'select',
            searchResults: results,
            contact: { email, phone, phone_country_code: phoneCountryCode },
          }));
        }
      }
    } catch (error) {
      handleLookupError(error, 'Unable to locate that booking.');
    }
  }

  async function selectAppointmentFromSearch(token, email, phone, phoneCountryCode = '') {
    updateManage(prev => ({ ...prev, lookupLoading: true, lookupError: '' }));
    try {
      const params = new URLSearchParams();
      if (email) params.set('email', email);
      if (phone) params.set('phone', phone);
      if (phoneCountryCode) params.set('phone_country_code', phoneCountryCode);
      const url = `${bookingBase}/${encodeURIComponent(token)}?${params.toString()}`;
      const { response, payload: data } = await apiRequest(url, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        authContext: 'public',
      });
      refreshCsrf(response, data);
      if (!response.ok) {
        const details = extractErrorDetails(data);
        throw new SubmissionError(data?.error?.message ?? data?.error ?? 'Unable to load that booking.', details);
      }
      updateManage(prev => ({ ...prev, lookupLoading: false }));
      enterRescheduleStage(data?.data, { email, phone, phone_country_code: phoneCountryCode });
    } catch (error) {
      handleLookupError(error, 'Unable to load that booking.');
    }
  }

  function enterRescheduleStage(appointment, contact = {}) {
    if (!appointment) {
      updateManage(prev => ({ ...prev, lookupError: 'We could not load that booking. Please try again.' }));
      return;
    }

    // Server-side flag: appointment is within the policy window or already past
    if (appointment.can_reschedule === false) {
      const policy = context.reschedulePolicy ?? { enabled: true, label: '24 hours' };
      const reason = !policy.enabled
        ? 'Online rescheduling is not available for this booking. Please contact us directly.'
        : `This appointment is too close to reschedule online. Changes must be made at least ${policy.label ?? '24 hours'} before your appointment.`;
      updateManage(prev => ({ ...prev, lookupError: reason }));
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
        phone_country_code: contact.phone_country_code ?? prev.contact?.phone_country_code ?? '',
      },
      lookupError: '',
      lookupErrors: {},
      success: null,
      cancelSubmitting: false,
      cancelError: '',
    }));

    updateManageForm(prev => ({
      ...prev,
      providerId: String(appointment.provider?.slug ?? appointment.provider_id ?? ''),
      serviceId: String(appointment.service_id ?? ''),
      appointmentDate,
      selectedSlot: appointment.start ? { start: appointment.start, end: appointment.end, label: slotLabel } : null,
      form: {
        ...prev.form,
        first_name: appointment.customer?.first_name ?? prev.form.first_name ?? '',
        last_name: appointment.customer?.last_name ?? prev.form.last_name ?? '',
        email: appointment.customer?.email ?? prev.form.email ?? contact.email ?? '',
        phone: appointment.customer?.phone ?? prev.form.phone ?? contact.phone ?? '',
        address: appointment.customer?.address ?? prev.form.address ?? '',
        notes: appointment.notes ?? prev.form.notes ?? '',
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

    // Update state silently (no re-render) to avoid cursor-reset on
    // input types that don't support setSelectionRange (email, tel, etc.)
    const draft = getDraft(target);
    draft.form[name] = value;
    if (draft.errors[name]) {
      delete draft.errors[name];
      const errEl = event.target.closest('label')?.querySelector('.text-red-600');
      if (errEl) errEl.remove();
    }
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
      const phoneCountryCode = (event.target?.querySelector('[name="phone_country_code"]')?.value || draft.form.phone_country_code || '').trim();
      const contactExtras = {
        ...(target === 'manage' ? (state.manage.contact ?? {}) : {}),
        ...(phoneCountryCode ? { phone_country_code: phoneCountryCode } : {}),
      };
      const payload = buildPayload(draft, contactExtras);
      let endpoint = bookingBase;
      let method = 'POST';

      if (target === 'manage') {
        const reference = state.manage.appointment?.reference;
        if (!reference) {
          throw new Error('Missing appointment reference.');
        }
        endpoint = `${bookingBase}/${encodeURIComponent(reference)}`;
        method = 'PATCH';
      }

      const { response, payload: data } = await apiRequest(endpoint, {
        method,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: payload,
        authContext: 'public',
      });

      rotateCsrfFromResponse(response, 'public');
      updateCsrfFromBody(data);

      if (!response.ok) {
        const details = extractErrorDetails(data);
        throw new SubmissionError(
          data?.error?.message ?? data?.error ?? (target === 'booking' ? 'Unable to save your booking.' : 'Unable to update the booking.'),
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
      const details = error instanceof SubmissionError ? error.details : {};
      updateDraft(target, prev => ({
        ...prev,
        submitting: false,
        globalError: error.message ?? 'Something went wrong. Please try again.',
        errors: { ...prev.errors, ...details },
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
      }));
      return;
    }

    updateDraft(target, prev => ({
      ...prev,
      calendar: { ...createCalendarState(), loading: true, error: '' },
      slots: [],
      slotsError: '',
      selectedSlot: null,
    }));

    const query = new URLSearchParams({
      provider_slug: draft.providerId,
      service_id: draft.serviceId,
      days: String(context.futureLimitDays ?? 60),
    });
    if (draft.selectedLocationId) {
      query.set('location_id', draft.selectedLocationId);
    }

    try {
      const { response, payload } = await apiRequest(`${bookingBase}/calendar?${query.toString()}`, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        authContext: 'public',
      });

      refreshCsrf(response, payload);

      if (!response.ok) {
        throw new Error(payload?.error?.message ?? payload?.error ?? MSG_NO_AVAILABILITY);
      }

      const calendarPayload = payload?.data ?? payload ?? {};
      updateDraft(target, prev => {
        const normalizedCalendar = { ...createCalendarState(calendarPayload), loading: false, error: '' };
        const selection = syncCalendarSelection(prev, normalizedCalendar, preferredDate);
        return {
          ...prev,
          ...selection,
          calendar: normalizedCalendar,
        };
      });
    } catch (error) {
      updateDraft(target, prev => ({
        ...prev,
        calendar: { ...prev.calendar, loading: false, error: error.message ?? MSG_NO_AVAILABILITY },
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
        calendar: { ...prev.calendar, loading: false },
        slots: calendarSlots,
        slotsError: calendarSlots.length === 0 ? MSG_NO_SLOTS : '',
        selectedSlot: calendarSlots.find(slot => slot.start === prev.selectedSlot?.start) ?? null,
      }));
      return;
    }

    updateDraft(target, prev => ({
      ...prev,
      calendar: { ...prev.calendar, loading: true },
      slotsError: '',
      slots: [],
    }));

    const query = new URLSearchParams({
      provider_slug: draft.providerId,
      service_id: draft.serviceId,
      date: draft.appointmentDate,
    });
    if (draft.selectedLocationId) {
      query.set('location_id', draft.selectedLocationId);
    }

    try {
      const { response, payload: data } = await apiRequest(`${bookingBase}/slots?${query.toString()}`, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        authContext: 'public',
      });

      refreshCsrf(response, data);

      if (!response.ok) {
        throw new Error(data?.error?.message ?? data?.error ?? MSG_NO_AVAILABILITY);
      }

      const slotList = Array.isArray(data?.data) ? data.data : [];
      const previous = draft.selectedSlot?.start;
      const stillValid = slotList.find(slot => slot.start === previous) ?? null;

      updateDraft(target, prev => ({
        ...prev,
        calendar: { ...prev.calendar, loading: false },
        slots: slotList,
        selectedSlot: stillValid,
        slotsError: slotList.length === 0 ? MSG_NO_SLOTS : '',
      }));
    } catch (error) {
      updateDraft(target, prev => ({
        ...prev,
        calendar: { ...prev.calendar, loading: false },
        slotsError: error.message ?? MSG_NO_AVAILABILITY,
      }));
    }
  }

  function buildPayload(draft, extras = {}) {
    const payload = {
      provider_slug: String(draft.providerId || ''),
      service_id:    Number(draft.serviceId),
      slot_start:    draft.selectedSlot?.start ?? null,
      notes:         draft.form.notes ?? '',
      delivery_mode: draft.deliveryMode ?? 'onsite',
    };

    // Include location_id — prefer resolved location, fall back to selector
    const locationId = draft.resolvedLocation?.id ?? draft.selectedLocationId;
    if (locationId) {
      payload.location_id = Number(locationId);
    }

    Object.entries(draft.form).forEach(([key, value]) => {
      payload[key] = value;
    });

    return { ...payload, ...extras };
  }

  /**
   * Update CSRF token from JSON response body (CI4 regenerate=true sends
   * a fresh token in the response payload).
   */
  function updateCsrfFromBody(data) {
    if (data?.csrf?.value) {
      root.dataset.csrfValue = data.csrf.value;
    }
  }

  function renderHero(ctx) {
    const timezone = ctx.timezone ?? 'local timezone';
    const logoUrl = ctx.logoUrl ?? '';
    const businessName = ctx.businessName ?? 'WebScheduler';
    const logoHtml = logoUrl
      ? `<img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(businessName)}" class="mx-auto h-14 w-auto rounded-lg mb-4" />`
      : `<div class="mx-auto w-14 h-14 rounded-2xl bg-blue-600 flex items-center justify-center mb-4"><svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>`;
    return `
      <header class="text-center">
        ${logoHtml}
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(businessName)}</p>
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
            const stateClass = isActive
              ? 'bg-slate-900 text-white shadow'
              : 'text-slate-500 hover:text-slate-900';
            const detailClass = isActive ? 'text-slate-200' : 'text-slate-400';
            return `
              <button type="button" data-view-toggle="${tab.key}" class="${UI_CLASSES.tabButton} ${stateClass}" role="tab" aria-selected="${isActive}">
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
      const successStatus = String(manageState.success?.status ?? '').toLowerCase();
      const cancelled = successStatus === 'cancelled';
      return renderSuccess(manageState.success, ctx, {
        title: cancelled ? 'Appointment cancelled' : 'Appointment updated',
        subtitle: cancelled
          ? 'Your appointment has been cancelled. Contact us anytime if you want to book again.'
          : 'We emailed your updated confirmation with your secure manage link.',
        primaryButton: { label: 'Look up another booking', attr: 'data-manage-start-over' },
        footerText: cancelled
          ? 'Need another visit? Use Book a visit to reserve a new appointment.'
          : 'Need to adjust again? Open your secure manage link and confirm with email or phone.',
      });
    }

    if (manageState.stage === 'select') {
      return renderSelectStage(manageState, ctx);
    }

    if (manageState.stage === 'reschedule') {
      return renderRescheduleStage(manageState, ctx);
    }

    return renderLookupStage(manageState, ctx);
  }

  function renderSelectStage(manageState, ctx) {
    const results = manageState.searchResults ?? [];
    const isLoading = manageState.lookupLoading;
    const errorHtml = manageState.lookupError
      ? `<div class="${UI_CLASSES.cardError}" role="alert">${escapeHtml(manageState.lookupError)}</div>`
      : '';

    const cards = results.map((appt, index) => {
      const statusBadge = appt.status === 'confirmed'
        ? `<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Confirmed</span>`
        : appt.status === 'pending'
          ? `<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending</span>`
          : `<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">${escapeHtml(appt.status ?? '')}</span>`;

      return `
        <button type="button" data-select-appointment="${index}" class="w-full text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-400 hover:shadow-md transition-all" ${isLoading ? 'disabled' : ''}>
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-900 truncate">${escapeHtml(appt.service?.name ?? 'Appointment')}</p>
              <p class="text-sm text-slate-500 mt-0.5">${escapeHtml(appt.provider?.name ?? '')}</p>
              <p class="text-sm text-slate-700 font-medium mt-1">${escapeHtml(appt.display_range ?? '')}</p>
            </div>
            <div class="flex-shrink-0 mt-0.5">${statusBadge}</div>
          </div>
        </button>
      `;
    }).join('');

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="space-y-5">
          <div>
            <h2 class="text-xl font-semibold text-slate-900">Your bookings</h2>
            <p class="mt-1 text-sm text-slate-600">Select the appointment you would like to manage.</p>
          </div>
          ${errorHtml}
          <div class="space-y-3">${cards}</div>
          <button type="button" data-manage-start-over class="text-sm text-slate-500 underline hover:text-slate-700">Search again</button>
        </div>
      </section>
    `;
  }

  function renderLookupStage(manageState, ctx) {
    const info = manageState.lookupError
      ? `<div class="${UI_CLASSES.cardError}" role="alert">${escapeHtml(manageState.lookupError)}</div>`
      : '';
    const contactError = manageState.lookupErrors?.contact
      ? `<p class="text-sm text-red-600">${escapeHtml(manageState.lookupErrors.contact)}</p>`
      : '';
    const hasPrefilledReference = Boolean(manageState.hasPrefilledReference);
    const introCopy = hasPrefilledReference
      ? 'Confirm with the email or phone used when booking to continue.'
      : 'Enter the email or phone you used when booking and we will find your appointments.';
    const secureReferenceInfo = hasPrefilledReference
      ? `<div class="${UI_CLASSES.cardInfo}">Secure reference detected from your link. Confirm with email or phone to continue.</div>`
      : '';
    const referenceField = hasPrefilledReference
      ? `<input type="hidden" name="reference" value="${escapeHtml(manageState.lookupForm.reference ?? '')}">`
      : '';

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="booking-lookup-form" class="space-y-5" novalidate>
          <div>
            <h2 class="text-xl font-semibold text-slate-900">Already booked?</h2>
            <p class="mt-1 text-sm text-slate-600">${introCopy}</p>
          </div>
          ${info}
          ${secureReferenceInfo}
          ${referenceField}
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">
              Email address
              <input type="email" name="email" value="${escapeHtml(manageState.lookupForm.email ?? '')}" class="${UI_CLASSES.inputBase}" placeholder="you@example.com">
              ${renderFieldError('email', manageState.lookupErrors)}
            </label>
            <label class="block text-sm font-medium text-slate-700">
              Phone number
              <input type="tel" name="phone" value="${escapeHtml(manageState.lookupForm.phone ?? '')}" class="${UI_CLASSES.inputBase}" placeholder="(555) 555-1234">
              ${renderFieldError('phone', manageState.lookupErrors)}
            </label>
          </div>
          <div class="${UI_CLASSES.cardDashed}">
            Provide the contact method used on the booking so we can verify ownership. Email or phone is sufficient.
            ${contactError}
          </div>
          ${renderSchedulingTips(ctx)}
          <button type="submit" class="${UI_CLASSES.buttonPrimary}" ${manageState.lookupLoading ? 'disabled' : ''}>${manageState.lookupLoading ? 'Finding your booking...' : 'Find my booking'}</button>
        </form>
      </section>
    `;
  }

  function renderRescheduleStage(manageState, ctx) {
    const helper = 'We will send your updated confirmation immediately after you save.';
    const existingCustomFields = manageState.appointment?.custom_fields ?? null;
    const form = renderForm(manageState.formState, ctx, {
      formId: 'public-reschedule-form',
      existingCustomFields,
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
      const cancelDisabled = manageState.cancelSubmitting ? 'disabled' : '';
      const cancelLabel = manageState.cancelSubmitting ? 'Cancelling...' : 'Cancel appointment';
      const canCancel = appointment.can_cancel !== false;
      const cancelButton = canCancel
        ? `<button type="button" data-manage-cancel class="inline-flex items-center justify-center rounded-2xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 transition hover:border-red-400 hover:text-red-800" ${cancelDisabled}>${escapeHtml(cancelLabel)}</button>`
        : '';
      const cancelError = manageState.cancelError
        ? `<div class="${UI_CLASSES.cardError}" role="alert">${escapeHtml(manageState.cancelError)}</div>`
        : '';

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Booking reference</p>
            <p class="mt-0.5 text-base text-slate-900">Verified secure reference</p>
            <p class="mt-2 text-sm text-slate-600">${contactLine}</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            ${cancelButton}
            <button type="button" data-manage-reset class="${UI_CLASSES.buttonSecondary}">Use a different reference</button>
          </div>
        </div>
        ${cancelError}
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-3">
          <div class="${UI_CLASSES.cardBase}">
            <dt class="text-sm font-medium text-slate-500">Current time</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(slotSummary)}</dd>
          </div>
          <div class="${UI_CLASSES.cardBase}">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(providerLabel)}</dd>
          </div>
          <div class="${UI_CLASSES.cardBase}">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(serviceLabel)}</dd>
          </div>
        </dl>
      </section>
    `;
  }

  function renderForm(currentState, ctx, options = {}) {
    const generalError = currentState.globalError
      ? `<div class="${UI_CLASSES.cardError}" role="alert">${escapeHtml(currentState.globalError)}</div>`
      : '';
    const formId = options.formId ?? 'public-booking-form';
    const existingCustomFields = options.existingCustomFields ?? null;

    return `
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="${formId}" class="space-y-6" novalidate>
          ${generalError}
          ${renderSelections(currentState, ctx)}
          ${renderLocationCard(currentState)}
          ${renderSlotSection(currentState)}
          ${renderCustomerSection(currentState, ctx)}
          ${renderCustomFields(currentState, ctx, existingCustomFields)}
          ${renderNotesField(currentState, ctx)}
          ${renderActions(currentState, options.actionOptions)}
        </form>
      </section>
    `;
  }

  function renderSelections(currentState, ctx) {
    const providerOptions = (ctx.providers ?? []).map(provider => {
      const optionValue = escapeHtml(String(provider.slug ?? ''));
      const isSelected = String(provider.slug ?? '') === String(currentState.providerId) ? 'selected' : '';
      return `
        <option value="${optionValue}" ${isSelected}>
          ${escapeHtml(provider.name ?? provider.displayName ?? 'Provider')}
        </option>
      `;
    }).join('');

    const availableServices = currentState.providerId
      ? (currentState.services ?? [])
      : (ctx.services ?? []);
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

    // Build location selector (only when the selected provider has 2+ locations)
    const selectedProvider = (ctx.providers ?? []).find(p => String(p.slug ?? '') === String(currentState.providerId));
    const providerLocations = selectedProvider?.locations ?? [];
    const showLocationSelector = providerLocations.length > 1;
    const locationOptions = providerLocations.map(loc => {
      const optionValue = escapeHtml(String(loc.id ?? ''));
      const isSelected = String(loc.id) === String(currentState.selectedLocationId) ? 'selected' : '';
      const addressHint = loc.address ? ` — ${loc.address}` : '';
      return `<option value="${optionValue}" ${isSelected}>${escapeHtml(loc.name ?? 'Location')}${escapeHtml(addressHint)}</option>`;
    }).join('');

    const locationSelector = showLocationSelector ? `
      <div>
        <label class="block text-sm font-medium text-slate-700">
          Location
          <select name="location_id" data-location-select class="${UI_CLASSES.selectBase}">
            <option value="">All locations</option>
            ${locationOptions}
          </select>
          ${renderFieldError('location_id', currentState.errors)}
        </label>
      </div>
    ` : '';

    const providerProfileCard = renderProviderProfileCard(selectedProvider);
    const serviceCard = renderServiceCard(selectedService);
    const providerEmptyState = currentState.providerId
      ? renderDashedCard('Provider profile details are not available.')
      : renderDashedCard('Choose a provider to preview details.');
    const serviceEmptyState = currentState.serviceId
      ? renderDashedCard('Service details are not available.')
      : renderDashedCard('Choose a service to preview details.');

    return `
      <div class="space-y-4">
        <label class="block text-sm font-medium text-slate-700">
          Provider
          <select name="provider_id" data-provider-select class="${UI_CLASSES.selectBase}" ${ctx.providers?.length ? '' : 'disabled'}>
            <option value="" ${currentState.providerId ? '' : 'selected'}>Choose a provider</option>
            ${providerOptions}
          </select>
          ${renderFieldError('provider_id', currentState.errors)}
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Service
          <select name="service_id" data-service-select class="${UI_CLASSES.selectBase}" ${currentState.servicesLoading || !currentState.providerId ? 'disabled' : ''}>
            <option value="" ${currentState.serviceId ? '' : 'selected'}>${currentState.servicesLoading ? 'Loading services...' : (currentState.providerId ? 'Choose a service' : 'Select a provider first')}</option>
            ${serviceOptions}
          </select>
          ${renderFieldError('service_id', currentState.errors)}
        </label>
      </div>
      ${locationSelector}
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Selected provider &amp; service</h3>
        <div class="grid gap-3 md:grid-cols-2">
          ${providerProfileCard || providerEmptyState}
          ${serviceCard || serviceEmptyState}
        </div>
      </section>
      ${renderDeliveryModeSelector(currentState, ctx)}
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Select date</h3>
        ${renderDatePickerField(currentState)}
        ${renderSchedulingTips(ctx)}
      </section>
    `;
  }

  function renderProviderProfileCard(provider) {
    if (!provider) {
      return '';
    }

    const title = (provider.title || '').trim();
    const bio = (provider.bio || '').trim();
    const education = (provider.education || '').trim();
    const qualifications = (provider.qualifications || '').trim();
    const imageUrl = (provider.profile_image_url || '').trim();

    const titleHtml = title
      ? `<p class="text-sm font-medium text-slate-700">${escapeHtml(title)}</p>`
      : '';
    const bioHtml = bio
      ? `<p class="mt-2 text-sm text-slate-600">${escapeHtml(bio)}</p>`
      : '';
    const educationHtml = education
      ? `<p class="mt-2 text-xs text-slate-500"><span class="font-semibold text-slate-600">Education:</span> ${escapeHtml(education)}</p>`
      : '';
    const qualificationsHtml = qualifications
      ? `<p class="mt-1 text-xs text-slate-500"><span class="font-semibold text-slate-600">Qualifications:</span> ${escapeHtml(qualifications)}</p>`
      : '';
    const imageHtml = imageUrl
      ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(provider.name || 'Provider')}" class="h-12 w-12 rounded-full object-cover border border-slate-200 transition-all duration-200">`
      : `<div class="flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-sm font-semibold text-slate-600 transition-all duration-200">${escapeHtml((provider.name || 'P').trim().charAt(0).toUpperCase() || 'P')}</div>`;

    return `
      <div class="min-h-[80px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition-all duration-200">
        <div class="flex items-start gap-3">
          ${imageHtml}
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900">${escapeHtml(provider.name || 'Provider')}</p>
            ${titleHtml}
            ${bioHtml}
            ${educationHtml}
            ${qualificationsHtml}
          </div>
        </div>
      </div>
    `;
  }

  const DELIVERY_MODE_META = {
    onsite:       { label: 'In Person', icon: 'location_on',  selCls: 'border-blue-300 bg-blue-50 text-blue-700',     defCls: 'border-slate-200 bg-white text-slate-700' },
    online_zoom:  { label: 'Zoom',      icon: 'video_call',   selCls: 'border-purple-300 bg-purple-50 text-purple-700', defCls: 'border-slate-200 bg-white text-slate-700' },
    online_jitsi: { label: 'Jitsi Meet', icon: 'videocam',    selCls: 'border-teal-300 bg-teal-50 text-teal-700',      defCls: 'border-slate-200 bg-white text-slate-700' },
  };

  function renderDeliveryModeSelector(currentState, ctx) {
    if (!currentState.serviceId) return '';
    const available = getAvailableModes(currentState.serviceId, currentState.services, ctx);
    if (available.length < 2) return '';

    const selected = currentState.deliveryMode ?? available[0];
    const options  = available.map(m => {
      const meta       = DELIVERY_MODE_META[m] ?? { label: m, icon: 'help', selCls: 'border-slate-300 bg-slate-100 text-slate-700', defCls: 'border-slate-200 bg-white text-slate-700' };
      const isSelected = m === selected;
      return `
        <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 transition-all
                      ${isSelected ? meta.selCls : meta.defCls}">
          <input type="radio" name="public_delivery_mode" value="${escapeHtml(m)}"
                 ${isSelected ? 'checked' : ''} class="sr-only">
          <span class="material-symbols-outlined text-base">${meta.icon}</span>
          <span class="text-sm font-medium">${escapeHtml(meta.label)}</span>
        </label>`;
    }).join('');

    return `
      <section class="space-y-2">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Session type</h3>
        <div class="flex flex-wrap gap-2">${options}</div>
      </section>`;
  }

  function renderServiceCard(service) {
    if (!service) {
      return '';
    }

    const name = (service.name || '').trim();
    const description = (service.description || '').trim();
    const duration = service.duration ?? null;
    const formattedPrice = (service.formattedPrice || '').trim();

    if (!name && !description && !duration && !formattedPrice) {
      return '';
    }

    const descriptionHtml = description
      ? `<p class="mt-1 text-sm text-slate-600">${escapeHtml(description)}</p>`
      : '';
    const durationHtml = duration
      ? `<p class="mt-2 text-xs text-slate-500"><span class="font-semibold text-slate-600">Duration:</span> ${escapeHtml(String(duration))} min</p>`
      : '';
    const priceHtml = formattedPrice
      ? `<p class="mt-1 text-xs text-slate-500"><span class="font-semibold text-slate-600">Price:</span> ${escapeHtml(formattedPrice)}</p>`
      : '';

    const modeMeta = {
      onsite:       { label: 'In Person', icon: 'location_on',  cls: 'bg-blue-50 text-blue-700' },
      online_zoom:  { label: 'Zoom',      icon: 'video_call',   cls: 'bg-purple-50 text-purple-700' },
      online_jitsi: { label: 'Jitsi Meet', icon: 'videocam',    cls: 'bg-teal-50 text-teal-700' },
    };
    const modes = Array.isArray(service.deliveryModes) ? service.deliveryModes : ['onsite'];
    const deliveryHtml = modes.length > 0
      ? `<div class="mt-2 flex flex-wrap gap-1.5">
          ${modes.map(m => {
            const meta = modeMeta[m] ?? { label: m, icon: 'help', cls: 'bg-slate-100 text-slate-600' };
            return `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${meta.cls}">
              <span class="material-symbols-outlined" style="font-size:11px">${meta.icon}</span>
              ${escapeHtml(meta.label)}
            </span>`;
          }).join('')}
        </div>`
      : '';

    return `
      <div class="min-h-[80px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition-all duration-200">
        <p class="text-sm font-semibold text-slate-900">${escapeHtml(name || 'Service')}</p>
        ${descriptionHtml}
        ${deliveryHtml}
        ${durationHtml}
        ${priceHtml}
      </div>
    `;
  }

  function renderSlotSection(currentState) {
    const isLoading = Boolean(currentState.calendar?.loading);
    const loading = isLoading
      ? '<p class="text-sm text-slate-500">Checking availability...</p>'
      : '';

    const slotButtons = currentState.slots.map(slot => {
      const isSelected = slot.start === currentState.selectedSlot?.start;
      const baseClasses = UI_CLASSES.slotButton;
      const stateClasses = isSelected
        ? 'border-blue-600 bg-blue-50 text-blue-900 shadow-sm'
        : 'border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700';
      return `<button type="button" data-slot-option="${slot.start}" class="${baseClasses} ${stateClasses}">${escapeHtml(slot.label ?? formatSlotLabel(slot))}</button>`;
    }).join('');

    const grid = slotButtons
      ? `<div class="grid gap-2 sm:grid-cols-2">${slotButtons}</div>`
      : ''; 

    const emptyMessage = !isLoading && !currentState.slots.length
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

  function renderLocationCard(currentState) {
    const loc = currentState.resolvedLocation;
    if (!loc) {
      return '';
    }
    const addressHtml = loc.address
      ? `<p class="text-sm text-slate-600">${escapeHtml(loc.address)}</p>`
      : '';
    const contactHtml = loc.contact_number
      ? `<p class="text-sm text-slate-500">${escapeHtml(loc.contact_number)}</p>`
      : '';
    return `
      <div class="${UI_CLASSES.cardInfo} flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
        <div class="min-w-0">
          <p class="font-semibold text-slate-900">${escapeHtml(loc.name)}</p>
          ${addressHtml}
          ${contactHtml}
        </div>
      </div>
    `;
  }

  function renderSchedulingTips(ctx) {
    const policy = ctx?.reschedulePolicy ?? { enabled: true, label: '24 hours' };
    const cancelPolicy = ctx?.cancelPolicy ?? { enabled: true, label: '24 hours' };
    const futureLimitDays = Number(ctx?.futureLimitDays ?? 60);
    const legal = ctx?.legalPolicies ?? {};

    const truncatePolicy = (text) => {
      const compact = String(text ?? '').trim();
      if (!compact) {
        return '';
      }
      if (compact.length <= 130) {
        return compact;
      }
      return `${compact.slice(0, 127).trimEnd()}...`;
    };

    const rescheduleRule = policy.enabled
      ? `Reschedule online up to ${escapeHtml(policy.label ?? '24 hours')} before your appointment.`
      : 'Online rescheduling is disabled. Contact the office for changes.';
    const cancelRule = cancelPolicy.enabled
      ? `Cancel online up to ${escapeHtml(cancelPolicy.label ?? '24 hours')} before your appointment.`
      : 'Online cancellations are disabled. Contact the office for assistance.';

    const cancellationSummary = truncatePolicy(legal.cancellationPolicy);
    const reschedulingSummary = truncatePolicy(legal.reschedulingPolicy);
    const legalPageUrl = String(legal.legalPageUrl ?? '/booking/legal');
    const termsUrl = String(legal.termsUrl ?? '').trim();
    const privacyUrl = String(legal.privacyUrl ?? '').trim();

    const legalLinks = [
      `<a href="${escapeHtml(legalPageUrl)}" target="_blank" rel="noopener" class="font-medium text-blue-700 underline hover:text-blue-800">Full legal policies</a>`,
      termsUrl ? `<a href="${escapeHtml(termsUrl)}" target="_blank" rel="noopener" class="font-medium text-blue-700 underline hover:text-blue-800">Terms</a>` : '',
      privacyUrl ? `<a href="${escapeHtml(privacyUrl)}" target="_blank" rel="noopener" class="font-medium text-blue-700 underline hover:text-blue-800">Privacy</a>` : '',
    ].filter(Boolean).join(' &middot; ');

    return `
      <div class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <span class="font-semibold text-slate-700">Scheduling tips</span>
        <span>Only days with openings are shown. Need another time? Try a different provider or service.</span>
        <span class="mt-2">${rescheduleRule}</span>
        <span>${cancelRule}</span>
        <span>Bookings can be made up to ${escapeHtml(String(futureLimitDays > 0 ? futureLimitDays : 60))} days ahead.</span>
        ${cancellationSummary ? `<span class="mt-2 text-slate-500"><span class="font-medium text-slate-600">Cancellation policy:</span> ${escapeHtml(cancellationSummary)}</span>` : ''}
        ${reschedulingSummary ? `<span class="text-slate-500"><span class="font-medium text-slate-600">Rescheduling policy:</span> ${escapeHtml(reschedulingSummary)}</span>` : ''}
        <span class="mt-2 text-xs">${legalLinks}</span>
      </div>
    `;
  }

  function renderDatePickerField(state) {
    const calendar = state.calendar ?? createCalendarState();
    const dates = calendar.availableDates ?? [];
    const disabled = calendar.loading;
    const hasSelections = Boolean(state.providerId && state.serviceId);
    const futureLimitDays = getFutureLimitDays();

    if (!dates.length && calendar.loading) {
      return `
        <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm">
          <p class="font-medium text-slate-600">Preparing availability…</p>
          <p class="text-slate-500">We are checking the next ${escapeHtml(String(futureLimitDays))} days for openings.</p>
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
          <p>We could not find any openings in the next ${escapeHtml(String(futureLimitDays))} days. Try another provider or service.</p>
        </div>
      `;
    }

    const options = dates.map(date => {
      const selected = date === state.appointmentDate ? 'selected' : '';
      return `<option value="${escapeHtml(date)}" ${selected}>${escapeHtml(formatDateSelectLabel(date))}</option>`;
    }).join('');

    const pills = dates.slice(0, 6).map(date => {
      const isSelected = date === state.appointmentDate;
      const stateClass = isSelected
        ? 'border-blue-600 bg-blue-50 text-blue-900'
        : 'border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700';
      return `<button type="button" data-date-pill="${escapeHtml(date)}" class="${UI_CLASSES.datePill} ${stateClass}">${escapeHtml(formatDateSelectLabel(date))}</button>`;
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

  function renderCustomFields(currentState, ctx, existingCustomFields) {
    const customConfig = ctx.customFieldConfig ?? {};
    const keys = Object.keys(customConfig);

    if (!keys.length) {
      return '';
    }

    const inputs = keys.map(key => renderCustomField(key, customConfig[key], currentState, existingCustomFields)).filter(Boolean).join('');

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

  function renderActions(currentState, options = {}) {
    const submitLabel = options?.submitLabel ?? 'Confirm appointment';
    const pendingLabel = options?.pendingLabel ?? 'Booking your appointment...';
    const helperText = options?.helperText ?? 'We respect your privacy. You can manage this booking later using your secure link plus contact verification.';
    const disabled = currentState.submitting ? 'disabled' : '';
    const text = currentState.submitting ? pendingLabel : submitLabel;
    return `
      <div class="flex flex-col gap-3">
        <button type="submit" class="${UI_CLASSES.buttonPrimary}" ${disabled}>${escapeHtml(text)}</button>
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
    const locationLabel = appointment.location_name ?? '';
    const locationAddr = appointment.location_address ?? '';
    const title = options.title ?? "You're booked!";
    const subtitle = options.subtitle ?? 'We\'ll send a confirmation email shortly with your secure manage link.';
    const footerText = options.footerText ?? 'Need to reschedule? Use your secure manage link and confirm with email or phone.';
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
            <dt class="text-sm font-medium text-slate-500">Manage booking</dt>
            <dd class="text-base font-semibold text-slate-900">Use the secure link in your confirmation message.</dd>
          </div>
          ${locationLabel ? `
          <div class="rounded-2xl border border-slate-200 px-4 py-3 md:col-span-2">
            <dt class="text-sm font-medium text-slate-500">Location</dt>
            <dd class="text-base font-semibold text-slate-900">${escapeHtml(locationLabel)}${locationAddr ? ` &middot; <span class="font-normal text-slate-600">${escapeHtml(locationAddr)}</span>` : ''}</dd>
          </div>
          ` : ''}
          ${appointment.video_link ? `
          <div class="rounded-2xl border border-purple-200 bg-purple-50 px-4 py-3 md:col-span-2">
            <dt class="text-sm font-medium text-purple-700">
              <span class="material-symbols-outlined text-sm align-middle">video_call</span>
              Video meeting link
            </dt>
            <dd class="mt-1">
              <a href="${escapeHtml(appointment.video_link)}" target="_blank" rel="noopener noreferrer"
                 class="break-all text-sm font-semibold text-purple-700 underline hover:text-purple-900">
                ${escapeHtml(appointment.video_link)}
              </a>
            </dd>
          </div>
          ` : ''}
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
        <input name="${name}" value="${escapeHtml(currentState.form[name] ?? '')}" type="${name === 'email' ? 'email' : (name === 'phone' ? 'tel' : 'text')}" class="${UI_CLASSES.inputBase}" ${required ? 'aria-required="true"' : ''}>
        ${renderFieldError(name, currentState.errors)}
      </label>
    `;
  }

  function renderCustomField(key, config, currentState, existingCustomFields) {
    if (!config || config.display === false) {
      return '';
    }

    const label = config.title ?? `Custom field ${config.index}`;
    const isSensitive = !!config.is_sensitive;
    const existingEntry = (existingCustomFields ?? []).find(f => f.field_key === key);
    const existingMasked = existingEntry?.value_masked ?? '';
    const isRequiredForSubmission = !!config.required && !existingMasked;

    const existingHint = existingMasked
      ? `<p class="mt-1 text-xs text-slate-500">Current: <span class="font-medium text-slate-700">${escapeHtml(existingMasked)}</span></p>`
      : '';

    if (config.type === 'textarea') {
      return `
        <label class="block text-sm font-medium text-slate-700">
          ${escapeHtml(label)} ${isRequiredForSubmission ? '<span class="text-red-500">*</span>' : ''}
          <textarea name="${key}" rows="3" class="${UI_CLASSES.inputBase}">${escapeHtml(currentState.form[key] ?? '')}</textarea>
          ${existingHint}
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
        ${existingHint}
        ${renderFieldError(key, currentState.errors)}
      `;
    }

    const placeholder = isSensitive && existingMasked ? existingMasked : '';
    return `
      <label class="block text-sm font-medium text-slate-700">
        ${escapeHtml(label)} ${isRequiredForSubmission ? '<span class="text-red-500">*</span>' : ''}
        <input name="${key}" value="${escapeHtml(currentState.form[key] ?? '')}" type="text" placeholder="${escapeHtml(placeholder)}" class="${UI_CLASSES.inputBase}">
        ${existingHint}
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
    // Delegate to formatSlotSummary but extract only the time portion
    const summary = formatSlotSummary(slot);
    if (!summary) {
      return 'Selected time';
    }
    // Extract "HH:MM – HH:MM" from "Day, Month Date, HH:MM – HH:MM"
    const match = summary.match(/(\d{1,2}:\d{2}\s?[AP]M|\d{1,2}:\d{2})\s–\s(\d{1,2}:\d{2}\s?[AP]M|\d{1,2}:\d{2})/);
    return match ? `${match[1]} – ${match[2]}` : 'Selected time';
  }

  function formatSlotSummary(slot) {
    if (!slot?.start) {
      return '';
    }
    const start = new Date(slot.start);
    const end = slot.end ? new Date(slot.end) : null;
    const use12h = context?.timeFormat !== '24h';
    const appTz = context?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
    const dateFormatter = new Intl.DateTimeFormat('en', {
      weekday: 'short', month: 'short', day: 'numeric', timeZone: appTz,
    });
    const timeFormatter = new Intl.DateTimeFormat('en', {
      hour: 'numeric', minute: '2-digit', hour12: use12h, timeZone: appTz,
    });
    const dateLabel = dateFormatter.format(start);
    const rangeLabel = end
      ? `${timeFormatter.format(start)} – ${timeFormatter.format(end)}`
      : timeFormatter.format(start);
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
    const providerSlug = appointment?.provider?.slug ?? appointment?.provider_slug ?? null;
    const provider = providerSlug
      ? (ctx.providers ?? []).find(item => String(item.slug ?? '') === String(providerSlug))
      : null;
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
    const scriptPayload = document.getElementById('public-booking-context');
    if (scriptPayload?.textContent) {
      try {
        return JSON.parse(scriptPayload.textContent) || {};
      } catch (error) {
        console.error('[public-booking] Failed to parse script context payload.', error);
      }
    }

    try {
      return JSON.parse(root.dataset.context ?? '{}') || window.__PUBLIC_BOOKING__ || {};
    } catch (error) {
      console.error('[public-booking] Failed to parse context payload.', error);
      return window.__PUBLIC_BOOKING__ || {};
    }
  }

  // Resolves app base URL from context or window globals.
  // Note: This intentionally runs standalone without importing url-helpers.js
  // to allow the booking module to function independently.
  function resolveAppBaseUrl(ctx) {
    if (ctx?.appBaseUrl) {
      return String(ctx.appBaseUrl).replace(/\/+$/, '');
    }

    if (typeof window !== 'undefined' && window.__BASE_URL__) {
      return String(window.__BASE_URL__).replace(/\/+$/, '');
    }

    if (ctx?.bookingBaseUrl) {
      return String(ctx.bookingBaseUrl).replace(/\/booking\/?$/, '').replace(/\/+$/, '');
    }

    return '';
  }

  function syncCalendarSelection(prevState, calendar, preferredDate = null) {
    const availableDates = Array.isArray(calendar.availableDates) ? calendar.availableDates : [];

    if (!availableDates.length) {
      return {
        appointmentDate: prevState.appointmentDate,
        resolvedLocation: prevState.resolvedLocation ?? null,
        slots: [],
        selectedSlot: null,
        slotsError: calendar.error || `No availability found in the next ${getFutureLimitDays()} days.`,
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

    // If user has a selected location, use that; otherwise auto-resolve from day-of-week
    let resolvedLocation;
    if (prevState.selectedLocationId) {
      const provider = (context.providers ?? []).find(p => String(p.slug ?? '') === String(prevState.providerId));
      resolvedLocation = (provider?.locations ?? []).find(l => String(l.id) === String(prevState.selectedLocationId)) ?? null;
    } else {
      resolvedLocation = resolveLocationForDate(prevState.providerId, appointmentDate);
    }

    return {
      appointmentDate,
      resolvedLocation,
      slots: slotList,
      selectedSlot,
      slotsError: slotList.length === 0 ? MSG_NO_SLOTS : '',
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

  /**
   * Helper: Find the matching location object for a given providerId + date string.
   * Checks configured location days from ctx.providers[].locations[].days[].
   * Returns the matching location object or null.
   */
  function resolveLocationForDate(providerId, dateStr) {
    if (!providerId || !dateStr) {
      return null;
    }
    const provider = (context.providers ?? []).find(p => String(p.slug ?? '') === String(providerId));
    if (!provider?.locations?.length) {
      return null;
    }
    const d = new Date(`${dateStr}T00:00:00`);
    if (Number.isNaN(d.getTime())) {
      return null;
    }
    const dayOfWeek = d.getDay(); // 0=Sun, 6=Sat
    for (const loc of provider.locations) {
      if (Array.isArray(loc.days) && loc.days.includes(dayOfWeek)) {
        return loc;
      }
    }
    // Fallback: no location matched for this day — return null (don't guess)
    return null;
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
