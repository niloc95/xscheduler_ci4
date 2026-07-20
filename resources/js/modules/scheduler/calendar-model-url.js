export function buildCalendarModelUrl(baseUrl, view, currentDate, activeFilters = {}) {
  let url;

  // The agenda (list) view shares the day render model — there is no
  // dedicated /api/calendar/agenda endpoint.
  const apiView = view === 'agenda' ? 'day' : view;

  if (apiView === 'month') {
    url = `${baseUrl}/month?year=${currentDate.year}&month=${currentDate.month}`;
  } else {
    url = `${baseUrl}/${apiView}?date=${currentDate.toISODate()}`;
  }

  if (activeFilters.providerId) url += `&provider_id=${activeFilters.providerId}`;
  if (activeFilters.serviceId) url += `&service_id=${activeFilters.serviceId}`;
  if (activeFilters.locationId) url += `&location_id=${activeFilters.locationId}`;
  if (activeFilters.status) url += `&status=${activeFilters.status}`;

  return url;
}
