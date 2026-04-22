# Public Booking Deployment Guide

This document explains how to enable and deploy the `/public/booking` experience so customers can book, look up, and reschedule appointments without logging in.

## Prerequisites

1. **Database migrations** – Run the latest application migrations to ensure the public-token columns and settings exist:
   ```bash
   php spark migrate -n App
   ```
2. **Environment variables** – Confirm `app.baseURL` is reachable from the public internet and that HTTPS termination is configured upstream.
3. **Rate limiting + CSRF** – The `PublicBookingRateLimiter` filter is attached to every `/public/booking` route and requires the cache store to be writable. CSRF headers are emitted from the Blade view and must not be stripped by proxies.

## Configuration Checklist

| Setting | Location | Notes |
| --- | --- | --- |
| Providers & Services | Scheduler > Providers / Services | Only active providers/services are surfaced to the booking widget. |
| Field visibility | Settings > Booking | Controls which contact fields display/are required. The UI reads these rules at bootstrap. |
| Custom fields | Settings > Booking > Custom | Plain-text, checkbox, or textarea inputs automatically render under "Additional information." |
| Reschedule policy | Settings > Business > Reschedule window (`business.reschedule`) | Values: `none`, `12h`, `24h`, `48h`. Drives the lookup policy message and enforcement window. |
| Branding assets | Settings > Appearance | Shared Tailwind bundle is reused by the public page.

## Deployment Steps

1. **Build the frontend assets**
   ```bash
   npm install   # once per environment
   npm run build
   ```
   This emits `public/build/assets/public-booking.js` referenced by `app/Views/public/booking.php`.

2. **Publish the PHP changes**
   - Deploy the updated CodeIgniter application code.
   - Ensure the writable directories (`writable/cache`, `writable/session`, etc.) are writeable by PHP-FPM.

3. **Update web server routing**
   - Point `https://<your-domain>/public/booking` to the CodeIgniter public index.
   - If you rely on a reverse proxy, allow the `X-CSRF-TOKEN` header through and do not cache responses from `/public/booking/*`.

4. **Smoke test the flow**
   - Visit `/public/booking` in a private window.
   - Create a booking, capture the confirmation token, and immediately test the manage/reschedule tab.
   - Inspect the browser console and server logs for rate-limiter or CSRF errors.

## Operational Notes

- **Email/SMS confirmation** – The service returns provider/service metadata; tie into your notification worker to send reminders using the returned `token`.
- **Rescheduling** – The manage tab enforces the configured cutoff. Staff can override by disabling the policy (`business.reschedule = none`).
- **Monitoring** – Cache exhaustion will disable the rate limiter. Configure a systemd watchdog or Logtail alert for the `Cache key contains reserved characters` and `Too many requests` log lines.
- **Zero-downtime deploys** – Rebuild assets before swapping releases so the Blade view never references a missing bundle (`public-booking.js`).

Following the steps above keeps the public scheduler aligned with admin-configured settings and reduces the manual effort needed to expose the experience to patients or clients.
