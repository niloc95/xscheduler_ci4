import { formatCurrency } from './currency.js';
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
import {
  renderHero,
  renderTabs,
  renderManageSection,
  renderForm,
  renderSuccess,
  renderDashedCard,
  formatSlotSummary,
  extractErrorDetails,
} from './modules/public-booking/render.js';
import {
  parseContext,
  resolveAppBaseUrl,
  cloneState,
  getAvailableModes,
  SubmissionError,
} from './modules/public-booking/utils.js';

const root = document.getElementById('public-booking-root');

const MSG_NO_SLOTS = 'No slots available for this date. Try another day.';
const MSG_NO_AVAILABILITY = 'Unable to load availability.';


if (!root) {
  console.warn('[public-booking] Root element not found.');
} else {
  bootstrapPublicBooking();
}

function bootstrapPublicBooking() {
  const context = parseContext(root);
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
        <div class="mx-auto w-full max-w-5xl space-y-6">
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

    form.querySelector('[data-action="tips-mobile-toggle"]')?.addEventListener('click', () => {
      updateDraft(target, prev => ({ ...prev, tipsMobileOpen: !prev.tipsMobileOpen }));
    });
  }

  function bindLookupEvents() {
    const form = root.querySelector('#booking-lookup-form');
    if (!form) {
      return;
    }
    form.addEventListener('input', handleLookupInput);
    form.addEventListener('submit', handleLookupSubmit);
    form.querySelector('[data-action="tips-mobile-toggle"]')?.addEventListener('click', () => {
      updateManage(prev => ({ ...prev, tipsMobileOpen: !prev.tipsMobileOpen }));
    });
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
        enterRescheduleStage(data?.data, { email, phone, phone_country_code: phoneCountryCode });
      } else {
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
    const slotLabel = appointment.start ? formatSlotSummary({ start: appointment.start, end: appointment.end }, context) : '';

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

    const locationId = draft.resolvedLocation?.id ?? draft.selectedLocationId;
    if (locationId) {
      payload.location_id = Number(locationId);
    }

    Object.entries(draft.form).forEach(([key, value]) => {
      payload[key] = value;
    });

    return { ...payload, ...extras };
  }

  function updateCsrfFromBody(data) {
    if (data?.csrf?.value) {
      root.dataset.csrfValue = data.csrf.value;
    }
  }

  // ─── Context-dependent utilities (closure-scoped) ────────────────────────

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
    const dayOfWeek = d.getDay();
    for (const loc of provider.locations) {
      if (Array.isArray(loc.days) && loc.days.includes(dayOfWeek)) {
        return loc;
      }
    }
    return null;
  }

  // ─── Focus preservation ───────────────────────────────────────────────────

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
        // ignore selection errors for input types that don't support ranges (email, tel, etc.)
      }
    }
  }
}
