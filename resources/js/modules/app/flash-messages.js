/**
 * Server-rendered flash messages (components/ui/flash-messages).
 *
 * Auto-dismisses each message after 8s, wires the close button, and scrolls to
 * the top when a new message appears so it is visible on long form pages.
 * Idempotent per message element — safe on initial load and after `spa:navigated`.
 */

const AUTO_DISMISS_MS = 8000;
const FADE_MS = 300;

export function initFlashMessages(root = document) {
    const fresh = Array.from(root.querySelectorAll('[data-flash-message]'))
        .filter((el) => el.dataset.flashInitialized !== 'true');

    if (!fresh.length) {
        return;
    }

    fresh.forEach((el) => {
        el.dataset.flashInitialized = 'true';

        el.querySelector('[data-dismiss-flash]')?.addEventListener('click', () => el.remove());

        setTimeout(() => {
            el.classList.add('xs-fade-out');
            setTimeout(() => el.remove(), FADE_MS);
        }, AUTO_DISMISS_MS);
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
}
