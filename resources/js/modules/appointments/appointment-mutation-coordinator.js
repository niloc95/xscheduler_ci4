/**
 * Appointment Mutation Coordinator
 *
 * Single entry point for all appointment mutations (create, update, delete,
 * reschedule, status-change, notes-save, cancel).
 *
 * Responsibilities:
 *  - CSRF token injection (authenticated vs public paths)
 *  - Loading state management via loadingTargets selectors
 *  - Concurrent mutation guard (isExecuting flag)
 *  - Unified error classification (forbidden / conflict / validation / server / network)
 *  - Context-aware post-mutation refresh (scheduler | authenticated-form | public-manage | passive)
 *  - Scroll position preservation on scheduler refresh
 *  - appointment:changed event dispatch with canonical payload
 *  - Toast via window.XSNotify (callers must NOT toast separately)
 *
 * NOTE: handleNotify() (manual re-send) is intentionally excluded — it is
 * a re-send action, not a mutation, and is not routed through this coordinator.
 *
 * Usage:
 *   import { appointmentMutationCoordinator } from './appointment-mutation-coordinator.js';
 *
 *   const result = await appointmentMutationCoordinator.execute({
 *     action:   'reschedule',
 *     endpoint: `/api/appointments/${id}`,
 *     method:   'PATCH',
 *     body:     { start: '...', end: '...' },
 *     uiContext:      'scheduler',
 *     authContext:    'authenticated',
 *     loadingTargets: ['#scheduler-loading'],
 *     preserveScrollContext: true,
 *     toast:   true,
 *     onSuccess: (data) => {},
 *     onError:   (error) => {},
 *   });
 */

