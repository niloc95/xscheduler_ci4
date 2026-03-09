/**
 * Custom Scheduler - Week View (Month-style 7-column row)
 */

import { DateTime } from 'luxon';
import { getProviderColor, getStatusColors } from './appointment-colors.js';
import { weekStart } from './time-grid-utils.js';
import { getRotatedWeekdayShortNames } from './calendar-grid-shared.js';
import { renderMonthModelAppointmentChip } from './month-view-components.js';
import {
    renderWeekShell,
    renderWeekDayCell,
    renderWeekSlotPanel,
    renderWeekAppointmentRow,
    renderWeekAppointmentEmptyState,
    renderWeekSlotRow,
    renderWeekSlotEmptyState,
} from './week-view-components.js';
import { withBaseUrl } from '../../utils/url-helpers.js';

export class WeekView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.selectedDate = null;
        this._slotRequestToken = 0;
        this._appointmentById = new Map();
    }

    debugLog(...args) {
        if (this.scheduler?.debugLog) {
            this.scheduler.debugLog(...args);
        } else if (typeof window !== 'undefined' && window.appConfig?.debug) {
            console.log(...args);
        }
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
            slotPanelHtml: this._renderSlotPanelSkeleton(),
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
            'min-h-[160px]',
            'h-full',
            'p-2',
            'rounded-lg',
            'relative',
            '!overflow-hidden',
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

        const chips = dayAppointments.slice(0, 3).map((appointment) => {
            const provider = this.providers.find((p) => Number(p.id) === Number(appointment.providerId));
            const providerColor = getProviderColor(provider);
            const statusColor = getStatusColors(appointment.status, false).dot;
            const customerName = appointment.customerName || appointment.title || 'Appointment';

            return renderMonthModelAppointmentChip({
                appointmentId: appointment.id,
                providerColor,
                statusColor,
                customerName,
                isHidden: false,
            });
        }).join('');

        return renderWeekDayCell({
            dateIso: day.toISODate(),
            dayNumber: day.day,
            dayNumberClass: dayNumColor,
            cellClasses,
            appointmentChipsHtml: chips,
            overflowLabel: dayAppointments.length > 3 ? `+${dayAppointments.length - 3} more` : '',
        });
    }

    _renderSlotPanelSkeleton() {
        return renderWeekSlotPanel({
            selectedDateLabel: this.selectedDate.toFormat('EEEE, MMMM d, yyyy'),
            appointmentCount: 0,
            appointmentListHtml: renderWeekAppointmentEmptyState(),
            slotsHtml: renderWeekSlotEmptyState('Loading slots...'),
        });
    }

    async _refreshSlotPanel(container, selectedAppointments) {
        const slotPanel = container.querySelector('#week-slot-panel');
        if (!slotPanel) return;

        const appointmentListHtml = selectedAppointments.length
            ? selectedAppointments
                  .slice()
                  .sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis())
                  .map((appointment) =>
                      renderWeekAppointmentRow({
                          appointmentId: appointment.id,
                          timeLabel: this._formatAppointmentTime(appointment),
                          providerName: this._providerNameFor(appointment.providerId),
                          serviceName: appointment.serviceName || '',
                      }),
                  )
                  .join('')
            : renderWeekAppointmentEmptyState();

        slotPanel.innerHTML = renderWeekSlotPanel({
            selectedDateLabel: this.selectedDate.toFormat('EEEE, MMMM d, yyyy'),
            appointmentCount: selectedAppointments.length,
            appointmentListHtml,
            slotsHtml: renderWeekSlotEmptyState('Loading slots...'),
        });

        this._attachPanelListeners(slotPanel);

        const slotsHtml = await this._loadSlotsHtml();
        slotPanel.innerHTML = renderWeekSlotPanel({
            selectedDateLabel: this.selectedDate.toFormat('EEEE, MMMM d, yyyy'),
            appointmentCount: selectedAppointments.length,
            appointmentListHtml,
            slotsHtml,
        });

        this._attachPanelListeners(slotPanel);
    }

    async _loadSlotsHtml() {
        const selectedServiceId = Number(this.scheduler?.selectedServiceId || 0);
        if (!selectedServiceId) {
            return renderWeekSlotEmptyState('Select a service to view available slots.');
        }

        const providerIds = Array.from(this.scheduler?.visibleProviders || []);
        if (!providerIds.length) {
            return renderWeekSlotEmptyState('No providers selected.');
        }

        const requestToken = ++this._slotRequestToken;
        const dateKey = this.selectedDate.toFormat('yyyy-MM-dd');
        const slots = [];

        await Promise.all(
            providerIds.map(async (providerId) => {
                try {
                    const response = await fetch(
                        withBaseUrl(`/api/availability/slots?provider_id=${providerId}&date=${dateKey}&service_id=${selectedServiceId}`),
                    );
                    if (!response.ok) return;
                    const data = await response.json();
                    const rawSlots = data?.data?.slots || [];
                    rawSlots.forEach((slot) => {
                        slots.push({ providerId, slot });
                    });
                } catch {
                    // Ignore provider fetch failures; partial slots are still useful.
                }
            }),
        );

        if (requestToken !== this._slotRequestToken) {
            return renderWeekSlotEmptyState('Loading slots...');
        }

        if (!slots.length) {
            return renderWeekSlotEmptyState('No available slots for this day.');
        }

        slots.sort((a, b) => {
            const at = DateTime.fromISO(a.slot.start).toMillis();
            const bt = DateTime.fromISO(b.slot.start).toMillis();
            return at - bt;
        });

        return slots
            .slice(0, 30)
            .map(({ providerId, slot }) =>
                renderWeekSlotRow({
                    providerName: this._providerNameFor(providerId),
                    timeLabel: slot.label || this._formatSlotLabel(slot),
                }),
            )
            .join('');
    }

    _providerNameFor(providerId) {
        const provider = this.providers.find((p) => Number(p.id) === Number(providerId));
        return provider?.name || provider?.username || 'Provider';
    }

    _formatAppointmentTime(appointment) {
        const is24 = this.settings?.getTimeFormat?.() === '24h';
        const fmt = is24 ? 'HH:mm' : 'h:mm a';
        return `${appointment.startDateTime.toFormat(fmt)} - ${appointment.endDateTime.toFormat(fmt)}`;
    }

    _formatSlotLabel(slot) {
        try {
            const is24 = this.settings?.getTimeFormat?.() === '24h';
            const fmt = is24 ? 'HH:mm' : 'h:mm a';
            const s = DateTime.fromISO(slot.start).setZone(this.scheduler.options.timezone);
            const e = DateTime.fromISO(slot.end).setZone(this.scheduler.options.timezone);
            return `${s.toFormat(fmt)} - ${e.toFormat(fmt)}`;
        } catch {
            return 'Available slot';
        }
    }

    _attachListeners(container) {
        container.querySelectorAll('[data-week-select-day]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const dateIso = button.dataset.weekSelectDay;
                this.selectedDate = DateTime.fromISO(dateIso, { zone: this.scheduler.options.timezone }).startOf('day');

                // Re-render to refresh selected-day highlight + chips + slot panel.
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

        this._attachPanelListeners(container);
    }

    _attachPanelListeners(scopeElement) {
        scopeElement.querySelectorAll('[data-appointment-id]').forEach((el) => {
            el.addEventListener('click', () => {
                const appointmentId = Number(el.dataset.appointmentId);
                const appointment = this._appointmentById.get(appointmentId);
                if (appointment && this.scheduler?.handleAppointmentClick) {
                    this.scheduler.handleAppointmentClick(appointment);
                }
            });
        });
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
