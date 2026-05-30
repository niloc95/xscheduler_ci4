import { buildCsrfHeader, rotateCsrfFromResponse } from './csrf.js';
import { getBaseUrl } from '../utils/url-helpers.js';

/**
 * Shared API transport helper.
 * Returns both raw response and parsed payload so callers can handle envelopes.
 *
 * Endpoints starting with '/' are resolved against the app base URL so
 * subdirectory installs (e.g. /demo/public/) work without each call site
 * having to call withBaseUrl() manually. Full URLs (http/https) pass through.
 */
export async function apiRequest(endpoint, options = {}) {
    const {
        method = 'GET',
        body = null,
        headers = {},
        signal,
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

    // Resolve root-relative paths against the app base URL.
    // Full URLs and relative paths (no leading /) pass through unchanged.
    const resolvedEndpoint = endpoint.startsWith('/') ? getBaseUrl() + endpoint : endpoint;

    const response = await fetch(resolvedEndpoint, {
        method,
        headers: baseHeaders,
        credentials,
        body: requestBody,
        signal,
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