class AppointmentMutationCoordinator {
    constructor() {
        /** @type {boolean} Concurrent mutation guard */
        this.isExecuting = false;

        /** @type {number|null} Saved scroll position (px) for restore after refresh */
        this._savedScrollTop = null;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Execute an appointment mutation.
     *
     * @param {object} options
     * @param {'create'|'update'|'delete'|'reschedule'|'status-change'|'notes-save'|'cancel'} options.action
     * @param {string}  options.endpoint
     * @param {'POST'|'PATCH'|'PUT'|'DELETE'} options.method
     * @param {object}  [options.body]
     * @param {'scheduler'|'authenticated-form'|'public-manage'|'passive'} [options.uiContext='passive']
     * @param {'authenticated'|'public'} [options.authContext='authenticated']
     * @param {function} [options.validate]        - (body) => { valid, errors }
     * @param {function} [options.transformRequest] - (body) => transformed body
     * @param {function} [options.optimisticUpdate] - called synchronously before fetch
     * @param {function} [options.onOptimisticRevert] - called on failure if optimisticUpdate was called
     * @param {string[]} [options.loadingTargets]   - CSS selectors to disable during execution
     * @param {boolean}  [options.preserveScrollContext=false]
     * @param {function} [options.onSuccess]         - (data) => void
     * @param {function} [options.onError]           - (error: MutationError) => void
     * @param {boolean|{type:string,message:string}} [options.toast=true]
     * @returns {Promise<{success:boolean, data:object|null, error:object|null, undo:object}>}
     */
    async execute(options) {
        const {
            action,
            endpoint,
            method = 'POST',
            body = {},
            uiContext = 'passive',
            authContext = 'authenticated',
            validate = null,
            transformRequest = null,
            optimisticUpdate = null,
            onOptimisticRevert = null,
            loadingTargets = [],
            preserveScrollContext = false,
            onSuccess = null,
            onError = null,
            toast = true,
        } = options;

        // Concurrent mutation guard
        if (this.isExecuting) {
            const concurrentError = {
                status: 0,
                code: 'concurrent',
                message: 'Another action is in progress. Please wait.',
                fields: {},
            };
            if (typeof onError === 'function') onError(concurrentError);
            return { success: false, data: null, error: concurrentError, undo: {} };
        }

        // Client-side validation
        if (typeof validate === 'function') {
            const validation = validate(body);
            if (!validation.valid) {
                const validationError = {
                    status: 0,
                    code: 'validation',
                    message: 'Please correct the highlighted fields.',
                    fields: validation.errors ?? {},
                };
                if (typeof onError === 'function') onError(validationError);
                return { success: false, data: null, error: validationError, undo: {} };
            }
        }

        this.isExecuting = true;
        this._applyLoadingTargets(loadingTargets, true);
        this._dispatchLifecycleEvent('appointment:mutation:attempted', { action, endpoint });

        let optimisticApplied = false;
        const oldValues = {};

        // Apply optimistic update before awaiting fetch
        if (typeof optimisticUpdate === 'function') {
            optimisticUpdate();
            optimisticApplied = true;
        }

        // Save scroll before data refresh
        if (preserveScrollContext && uiContext === 'scheduler') {
            this._saveScroll();
        }

        let result;
        try {
            const payload = typeof transformRequest === 'function' ? transformRequest(body) : body;
            const headers = this._buildHeaders(authContext);
            const response = await fetch(endpoint, {
                method,
                headers,
                body: JSON.stringify(payload),
            });

            // Rotate CSRF from response
            this._rotateCsrf(authContext, response.headers);

            let responseData = null;
            const contentType = response.headers.get('content-type') ?? '';
            if (contentType.includes('application/json')) {
                responseData = await response.json();
            }

            if (!response.ok) {
                const mutationError = this._classifyError(response.status, responseData);
                throw mutationError;
            }

            // --- Success path ---
            const data = responseData?.data ?? responseData ?? {};
            const newValues = data;

            await this._refreshContext(uiContext, preserveScrollContext);

            this._dispatchChangedEvent(action, endpoint, data);
            this._dispatchLifecycleEvent('appointment:mutation:completed', { action, endpoint, success: true });

            this._showToast(toast, 'success');

            if (typeof onSuccess === 'function') onSuccess(data);

            result = { success: true, data, error: null, undo: { action, oldValues, newValues } };
        } catch (err) {
            const mutationError = err.code
                ? err  // already a MutationError
                : { status: 0, code: 'network', message: 'Network error. Please check your connection.', fields: {} };

            if (optimisticApplied && typeof onOptimisticRevert === 'function') {
                onOptimisticRevert();
            }

            this._restoreScroll();

            this._dispatchLifecycleEvent('appointment:mutation:completed', { action, endpoint, success: false });

            this._showToast(toast, 'error', mutationError.message);

            if (typeof onError === 'function') onError(mutationError);

            result = { success: false, data: null, error: mutationError, undo: {} };
        } finally {
            this.isExecuting = false;
            this._applyLoadingTargets(loadingTargets, false);
        }

        return result;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build fetch headers for the given auth context.
     * @param {'authenticated'|'public'} authContext
     * @returns {Record<string,string>}
     */
    _buildHeaders(authContext) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (authContext === 'authenticated') {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                ?? window.__CSRF_TOKEN__
                ?? null;
            if (token) headers['X-CSRF-TOKEN'] = token;
        } else if (authContext === 'public') {
            const root = document.querySelector('[data-booking-root]') ?? document.body;
            const token = root.dataset?.csrfValue ?? null;
            if (token) headers['X-CSRF-TOKEN'] = token;
        }

        return headers;
    }

    /**
     * Rotate CSRF token from response headers after a successful exchange.
     * @param {'authenticated'|'public'} authContext
     * @param {Headers} responseHeaders
     */
    _rotateCsrf(authContext, responseHeaders) {
        const newToken = responseHeaders.get('X-CSRF-TOKEN');
        if (!newToken) return;

        if (authContext === 'authenticated') {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', newToken);
            if (typeof window.__CSRF_TOKEN__ !== 'undefined') {
                window.__CSRF_TOKEN__ = newToken;
            }
        } else if (authContext === 'public') {
            const root = document.querySelector('[data-booking-root]') ?? document.body;
            if (root.dataset) root.dataset.csrfValue = newToken;
        }
    }

    /**
     * Enable or disable all loadingTargets elements.
     * @param {string[]} targets - CSS selectors
     * @param {boolean}  loading
     */
    _applyLoadingTargets(targets, loading) {
        if (!targets || targets.length === 0) return;
        targets.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => {
                if (loading) {
                    el.setAttribute('disabled', 'disabled');
                    el.setAttribute('aria-busy', 'true');
                } else {
                    el.removeAttribute('disabled');
                    el.removeAttribute('aria-busy');
                }
            });
        });
    }

    /**
     * Perform context-aware post-mutation refresh.
     * @param {'scheduler'|'authenticated-form'|'public-manage'|'passive'} uiContext
     * @param {boolean} preserveScrollContext
     */
    async _refreshContext(uiContext, preserveScrollContext) {
        if (uiContext === 'scheduler') {
            const scheduler = window.scheduler;
            if (!scheduler) return;

            if (typeof scheduler.loadData === 'function') {
                await scheduler.loadData();
            } else if (typeof scheduler.loadAppointments === 'function') {
                await scheduler.loadAppointments();
            }

            if (typeof scheduler.render === 'function') {
                scheduler.render();
            }

            if (preserveScrollContext) {
                // Restore is deferred to next tick so render() can flush to DOM
                requestAnimationFrame(() => this._restoreScroll());
            }
        }
        // authenticated-form and public-manage do nothing here;
        // refresh is driven by the onSuccess callback in those contexts.
    }

    /**
     * Save the current scroll position of the scheduler scroll container.
     */
    _saveScroll() {
        const container = this._getScrollContainer();
        if (container) {
            this._savedScrollTop = container.scrollTop;
        }
    }

    /**
     * Restore scroll position after re-render.
     */
    _restoreScroll() {
        if (this._savedScrollTop === null) return;
        const container = this._getScrollContainer();
        if (container) {
            container.scrollTop = this._savedScrollTop;
        }
        this._savedScrollTop = null;
    }

    /**
     * Find the scrollable container for the scheduler.
     * @returns {Element|null}
     */
    _getScrollContainer() {
        const explicit = document.querySelector('.scheduler-scroll-container');
        if (explicit) return explicit;

        const root = document.querySelector('#appointments-inline-calendar');
        if (!root) return null;

        // Walk up to find nearst scrollable ancestor
        let el = root;
        while (el && el !== document.body) {
            const style = window.getComputedStyle(el);
            if (style.overflowY === 'auto' || style.overflowY === 'scroll') return el;
            el = el.parentElement;
        }
        return null;
    }

    /**
     * Classify an HTTP error response into a typed MutationError.
     * @param {number} status
     * @param {object|null} body
     * @returns {{status:number,code:string,message:string,fields:object}}
     */
    _classifyError(status, body) {
        const serverMessage = body?.error?.message ?? body?.message ?? null;
        const serverFields  = body?.error?.errors  ?? body?.errors  ?? body?.details ?? {};

        if (status === 403) {
            return {
                status,
                code: 'forbidden',
                message: "You don't have permission to perform this action.",
                fields: {},
            };
        }
        if (status === 409) {
            return {
                status,
                code: 'conflict',
                message: serverMessage ?? 'This time slot conflicts with an existing appointment.',
                fields: serverFields,
            };
        }
        if (status === 422 || status === 400) {
            return {
                status,
                code: 'validation',
                message: serverMessage ?? 'Please correct the highlighted fields.',
                fields: serverFields,
            };
        }
        return {
            status,
            code: 'server',
            message: serverMessage ?? `Server error (${status}). Please try again.`,
            fields: {},
        };
    }

    /**
     * Dispatch the canonical appointment:changed domain event.
     * @param {string} action
     * @param {string} endpoint
     * @param {object} data
     */
    _dispatchChangedEvent(action, endpoint, data) {
        window.dispatchEvent(new CustomEvent('appointment:changed', {
            detail: {
                action,
                source: 'mutation-coordinator',
                appointmentId: data?.id ?? data?.appointmentId ?? null,
                updatedData: data,
                affectedIds: data?.affectedIds ?? [],
            },
        }));
    }

    /**
     * Dispatch internal lifecycle events for analytics / observability.
     * @param {string} name
     * @param {object} detail
     */
    _dispatchLifecycleEvent(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail }));
    }

    /**
     * Show a toast via window.XSNotify.
     * @param {boolean|{type:string,message:string}} toastOption
     * @param {'success'|'error'} defaultType
     * @param {string} [defaultMessage]
     */
    _showToast(toastOption, defaultType, defaultMessage = '') {
        if (toastOption === false) return;
        if (!window.XSNotify?.toast) return;

        if (toastOption === true || toastOption == null) {
            if (defaultType === 'success') {
                window.XSNotify.toast({ type: 'success', message: 'Changes saved successfully.' });
            } else if (defaultMessage) {
                window.XSNotify.toast({ type: 'error', message: defaultMessage });
            }
            return;
        }

        // Caller provided explicit { type, message }
        if (typeof toastOption === 'object' && toastOption.message) {
            window.XSNotify.toast({
                type: toastOption.type ?? defaultType,
                message: toastOption.message,
            });
        }
    }
}

export const appointmentMutationCoordinator = new AppointmentMutationCoordinator();
