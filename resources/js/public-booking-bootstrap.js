const body = document.body;

if (body) {
    const baseUrl = body.dataset.baseUrl || '';
    if (baseUrl) {
        window.__BASE_URL__ = baseUrl;
        window.appBaseUrl = baseUrl;
    }
}
