export function buildCalendarModelUrl(baseUrl, view, currentDate, activeFilters = {}) {
  let url;

  if (view === 'month') {
    url = `${baseUrl}/month?year=${currentDate.year}&month=${currentDate.month}`;
  } else {
    url = `${baseUrl}/${view}?date=${currentDate.toISODate()}`;
  }

  if (activeFilters.providerId) url += `&provider_id=${activeFilters.providerId}`;
  if (activeFilters.serviceId) url += `&service_id=${activeFilters.serviceId}`;
  if (activeFilters.locationId) url += `&location_id=${activeFilters.locationId}`;
  if (activeFilters.status) url += `&status=${activeFilters.status}`;

  return url;
}
