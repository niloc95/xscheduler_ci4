/**
 * Thin wrapper over the global XSNotify toast with the app's default timing:
 * success/info/warning auto-close after 4s, errors stay until dismissed.
 * Falls back to console.error when the notifier is unavailable.
 *
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {string} title
 * @param {string} message
 * @param {boolean} [autoClose]
 */
export function showToast(type, title, message, autoClose = type !== 'error') {
    if (window.XSNotify?.toast) {
        window.XSNotify.toast({
            type,
            title,
            message,
            autoClose,
            duration: autoClose ? 4000 : undefined,
        });
        return;
    }

    console.error(message);
}
