const DEFAULT_HEADERS = {
  'Accept': 'application/json',
};

const jsonHeaders = {
  'Accept': 'application/json',
  'Content-Type': 'application/json',
};

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]';
}

function toQuery(params = {}) {
  const qs = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;
    if (Array.isArray(value)) {
      value.forEach((v) => {
        if (v !== null && v !== undefined && v !== '') {
          qs.append(key, v);
        }
      });
    } else {
      qs.append(key, value);
    }
  });
  return qs.toString();
}

function unwrapPayload(payload) {
  if (!isPlainObject(payload)) {
    return payload;
  }

  if ('error' in payload) {
    const message = payload?.error?.message || payload?.message || 'Request failed';
    const error = new Error(message);
    error.name = 'SchedulerServiceError';
    error.payload = payload;
    throw error;
  }

  if ('data' in payload) {
    return payload.data;
  }

  return payload;
}

async function executeJSON(url, options = {}) {
  const response = await fetch(url, options);
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const error = new Error(body?.message || `Request failed with status ${response.status}`);
    error.status = response.status;
    error.payload = body;
    throw error;
  }
  const json = await response.json().catch(() => null);
  return unwrapPayload(json);
}

export function createSchedulerService({ baseUrl, slotsUrl }) {
  if (!baseUrl) {
    throw new Error('[scheduler-service] baseUrl is required');
  }
  const normalizedBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
  const normalizedSlots = slotsUrl ? (slotsUrl.endsWith('/') ? slotsUrl.slice(0, -1) : slotsUrl) : null;

  const withBase = (path, params) => {
    const hasProtocol = /^https?:/i.test(path);
    const target = hasProtocol ? path : `${normalizedBase}${path.startsWith('/') ? path : `/${path}`}`;
    if (!params || Object.keys(params).length === 0) {
      return target;
    }
    const queryString = toQuery(params);
    return queryString ? `${target}?${queryString}` : target;
  };

  async function getCounts(filters = {}) {
    const url = withBase('/appointments/counts', {
      providerId: filters.providerId,
      serviceId: filters.serviceId,
    });
    return executeJSON(url, { headers: DEFAULT_HEADERS });
  }

  async function getAppointments(range, filters = {}, options = {}) {
    const url = withBase('/appointments', {
      providerId: filters.providerId,
      serviceId: filters.serviceId,
      start: range?.start,
      end: range?.end,
    });
    const requestOptions = { headers: DEFAULT_HEADERS };
    if (options.signal) requestOptions.signal = options.signal;
    return executeJSON(url, requestOptions);
  }

  async function getSummary(filters = {}) {
    const url = withBase('/appointments/summary', {
      providerId: filters.providerId,
      serviceId: filters.serviceId,
    });
    return executeJSON(url, { headers: DEFAULT_HEADERS });
  }

  async function getAppointment(id) {
    if (!id) throw new Error('[scheduler-service] Appointment id is required');
    const url = withBase(`/appointments/${id}`);
    return executeJSON(url, { headers: DEFAULT_HEADERS });
  }

  async function createAppointment(payload) {
    const url = withBase('/appointments');
    return executeJSON(url, {
      method: 'POST',
      headers: jsonHeaders,
      body: JSON.stringify(payload ?? {}),
    });
  }

  async function updateAppointment(id, payload) {
    if (!id) throw new Error('[scheduler-service] Appointment id is required');
    const url = withBase(`/appointments/${id}`);
    return executeJSON(url, {
      method: 'PATCH',
      headers: jsonHeaders,
      body: JSON.stringify(payload ?? {}),
    });
  }

  async function cancelAppointment(id) {
    if (!id) throw new Error('[scheduler-service] Appointment id is required');
    const url = withBase(`/appointments/${id}`);
    return executeJSON(url, {
      method: 'DELETE',
      headers: DEFAULT_HEADERS,
    });
  }

  async function getSlots(filters = {}) {
    if (!normalizedSlots) {
      throw new Error('[scheduler-service] slotsUrl is required for slot queries');
    }
    const query = toQuery({
      provider_id: filters.providerId,
      service_id: filters.serviceId,
      date: filters.date,
    });
    const url = query ? `${normalizedSlots}?${query}` : normalizedSlots;
    return executeJSON(url, { headers: DEFAULT_HEADERS });
  }

  return {
    getCounts,
    getAppointments,
    getAppointment,
    createAppointment,
    updateAppointment,
    cancelAppointment,
    getSlots,
    getSummary,
  };
}

export default createSchedulerService;
