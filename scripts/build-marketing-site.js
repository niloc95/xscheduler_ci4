/**
 * build-marketing-site.js
 * -----------------------------------------------------------------------------
 * Assembles the standalone, self-contained marketing site into `dist/site/`.
 * The output folder can be uploaded as-is to any static host (e.g. the root of
 * webscheduler.co.za) — it has NO app, PHP, or CDN dependency and makes zero
 * external requests at runtime.
 *
 * Mirrors the conventions of scripts/build-docs-site.js.
 *
 * Sources (single source of truth):
 *   - marketing-site/*.html                      (page templates)
 *   - marketing-site/src/styles.css              (Tailwind input)
 *   - marketing-site/tailwind.config.cjs         (brand tokens)
 *   - marketing-site/assets/**                    (logo, fonts, screenshots, site.js)
 *
 * Steps:
 *   1. Clean + recreate dist/site/
 *   2. Compile Tailwind → dist/site/assets/styles.css
 *   3. Copy the HTML pages and assets/
 *   4. Guards: fail on external hosts and on banned positioning words
 *
 * Usage: npm run site:build
 * -----------------------------------------------------------------------------
 */

import fs from 'fs';
import path from 'path';
import { execFileSync } from 'child_process';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const srcDir = path.join(projectRoot, 'marketing-site');
const outDir = path.join(projectRoot, 'dist', 'site');
const assetsSrc = path.join(srcDir, 'assets');
const assetsOut = path.join(outDir, 'assets');

const PAGES = ['index.html', 'features.html', 'pricing.html', 'about.html', 'contact.html'];

/** Canonical site origin — used for robots.txt and sitemap.xml. */
const BASE_URL = 'https://webscheduler.co.za';

/** Positioning guard: the owner does not want free/open-source messaging. */
const BANNED = [/open[\s-]?source/i, /free\s+plan/i, /free\s+tier/i, /free\s+forever/i, /100%\s*free/i];

function fail(message) {
  console.error('❌ ' + message);
  process.exit(1);
}

console.log('🌐 Building marketing site → dist/site/');

// 1) Clean + recreate.
fs.rmSync(outDir, { recursive: true, force: true });
fs.mkdirSync(assetsOut, { recursive: true });

// 2) Copy HTML pages.
for (const page of PAGES) {
  const from = path.join(srcDir, page);
  if (!fs.existsSync(from)) fail(`Missing page: ${page}`);
  fs.copyFileSync(from, path.join(outDir, page));
  console.log('  • ' + page);
}

// 2b) Copy assets (logo, fonts, screenshots, site.js). A locally-generated
//     assets/styles.css (from `npm run site:css`) is skipped — the minified
//     Tailwind build in step 3 is the authoritative stylesheet.
function copyDir(from, to, skip = []) {
  fs.mkdirSync(to, { recursive: true });
  for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
    if (skip.includes(entry.name)) continue;
    const s = path.join(from, entry.name);
    const d = path.join(to, entry.name);
    if (entry.isDirectory()) copyDir(s, d);
    else fs.copyFileSync(s, d);
  }
}
copyDir(assetsSrc, assetsOut, ['styles.css']);

// 3) Compile Tailwind (after the asset copy, so it is the authoritative CSS).
console.log('  • compiling Tailwind → assets/styles.css');
try {
  execFileSync(
    'npx',
    [
      'tailwindcss',
      '-c', path.join(srcDir, 'tailwind.config.cjs'),
      '-i', path.join(srcDir, 'src', 'styles.css'),
      '-o', path.join(assetsOut, 'styles.css'),
      '--minify',
    ],
    { cwd: projectRoot, stdio: ['ignore', 'ignore', 'inherit'] }
  );
} catch (err) {
  fail('Tailwind build failed: ' + (err && err.message ? err.message : String(err)));
}
console.log('  • assets/ (logo, fonts, screenshots, site.js)');

// Confirm the expected screenshots shipped.
const shotDir = path.join(assetsOut, 'screenshots');
const expectedShots = ['login.png', 'dashboard.png', 'appointments.png', 'services.png', 'customers.png', 'analytics.png', 'user-management.png'];
const missingShots = expectedShots.filter((s) => !fs.existsSync(path.join(shotDir, s)));
if (missingShots.length) {
  fail(`Missing screenshots: ${missingShots.join(', ')}. Run "npm run site:shots" first.`);
}

