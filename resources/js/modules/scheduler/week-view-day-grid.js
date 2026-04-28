import { DateTime } from 'luxon';
import { getProviderColor, getStatusColors } from './appointment-colors.js';
import { getRotatedWeekdayShortNames } from './calendar-grid-shared.js';
import { renderAppointmentChip } from './appointment-chip.js';
import { renderWeekDayCell as renderWeekDayCellComponent } from './week-view-components.js';

export function renderWeekDayHeaders(days, settings, config) {
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

export function renderWeekDayCell({ day, dayAppointments, selectedDate, timezone, providers }) {
  const now = DateTime.now().setZone(timezone);
  const isToday = day.hasSame(now, 'day');
  const isSelected = selectedDate && day.hasSame(selectedDate, 'day');

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
  const visibleChips = dayAppointments
    .slice(0, maxChips)
    .map((appointment) => {
      const provider = providers.find((p) => Number(p.id) === Number(appointment.providerId));
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
    })
    .join('');

  const hiddenChips = dayAppointments
    .slice(maxChips)
    .map((appointment) => {
      const provider = providers.find((p) => Number(p.id) === Number(appointment.providerId));
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
    })
    .join('');

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

  return renderWeekDayCellComponent({
    dateIso: day.toISODate(),
    dayNumber: day.day,
    dayNumberClass: dayNumColor,
    cellClasses,
    appointmentChipsHtml: `${visibleChips}${hiddenChips}`,
    overflowButtonHtml,
  });
}
