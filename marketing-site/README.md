# WebScheduler marketing site

A **standalone static marketing website** for WebScheduler (home, features, pricing,
about, contact) plus the bundled developer API portal. It is deployed to the public
site (e.g. the root of `webscheduler.co.za`) and is **independent of the CodeIgniter
app**.

## Not part of the app build

This directory is intentionally **excluded from the WebScheduler application build and
deployment package**:

- `npm run build` (Vite) only bundles the entry points in `vite.config.js`; none of
  them reference `marketing-site/`.
- `tailwind.config.js` (the app's) scans `app/Views`, `resources/` and `public/` — not
  `marketing-site/`. The site has its own `marketing-site/tailwind.config.cjs`.
- `scripts/package.js` copies an explicit allowlist (`app`, `public`, `vendor`,
  `writable`, …); `marketing-site/` is not in it, so it never ships in the app ZIP.

The site is committed to the repo for source control, but it builds and deploys on its
own track.

## Commands

Run from the project root:

| Command | What it does |
|---|---|
| `npm run site:shots` | Capture real app screenshots (needs the app running + seeded — see the plan/verify skill) into `assets/screenshots/`. |
| `npm run site:css`   | Compile Tailwind once → `assets/styles.css` (needed to preview the source locally). |
| `npm run site:dev`   | Same as above with `--watch` for live editing. |
| `npm run site:build` | Build the deployable site into `dist/site/` (minified CSS, bundled `/developer` portal, `robots.txt`, `sitemap.xml`). |

## Local preview

```bash
npm run site:dev          # keeps assets/styles.css fresh
python3 -m http.server 8080 --directory marketing-site
# open http://localhost:8080
```

Serve over HTTP (not `file://`) so the developer portal (Redoc) and self-hosted fonts
resolve.

## Deploy

```bash
npm run site:build        # → dist/site/
```

Upload the **contents of `dist/site/`** to the web host. `dist/` and the generated
`assets/styles.css` are git-ignored.

## Notes

- **Self-contained** except Google Analytics — the build fails if any other external
  host is referenced (see the guard in `scripts/build-marketing-site.js`).
- **Positioning guard** — the build fails on free/open-source phrasing; the product is
  positioned as self-hosted, paid from R160/month.
- Canonical/OG/sitemap URLs are hardcoded to `https://webscheduler.co.za`. Change
  `BASE_URL` in `scripts/build-marketing-site.js` and the `<head>` tags if the domain
  changes.
- The Google Search Console verification tag in `index.html` is a placeholder — replace
  the token before relying on verification.
