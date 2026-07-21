/**
 * Agenda / List View (mobile-first)
 *
 * Renders the current day's appointments as a vertical, fully-readable list of
 * cards instead of a compressed time-grid. This is the default calendar view on
 * phones (<768px), where the grid becomes unusable, but it is available at any
 * width via the Calendar/Agenda toggle.
 *
 * Data flow: shares the day render model. `agenda` maps to the `/api/calendar/day`
 * endpoint (see calendar-model-url.js) and consumes the same normalized
 * appointment objects the day/week/month views use, so there is no bespoke API.
 *
 * Quick actions reuse the canonical mutation path (appointment-mutation-coordinator
 * → PATCH /api/appointments/:id/status), the exact endpoint the details modal uses.
 * Tapping a card opens the shared appointment-details modal via onAppointmentClick.
 */

import { DateTime } from 'luxon';
import { getProviderColor, getProviderInitials } from './appointment-colors.js';
import { escapeHtml } from '../../utils/html.js';
import { appointmentMutationCoordinator } from '../appointments/appointment-mutation-coordinator.js';
import { withBaseUrl } from '../../utils/url-helpers.js';

// Status badge styling — mirrors the day-view STATUS_META for visual consistency.
const STATUS_META = {
    confirmed:   { label: 'Confirmed',   bg: 'bg-emerald-100 dark:bg-emerald-900/40', text: 'text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500' },
    pending:     { label: 'Pending',     bg: 'bg-amber-100 dark:bg-amber-900/40',     text: 'text-amber-700 dark:text-amber-300',    dot: 'bg-amber-500' },
    cancelled:   { label: 'Cancelled',   bg: 'bg-red-100 dark:bg-red-900/40',         text: 'text-red-700 dark:text-red-300',        dot: 'bg-red-500' },
    completed:   { label: 'Completed',   bg: 'bg-blue-100 dark:bg-blue-900/40',       text: 'text-blue-700 dark:text-blue-300',      dot: 'bg-blue-500' },
    'no-show':   { label: 'No Show',     bg: 'bg-gray-100 dark:bg-gray-700/60',       text: 'text-gray-600 dark:text-gray-300',      dot: 'bg-gray-400' },
    rescheduled: { label: 'Rescheduled', bg: 'bg-purple-100 dark:bg-purple-900/40',   text: 'text-purple-700 dark:text-purple-300',  dot: 'bg-purple-500' },
};
const statusMeta = (status) => STATUS_META[status] ?? STATUS_META.pending;

export class AgendaView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    render(container, data) {
        const timezone = this.scheduler?.options?.timezone || 'UTC';
        const timeFormat = data.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';

        const appointments = (data.appointments || [])
            .map((apt) => ({ apt, start: this._toDateTime(apt.startDateTime || apt.start, timezone) }))
            .filter((entry) => entry.start && entry.start.isValid)
            .sort((a, b) => a.start.toMillis() - b.start.toMillis());

        // No date heading here — the sticky toolbar is the single date source
        // (see date-nav-label.js 'agenda' case), which keeps the list area tall.
        if (appointments.length === 0) {
            container.innerHTML = `
                <div class="agenda-view">
                    <div class="agenda-view__empty">
                        <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-500 mb-3 block">event_available</span>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No appointments scheduled</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Enjoy the quiet, or book a new appointment.</p>
                    </div>
                </div>
            `;
            return;
        }

        const cards = appointments
            .map(({ apt }) => this._renderCard(apt, timeFormat, timezone))
            .join('');

        container.innerHTML = `
            <div class="agenda-view">
                <div class="agenda-view__list">${cards}</div>
            </div>
        `;

