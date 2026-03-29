import assert from 'node:assert/strict';
import test from 'node:test';

import { bindAppLifecycleEvents } from '../../resources/js/modules/app-lifecycle.js';

class FakeDocument {
    constructor() {
        this.listeners = new Map();
        this.elements = new Map();
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    getElementById(id) {
        return this.elements.get(id) || null;
    }

    dispatch(type, detail = {}) {
        for (const listener of this.listeners.get(type) || []) {
            listener({ detail });
        }
    }
}

test('bindAppLifecycleEvents initializes components and stats on DOM ready', () => {
    const documentRef = new FakeDocument();
    let initializeCalls = 0;
    let refreshCalls = 0;

    bindAppLifecycleEvents({
        documentRef,
        initializeComponents: () => {
            initializeCalls += 1;
        },
        refreshAppointmentStats: () => {
            refreshCalls += 1;
        },
    });

    documentRef.dispatch('DOMContentLoaded');

    assert.equal(initializeCalls, 1);
    assert.equal(refreshCalls, 1);
});

test('bindAppLifecycleEvents resets scheduler state and refreshes dashboard stats on spa:navigated', () => {
    const documentRef = new FakeDocument();
    let initializeCalls = 0;
    let refreshCalls = 0;
    let resetCalls = 0;

    documentRef.elements.set('upcomingCount', {});

    bindAppLifecycleEvents({
        documentRef,
        initializeComponents: () => {
            initializeCalls += 1;
        },
        refreshAppointmentStats: () => {
            refreshCalls += 1;
        },
        resetSchedulerInitAttempts: () => {
            resetCalls += 1;
        },
    });

    documentRef.dispatch('spa:navigated', { url: '/appointments/create' });

    assert.equal(resetCalls, 1);
    assert.equal(initializeCalls, 1);
    assert.equal(refreshCalls, 1);
});

test('bindAppLifecycleEvents skips stats refresh on spa:navigated when dashboard counters are absent', () => {
    const documentRef = new FakeDocument();
    let initializeCalls = 0;
    let refreshCalls = 0;

    bindAppLifecycleEvents({
        documentRef,
        initializeComponents: () => {
            initializeCalls += 1;
        },
        refreshAppointmentStats: () => {
            refreshCalls += 1;
        },
    });

    documentRef.dispatch('spa:navigated', { url: '/user-management/edit/provider-hash' });

    assert.equal(initializeCalls, 1);
    assert.equal(refreshCalls, 0);
});