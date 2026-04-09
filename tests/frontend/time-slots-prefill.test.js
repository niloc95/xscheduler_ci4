import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

function buildDom() {
  return new JSDOM(`<!doctype html>
    <html>
    <body>
      <select id="provider_id">
        <option value="">Select a provider...</option>
        <option value="12">Provider A</option>
      </select>

      <select id="service_id">
        <option value="">Select a provider first...</option>
      </select>

      <div id="location-selection-wrapper" class="hidden">
        <select id="location_id">
          <option value="">Select a provider first...</option>
        </select>
      </div>

      <input id="appointment_date" type="date" value="">
      <input id="appointment_time" type="hidden" value="">

      <div id="time-slots-prompt" class="hidden"></div>
      <div id="time-slots-loading" class="hidden"></div>
      <div id="time-slots-empty" class="hidden"></div>
      <div id="time-slots-error" class="hidden"></div>
      <div id="time-slots-error-message"></div>
      <div id="time-slots-grid" class="hidden"></div>
      <div id="available-dates-hint" class="hidden"></div>
      <div id="available-dates-pills"></div>
      <div id="no-availability-warning" class="hidden"></div>
    </body>
    </html>`, {
    url: 'http://localhost/appointments/create?provider_id=12&service_id=8&location_id=5&date=2026-04-10&time=09:30',
  });
}

function createJsonResponse(payload, ok = true, status = 200) {
  return {
    ok,
    status,
    async json() {
      return payload;
    },
  };
}

test('initTimeSlotsUI preserves provider, service, location, date and time from quick-book URL params', async () => {
  const dom = buildDom();
  const { window } = dom;

  global.window = window;
  global.document = window.document;
  global.Event = window.Event;

  const { initTimeSlotsUI } = await import('../../resources/js/modules/appointments/time-slots-ui.js');

  const availabilityUrls = [];

  global.fetch = async (url) => {
    const href = String(url);

    if (href.includes('/api/v1/providers/12/services')) {
      return createJsonResponse({
        data: [
          { id: 8, name: 'Consultation', duration_min: 60, price: '95.00' },
          { id: 9, name: 'Follow-up', duration_min: 30, price: '45.00' },
        ],
      });
    }

    if (href.includes('/api/locations?provider_id=12')) {
      return createJsonResponse({
        data: [
          { id: 5, name: 'Main Office', address: '1 Main St' },
          { id: 6, name: 'Branch Office', address: '2 Side St' },
        ],
      });
    }

    if (href.includes('/api/availability/calendar?')) {
      availabilityUrls.push(href);
      return createJsonResponse({
        data: {
          availableDates: ['2026-04-10'],
          slotsByDate: {
            '2026-04-10': [
              {
                startFormatted: '09:30',
                label: '09:30',
                start: '2026-04-10T09:30:00',
                end: '2026-04-10T10:30:00',
              },
            ],
          },
        },
      });
    }

    return createJsonResponse({}, false, 404);
  };

  initTimeSlotsUI({
    providerSelectId: 'provider_id',
    serviceSelectId: 'service_id',
    locationSelectId: 'location_id',
    dateInputId: 'appointment_date',
    timeInputId: 'appointment_time',
  });

  await new Promise((resolve) => setTimeout(resolve, 0));
  await new Promise((resolve) => setTimeout(resolve, 0));

  const providerSelect = window.document.getElementById('provider_id');
  const serviceSelect = window.document.getElementById('service_id');
  const locationSelect = window.document.getElementById('location_id');
  const dateInput = window.document.getElementById('appointment_date');
  const timeInput = window.document.getElementById('appointment_time');

  assert.equal(providerSelect.value, '12');
  assert.equal(serviceSelect.value, '8');
  assert.equal(locationSelect.value, '5');
  assert.equal(dateInput.value, '2026-04-10');
  assert.equal(timeInput.value, '09:30');

  assert.ok(
    availabilityUrls.some((u) => u.includes('location_id=5')),
    'availability request should include selected location_id for quick-book prefill'
  );

  delete global.window;
  delete global.document;
  delete global.Event;
  delete global.fetch;
});
