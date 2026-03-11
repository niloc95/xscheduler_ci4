/**
 * Custom Scheduler - Week View (Month-style 7-column row)
 */

import { DateTime } from 'luxon';
import { getProviderColor, getProviderInitials, getStatusColors, getStatusLabel } from './appointment-colors.js';
import { weekStart } from './time-grid-utils.js';
import { getRotatedWeekdayShortNames } from './calendar-grid-shared.js';
import { renderAppointmentChip } from './appointment-chip.js';
import {
    renderWeekShell,
    renderWeekDayCell,
    renderWeekSlotPanel,
    renderWeekAppointmentRow,
    renderWeekProviderAccordionSection,
    renderEmptyState,
} from './week-view-components.js';
import { createDebugLogger } from './scheduler-debug.js';

export class WeekView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.selectedDate = null;
        this._appointmentById = new Map();
        this.debugLog = createDebugLogger(() => this.scheduler);
    }

    async render(container, data) {
        const { currentDate, appointments, providers, config, settings, calendarModel } = data;

        this.currentDate = currentDate;
        this.providers = providers || [];
        this.settings = settings;
        this.config = config;
        this.calendarModel = calendarModel || null;

        const days = this._resolveWeekDays(currentDate, settings, config, calendarModel);
        const dayHeadersHtml = this._renderDayHeaders(days, settings, config);
        const appointmentsByDay = this._buildAppointmentsByDay(days, data);

        if (!this.selectedDate || !days.some((d) => d.hasSame(this.selectedDate, 'day'))) {
            this.selectedDate = currentDate.startOf('day');
        }

        this._indexAppointments(appointmentsByDay);

        const weekGridHtml = days
            .map((day) => this._renderDayCell(day, appointmentsByDay[day.toISODate()] || []))
            .join('');

        container.innerHTML = renderWeekShell({
            dayHeadersHtml,
            weekGridHtml,
            slotPanelHtml: '<div class="text-xs text-gray-500 dark:text-gray-400">Loading day details...</div>',
        });

        this._attachListeners(container);
        await this._refreshSlotPanel(container, appointmentsByDay[this.selectedDate.toISODate()] || []);
    }

    _renderDayHeaders(days, settings, config) {
        const firstDay = settings?.getFirstDayOfWeek?.() ?? config?.firstDayOfWeek ?? 1;
        const shortDays = getRotatedWeekdayShortNames(days[0], firstDay);

        return shortDays
            .map(
                (day) => `
            <div class="text-center py-1.5">
                <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">${day}</span>
            </div>
        `,
            )
            .join('');
    }

    _renderDayCell(day, dayAppointments) {
        const now = DateTime.now().setZone(this.scheduler.options.timezone);
        const isToday = day.hasSame(now, 'day');
        const isSelected = this.selectedDate && day.hasSame(this.selectedDate, 'day');

        const dayNumColor = isToday
            ? 'bg-blue-600 text-white font-bold'
            : isSelected
              ? 'bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-200 font-semibold'
              : 'text-gray-900 dark:text-white font-medium';

        const cellClasses = [
            'scheduler-day-cell',
            'min-h-[80px]',
            'sm:min-h-[120px]',
            'md:min-h-[160px]',
            'h-full',
            'p-2',
            'rounded-lg',
            'relative',
            'flex',
            'flex-col',
            'cursor-pointer',
            'transition-all',
            'duration-150',
            isSelected
                ? 'bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500/40'
                : isToday
                  ? 'bg-blue-50/40 dark:bg-blue-900/10'
                  : 'hover:bg-gray-50 dark:hover:bg-white/[0.03]',
        ].join(' ');

        const maxChips = window.matchMedia('(max-width: 639px)').matches ? 2 : 3;
        const visibleChips = dayAppointments.slice(0, maxChips).map((appointment) => {
            const provider = this.providers.find((p) => Number(p.id) === Number(appointment.providerId));
            const providerColor = getProviderColor(provider);
            const statusColor = getStatusColors(appointment.status, false).dot;
            const customerName = appointment.customerName || appointment.title || 'Appointment';

            return renderAppointmentChip({
                appointmentId: appointment.id,
                providerColor,
                statusColor,
                customerName,
                isHidden: false,
            });
        }).join('');

        const hiddenChips = dayAppointments.slice(maxChips).map((appointment) => {
            const provider = this.providers.find((p) => Number(p.id) === Number(appointment.providerId));
            const providerColor = getProviderColor(provider);
            const statusColor = getStatusColors(appointment.status, false).dot;
            const customerName = appointment.customerName || appointment.title || 'Appointment';

            return renderAppointmentChip({
                appointmentId: appointment.id,
                providerColor,
                statusColor,
                customerName,
                isHidden: true,
            });
        }).join('');

        const hiddenCount = Math.max(0, dayAppointments.length - maxChips);
        const overflowButtonHtml = hiddenCount
            ? `
                <button
                    type="button"
                    class="week-expand-btn w-full text-[10px] font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 rounded px-1 py-0.5"
                    data-week-expand-day="${day.toISODate()}"
                    data-expanded="false"
                >
                    <span class="week-expand-text">+${hiddenCount} more</span>
                </button>
            `
            : '';

        return renderWeekDayCell({
            dateIso: day.toISODate(),
            dayNumber: day.day,
            dayNumberClass: dayNumColor,
            cellClasses,
            appointmentChipsHtml: `${visibleChips}${hiddenChips}`,
            overflowButtonHtml,
        });
    }

    async _refreshSlotPanel(container, selectedAppointments) {
        const slotPanel = container.querySelector('#week-slot-panel');
        if (!slotPanel) return;

        const appointmentListHtml = this._renderAppointmentList(selectedAppointments);

        slotPanel.innerHTML = renderWeekSlotPanel({
            selectedDateLabel: this.selectedDate.toFormat('EEEE, MMMM d, yyyy'),
            appointmentCount: selectedAppointments.length,
            appointmentListHtml,
        });

        this._attachPanelListeners(slotPanel);
    }

    _renderAppointmentList(selectedAppointments) {
        if (!selectedAppointments.length) {
            return renderEmptyState('No appointments for this day.');
        }

        const sortedAppointments = selectedAppointments
            .slice()
            .sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis())
            ;

        const groupedByProvider = new Map();
        sortedAppointments.forEach((appointment) => {
            const providerId = Number(appointment.providerId || 0);
            if (!groupedByProvider.has(providerId)) {
                groupedByProvider.set(providerId, []);
            }
            groupedByProvider.get(providerId).push(appointment);
        });

        const providerOrder = this.providers
            .filter((provider) => groupedByProvider.has(Number(provider.id)))
            .map((provider) => Number(provider.id));

        // Include any provider IDs not present in providers list as a fallback.
        Array.from(groupedByProvider.keys()).forEach((providerId) => {
            if (!providerOrder.includes(providerId)) {
                providerOrder.push(providerId);
            }
        });

        return providerOrder
            .map((providerId, index) => {
                const providerAppointments = groupedByProvider.get(providerId) || [];
                const provider = this.providers.find((p) => Number(p.id) === Number(providerId));
                const providerColor = getProviderColor(provider);
                const providerName = this._providerNameFor(providerId);
                const providerInitials = getProviderInitials(provider?.name || provider?.username || providerName);

                const appointmentsHtml = providerAppointments.map((appointment) => {
                const provider = this.providers.find((p) => Number(p.id) === Number(appointment.providerId));
                const providerColor = getProviderColor(provider);
                const statusColors = getStatusColors(appointment.status, false);

                return renderWeekAppointmentRow({
                    appointmentId: appointment.id,
                    timeLabel: this._formatAppointmentTime(appointment),
                    providerName: this._providerNameFor(appointment.providerId),
                    serviceName: appointment.serviceName || '',
                    providerInitials: getProviderInitials(provider?.name || provider?.username),
                    providerColor,
                    statusLabel: getStatusLabel(appointment.status),
                    statusDotColor: statusColors.dot,
                });
                }).join('');

                return renderWeekProviderAccordionSection({
                    providerId,
                    providerName,
                    providerInitials,
                    providerColor,
                    appointmentCount: providerAppointments.length,
                    appointmentsHtml,
                    expanded: false,
                });
            })
            .join('');
    }

    _providerNameFor(providerId) {
        const provider = this.providers.find((p) => Number(p.id) === Number(providerId));
        return provider?.name || provider?.username || 'Provider';
    }

    _timeFormatter() {
        return this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
    }

    _formatAppointmentTime(appointment) {
        const fmt = this._timeFormatter();
        return `${appointment.startDateTime.toFormat(fmt)} - ${appointment.endDateTime.toFormat(fmt)}`;
    }

    _attachListeners(container) {
        container.querySelectorAll('[data-week-select-day]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const dateIso = button.dataset.weekSelectDay;
                this.selectedDate = DateTime.fromISO(dateIso, { zone: this.scheduler.options.timezone }).startOf('day');

                await this.render(container, {
                    currentDate: this.currentDate,
                    appointments: this.scheduler.getFilteredAppointments(),
                    providers: this.providers,
                    config: this.config,
                    settings: this.settings,
                    calendarModel: this.calendarModel,
                });
            });
        });

        container.querySelectorAll('[data-week-expand-day]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this._toggleDayExpansion(button);
            });
        });
    }

    _attachPanelListeners(scopeElement) {
        scopeElement.addEventListener('click', (event) => {
            const providerToggle = event.target.closest('[data-week-provider-toggle]');
            if (providerToggle) {
                event.preventDefault();
                this._toggleProviderAccordion(scopeElement, providerToggle);
                return;
            }

            const appointmentElement = event.target.closest('[data-appointment-id]');
            if (appointmentElement) {
                const appointmentId = Number(appointmentElement.dataset.appointmentId);
                const appointment = this._appointmentById.get(appointmentId);
                if (appointment && this.scheduler?.handleAppointmentClick) {
                    this.scheduler.handleAppointmentClick(appointment);
                }
                return;
            }
        });
    }

    _toggleProviderAccordion(scopeElement, toggleElement) {
        const providerId = toggleElement.dataset.weekProviderToggle;
        if (!providerId) {
            return;
        }

        const body = scopeElement.querySelector(`[data-week-provider-body="${providerId}"]`);
        const caret = scopeElement.querySelector(`[data-week-provider-caret="${providerId}"]`);
        if (!body || !caret) {
            return;
        }

        const isExpanded = toggleElement.dataset.expanded === 'true';
        if (isExpanded) {
            toggleElement.dataset.expanded = 'false';
            body.classList.add('hidden');
            caret.textContent = '+';
            return;
        }

        toggleElement.dataset.expanded = 'true';
        body.classList.remove('hidden');
        caret.textContent = '-';
    }

    _toggleDayExpansion(buttonElement) {
        const dayCell = buttonElement.closest('.scheduler-day-cell');
        const appointmentsContainer = dayCell?.querySelector('.day-appointments');
        if (!dayCell || !appointmentsContainer) {
            return;
        }

        const isExpanded = buttonElement.dataset.expanded === 'true';
        const hiddenItems = appointmentsContainer.querySelectorAll('.scheduler-appointment.hidden');
        const visibleItems = appointmentsContainer.querySelectorAll('.scheduler-appointment');
        const textElement = buttonElement.querySelector('.week-expand-text');
        const maxVisible = window.matchMedia('(max-width: 639px)').matches ? 2 : 3;

        if (isExpanded) {
            visibleItems.forEach((item, index) => {
                if (index >= maxVisible) {
                    item.classList.add('hidden');
                }
            });
            buttonElement.dataset.expanded = 'false';
            if (textElement) {
                textElement.textContent = `+${Math.max(0, visibleItems.length - maxVisible)} more`;
            }
            return;
        }

        hiddenItems.forEach((item) => item.classList.remove('hidden'));
        buttonElement.dataset.expanded = 'true';
        if (textElement) {
            textElement.textContent = 'Show less';
        }
    }

    _resolveWeekDays(currentDate, settings, config, calendarModel) {
        if (Array.isArray(calendarModel?.days) && calendarModel.days.length === 7) {
            return calendarModel.days.map((day) =>
                DateTime.fromISO(String(day.date), { zone: this.scheduler.options.timezone }).startOf('day'),
            );
        }

        const firstDayOfWeek = settings?.getFirstDayOfWeek?.() ?? config?.firstDayOfWeek ?? 1;
        const weekStartDate = weekStart(currentDate, firstDayOfWeek);
        return Array.from({ length: 7 }, (_, i) => weekStartDate.plus({ days: i }));
    }

    _buildAppointmentsByDay(days, data) {
        const fromModel = this._extractFromWeekModel(data.calendarModel);
        if (fromModel && this._hasAnyAppointments(fromModel)) {
            return fromModel;
        }

        const weekStartDate = days[0].startOf('day');
        const weekEndDate = days[6].endOf('day');

        const weekAppointments = (data.appointments || []).filter(
            (apt) => apt.startDateTime >= weekStartDate && apt.startDateTime <= weekEndDate,
        );

        const byDay = {};
        days.forEach((day) => {
            const key = day.toISODate();
            byDay[key] = weekAppointments.filter((apt) => apt.startDateTime.hasSame(day, 'day'));
        });

        return byDay;
    }

    _hasAnyAppointments(grouped = {}) {
        return Object.values(grouped).some((list) => Array.isArray(list) && list.length > 0);
    }

    _extractFromWeekModel(calendarModel) {
        if (!Array.isArray(calendarModel?.days)) {
            return null;
        }

        const mapped = {};
        calendarModel.days.forEach((day) => {
            const dateKey = day.date;
            const seen = new Set();
            const items = [];

            ((day.dayGrid?.slots) || []).forEach((slot) => {
                (slot.appointments || []).forEach((appt) => {
                    const normalized = this._normalizeModelAppointment(appt);
                    if (!normalized?.id || seen.has(normalized.id)) return;
                    seen.add(normalized.id);
                    items.push(normalized);
                });
            });

            mapped[dateKey] = items;
        });

        return mapped;
    }

    _normalizeModelAppointment(modelAppointment) {
        const startIso = modelAppointment.start || modelAppointment.start_datetime || modelAppointment.startDateTime;
        const endIso = modelAppointment.end || modelAppointment.end_datetime || modelAppointment.endDateTime;

        const startDateTime = DateTime.fromISO(String(startIso), { zone: 'utc', setZone: true }).setZone(this.scheduler.options.timezone);
        const endDateTime = DateTime.fromISO(String(endIso), { zone: 'utc', setZone: true }).setZone(this.scheduler.options.timezone);

        return {
            ...modelAppointment,
            id: Number(modelAppointment.id),
            providerId: Number(modelAppointment.provider_id ?? modelAppointment.providerId),
            customerName: modelAppointment.customer_name ?? modelAppointment.customerName,
            serviceName: modelAppointment.service_name ?? modelAppointment.serviceName,
            location: modelAppointment.location ?? modelAppointment.locationName ?? '',
            notes: modelAppointment.notes ?? '',
            status: modelAppointment.status || 'pending',
            startDateTime,
            endDateTime,
        };
    }

    _indexAppointments(appointmentsByDay) {
        this._appointmentById.clear();
        Object.values(appointmentsByDay).forEach((dayList) => {
            dayList.forEach((apt) => {
                if (apt?.id) {
                    this._appointmentById.set(Number(apt.id), apt);
                }
            });
        });
    }
}
