import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

import { initInactivityMonitor } from '../../resources/js/modules/auth/inactivity-monitor.js';

function buildDom() {
    return new JSDOM(`<!doctype html>
        <html>
        <body>
            <div id="spa-content"></div>
        </body>
        </html>`, { url: 'http://localhost/dashboard' });
}

function flushMicrotasks() {
    return Promise.resolve()
        .then(() => Promise.resolve())
        .then(() => Promise.resolve());
}

function createTimerHarness() {
    let nextId = 1;
    const timeouts = new Map();
    const intervals = new Map();

    function register(store, fn, delay) {
        const id = nextId;
        nextId += 1;
        store.set(id, { fn, delay, cleared: false });
        return id;
    }

    function install(targetWindow) {
        const originals = {
            globalSetTimeout: global.setTimeout,
            globalClearTimeout: global.clearTimeout,
            globalSetInterval: global.setInterval,
            globalClearInterval: global.clearInterval,
            windowSetTimeout: targetWindow.setTimeout,
            windowClearTimeout: targetWindow.clearTimeout,
            windowSetInterval: targetWindow.setInterval,
            windowClearInterval: targetWindow.clearInterval,
        };

        const setTimeoutStub = (fn, delay = 0) => register(timeouts, fn, delay);
        const clearTimeoutStub = (id) => {
            const timeout = timeouts.get(id);
            if (timeout) {
                timeout.cleared = true;
            }
        };
        const setIntervalStub = (fn, delay = 0) => register(intervals, fn, delay);
        const clearIntervalStub = (id) => {
            const interval = intervals.get(id);
            if (interval) {
                interval.cleared = true;
            }
        };

        global.setTimeout = setTimeoutStub;
        global.clearTimeout = clearTimeoutStub;
        global.setInterval = setIntervalStub;
        global.clearInterval = clearIntervalStub;
        targetWindow.setTimeout = setTimeoutStub;
        targetWindow.clearTimeout = clearTimeoutStub;
        targetWindow.setInterval = setIntervalStub;
        targetWindow.clearInterval = clearIntervalStub;

        return () => {
            global.setTimeout = originals.globalSetTimeout;
            global.clearTimeout = originals.globalClearTimeout;
            global.setInterval = originals.globalSetInterval;
            global.clearInterval = originals.globalClearInterval;
            targetWindow.setTimeout = originals.windowSetTimeout;
            targetWindow.clearTimeout = originals.windowClearTimeout;
            targetWindow.setInterval = originals.windowSetInterval;
            targetWindow.clearInterval = originals.windowClearInterval;
        };
    }

    function runNextTimeout() {
        const next = [...timeouts.entries()].find(([, entry]) => !entry.cleared);
        assert.ok(next, 'expected a pending timeout');
        const [id, entry] = next;
        entry.cleared = true;
        timeouts.delete(id);
        entry.fn();
    }

    function runTimeoutByDelay(delay) {
        const next = [...timeouts.entries()].find(([, entry]) => !entry.cleared && entry.delay === delay);
        assert.ok(next, `expected a pending timeout with delay ${delay}`);
        const [id, entry] = next;
        entry.cleared = true;
        timeouts.delete(id);
        entry.fn();
    }

    function activeTimeoutCount() {
        return [...timeouts.values()].filter((entry) => !entry.cleared).length;
    }

    function activeIntervalCount() {
        return [...intervals.values()].filter((entry) => !entry.cleared).length;
    }

    return {
        activeIntervalCount,
        activeTimeoutCount,
        install,
        runNextTimeout,
        runTimeoutByDelay,
    };
}

function installBrowserGlobals(dom) {
    const { window } = dom;
    const originals = {
        window: global.window,
        document: global.document,
        Event: global.Event,
        CustomEvent: global.CustomEvent,
        fetch: global.fetch,
    };

    global.window = window;
    global.document = window.document;
    global.Event = window.Event;
    global.CustomEvent = window.CustomEvent;
    window.__BASE_URL__ = 'http://localhost';

    return () => {
        global.window = originals.window;
        global.document = originals.document;
        global.Event = originals.Event;
        global.CustomEvent = originals.CustomEvent;
        global.fetch = originals.fetch;
    };
}

test('stay logged in retries transient ping failures and pauses the countdown while pending', async () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);
    const originalWarn = console.warn;

    let fetchCalls = 0;
    global.fetch = async () => {
        fetchCalls += 1;

        if (fetchCalls === 1) {
            return { ok: false, status: 401 };
        }

        return { ok: true, status: 200 };
    };
    console.warn = () => {};

    try {
        initInactivityMonitor();
        timers.runNextTimeout();

        const stayButton = document.getElementById('xs-session-stay');
        const modal = document.getElementById('xs-session-warning-modal');
        assert.ok(modal);
        assert.equal(modal.classList.contains('hidden'), false);

        stayButton.dispatchEvent(new dom.window.Event('click', { bubbles: true }));
        await flushMicrotasks();

        assert.equal(fetchCalls, 1);
        assert.equal(stayButton.disabled, true);
        assert.equal(timers.activeIntervalCount(), 0);
        assert.equal(dom.window.location.pathname, '/dashboard');

        timers.runTimeoutByDelay(3000);
        await flushMicrotasks();

        assert.equal(fetchCalls, 2);
        assert.equal(modal.classList.contains('hidden'), true);
        assert.equal(stayButton.disabled, false);
        assert.equal(dom.window.location.pathname, '/dashboard');
    } finally {
        console.warn = originalWarn;
        dom.window.document.dispatchEvent(new dom.window.CustomEvent('spa:leaving', {
            detail: { url: '/dashboard' },
        }));
        restoreTimers();
        restoreGlobals();
    }
});

test('spa leaving removes the warning modal and cancels pending keepalive retries before re-init', async () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);
    const originalWarn = console.warn;

    global.fetch = async () => ({ ok: false, status: 401 });
    console.warn = () => {};

    try {
        initInactivityMonitor();
        timers.runNextTimeout();

        document.getElementById('xs-session-stay').dispatchEvent(
            new dom.window.Event('click', { bubbles: true })
        );
        await flushMicrotasks();

        assert.equal(timers.activeTimeoutCount(), 2);
        assert.equal(document.querySelectorAll('#xs-session-warning-modal').length, 1);

        document.dispatchEvent(new dom.window.CustomEvent('spa:leaving', {
            detail: { url: '/appointments' },
        }));

        assert.equal(document.querySelectorAll('#xs-session-warning-modal').length, 0);
        assert.equal(timers.activeTimeoutCount(), 0);
        assert.equal(timers.activeIntervalCount(), 0);

        initInactivityMonitor();
        assert.equal(timers.activeTimeoutCount(), 1);
    } finally {
        console.warn = originalWarn;
        dom.window.document.dispatchEvent(new dom.window.CustomEvent('spa:leaving', {
            detail: { url: '/appointments' },
        }));
        restoreTimers();
        restoreGlobals();
    }
});