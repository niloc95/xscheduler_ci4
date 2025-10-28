/**
 * Appointment Tooltip
 * 
 * Shows a quick preview of appointment details on hover
 */

import { DateTime } from 'luxon';

export class AppointmentTooltip {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.tooltip = null;
        this.currentElement = null;
        this.showTimeout = null;
        this.hideTimeout = null;
        this.init();
    }
    
    init() {
        this.createTooltip();
    }
    
    /**
     * Create tooltip element
     */
    createTooltip() {
        const tooltipHTML = `
            <div id="appointment-tooltip" class="fixed z-50 hidden pointer-events-none">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 max-w-sm">
                    <div class="flex items-start gap-3 mb-3">
                        <div id="tooltip-status-indicator" class="w-2 h-2 rounded-full mt-2"></div>
                        <div class="flex-1 min-w-0">
                            <h4 id="tooltip-customer-name" class="font-semibold text-gray-900 dark:text-white mb-1 truncate"></h4>
                            <p id="tooltip-service-name" class="text-sm text-gray-600 dark:text-gray-400 truncate"></p>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <span class="material-symbols-outlined text-base text-gray-400">schedule</span>
                            <span id="tooltip-time"></span>
                        </div>
                        
                        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <span class="material-symbols-outlined text-base text-gray-400">person</span>
                            <span id="tooltip-provider-name"></span>
                        </div>
                        
                        <div id="tooltip-status-wrapper" class="flex items-center gap-2">
                            <span id="tooltip-status" class="text-xs px-2 py-1 rounded-full"></span>
                        </div>
                    </div>
                    
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Click for full details</p>
                    </div>
                </div>
                <div class="tooltip-arrow"></div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', tooltipHTML);
        this.tooltip = document.getElementById('appointment-tooltip');
    }
    
    /**
     * Enable tooltip for appointment elements
     */
    enableTooltips(container) {
        const appointmentElements = container.querySelectorAll('[data-appointment-id]');
        
        appointmentElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                const appointmentId = parseInt(element.dataset.appointmentId);
                const appointment = this.scheduler.appointments.find(a => a.id === appointmentId);
                
                if (appointment) {
                    this.show(appointment, element);
                }
            });
            
            element.addEventListener('mouseleave', () => {
                this.scheduleHide();
            });
        });
        
        // Keep tooltip visible when hovering over it
        this.tooltip.addEventListener('mouseenter', () => {
            this.cancelHide();
        });
        
        this.tooltip.addEventListener('mouseleave', () => {
            this.scheduleHide();
        });
    }
    
    /**
     * Show tooltip
     */
    show(appointment, element) {
        // Clear any pending hide
        this.cancelHide();
        
        // Delay showing to avoid flashing on quick mouse moves
        this.showTimeout = setTimeout(() => {
            this.currentElement = element;
            this.populateTooltip(appointment);
            this.positionTooltip(element);
            this.tooltip.classList.remove('hidden');
        }, 300); // 300ms delay before showing
    }
    
    /**
     * Schedule tooltip hide
     */
    scheduleHide() {
        this.hideTimeout = setTimeout(() => {
            this.hide();
        }, 100); // Small delay to allow moving to tooltip
    }
    
    /**
     * Cancel scheduled hide
     */
    cancelHide() {
        if (this.hideTimeout) {
            clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }
    }
    
    /**
     * Hide tooltip
     */
    hide() {
        if (this.showTimeout) {
            clearTimeout(this.showTimeout);
            this.showTimeout = null;
        }
        
        this.tooltip.classList.add('hidden');
        this.currentElement = null;
    }
    
    /**
     * Populate tooltip with appointment data
     */
    populateTooltip(appointment) {
        const startDateTime = DateTime.fromISO(appointment.start);
        const endDateTime = DateTime.fromISO(appointment.end);
        const timeFormat = this.scheduler.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        
        // Status colors
        const statusColors = {
            confirmed: { bg: 'bg-green-100 dark:bg-green-900', text: 'text-green-800 dark:text-green-200', indicator: 'bg-green-500' },
            pending: { bg: 'bg-amber-100 dark:bg-amber-900', text: 'text-amber-800 dark:text-amber-200', indicator: 'bg-amber-500' },
            completed: { bg: 'bg-blue-100 dark:bg-blue-900', text: 'text-blue-800 dark:text-blue-200', indicator: 'bg-blue-500' },
            cancelled: { bg: 'bg-red-100 dark:bg-red-900', text: 'text-red-800 dark:text-red-200', indicator: 'bg-red-500' },
            booked: { bg: 'bg-purple-100 dark:bg-purple-900', text: 'text-purple-800 dark:text-purple-200', indicator: 'bg-purple-500' }
        };
        const statusColor = statusColors[appointment.status] || statusColors.pending;
        
        // Status indicator
        const indicator = this.tooltip.querySelector('#tooltip-status-indicator');
        indicator.className = `w-2 h-2 rounded-full mt-2 ${statusColor.indicator}`;
        
        // Customer & Service
        this.tooltip.querySelector('#tooltip-customer-name').textContent = appointment.name || appointment.customerName || 'Unknown';
        this.tooltip.querySelector('#tooltip-service-name').textContent = appointment.serviceName || 'Service';
        
        // Time
        this.tooltip.querySelector('#tooltip-time').textContent = `${startDateTime.toFormat(timeFormat)} - ${endDateTime.toFormat(timeFormat)}`;
        
        // Provider
        this.tooltip.querySelector('#tooltip-provider-name').textContent = appointment.providerName || 'Provider';
        
        // Status badge
        const statusBadge = this.tooltip.querySelector('#tooltip-status');
        statusBadge.textContent = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
        statusBadge.className = `text-xs px-2 py-1 rounded-full ${statusColor.bg} ${statusColor.text}`;
    }
    
    /**
     * Position tooltip relative to element
     */
    positionTooltip(element) {
        const rect = element.getBoundingClientRect();
        const tooltipRect = this.tooltip.firstElementChild.getBoundingClientRect();
        const padding = 10;
        
        let top = rect.top - tooltipRect.height - padding;
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        
        // Adjust if tooltip goes off-screen
        if (top < padding) {
            // Show below instead
            top = rect.bottom + padding;
        }
        
        if (left < padding) {
            left = padding;
        }
        
        const maxLeft = window.innerWidth - tooltipRect.width - padding;
        if (left > maxLeft) {
            left = maxLeft;
        }
        
        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.left = `${left}px`;
    }
    
    /**
     * Cleanup
     */
    destroy() {
        if (this.showTimeout) clearTimeout(this.showTimeout);
        if (this.hideTimeout) clearTimeout(this.hideTimeout);
        if (this.tooltip) this.tooltip.remove();
    }
}
