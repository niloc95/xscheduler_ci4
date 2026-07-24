/**
 * Developer API portal bootstrap.
 *
 * Renders the OpenAPI spec with Redoc on the standalone `/developers` page.
 * The Redoc runtime is vendored (resources/redoc/redoc.standalone.js) and
 * emitted by Vite as a hashed, same-origin asset via the `?url` import — the
 * app's CSP forbids external script hosts, so nothing loads from a CDN.
 *
 * The `?url` suffix makes Vite copy the file verbatim and hand back its built
 * URL instead of trying to parse the 1 MB UMD bundle as a module.
 */
import redocRuntimeUrl from '../redoc/redoc.standalone.js?url';

function initRedoc() {
  const container = document.getElementById('redoc-container');
  if (!container) {
    return;
  }

  const specUrl = container.dataset.spec;
  if (!specUrl) {
    return;
  }

  const script = document.createElement('script');
  script.src = redocRuntimeUrl;
  script.onload = () => {
    if (!window.Redoc || typeof window.Redoc.init !== 'function') {
      return;
    }

    window.Redoc.init(
      specUrl,
      {
        hideDownloadButton: false,
        expandResponses: '200,201',
        jsonSampleExpandLevel: 2,
        theme: {
          colors: { primary: { main: '#2563eb' } },
          typography: {
            fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
            headings: { fontFamily: 'Inter, system-ui, -apple-system, sans-serif' },
          },
        },
      },
      container,
    );
  };

  document.head.appendChild(script);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initRedoc);
} else {
  initRedoc();
}