        this._applyDynamicStyles(container);
        this._attachListeners(container, data);
    }

    _renderCard(apt, timeFormat, timezone) {
        const start = this._toDateTime(apt.startDateTime || apt.start, timezone);
        const end = this._toDateTime(apt.endDateTime || apt.end, timezone);
        const startLabel = start ? start.toFormat(timeFormat) : '';
        const endLabel = end ? end.toFormat(timeFormat) : '';

        const providerColor = getProviderColor({ color: apt.provider_color || apt.providerColor, name: apt.providerName });
        const customerName = apt.customerName || apt.title || apt.name || 'Appointment';
        const serviceName = apt.serviceName || apt.service_name || '';
        const providerName = apt.providerName || apt.provider_name || '';
        const status = apt.status || 'pending';
        const meta = statusMeta(status);
        const initials = getProviderInitials(providerName || '?');

        const providerChip = providerName ? `
            <span class="agenda-card__provider">
                <span class="agenda-card__avatar" data-style='${this._s({ backgroundColor: providerColor })}'>${escapeHtml(initials)}</span>
                <span class="truncate">${escapeHtml(providerName)}</span>
            </span>
        ` : '';

        return `
            <div class="agenda-card" role="button" tabindex="0"
                 data-appointment-id="${apt.id}"
                 data-style='${this._s({ borderLeftColor: providerColor })}'
                 aria-label="${escapeHtml(customerName)}${serviceName ? ' — ' + escapeHtml(serviceName) : ''} at ${escapeHtml(startLabel)}">
                <div class="agenda-card__main">
                    <div class="agenda-card__time">
                        <span class="agenda-card__time-start">${escapeHtml(startLabel)}</span>
                        ${endLabel ? `<span class="agenda-card__time-end">${escapeHtml(endLabel)}</span>` : ''}
                    </div>
                    <div class="agenda-card__details">
                        <div class="agenda-card__customer">${escapeHtml(customerName)}</div>
                        ${serviceName ? `<div class="agenda-card__service"><span class="material-symbols-outlined text-[13px]">spa</span><span class="truncate">${escapeHtml(serviceName)}</span></div>` : ''}
                        ${providerChip}
                    </div>
                    <span class="agenda-card__status inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ${meta.bg} ${meta.text}">
                        <span class="w-1.5 h-1.5 rounded-full ${meta.dot}"></span>
                        ${meta.label}
                    </span>
                </div>
                <div class="agenda-card__actions">
                    ${this._renderActions(status)}
                </div>
            </div>
        `;
    }

    /**
     * Context-aware quick actions. "Check-in" maps to confirmed, "Complete" to
     * completed — matching the details-modal status dropdown transitions.
     */
    _renderActions(status) {
        const btn = (action, icon, label, extra = '') => `
            <button type="button" class="agenda-card__action ${extra}" data-agenda-action="${action}">
                <span class="material-symbols-outlined text-base">${icon}</span>
                <span>${label}</span>
            </button>
        `;

        const actions = [btn('view', 'visibility', 'View')];
        if (status === 'pending') {
            actions.push(btn('checkin', 'how_to_reg', 'Check-in'));
        }
        if (status === 'pending' || status === 'confirmed') {
            actions.push(btn('complete', 'task_alt', 'Complete'));
        }
        if (status !== 'cancelled' && status !== 'completed' && status !== 'no-show') {
            actions.push(btn('cancel', 'cancel', 'Cancel', 'agenda-card__action--danger'));
        }
        return actions.join('');
    }

    _attachListeners(container, data) {
        container.querySelectorAll('.agenda-card').forEach((card) => {
            const aptId = parseInt(card.dataset.appointmentId, 10);
            const appointment = data.appointments.find((a) => a.id === aptId);
            if (!appointment) return;

            const open = () => {
                if (data.onAppointmentClick) data.onAppointmentClick(appointment);
            };

            // Card body opens the details modal; action buttons handle themselves.
            card.addEventListener('click', (event) => {
                if (event.target.closest('[data-agenda-action]')) return;
                open();
            });
            card.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open();
                }
            });

            card.querySelectorAll('[data-agenda-action]').forEach((actionBtn) => {
                actionBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this._handleAction(actionBtn.dataset.agendaAction, appointment, open);
                });
            });
        });
    }

    async _handleAction(action, appointment, open) {
        if (action === 'view') {
            open();
            return;
        }

        const statusByAction = { checkin: 'confirmed', complete: 'completed', cancel: 'cancelled' };
        const newStatus = statusByAction[action];
        if (!newStatus) return;

        // Cancelling is irreversible — confirm first, matching the details modal.
        if (action === 'cancel') {
            const confirmed = await window.XSConfirm?.show({
                title: 'Cancel Appointment',
                message: `Cancel the appointment for ${appointment.customerName || 'this customer'}? This cannot be undone.`,
                confirmText: 'Cancel Appointment',
                danger: true,
            });
            if (!confirmed) return;
        }

        appointmentMutationCoordinator.execute({
            action: action === 'cancel' ? 'cancel' : 'status-change',
            endpoint: withBaseUrl(`/api/appointments/${appointment.id}/status`),
            method: 'PATCH',
            body: { status: newStatus },
            uiContext: 'scheduler',
            authContext: 'authenticated',
            toast: { type: 'success', message: 'Status updated successfully' },
        }).catch((err) => console.error('[AgendaView] status change failed:', err));
    }

    _toDateTime(value, timezone) {
        if (!value) return null;
        if (DateTime.isDateTime(value)) return value;
        const utc = DateTime.fromISO(String(value), { zone: 'utc', setZone: true });
        if (utc.isValid) return utc.setZone(timezone);
        const sql = DateTime.fromSQL(String(value), { zone: 'utc' });
        return sql.isValid ? sql.setZone(timezone) : null;
    }

    // CSP-safe dynamic styles — same pattern as the other scheduler views.
    _s(styles) {
        return JSON.stringify(styles);
    }

    _applyDynamicStyles(root) {
        root.querySelectorAll('[data-style]').forEach((el) => {
            try {
                const styles = JSON.parse(el.getAttribute('data-style'));
                for (const [prop, val] of Object.entries(styles)) {
                    if (prop.startsWith('--')) {
                        el.style.setProperty(prop, val);
                    } else {
                        el.style[prop] = val;
                    }
                }
            } catch (_) {
                // ignore malformed data-style
            }
            el.removeAttribute('data-style');
        });
    }
}
