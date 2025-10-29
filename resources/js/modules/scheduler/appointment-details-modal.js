/**
 * Appointment Details Modal
 * 
 * Displays appointment information in a modal with actions:
 * - View full details
 * - Edit appointment
 * - Reschedule (drag to new slot)
 * - Cancel appointment
 * - Quick hover preview (tooltip)
 */

import { DateTime } from 'luxon';

export class AppointmentDetailsModal {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.modal = null;
        this.currentAppointment = null;
        this.init();
    }
    
    init() {
        this.createModal();
        this.attachEventListeners();
    }
    
    /**
     * Create modal HTML structure
     */
    createModal() {
        const modalHTML = `
            <div id="appointment-details-modal" class="scheduler-modal hidden" role="dialog" aria-labelledby="appointment-modal-title" aria-modal="true">
                <!-- Backdrop -->
                <div class="scheduler-modal-backdrop" data-modal-close></div>
                
                <!-- Centering Wrapper -->
                <div class="scheduler-modal-wrapper">
                    <!-- Modal Panel -->
                    <div class="scheduler-modal-panel max-w-2xl">
                    <!-- Header -->
                    <div class="scheduler-modal-header">
                        <div class="flex items-center gap-3">
                            <div id="appointment-status-indicator" class="w-3 h-3 rounded-full"></div>
                            <h3 id="appointment-modal-title" class="text-xl font-semibold text-gray-900 dark:text-white">
                                Appointment Details
                            </h3>
                        </div>
                        <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                            <span class="material-symbols-outlined text-2xl">close</span>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="scheduler-modal-body">
                        <!-- Loading State -->
                        <div id="details-loading" class="hidden text-center py-8">
                            <div class="loading-spinner mx-auto mb-4"></div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Loading details...</p>
                        </div>
                        
                        <!-- Content -->
                        <div id="details-content" class="space-y-6">
                            <!-- Date & Time Section -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="flex items-start gap-4">
                                    <span class="material-symbols-outlined text-3xl text-blue-600 dark:text-blue-400">event</span>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Date & Time</h4>
                                        <p id="detail-date" class="text-lg font-semibold text-gray-900 dark:text-white mb-1"></p>
                                        <p id="detail-time" class="text-base text-gray-700 dark:text-gray-300"></p>
                                        <p id="detail-duration" class="text-sm text-gray-600 dark:text-gray-400 mt-1"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Section -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">person</span>
                                    Customer Information
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-gray-400">badge</span>
                                        <span id="detail-customer-name" class="text-base text-gray-900 dark:text-white font-medium"></span>
                                    </div>
                                    <div id="detail-customer-email-wrapper" class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-gray-400">mail</span>
                                        <a id="detail-customer-email" href="#" class="text-base text-blue-600 dark:text-blue-400 hover:underline"></a>
                                    </div>
                                    <div id="detail-customer-phone-wrapper" class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-gray-400">phone</span>
                                        <a id="detail-customer-phone" href="#" class="text-base text-gray-900 dark:text-white hover:underline"></a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Service Section -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">medical_services</span>
                                    Service Details
                                </h4>
                                <div class="flex items-center justify-between">
                                    <span id="detail-service-name" class="text-base text-gray-900 dark:text-white font-medium"></span>
                                    <span id="detail-service-price" class="text-lg font-bold text-green-600 dark:text-green-400"></span>
                                </div>
                            </div>
                            
                            <!-- Provider Section -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">person_pin</span>
                                    Provider
                                </h4>
                                <div class="flex items-center gap-3">
                                    <div id="detail-provider-color" class="w-10 h-10 rounded-full"></div>
                                    <span id="detail-provider-name" class="text-base text-gray-900 dark:text-white font-medium"></span>
                                </div>
                            </div>
                            
                            <!-- Notes Section -->
                            <div id="detail-notes-wrapper" class="hidden">
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">note</span>
                                    Notes
                                </h4>
                                <p id="detail-notes" class="text-base text-gray-700 dark:text-gray-300 whitespace-pre-wrap bg-gray-50 dark:bg-gray-700 rounded-lg p-3"></p>
                            </div>
                            
                            <!-- Status Badge -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</span>
                                <span id="detail-status-badge" class="px-3 py-1 text-sm font-medium rounded-full"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Actions -->
                    <div class="scheduler-modal-footer">
                        <button type="button" data-modal-close class="btn btn-secondary">
                            Close
                        </button>
                        <div class="flex gap-2">
                            <button type="button" id="btn-cancel-appointment" class="btn btn-danger">
                                <span class="material-symbols-outlined text-base">cancel</span>
                                Cancel Appointment
                            </button>
                            <button type="button" id="btn-edit-appointment" class="btn btn-primary">
                                <span class="material-symbols-outlined text-base">edit</span>
                                Edit
                            </button>
                        </div>
                    </div>
                </div>
                <!-- End Modal Panel -->
                </div>
                <!-- End Centering Wrapper -->
            </div>
        `;
        
        // Create a wrapper div at the root body level
        const modalContainer = document.createElement('div');
        modalContainer.id = 'scheduler-modal-container';
        modalContainer.style.cssText = 'position: fixed; inset: 0; z-index: 9999; pointer-events: none;';
        modalContainer.innerHTML = modalHTML;
        
        // Ensure it's appended directly to body
        document.body.appendChild(modalContainer);
        this.modal = document.getElementById('appointment-details-modal');
        
        // Enable pointer events on the modal itself
        if (this.modal) {
            this.modal.style.pointerEvents = 'auto';
        }
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Close modal handlers
        this.modal.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });
        
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('scheduler-modal-open')) {
                this.close();
            }
        });
        
        // Edit button
        const editBtn = this.modal.querySelector('#btn-edit-appointment');
        editBtn.addEventListener('click', () => {
            if (this.currentAppointment) {
                this.handleEdit(this.currentAppointment);
            }
        });
        
        // Cancel button
        const cancelBtn = this.modal.querySelector('#btn-cancel-appointment');
        cancelBtn.addEventListener('click', () => {
            if (this.currentAppointment) {
                this.handleCancel(this.currentAppointment);
            }
        });
    }
    
    /**
     * Open modal with appointment data
     */
    open(appointment) {
        console.log('[AppointmentDetailsModal] open() called');
        console.log('[AppointmentDetailsModal] this.modal:', this.modal);
        console.log('[AppointmentDetailsModal] modal element in DOM?', document.getElementById('appointment-details-modal'));
        
        if (!this.modal) {
            console.error('[AppointmentDetailsModal] Modal element not found!');
            return;
        }
        
        try {
            this.currentAppointment = appointment;
            console.log('[AppointmentDetailsModal] Populating details...');
            this.populateDetails(appointment);
            
            console.log('[AppointmentDetailsModal] Removing hidden class...');
            // Show modal with fade-in animation
            this.modal.classList.remove('hidden');
            console.log('[AppointmentDetailsModal] Adding scheduler-modal-open class...');
            requestAnimationFrame(() => {
                this.modal.classList.add('scheduler-modal-open');
                console.log('[AppointmentDetailsModal] Modal should be visible now');
                console.log('[AppointmentDetailsModal] Modal classes:', this.modal.className);
            });
        } catch (error) {
            console.error('[AppointmentDetailsModal] Error opening modal:', error);
        }
    }
    
    /**
     * Close modal
     */
    close() {
        this.modal.classList.remove('scheduler-modal-open');
        setTimeout(() => {
            this.modal.classList.add('hidden');
            this.currentAppointment = null;
        }, 300);
    }
    
    /**
     * Populate modal with appointment details
     */
    populateDetails(appointment) {
        try {
            // Use existing DateTime objects if available, otherwise parse from ISO
            const startDateTime = appointment.startDateTime || DateTime.fromISO(appointment.start_time);
            const endDateTime = appointment.endDateTime || DateTime.fromISO(appointment.end_time);
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
            const indicator = this.modal.querySelector('#appointment-status-indicator');
            indicator.className = `w-3 h-3 rounded-full ${statusColor.indicator}`;
            
            // Date & Time
            this.modal.querySelector('#detail-date').textContent = startDateTime.toFormat('EEEE, MMMM d, yyyy');
            this.modal.querySelector('#detail-time').textContent = `${startDateTime.toFormat(timeFormat)} - ${endDateTime.toFormat(timeFormat)}`;
            
            // Duration
            const duration = appointment.serviceDuration || Math.round(endDateTime.diff(startDateTime, 'minutes').minutes);
            this.modal.querySelector('#detail-duration').textContent = `Duration: ${duration} minutes`;
            
            // Customer
            this.modal.querySelector('#detail-customer-name').textContent = appointment.name || appointment.customerName || 'Unknown';
            
            if (appointment.email) {
                this.modal.querySelector('#detail-customer-email').textContent = appointment.email;
                this.modal.querySelector('#detail-customer-email').href = `mailto:${appointment.email}`;
                this.modal.querySelector('#detail-customer-email-wrapper').classList.remove('hidden');
            } else {
                this.modal.querySelector('#detail-customer-email-wrapper').classList.add('hidden');
            }
            
            if (appointment.phone) {
                this.modal.querySelector('#detail-customer-phone').textContent = appointment.phone;
                this.modal.querySelector('#detail-customer-phone').href = `tel:${appointment.phone}`;
                this.modal.querySelector('#detail-customer-phone-wrapper').classList.remove('hidden');
            } else {
                this.modal.querySelector('#detail-customer-phone-wrapper').classList.add('hidden');
            }
            
            // Service
            this.modal.querySelector('#detail-service-name').textContent = appointment.serviceName || 'Service';
            if (appointment.servicePrice) {
                this.modal.querySelector('#detail-service-price').textContent = `$${parseFloat(appointment.servicePrice).toFixed(2)}`;
            } else {
                this.modal.querySelector('#detail-service-price').textContent = '';
            }
            
            // Provider
            const providerColor = appointment.providerColor || '#3B82F6';
            this.modal.querySelector('#detail-provider-color').style.backgroundColor = providerColor;
            this.modal.querySelector('#detail-provider-name').textContent = appointment.providerName || 'Provider';
            
            // Notes
            if (appointment.notes && appointment.notes.trim()) {
                this.modal.querySelector('#detail-notes').textContent = appointment.notes;
                this.modal.querySelector('#detail-notes-wrapper').classList.remove('hidden');
            } else {
                this.modal.querySelector('#detail-notes-wrapper').classList.add('hidden');
            }
            
            // Status badge
            const statusBadge = this.modal.querySelector('#detail-status-badge');
            statusBadge.textContent = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
            statusBadge.className = `px-3 py-1 text-sm font-medium rounded-full ${statusColor.bg} ${statusColor.text}`;
            
            // Hide cancel button if already cancelled
            const cancelBtn = this.modal.querySelector('#btn-cancel-appointment');
            if (appointment.status === 'cancelled' || appointment.status === 'completed') {
                cancelBtn.classList.add('hidden');
            } else {
                cancelBtn.classList.remove('hidden');
            }
        } catch (error) {
            console.error('[AppointmentDetailsModal] Error populating details:', error);
            throw error;
        }
    }
    
    /**
     * Handle edit action
     */
    handleEdit(appointment) {
        this.close();
        // Navigate to edit page
        window.location.href = `/appointments/edit/${appointment.id}`;
    }
    
    /**
     * Handle cancel action
     */
    async handleCancel(appointment) {
        const startDateTime = appointment.startDateTime || DateTime.fromISO(appointment.start_time);
        const confirmed = confirm(`Are you sure you want to cancel this appointment?\n\nCustomer: ${appointment.name || appointment.customerName || 'Unknown'}\nDate: ${startDateTime.toFormat('MMMM d, yyyy h:mm a')}`);
        
        if (!confirmed) return;
        
        try {
            const response = await fetch(`/api/appointments/${appointment.id}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'cancelled'
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to cancel appointment');
            }
            
            // Show success message
            if (this.scheduler.dragDropManager) {
                this.scheduler.dragDropManager.showToast('Appointment cancelled successfully', 'success');
            }
            
            // Refresh calendar
            this.close();
            await this.scheduler.loadAppointments();
            this.scheduler.render();
            
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            if (this.scheduler.dragDropManager) {
                this.scheduler.dragDropManager.showToast('Failed to cancel appointment', 'error');
            } else {
                alert('Failed to cancel appointment. Please try again.');
            }
        }
    }
}
