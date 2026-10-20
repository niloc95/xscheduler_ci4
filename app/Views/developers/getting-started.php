<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Getting started · WebScheduler API</title>
    <?php foreach (vite_css('resources/scss/pages/developers-guide.scss') as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>
</head>
<body>
    <div class="wrap">
        <a class="back" href="<?= site_url('developers') ?>">← API reference</a>
        <h1>WebScheduler API — Getting started</h1>
        <p class="lead">A REST API for integrating with your WebScheduler instance:
           create appointments, read availability, and manage customers, services
           and providers.</p>

        <h2>1. Get an API key</h2>
        <p>Keys are per-user and issued from the server CLI. The full token is shown
           <strong>once</strong> and cannot be recovered afterwards.</p>
        <pre><code>php spark api:key create --user &lt;userId&gt; --name "Zapier production"</code></pre>
        <p>The request runs with the bound user's roles, so a key used to create
           providers or edit business hours must belong to an admin.</p>

        <h2>2. Authenticate every request</h2>
        <p>Send the token as a Bearer credential. There is no shared secret and no
           API key in query strings.</p>
        <pre><code>curl -H "Authorization: Bearer xsk_ab12cd34ef56_XXXXXXXX…" \
     <?= esc(rtrim(site_url('api/v1/appointments'), '/')) ?></code></pre>

        <h2>3. Read the response envelope</h2>
        <p>Successful responses wrap the payload in <code>data</code>, with optional
           <code>meta</code> (pagination, timestamps):</p>
        <pre><code>{ "data": { ... }, "meta": { "page": 1, "per_page": 25, "total": 42 } }</code></pre>
        <p>Errors return a stable machine-readable <code>code</code>:</p>
        <pre><code>{ "error": { "message": "Validation failed", "code": "VALIDATION_ERROR",
             "details": { "email": "Email address is not valid." } } }</code></pre>

        <h2>4. Common error codes</h2>
        <table>
            <thead><tr><th>HTTP</th><th>code</th><th>Meaning</th></tr></thead>
            <tbody>
                <tr><td>401</td><td><code>unauthorized</code></td><td>Missing or invalid token.</td></tr>
                <tr><td>403</td><td><code>forbidden</code></td><td>Token's user lacks the required role.</td></tr>
                <tr><td>422</td><td><code>VALIDATION_ERROR</code></td><td>Payload failed validation (see <code>details</code>).</td></tr>
                <tr><td>429</td><td><code>rate_limited</code></td><td>Too many requests — see <code>Retry-After</code>.</td></tr>
            </tbody>
        </table>

        <h2>5. Pagination, sorting, search</h2>
        <p>List endpoints accept <code>page</code>, <code>length</code> (max 100),
           <code>sort</code> (<code>field:asc|desc</code>) and, where supported,
           <code>q</code> for free-text search.</p>
        <pre><code>GET <?= esc(rtrim(site_url('api/v1/customers'), '/')) ?>?page=2&length=50&sort=created_at:desc&q=ada</code></pre>

        <h2>6. Rate limits</h2>
        <p>Each key is limited to <strong>120 requests per 60 seconds</strong>.
           Exceeding it returns <code>429</code> with a <code>Retry-After</code>
           header (seconds to wait).</p>

        <h2>Full reference</h2>
        <p>Browse every endpoint, schema and example in the
           <a href="<?= site_url('developers') ?>">interactive API reference</a>,
           or download the <a href="<?= site_url('developers/openapi.yaml') ?>">OpenAPI spec</a>.</p>
    </div>
</body>
</html>
