import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

import { bindAppLifecycleEvents } from '../../resources/js/modules/app-lifecycle.js';
import { initAppointmentForm } from '../../resources/js/modules/appointments/appointments-form.js';

function buildDom() {
    return new JSDOM(`<!doctype html>
        <html>
        <head>
            <meta name="csrf-token" content="csrf-token-value">
            <meta name="csrf-header" content="X-CSRF-TOKEN">
        </head>
        <body>
            <form action="http://localhost/appointments/update/demo-hash" method="POST"
                data-appointment-form="true"
                data-is-edit-mode="1"
                data-expects-time-slots="0"
                data-currency-symbol="$"
                data-time-format="12h">
                <input id="client_timezone" name="client_timezone" value="">
                <input id="client_offset" name="client_offset" value="">
                <input name="csrf_test_name" value="csrf-token-value">
                <select id="provider_id"><option value="12" selected>Provider</option></select>
                <select id="service_id"><option value="8" data-duration="60" data-price="95" selected>Consultation - 60 min</option></select>
                <select id="location_id"><option value="5" selected>Main Office</option></select>
                <input id="appointment_date" value="2026-04-10">
                <input id="appointment_time" value="09:30">
                <div id="appointment-summary" class="hidden"></div>
                <div id="summary-location-label" class="hidden"></div>
                <div id="summary-location" class="hidden"></div>
                <div id="summary-service"></div>
                <div id="summary-provider"></div>
                <div id="summary-datetime"></div>
                <div id="summary-duration"></div>
                <div id="summary-price"></div>
                <button type="submit">Save</button>
            </form>
        </body>
        </html>`, { url: 'http://localhost/appointments/edit/demo-hash' });
}

test('spa:navigated re-runs appointment form initializer against browser-like DOM', async () => {
    const dom = buildDom();
    const { window } = dom;

    global.window = window;
    global.document = window.document;
    global.Event = window.Event;
    global.FormData = window.FormData;
    global.fetch = async () => ({ ok: true });

    window.xsEscapeHtml = (value) => String(value ?? '');
    window.currencyFormatter = {
        format(value) {
            return `$${Number.parseFloat(value).toFixed(2)}`;
        },
    };

    bindAppLifecycleEvents({
        documentRef: window.document,
        initializeComponents: () => {
            initAppointmentForm();
        },
        refreshAppointmentStats: () => {},
        resetSchedulerInitAttempts: () => {},
        hasDashboardStats: () => false,
    });

    await new Promise((resolve) => setTimeout(resolve, 0));

    window.document.dispatchEvent(new window.CustomEvent('spa:navigated', {
        detail: { url: '/appointments/edit/demo-hash' },
    }));

    const form = window.document.querySelector('[data-appointment-form="true"]');
    const timezoneField = window.document.getElementById('client_timezone');
    const offsetField = window.document.getElementById('client_offset');

    assert.equal(form.dataset.appointmentUiWired, 'true');
    assert.equal(form.dataset.formSubmitWired, 'true');
    assert.equal(window.document.body.dataset.visibilityRefreshBound, 'true');
    assert.notEqual(timezoneField.value, '');
    assert.notEqual(offsetField.value, '');
    assert.equal(window.appCurrencySymbol, '$');

    window.document.dispatchEvent(new window.CustomEvent('spa:navigated', {
        detail: { url: '/appointments/edit/demo-hash' },
    }));

    assert.equal(form.dataset.appointmentUiWired, 'true');
    assert.equal(form.dataset.formSubmitWired, 'true');

    await new Promise((resolve) => setTimeout(resolve, 0));

    delete global.window;
    delete global.document;
    delete global.Event;
    delete global.FormData;
    delete global.fetch;
});