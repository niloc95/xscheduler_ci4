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
    renderWeekSlotRow,
    renderEmptyState,
} from './week-view-components.js';
import { fetchSlotsForDate } from './availability-slots.js';
import { createDebugLogger } from './scheduler-debug.js';
import { withBaseUrl } from '../../utils/url-helpers.js';

export class WeekView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.selectedDate = null;
        this._slotRequestToken = 0;
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

        const maxChips = window.matchMedia('(max-width: 639px)').matches ? 2 : 3;
        const chips = dayAppointments.slice(0, maxChips).map((appointment) => {
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

        return renderWeekDayCell({
            dateIso: day.toISODate(),
            dayNumber: day.day,
            dayNumberClass: dayNumColor,
            cellClasses,
            appointmentChipsHtml: chips,
            overflowLabel: dayAppointments.length > maxChips ? `+${dayAppointments.length - maxChips} more` : '',
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
            slotsHtml: renderEmptyState('Loading slots...'),
            serviceSelectorHtml: '',
        });

        this._attachPanelListeners(slotPanel);

        const requestToken = ++this._slotRequestToken;
        const { slotsHtml, serviceSelectorHtml } = await this._loadSlotsHtml();
        if (requestToken !== this._slotRequestToken) {
            return;
        }

        const slotsElement = slotPanel.querySelector('[data-week-slots-content]');
        if (slotsElement) {
            slotsElement.innerHTML = slotsHtml;
        }

        const selectorElement = slotPanel.querySelector('[data-week-service-selector]');
        if (selectorElement) {
            selectorElement.innerHTML = serviceSelectorHtml;
        }
    }

    _renderAppointmentList(selectedAppointments) {
        if (!selectedAppointments.length) {
            return renderEmptyState('No appointments for this day.');
        }

        return selectedAppointments
            .slice()
            .sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis())
            .map((appointment) => {
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
            })
            .join('');
    }

    async _loadSlotsHtml() {
        const selectedServiceId = Number(this.scheduler?.selectedServiceId || 0);
        const serviceOptions = this._getServiceOptions();

        if (!selectedServiceId) {
            return {
                slotsHtml: renderEmptyState('Select a service to view available slots.'),
                serviceSelectorHtml: this._renderServiceSelector(serviceOptions),
            };
        }

        const providerIds = Array.from(this.scheduler?.visibleProviders || []);
        if (!providerIds.length) {
            return {
                slotsHtml: renderEmptyState('No providers selected.'),
                serviceSelectorHtml: this._renderServiceSelector(serviceOptions),
            };
        }

        const dateKey = this.selectedDate.toFormat('yyyy-MM-dd');
        const slots = await fetchSlotsForDate({
            providerIds,
            dateIso: dateKey,
            serviceId: selectedServiceId,
        });

        if (!slots.length) {
            return {
                slotsHtml: renderEmptyState('No available slots for this day.'),
                serviceSelectorHtml: this._renderServiceSelector(serviceOptions),
            };
        }

        const slotsHtml = slots
            .slice(0, 30)
            .map(({ providerId, slot }) =>
                renderWeekSlotRow({
                    providerId,
                    providerName: this._providerNameFor(providerId),
                    timeLabel: slot.label || this._formatSlotLabel(slot),
                    slotStart: slot.start,
                    slotEnd: slot.end,
                }),
            )
            .join('');

        return {
            slotsHtml,
            serviceSelectorHtml: this._renderServiceSelector(serviceOptions),
        };
    }

    _renderServiceSelector(serviceOptions) {
        if (!serviceOptions.length) {
            return '<span class="text-[11px] text-gray-500 dark:text-gray-400">No services available</span>';
        }

        const currentServiceId = Number(this.scheduler?.selectedServiceId || 0);
        const optionsHtml = serviceOptions
            .map((service) => {
                const selected = Number(service.id) === currentServiceId ? 'selected' : '';
                return `<option value="${service.id}" ${selected}>${service.label || service.name || `Service ${service.id}`}</option>`;
            })
            .join('');

        return `
            <select class="text-[11px] rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 px-2 py-1" data-week-service-select>
                <option value="">Select service</option>
                ${optionsHtml}
            </select>
        `;
    }

    _getServiceOptions() {
        if (Array.isArray(this.scheduler?.services) && this.scheduler.services.length) {
            return this.scheduler.services;
        }

        const serviceSelect = document.getElementById('service-filter') || document.querySelector('[name="service_id"]');
        if (!serviceSelect) {
            return [];
        }

        return Array.from(serviceSelect.options || [])
            .map((option) => {
                const id = Number(option.value || 0);
                if (!id) return null;
                return {
                    id,
                    label: option.textContent?.trim() || `Service ${id}`,
                };
            })
            .filter(Boolean);
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

    _formatSlotLabel(slot) {
        try {
            const fmt = this._timeFormatter();
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
    }

    _attachPanelListeners(scopeElement) {
        scopeElement.addEventListener('click', (event) => {
            const appointmentElement = event.target.closest('[data-appointment-id]');
            if (appointmentElement) {
                const appointmentId = Number(appointmentElement.dataset.appointmentId);
                const appointment = this._appointmentById.get(appointmentId);
                if (appointment && this.scheduler?.handleAppointmentClick) {
                    this.scheduler.handleAppointmentClick(appointment);
                }
                return;
            }

            const slotElement = event.target.closest('[data-slot-start]');
            if (slotElement) {
                const providerId = Number(slotElement.dataset.providerId || 0);
                const start = slotElement.dataset.slotStart;
                const date = this.selectedDate.toISODate();
                const serviceId = Number(this.scheduler?.selectedServiceId || 0);

                if (!providerId || !start || !serviceId) {
                    return;
                }

                const timeString = DateTime.fromISO(start).setZone(this.scheduler.options.timezone).toFormat('HH:mm');
                const params = new URLSearchParams({
                    provider_id: String(providerId),
                    service_id: String(serviceId),
                    date,
                    time: timeString,
                });

                window.location.href = withBaseUrl(`/appointments/create?${params.toString()}`);
            }
        });

        scopeElement.addEventListener('change', async (event) => {
            const selector = event.target.closest('[data-week-service-select]');
            if (!selector) {
                return;
            }

            const selectedId = Number(selector.value || 0) || null;
            this.scheduler.selectedServiceId = selectedId;

            const slotsElement = scopeElement.querySelector('[data-week-slots-content]');
            if (slotsElement) {
                slotsElement.innerHTML = renderEmptyState('Loading slots...');
            }

            const requestToken = ++this._slotRequestToken;
            const { slotsHtml } = await this._loadSlotsHtml();
            if (requestToken !== this._slotRequestToken) {
                return;
            }

            if (slotsElement) {
                slotsElement.innerHTML = slotsHtml;
            }
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
