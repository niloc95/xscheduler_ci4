/**
 * XSConfirm — custom confirmation dialog replacing window.confirm().
 *
 * Usage:
 *   const ok = await XSConfirm.show({ message: 'Delete this item?', danger: true });
 *   if (ok) { ... }
 *
 * Options:
 *   title        {string}  Optional heading. Defaults to 'Confirm'.
 *   message      {string}  Required body text.
 *   confirmText  {string}  Confirm button label. Defaults to 'Confirm'.
 *   cancelText   {string}  Cancel button label. Defaults to 'Cancel'.
 *   danger       {boolean} Red confirm button for destructive actions.
 *
 * Returns Promise<boolean> — resolves true on confirm, false on cancel/dismiss.
 */

let _activeReject = null;

function _removeModal() {
    const el = document.getElementById('xs-confirm-dialog');
    if (el) el.remove();
    document.documentElement.classList.remove('xs-no-scroll');
    _activeReject = null;
}

function _buildModal({ title, message, confirmText, cancelText, danger }) {
    const confirmCls = danger
        ? 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium bg-red-600 text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500'
        : 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500';

    const wrapper = document.createElement('div');
    wrapper.id = 'xs-confirm-dialog';
    wrapper.setAttribute('role', 'alertdialog');
    wrapper.setAttribute('aria-modal', 'true');
    wrapper.setAttribute('aria-labelledby', 'xs-confirm-title');
    wrapper.setAttribute('aria-describedby', 'xs-confirm-message');
    wrapper.className = 'fixed inset-0 z-[9999] flex items-center justify-center p-4';

    wrapper.innerHTML = `
        <div id="xs-confirm-backdrop" class="absolute inset-0 bg-black/50 dark:bg-black/70"></div>
        <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-black/10 dark:ring-white/10 p-6 flex flex-col gap-4">
            <div class="flex items-start gap-3">
                ${danger ? `<span class="material-symbols-outlined text-red-500 dark:text-red-400 text-xl mt-0.5 shrink-0">warning</span>` : `<span class="material-symbols-outlined text-blue-500 dark:text-blue-400 text-xl mt-0.5 shrink-0">help</span>`}
                <div class="flex-1 min-w-0">
                    <h2 id="xs-confirm-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">${_esc(title)}</h2>
                    <p id="xs-confirm-message" class="mt-1 text-sm text-gray-600 dark:text-gray-400">${_esc(message)}</p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-1">
                <button id="xs-confirm-cancel" type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400">
                    ${_esc(cancelText)}
                </button>
                <button id="xs-confirm-ok" type="button" class="${confirmCls}">
                    ${_esc(confirmText)}
                </button>
            </div>
        </div>`;

    return wrapper;
}

function _esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export const XSConfirm = {
    /**
     * @param {object} opts
     * @param {string} [opts.title]
     * @param {string} opts.message
     * @param {string} [opts.confirmText]
     * @param {string} [opts.cancelText]
     * @param {boolean} [opts.danger]
     * @returns {Promise<boolean>}
     */
    show({
        title = 'Confirm',
        message = '',
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        danger = false,
    } = {}) {
        // Dismiss any existing dialog first.
        if (_activeReject) {
            _activeReject(false);
        }
        _removeModal();

        return new Promise((resolve) => {
            _activeReject = () => {
                resolve(false);
                _removeModal();
            };

            const modal = _buildModal({ title, message, confirmText, cancelText, danger });
            document.body.appendChild(modal);
            document.documentElement.classList.add('xs-no-scroll');

            const okBtn = document.getElementById('xs-confirm-ok');
            const cancelBtn = document.getElementById('xs-confirm-cancel');
            const backdrop = document.getElementById('xs-confirm-backdrop');

            const confirm = () => { resolve(true); _removeModal(); };
            const cancel = () => { resolve(false); _removeModal(); };

            okBtn?.addEventListener('click', confirm);
            cancelBtn?.addEventListener('click', cancel);
            backdrop?.addEventListener('click', cancel);

            const onKey = (e) => {
                if (e.key === 'Escape') { cancel(); document.removeEventListener('keydown', onKey); }
                if (e.key === 'Enter' && document.activeElement !== cancelBtn) { confirm(); document.removeEventListener('keydown', onKey); }
            };
            document.addEventListener('keydown', onKey);

            // Focus the confirm button for keyboard accessibility
            requestAnimationFrame(() => okBtn?.focus());
        });
    },
};
