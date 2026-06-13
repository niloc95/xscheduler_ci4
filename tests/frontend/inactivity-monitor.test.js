import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM, VirtualConsole } from 'jsdom';

import { initInactivityMonitor } from '../../resources/js/modules/auth/inactivity-monitor.js';

// Mirror the constants in inactivity-monitor.js
const SESSION_MS = 7200 * 1000;
const WARNING_MS = 5 * 60 * 1000;

function buildDom() {
    // Capture redirect attempts: jsdom does not implement navigation, so a
    // `window.location.href = ...` assignment surfaces as a jsdomError.
    const navigations = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', (error) => {
        if (String(error.message).includes('navigation')) {
            navigations.push(error);
        }
    });

    const dom = new JSDOM(`<!doctype html>
        <html>
        <body>
            <div id="spa-content"></div>
        </body>
        </html>`, { url: 'http://localhost/dashboard', virtualConsole });
    dom.navigations = navigations;
    return dom;
}

function flushMicrotasks() {
    return Promise.resolve()
        .then(() => Promise.resolve())
        .then(() => Promise.resolve());
}

function installClock(startMs = 1_750_000_000_000) {
    const originalNow = Date.now;
    let nowMs = startMs;
    Date.now = () => nowMs;
    return {
        advance(ms) { nowMs += ms; },
        restore() { Date.now = originalNow; },
    };
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

    function runTimeoutByDelay(delay) {
        const next = [...timeouts.entries()].find(([, entry]) => !entry.cleared && entry.delay === delay);
        assert.ok(next, `expected a pending timeout with delay ${delay}`);
        const [id, entry] = next;
        entry.cleared = true;
        timeouts.delete(id);
        entry.fn();
    }

    function activeTimeoutCount() {
        // jsdom's Storage implementation schedules 0ms timeouts to fire
        // `storage` events; only the monitor's own delayed timers matter here.
        return [...timeouts.values()].filter((entry) => !entry.cleared && entry.delay > 0).length;
    }

    function activeIntervalCount() {
        return [...intervals.values()].filter((entry) => !entry.cleared).length;
    }

    return {
        activeIntervalCount,
        activeTimeoutCount,
        install,
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

/** Simulate the tab regaining focus, which re-evaluates idle state from wall-clock. */
function triggerWake(dom) {
    dom.window.dispatchEvent(new dom.window.Event('focus'));
}

function teardown(dom) {
    dom.window.document.dispatchEvent(new dom.window.CustomEvent('spa:leaving', {
        detail: { url: '/teardown' },
    }));
}

test('warning appears at the wall-clock threshold with a full countdown', () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const clock = installClock();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);

    global.fetch = async () => ({ ok: true, status: 200 });

    try {
        initInactivityMonitor();
        assert.equal(document.getElementById('xs-session-warning-modal'), null);

        clock.advance(SESSION_MS - WARNING_MS);
        triggerWake(dom);

        const modal = document.getElementById('xs-session-warning-modal');
        assert.ok(modal);
        assert.equal(modal.classList.contains('hidden'), false);
        assert.equal(document.getElementById('xs-session-countdown').textContent, '5:00');
        assert.equal(dom.navigations.length, 0);
    } finally {
        teardown(dom);
        restoreTimers();
        clock.restore();
        restoreGlobals();
    }
});

test('warning shown late displays the true remaining time, not a fresh 5:00', () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const clock = installClock();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);

    global.fetch = async () => ({ ok: true, status: 200 });

    try {
        initInactivityMonitor();

        // Tab was throttled: first re-evaluation happens 117 min in.
        clock.advance(SESSION_MS - WARNING_MS + 2 * 60 * 1000);
        triggerWake(dom);

        const modal = document.getElementById('xs-session-warning-modal');
        assert.equal(modal.classList.contains('hidden'), false);
        assert.equal(document.getElementById('xs-session-countdown').textContent, '3:00');
    } finally {
        teardown(dom);
        restoreTimers();
        clock.restore();
        restoreGlobals();
    }
});

