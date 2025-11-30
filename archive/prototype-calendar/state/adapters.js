function buildCanonicalState(prevState, payload, meta = {}) {
  if (!payload) return prevState;
  const {
    providers = [],
    appointments = [],
    providerOrder = [],
    rangeStart,
    rangeEnd,
    timezone,
  } = payload;

  const nextProviders = { ...prevState.providers };
  providers.forEach(provider => {
    nextProviders[provider.id] = {
      id: provider.id,
      displayName: provider.displayName ?? provider.name ?? 'Provider',
      colorToken: provider.colorToken ?? 'blue-500',
      location: provider.location ?? 'Main',
      specialty: provider.specialty,
      avatarUrl: provider.avatarUrl,
      orderingWeight: provider.orderingWeight ?? 0,
      isActive: provider.isActive ?? true,
    };
  });

  const nextAppointments = { ...prevState.appointments };
  appointments.forEach(appt => {
    nextAppointments[appt.id] = {
      id: appt.id,
      providerId: appt.providerId,
      patientName: appt.patientName ?? 'Patient',
      visitType: appt.visitType ?? 'Visit',
      status: appt.status ?? 'confirmed',
      start: appt.start,
      end: appt.end,
      durationMinutes: appt.durationMinutes ?? 30,
      room: appt.room,
      colorOverride: appt.colorOverride,
      metadata: appt.metadata ?? {},
    };
  });

  return {
    ...prevState,
    providers: nextProviders,
    appointments: nextAppointments,
    providerOrder: providerOrder.length ? providerOrder : prevState.providerOrder,
    meta: {
      ...prevState.meta,
      hydrationSource: meta.source ?? 'bootstrap',
      lastUpdatedAt: Date.now(),
      rangeStart: rangeStart ?? prevState.meta.rangeStart,
      rangeEnd: rangeEnd ?? prevState.meta.rangeEnd,
      timezone: timezone ?? prevState.meta.timezone,
      loadedRangeStart: extendBoundary(prevState.meta.loadedRangeStart, rangeStart, 'start'),
      loadedRangeEnd: extendBoundary(prevState.meta.loadedRangeEnd, rangeEnd, 'end'),
    },
  };
}

function extendBoundary(previousValue, incomingValue, direction = 'start') {
  if (!incomingValue) {
    return previousValue ?? null;
  }
  if (!previousValue) {
    return incomingValue;
  }

  if (direction === 'start') {
    return incomingValue < previousValue ? incomingValue : previousValue;
  }
  return incomingValue > previousValue ? incomingValue : previousValue;
}

export { buildCanonicalState };
