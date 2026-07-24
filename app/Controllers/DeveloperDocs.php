<?php

/**
 * =============================================================================
 * DEVELOPER DOCS CONTROLLER
 * =============================================================================
 *
 * @file        app/Controllers/DeveloperDocs.php
 * @description Public developer portal for the WebScheduler API. Renders the
 *              OpenAPI spec with a self-hosted Redoc bundle and serves the spec
 *              same-origin so no external hosts are contacted (CSP-clean).
 *
 * ROUTES:
 * -----------------------------------------------------------------------------
 * GET /developers                 : Redoc reference (this::index)
 * GET /developers/getting-started : Auth + conventions guide (this::gettingStarted)
 * GET /developers/openapi.yaml    : The raw spec, served same-origin (this::spec)
 *
 * The page is public — it exposes only the API contract, never data — and is
 * registered outside the setup/auth groups so it renders pre-login.
 *
 * @see         resources/js/developers.js        (Redoc bootstrap)
 * @see         docs/technical/openapi.yml         (the spec, drift-guarded)
 * @package     App\Controllers
 * =============================================================================
 */

namespace App\Controllers;

use Config\ContentSecurityPolicy;

class DeveloperDocs extends BaseController
{
    private const SPEC_FILE = ROOTPATH . 'docs/technical/openapi.yml';

    /**
     * Redoc reference page.
     */
    public function index()
    {
        $csp = $this->response->getCSP();

        // Redoc builds its search index in a web worker created from a blob URL.
        // The global CSP is child-src 'self'; permit blob workers for THIS
        // response only so search works without loosening the site-wide policy.
        $csp->addChildSrc('blob:');

        // Redoc (styled-components) injects <style> elements AND inline `style="…"`
        // attributes at runtime. A nonce cannot cover style attributes, and a
        // browser ignores 'unsafe-inline' the moment a nonce appears in style-src
        // — so any style nonce blocks Redoc (styled-components error #17).
        //
        // In development, CodeIgniter::initializeKint() unconditionally calls
        // getStyleNonce() (CI_DEBUG only), appending a nonce to style-src on every
        // request. Strip it for THIS response and restore the app's declared,
        // nonce-free style-src, so Redoc renders in dev exactly as it does in
        // production (where Kint never runs). script-src / connect-src / child-src
        // etc. stay fully enforced; only the Phase-2 style nonce is dropped, back
        // to the config's Phase-1 'unsafe-inline' baseline, on this data-free page.
        $csp->clearDirective('style-src');
        $csp->addStyleSrc(config(ContentSecurityPolicy::class)->styleSrc);

        return view('developers/index', [
            'specUrl' => site_url('developers/openapi.yaml'),
        ]);
    }

    /**
     * Getting-started guide (auth, envelope, errors, pagination, rate limits).
     */
    public function gettingStarted()
    {
        return view('developers/getting-started');
    }

    /**
     * Serve the OpenAPI spec verbatim, same-origin, so Redoc's fetch stays
     * within connect-src 'self'.
     */
    public function spec()
    {
        if (!is_file(self::SPEC_FILE)) {
            return $this->response->setStatusCode(404)->setBody('Spec not found.');
        }

        return $this->response
            ->setHeader('Content-Type', 'application/yaml; charset=UTF-8')
            ->setHeader('Cache-Control', 'public, max-age=300')
            ->setBody((string) file_get_contents(self::SPEC_FILE));
    }
}