test('waking after the session window has fully passed redirects instead of showing a stale countdown', () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const clock = installClock();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);

    global.fetch = async () => ({ ok: true, status: 200 });

    try {
        initInactivityMonitor();

        // Machine slept through the entire session window.
        clock.advance(SESSION_MS + 60 * 1000);
        triggerWake(dom);

        assert.equal(document.getElementById('xs-session-warning-modal'), null);
        assert.equal(dom.navigations.length, 1);
    } finally {
        teardown(dom);
        restoreTimers();
        clock.restore();
        restoreGlobals();
    }
});

test('activity in another tab (shared storage) retracts a visible warning', () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const clock = installClock();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);

    global.fetch = async () => ({ ok: true, status: 200 });

    try {
        initInactivityMonitor();
        clock.advance(SESSION_MS - WARNING_MS);
        triggerWake(dom);

        const modal = document.getElementById('xs-session-warning-modal');
        assert.equal(modal.classList.contains('hidden'), false);

        // Another tab extended the session and wrote fresh shared timestamps.
        dom.window.localStorage.setItem('xs-last-activity-at', String(Date.now()));
        triggerWake(dom);

        assert.equal(modal.classList.contains('hidden'), true);
        assert.equal(dom.navigations.length, 0);
    } finally {
        teardown(dom);
        restoreTimers();
        clock.restore();
        restoreGlobals();
    }
});

test('stay logged in retries transient ping failures and pauses the countdown while pending', async () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const clock = installClock();
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
        clock.advance(SESSION_MS - WARNING_MS);
        triggerWake(dom);

        const stayButton = document.getElementById('xs-session-stay');
        const modal = document.getElementById('xs-session-warning-modal');
        assert.ok(modal);
        assert.equal(modal.classList.contains('hidden'), false);

        stayButton.dispatchEvent(new dom.window.Event('click', { bubbles: true }));
        await flushMicrotasks();

        assert.equal(fetchCalls, 1);
        assert.equal(stayButton.disabled, true);
        // Countdown interval cleared while pending; only the 30s check interval remains.
        assert.equal(timers.activeIntervalCount(), 1);
        assert.equal(dom.navigations.length, 0);

        timers.runTimeoutByDelay(3000);
        await flushMicrotasks();

        assert.equal(fetchCalls, 2);
        assert.equal(modal.classList.contains('hidden'), true);
        assert.equal(stayButton.disabled, false);
        assert.equal(dom.navigations.length, 0);

        // The successful extension reset the activity clock: approaching the
        // threshold relative to the *new* baseline shows the warning again.
        clock.advance(SESSION_MS - WARNING_MS);
        triggerWake(dom);
        assert.equal(modal.classList.contains('hidden'), false);
    } finally {
        console.warn = originalWarn;
        teardown(dom);
        restoreTimers();
        clock.restore();
        restoreGlobals();
    }
});

test('spa leaving removes the warning modal and cancels pending retries and timers before re-init', async () => {
    const dom = buildDom();
    const timers = createTimerHarness();
    const clock = installClock();
    const restoreGlobals = installBrowserGlobals(dom);
    const restoreTimers = timers.install(dom.window);
    const originalWarn = console.warn;

    global.fetch = async () => ({ ok: false, status: 401 });
    console.warn = () => {};

    try {
        initInactivityMonitor();
        assert.equal(timers.activeIntervalCount(), 1); // 30s wall-clock check

        clock.advance(SESSION_MS - WARNING_MS);
        triggerWake(dom);

        document.getElementById('xs-session-stay').dispatchEvent(
            new dom.window.Event('click', { bubbles: true })
        );
        await flushMicrotasks();

        // Pending ping retry is the only timeout (no activity setTimeout anymore).
        assert.equal(timers.activeTimeoutCount(), 1);
        assert.equal(document.querySelectorAll('#xs-session-warning-modal').length, 1);

        document.dispatchEvent(new dom.window.CustomEvent('spa:leaving', {
            detail: { url: '/appointments' },
        }));

        assert.equal(document.querySelectorAll('#xs-session-warning-modal').length, 0);
        assert.equal(timers.activeTimeoutCount(), 0);
        assert.equal(timers.activeIntervalCount(), 0);

        initInactivityMonitor();
        assert.equal(timers.activeTimeoutCount(), 0);
        assert.equal(timers.activeIntervalCount(), 1);
    } finally {
        console.warn = originalWarn;
        teardown(dom);
        restoreTimers();
        clock.restore();
        restoreGlobals();
    }
});
