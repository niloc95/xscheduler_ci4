import { buildCsrfHeader, rotateCsrfFromResponse } from './csrf.js';

/**
 * Shared API transport helper.
 * Returns both raw response and parsed payload so callers can handle envelopes.
 */
export async function apiRequest(endpoint, options = {}) {
    const {
        method = 'GET',
        body = null,
        headers = {},
        authContext = 'authenticated',
        credentials = 'same-origin',
        includeRequestedWith = true,
        rotateCsrf = true,
    } = options;

    const baseHeaders = {
        Accept: 'application/json',
        ...(includeRequestedWith ? { 'X-Requested-With': 'XMLHttpRequest' } : {}),
        ...buildCsrfHeader(authContext),
        ...headers,
    };

    let requestBody = body;
    const isJsonBody = body && !(body instanceof FormData) && typeof body === 'object';

    if (isJsonBody) {
        requestBody = JSON.stringify(body);
        if (!baseHeaders['Content-Type']) {
            baseHeaders['Content-Type'] = 'application/json';
        }
    }

    const response = await fetch(endpoint, {
        method,
        headers: baseHeaders,
        credentials,
        body: requestBody,
    });

    if (rotateCsrf) {
        rotateCsrfFromResponse(response, authContext);
    }

    const contentType = response?.headers?.get?.('content-type') || '';
    let payload = null;

    if (contentType.includes('application/json') && typeof response?.json === 'function') {
        payload = await response.json();
    } else if (contentType.includes('text/') && typeof response?.text === 'function') {
        payload = await response.text();
    } else if (typeof response?.json === 'function') {
        payload = await response.json().catch(() => null);
    }

    return { response, payload };
}
