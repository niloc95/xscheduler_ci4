import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

import { bindAppLifecycleEvents } from '../../resources/js/modules/app-lifecycle.js';
import { initProviderSchedule } from '../../resources/js/modules/user-management/provider-schedule.js';

function buildDom() {
    return new JSDOM(`<!doctype html>
        <html>
        <body>
            <select id="role">
                <option value="">Select</option>
                <option value="provider" selected>Provider</option>
                <option value="staff">Staff</option>
            </select>
            <div id="providerScheduleSection"></div>
            <div id="providerLocationsWrapper"></div>
            <div id="providerAssignmentsSection"></div>
            <div id="staffAssignmentsSection" class="hidden"></div>
            <div id="role-description" class="hidden"></div>
            <div id="role-permissions"></div>
            <div class="provider-color-field hidden"></div>

            <section data-provider-schedule-section data-source-day="monday">
                <button type="button" data-copy-schedule>Copy</button>

                <div data-schedule-day="monday" data-day-label="Monday">
                    <input type="checkbox" class="js-day-active" checked>
                    <input type="hidden" name="schedule[monday][is_active]" value="1">
                    <input data-time-input data-field="start" value="09:00">
                    <input data-time-input data-field="end" value="17:00">
                    <input data-time-input data-field="break_start" value="12:00">
                    <input data-time-input data-field="break_end" value="13:00">
                </div>

                <div data-schedule-day="tuesday" data-day-label="Tuesday">
                    <input type="checkbox" class="js-day-active">
                    <input type="hidden" name="schedule[tuesday][is_active]" value="0">
                    <input data-time-input data-field="start" value="">
                    <input data-time-input data-field="end" value="">
                    <input data-time-input data-field="break_start" value="">
                    <input data-time-input data-field="break_end" value="">
                </div>
            </section>
        </body>
        </html>`, { url: 'http://localhost/appointments' });
}

test('spa:navigated re-runs provider schedule initializer against browser-like DOM', async () => {
    const dom = buildDom();
    const { window } = dom;

    global.window = window;
    global.document = window.document;
    global.Event = window.Event;

    bindAppLifecycleEvents({
        documentRef: window.document,
        initializeComponents: () => {
            initProviderSchedule();
        },
        refreshAppointmentStats: () => {},
        resetSchedulerInitAttempts: () => {},
        hasDashboardStats: () => false,
    });

    await new Promise((resolve) => setTimeout(resolve, 0));

    window.document.dispatchEvent(new window.CustomEvent('spa:navigated', {
        detail: { url: '/user-management/edit/provider-hash' },
    }));

    const section = window.document.querySelector('[data-provider-schedule-section]');
    const copyButton = window.document.querySelector('[data-copy-schedule]');
    const providerSection = window.document.getElementById('providerScheduleSection');
    const roleDescription = window.document.getElementById('role-description');
    const rolePermissions = window.document.getElementById('role-permissions');
    const providerColorField = window.document.querySelector('.provider-color-field');

    assert.equal(section.dataset.providerScheduleInitialized, 'true');
    assert.equal(copyButton.disabled, false);
    assert.equal(copyButton.getAttribute('aria-disabled'), 'false');
    assert.equal(providerSection.classList.contains('hidden'), false);
    assert.equal(roleDescription.classList.contains('hidden'), false);
    assert.match(rolePermissions.innerHTML, /manage own calendar/i);
    assert.equal(providerColorField.classList.contains('hidden'), false);

    window.document.dispatchEvent(new window.CustomEvent('spa:navigated', {
        detail: { url: '/user-management/edit/provider-hash' },
    }));

    assert.equal(section.dataset.providerScheduleInitialized, 'true');

    await new Promise((resolve) => setTimeout(resolve, 0));

    delete global.window;
    delete global.document;
    delete global.Event;
});