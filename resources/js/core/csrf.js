/**
 * Shared CSRF helpers for authenticated and public flows.
 */

function getPublicRoot() {
    return document.querySelector('[data-booking-root]')
        || document.getElementById('public-booking-root')
        || document.body;
}

export function getCsrfHeaderName() {
    return document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
}

export function readCsrfToken(authContext = 'authenticated') {
    if (authContext === 'public') {
        const root = getPublicRoot();
        return root?.dataset?.csrfValue || null;
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || window.__CSRF_TOKEN__
        || null;
}

export function buildCsrfHeader(authContext = 'authenticated') {
    const token = readCsrfToken(authContext);
    if (!token) {
        return {};
    }

    return {
        [getCsrfHeaderName()]: token,
    };
}

export function getFormCsrfContext(form) {
    const fallbackInput = form?.querySelector('input[name]') || null;
    const namedInput = form?.querySelector('input[name="csrf_test_name"]')
        || form?.querySelector('input[name^="csrf_"]')
        || null;

    const tokenName = namedInput?.name || 'csrf_test_name';
    const headerName = getCsrfHeaderName();
    const tokenValue = readCsrfToken('authenticated') || namedInput?.value || '';

    return {
        headerName,
        tokenName,
        tokenValue,
        input: namedInput || fallbackInput,
    };
}

export function syncCsrfIntoForm(form) {
    const csrf = getFormCsrfContext(form);
    if (csrf.input && csrf.tokenValue) {
        csrf.input.value = csrf.tokenValue;
    }
    return csrf;
}

export function rotateCsrfFromResponse(response, authContext = 'authenticated', form = null) {
    const nextToken = response?.headers?.get('X-CSRF-TOKEN') || response?.headers?.get('x-csrf-token');
    if (!nextToken) {
        return;
    }

    if (authContext === 'public') {
        const root = getPublicRoot();
        if (root?.dataset) {
            root.dataset.csrfValue = nextToken;
        }
        return;
    }

    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        metaToken.setAttribute('content', nextToken);
    }

    window.__CSRF_TOKEN__ = nextToken;

    if (form) {
        const csrf = getFormCsrfContext(form);
        if (csrf.input) {
            csrf.input.value = nextToken;
        }
    }

    document.querySelectorAll('input[type="hidden"][name*="csrf"]').forEach((input) => {
        input.value = nextToken;
    });
}