// 3c) Bundle the developer portal at /developer using the canonical docs builder
//     (single source of truth), then copy its output under dist/site/developer/.
console.log('  • developer portal → developer/');
try {
  execFileSync('node', [path.join(__dirname, 'build-docs-site.js')], {
    cwd: projectRoot,
    stdio: ['ignore', 'ignore', 'inherit'],
  });
} catch (err) {
  fail('Developer docs build failed: ' + (err && err.message ? err.message : String(err)));
}
const devSrc = path.join(projectRoot, 'dist', 'developer');
if (!fs.existsSync(path.join(devSrc, 'index.html'))) {
  fail('Developer docs build did not produce dist/developer/index.html');
}
copyDir(devSrc, path.join(outDir, 'developer'));

// 4) Guards over the emitted HTML + CSS.
const emitted = [
  ...PAGES.map((p) => path.join(outDir, p)),
  path.join(assetsOut, 'styles.css'),
];

// 4a) No UNAPPROVED external hosts. The site loads no external fonts/CSS/JS,
//     with two deliberate exceptions: Google Analytics (gtag) and self-references
//     to our own canonical/OG domain. Everything else (CDNs, font hosts) fails.
const ALLOWED_HOSTS = new Set([
  'webscheduler.co.za',
  'www.webscheduler.co.za',
  'www.googletagmanager.com',
  'www.google-analytics.com',
]);
const EXTERNAL = /(?:src|href)\s*=\s*["'](https?:\/\/[^"']+)["']/gi;
for (const file of emitted) {
  const text = fs.readFileSync(file, 'utf8');
  const bad = [];
  let m;
  while ((m = EXTERNAL.exec(text)) !== null) {
    let host = '';
    try { host = new URL(m[1]).host; } catch { /* malformed → treat as bad */ }
    if (!ALLOWED_HOSTS.has(host)) bad.push(m[1]);
  }
  if (bad.length) {
    fail(
      `Disallowed external resource in ${path.relative(projectRoot, file)}:\n    `
      + bad.slice(0, 5).join('\n    ')
      + '\nOnly self-references and Google Analytics are allowed (no CDN/font hosts).'
    );
  }
}

// 4b) No banned positioning words.
for (const file of PAGES.map((p) => path.join(outDir, p))) {
  const text = fs.readFileSync(file, 'utf8');
  for (const rx of BANNED) {
    const m = text.match(rx);
    if (m) {
      fail(
        `Banned phrase "${m[0]}" found in ${path.relative(projectRoot, file)}. `
        + 'The site must not use free/open-source messaging.'
      );
    }
  }
}

// 5) SEO files: robots.txt + sitemap.xml (generated with today's lastmod).
const today = new Date().toISOString().slice(0, 10);
const sitemapUrls = [
  { loc: `${BASE_URL}/`, priority: '1.0', changefreq: 'weekly' },
  { loc: `${BASE_URL}/features.html`, priority: '0.8', changefreq: 'monthly' },
  { loc: `${BASE_URL}/pricing.html`, priority: '0.9', changefreq: 'monthly' },
  { loc: `${BASE_URL}/about.html`, priority: '0.5', changefreq: 'yearly' },
  { loc: `${BASE_URL}/contact.html`, priority: '0.7', changefreq: 'yearly' },
  { loc: `${BASE_URL}/developer/`, priority: '0.6', changefreq: 'monthly' },
];
const sitemap =
  '<?xml version="1.0" encoding="UTF-8"?>\n'
  + '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
  + sitemapUrls
    .map((u) => `  <url><loc>${u.loc}</loc><lastmod>${today}</lastmod>`
      + `<changefreq>${u.changefreq}</changefreq><priority>${u.priority}</priority></url>`)
    .join('\n')
  + '\n</urlset>\n';
fs.writeFileSync(path.join(outDir, 'sitemap.xml'), sitemap);

const robots =
  'User-agent: *\n'
  + 'Allow: /\n\n'
  + `Sitemap: ${BASE_URL}/sitemap.xml\n`;
fs.writeFileSync(path.join(outDir, 'robots.txt'), robots);
console.log('  • robots.txt + sitemap.xml');

console.log('✅ Done. Upload the contents of dist/site/ to your host.');
