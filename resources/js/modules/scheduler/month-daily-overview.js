import { escapeHtml } from '../../utils/html.js';
import { getStatusColors, getProviderColor, getProviderInitials, isDarkMode } from './appointment-colors.js';

export function renderMonthDailyOverview({
    currentDate,
    appointments,
    providers,
    settings,
}) {
    const monthStart = currentDate.startOf('month');
    const monthEnd = currentDate.endOf('month');

    const monthAppointments = appointments.filter((appointment) => (
        appointment.startDateTime >= monthStart && appointment.startDateTime <= monthEnd
    ));

    const appointmentsByProvider = {};
    providers.forEach((provider) => {
        appointmentsByProvider[provider.id] = [];
    });

    monthAppointments.forEach((appointment) => {
        if (appointmentsByProvider[appointment.providerId]) {
            appointmentsByProvider[appointment.providerId].push(appointment);
        }
    });

    const activeProviders = providers.filter((provider) => appointmentsByProvider[provider.id].length > 0);
    const timeFormat = settings?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';

    let html = `
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        ${currentDate.toFormat('MMMM yyyy')}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Monthly appointment overview</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    ${monthAppointments.length} ${monthAppointments.length === 1 ? 'appointment' : 'appointments'}
                </span>
            </div>
        </div>
    `;

    if (activeProviders.length === 0) {
        html += `
            <div class="p-12 text-center">
                <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled for ${currentDate.toFormat('MMMM yyyy')}</p>
            </div>
        `;

        return html;
    }

    html += `
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${Math.min(activeProviders.length, 3)} gap-4">
    `;

    activeProviders.forEach((provider) => {
        const providerAppointments = appointmentsByProvider[provider.id] || [];
        const color = provider.color || '#3B82F6';

        providerAppointments.sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        html += `
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                     data-border-left-color="${color}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                             data-bg-color="${color}">
                            ${getProviderInitials(provider.name)}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                ${escapeHtml(provider.name)}
                            </h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                ${providerAppointments.length} ${providerAppointments.length === 1 ? 'appointment' : 'appointments'} this month
                            </p>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
        `;

        if (providerAppointments.length > 0) {
            const maxVisible = 10;

            providerAppointments.forEach((appointment, index) => {
                const date = appointment.startDateTime.toFormat('MMM d');
                const time = appointment.startDateTime.toFormat(timeFormat);
                const customerName = appointment.customerName || appointment.title || 'Unknown';
                const serviceName = appointment.serviceName || 'Appointment';

                const darkMode = isDarkMode();
                const statusColors = getStatusColors(appointment.status, darkMode);
                const providerColor = getProviderColor(provider);
                const hiddenClass = index >= maxVisible ? 'hidden' : '';

                html += `
                    <div class="appointment-item p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer border-l-4 ${hiddenClass}"
                         data-border-left-color="${statusColors.border}"
                         data-bg-color="${statusColors.bg}"
                         data-text-color="${statusColors.text}"
                         data-appointment-id="${appointment.id}">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div class="flex-1 min-w-0 flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" data-bg-color="${providerColor}"></span>
                                <div class="text-xs font-medium">
                                    ${date} • ${time}
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full flex-shrink-0 text-white"
                                  data-bg-color="${statusColors.dot}">
                                ${appointment.status}
                            </span>
                        </div>
                        <h5 class="font-semibold text-sm mb-1 truncate">
                            ${escapeHtml(customerName)}
                        </h5>
                        <p class="text-xs opacity-80 truncate">
                            ${escapeHtml(serviceName)}
                        </p>
                    </div>
                `;
            });

            if (providerAppointments.length > maxVisible) {
                const remaining = providerAppointments.length - maxVisible;
                html += `
                    <div class="p-3 text-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors border-t border-gray-200 dark:border-gray-700"
                         data-expand-provider="${provider.id}"
                         data-expanded="false">
                        <div class="flex items-center justify-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" />
                            </svg>
                            <span>+${remaining} more</span>
                        </div>
                    </div>
                `;
            }
        } else {
            html += `
                <div class="p-8 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-4xl mb-2">event_available</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No appointments</p>
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    return html;
}
