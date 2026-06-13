import { normalizeCalendarPayload } from '../calendar/calendar-utils.js';

export const FIELD_LABELS = {
  first_name: 'First name',
  last_name: 'Last name',
  email: 'Email address',
  phone: 'Phone number',
  address: 'Address',
  notes: 'Notes',
};

export const UI_CLASSES = {
  buttonPrimary: 'inline-flex w-full items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 dark:focus-visible:ring-blue-800 disabled:cursor-not-allowed disabled:bg-blue-300 dark:disabled:bg-blue-900',
  buttonSecondary: 'inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 transition hover:border-blue-400 hover:text-blue-600 dark:hover:border-blue-500 dark:hover:text-blue-400',
  inputBase: 'mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus-visible:ring-blue-200',
  selectBase: 'mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus-visible:ring-blue-200',
  cardBase: 'rounded-2xl border border-slate-200 dark:border-slate-700 px-4 py-3',
  cardInfo: 'rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40 px-4 py-3 text-sm text-slate-600 dark:text-slate-300',
  cardError: 'rounded-2xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-300',
  cardWarning: 'rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-700 dark:text-amber-300',
  cardDashed: 'rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 px-4 py-3 text-sm text-slate-600 dark:text-slate-300',
  slotButton: 'w-full rounded-2xl border px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200',
  datePill: 'rounded-2xl border px-3 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200',
  tabButton: 'w-full rounded-2xl px-5 py-3 text-left text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200',
};

export function createCalendarState(source = null) {
  const normalized = normalizeCalendarPayload(source);
  return {
    ...normalized,
    loading: false,
    error: '',
  };
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
  Object.keys(fieldConfig).forEach((key) => {
    if (base[key] === undefined) {
      base[key] = '';
    }
  });

  const customConfig = ctx.customFieldConfig ?? {};
  Object.keys(customConfig).forEach((key) => {
    base[key] = customConfig[key].type === 'checkbox' ? '0' : '';
  });

  return base;
}

export function createBookingDraft(ctx, defaultDate) {
  const providerId = ctx.providers?.[0]?.slug?.toString() ?? '';
  const serviceId = ctx.services?.[0]?.id?.toString() ?? '';

  const firstProvider = (ctx.providers ?? []).find((provider) => String(provider.slug ?? '') === providerId);
  const providerLocations = firstProvider?.locations ?? [];
  const autoLocationId = providerLocations.length === 1 ? String(providerLocations[0].id) : '';

  return {
    providerId,
    serviceId,
    selectedLocationId: autoLocationId,
    services: ctx.services ?? [],
    servicesLoading: false,
    appointmentDate: defaultDate,
    slots: [],
    slotsError: '',
    selectedSlot: null,
    resolvedLocation: null,
    deliveryMode: null,
    selectedPaymentGateway: null,
    calendar: createCalendarState(),
    form: createInitialFormState(ctx),
    errors: {},
    globalError: '',
    submitting: false,
    success: null,
    tipsMobileOpen: false,
  };
}

export function createManageDraft(ctx, defaultDate) {
  const prefilledReference = String(ctx.manageReference ?? '');

  return {
    stage: 'lookup',
    hasPrefilledReference: Boolean(prefilledReference.trim()),
    lookupForm: { reference: prefilledReference, email: '', phone: '', phone_country_code: '' },
    lookupErrors: {},
    lookupError: '',
    lookupLoading: false,
    appointment: null,
    searchResults: [],
    success: null,
    contact: { email: '', phone: '', phone_country_code: '' },
    cancelSubmitting: false,
    cancelError: '',
    tipsMobileOpen: false,
    formState: {
      providerId: '',
      serviceId: '',
      selectedLocationId: '',
      services: ctx.services ?? [],
      servicesLoading: false,
      appointmentDate: defaultDate,
      slots: [],
      slotsError: '',
      selectedSlot: null,
      resolvedLocation: null,
      calendar: createCalendarState(),
      form: createInitialFormState(ctx),
      errors: {},
      globalError: '',
      submitting: false,
      tipsMobileOpen: false,
    },
  };
}
