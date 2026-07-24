/**
 * build-docs-site.js
 * -----------------------------------------------------------------------------
 * Assembles the standalone, self-contained developer API docs site into
 * `dist/developer/`. The output folder can be uploaded as-is to any static host
 * (e.g. webscheduler.co.za/developer) — it has NO app, PHP, or CDN dependency
 * and makes zero external requests.
 *
 * Sources (single source of truth, reused — nothing is duplicated here):
 *   - docs-site/index.html, docs-site/getting-started.html  (static templates)
 *   - resources/redoc/redoc.standalone.js                   (vendored Redoc)
 *   - docs/technical/openapi.yml                            (the API spec)
 *
 * Usage: npm run docs:build
 * -----------------------------------------------------------------------------
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const outDir = path.join(projectRoot, 'dist', 'developer');

/** [sourcePath, outputName] — copied verbatim into dist/developer/. */
const copies = [
  ['docs-site/index.html', 'index.html'],
  ['docs-site/getting-started.html', 'getting-started.html'],
  ['resources/redoc/redoc.standalone.js', 'redoc.standalone.js'],
  ['resources/redoc/redoc.standalone.js.LICENSE.txt', 'redoc.standalone.js.LICENSE.txt'],
  // The spec is authored as .yml; publish it as .yaml (what index.html fetches).
  ['docs/technical/openapi.yml', 'openapi.yaml'],
];

function fail(message) {
  console.error('❌ ' + message);
  process.exit(1);
}

console.log('📚 Building developer docs site → dist/developer/');

// Clean + recreate the output directory.
fs.rmSync(outDir, { recursive: true, force: true });
fs.mkdirSync(outDir, { recursive: true });

for (const [src, outName] of copies) {
  const srcPath = path.join(projectRoot, src);
  if (!fs.existsSync(srcPath)) {
    fail(`Missing source file: ${src}`);
  }
  fs.copyFileSync(srcPath, path.join(outDir, outName));
  console.log('  • ' + outName);
}

// Guard: the published Redoc bundle must not reach out to any external host.
const bundle = fs.readFileSync(path.join(outDir, 'redoc.standalone.js'), 'utf8');
if (bundle.includes('https://cdn.redoc.ly/redoc/logo-mini.svg')) {
  fail(
    'redoc.standalone.js still references cdn.redoc.ly (external request). '
    + 'Re-patch the vendored bundle before publishing.'
  );
}

console.log(`✅ Done. Upload the contents of ${path.relative(projectRoot, outDir)}/ to your host's /developer path.`);
