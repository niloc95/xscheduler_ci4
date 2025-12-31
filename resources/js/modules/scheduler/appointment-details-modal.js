/**
 * Appointment Details Modal
 * 
 * PURPOSE: Quick view and status management for appointments from calendar.
 * This modal is designed for QUICK ACTIONS only:
 * - View appointment details
 * - Quick status changes (pending → confirmed, etc.)
 * - Cancel appointment
 * - Navigate to full edit page
 * 
 * For FULL EDITING (customer info, dates, services), use the Edit page.
 * The "Edit" button redirects to /appointments/edit/:hash for comprehensive changes.
 * 
 * UPDATE FLOWS:
 * - Status changes → PATCH /api/appointments/:id/status (API)
 * - Full edits → Navigate to edit page → PUT /appointments/update/:hash (Controller)
 */

import { DateTime } from 'luxon';

function getBaseUrl() {
    const raw = typeof window !== 'undefined' ? window.__BASE_URL__ : '';
    if (!raw) return '';
    return String(raw).replace(/\/+$/, '');
}

function withBaseUrl(path) {
    const base = getBaseUrl();
    if (!base) return path;
    if (!path) return base + '/';
    if (path.startsWith('/')) return base + path;
    return base + '/' + path;
}

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
                <div class="scheduler-modal-backdrop" data-modal-close></div>
                <div class="scheduler-modal-dialog">
                    <div class="scheduler-modal-panel">
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
                        <div id="details-content" class="space-y-4">
                            <!-- Date & Time Section -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-2xl text-blue-600 dark:text-blue-400">event</span>
                                    <div class="flex-1">
                                        <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date & Time</h4>
                                        <p id="detail-date" class="text-base font-semibold text-gray-900 dark:text-white mb-1"></p>
                                        <p id="detail-time" class="text-sm text-gray-700 dark:text-gray-300"></p>
                                        <p id="detail-duration" class="text-xs text-gray-600 dark:text-gray-400 mt-1"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Section -->
                            <div>
                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">person</span>
                                    Customer Information
                                </h4>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm text-gray-400">badge</span>
                                        <span id="detail-customer-name" class="text-sm text-gray-900 dark:text-white font-medium"></span>
                                    </div>
                                    <div id="detail-customer-email-wrapper" class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm text-gray-400">mail</span>
                                        <a id="detail-customer-email" href="#" class="text-sm text-blue-600 dark:text-blue-400 hover:underline"></a>
                                    </div>
                                    <div id="detail-customer-phone-wrapper" class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm text-gray-400">phone</span>
                                        <a id="detail-customer-phone" href="#" class="text-sm text-gray-900 dark:text-white hover:underline"></a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Service & Provider in Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Service Section -->
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">medical_services</span>
                                        Service
                                    </h4>
                                    <p id="detail-service-name" class="text-sm text-gray-900 dark:text-white font-medium mb-1"></p>
                                    <p id="detail-service-price" class="text-base font-bold text-green-600 dark:text-green-400"></p>
                                </div>
                                
                                <!-- Provider Section -->
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">person_pin</span>
                                        Provider
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        <div id="detail-provider-color" class="w-8 h-8 rounded-full flex-shrink-0"></div>
                                        <span id="detail-provider-name" class="text-sm text-gray-900 dark:text-white font-medium"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notes Section -->
                            <div id="detail-notes-wrapper" class="hidden">
                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">note</span>
                                    Notes
                                </h4>
                                <p id="detail-notes" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap bg-gray-50 dark:bg-gray-700 rounded-lg p-2"></p>
                            </div>
                            
                            <!-- Status Management -->
                            <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</span>
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <select id="detail-status-select" class="appearance-none text-xs font-medium rounded-full pl-3 pr-8 py-1.5 border-0 focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                            <option value="no-show">No Show</option>
                                        </select>
                                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-sm pointer-events-none">expand_more</span>
                                    </div>
                                    <button type="button" id="btn-save-status" class="hidden px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Actions -->
                    <div class="scheduler-modal-footer">
                        <div class="flex gap-2">
                            <button type="button" data-modal-close class="btn btn-secondary">
                                Close
                            </button>
                            <button type="button" id="btn-whatsapp-appointment" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-green-600 border border-green-600 rounded-lg hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 transition-colors" title="Send WhatsApp message">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </button>
                            <button type="button" id="btn-cancel-appointment" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 hover:border-red-400 dark:bg-gray-800 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/20 transition-colors">
                                <span class="material-symbols-outlined text-base">event_busy</span>
                                Cancel Appointment
                            </button>
                        </div>
                        <button type="button" id="btn-edit-appointment" class="btn btn-primary">
                            <span class="material-symbols-outlined text-base">edit</span>
                            Edit Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('appointment-details-modal');
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
        
        // WhatsApp button
        const whatsappBtn = this.modal.querySelector('#btn-whatsapp-appointment');
        whatsappBtn.addEventListener('click', () => {
            if (this.currentAppointment) {
                this.handleWhatsApp(this.currentAppointment);
            }
        });
        
        // Status change handler
        const statusSelect = this.modal.querySelector('#detail-status-select');
        const saveStatusBtn = this.modal.querySelector('#btn-save-status');
        
        statusSelect.addEventListener('change', () => {
            // Show save button when status changes
            if (this.currentAppointment && statusSelect.value !== this.currentAppointment.status) {
                saveStatusBtn.classList.remove('hidden');
                
                // Update status select styling based on new status
                this.updateStatusSelectStyling(statusSelect, statusSelect.value);
            } else {
                saveStatusBtn.classList.add('hidden');
                // Restore original styling
                if (this.currentAppointment) {
                    this.updateStatusSelectStyling(statusSelect, this.currentAppointment.status);
                }
            }
        });
        
        // Save status button
        saveStatusBtn.addEventListener('click', async () => {
            if (this.currentAppointment) {
                await this.handleStatusChange(this.currentAppointment, statusSelect.value);
            }
        });
    }
    
    /**
     * Open modal with appointment data
     */
    open(appointment) {
        if (!this.modal) {
            console.error('[AppointmentDetailsModal] Modal element not found!');
            return;
        }
        
        try {
            this.currentAppointment = appointment;
            this.populateDetails(appointment);
            
            // Show modal with fade-in animation
            this.modal.classList.remove('hidden');
            
            // Prevent body scroll when modal is open
            document.body.style.overflow = 'hidden';
            
            requestAnimationFrame(() => {
                this.modal.classList.add('scheduler-modal-open');
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
        
        // Restore body scroll
        document.body.style.overflow = '';
        
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
            
            // Status select
            const statusSelect = this.modal.querySelector('#detail-status-select');
            statusSelect.value = appointment.status;
            this.updateStatusSelectStyling(statusSelect, appointment.status);
            
            // Hide save button initially
            this.modal.querySelector('#btn-save-status').classList.add('hidden');
            
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
     * Update status select styling based on status value
     */
    updateStatusSelectStyling(selectElement, status) {
        const statusColors = {
            confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'no-show': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
        };
        
        const colorClass = statusColors[status] || statusColors.pending;
        selectElement.className = `appearance-none text-xs font-medium rounded-full pl-3 pr-8 py-1.5 border-0 focus:ring-2 focus:ring-blue-500 cursor-pointer ${colorClass}`;
    }
    
    /**
     * Handle status change
     */
    async handleStatusChange(appointment, newStatus) {
        const saveBtn = this.modal.querySelector('#btn-save-status');
        const statusSelect = this.modal.querySelector('#detail-status-select');
        const originalStatus = appointment.status;
        
        // Show loading state
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
        try {
            const response = await fetch(withBaseUrl(`/api/appointments/${appointment.id}/status`), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: newStatus
                })
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error?.message || 'Failed to update status');
            }
            
            // Update current appointment status
            appointment.status = newStatus;
            this.currentAppointment.status = newStatus;
            
            // Show success message
            if (this.scheduler.dragDropManager) {
                this.scheduler.dragDropManager.showToast('Status updated successfully', 'success');
            }
            
            // Hide save button
            saveBtn.classList.add('hidden');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
            
            // Refresh calendar to show updated status
            await this.scheduler.loadAppointments();
            this.scheduler.render();

            if (typeof window !== 'undefined') {
                const detail = {
                    source: 'status-change',
                    appointmentId: appointment.id,
                    status: newStatus
                };

                if (typeof window.emitAppointmentsUpdated === 'function') {
                    window.emitAppointmentsUpdated(detail);
                } else {
                    window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
                }
            }
            
        } catch (error) {
            console.error('Error updating status:', error);
            
            // Revert select to original status
            statusSelect.value = originalStatus;
            this.updateStatusSelectStyling(statusSelect, originalStatus);
            
            // Reset save button
            saveBtn.classList.add('hidden');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
            
            if (this.scheduler.dragDropManager) {
                this.scheduler.dragDropManager.showToast(error.message || 'Failed to update status', 'error');
            } else {
                alert(error.message || 'Failed to update status. Please try again.');
            }
        }
    }
    
    /**
     * Handle edit action
     */
    handleEdit(appointment) {
        this.close();
        // Navigate to edit page using hash for security
        const identifier = appointment.hash || appointment.id;
        window.location.href = withBaseUrl(`/appointments/edit/${identifier}`);
    }
    
    /**
     * Handle cancel action
     */
    async handleCancel(appointment) {
        const startDateTime = appointment.startDateTime || DateTime.fromISO(appointment.start_time);
        const confirmed = confirm(`Are you sure you want to cancel this appointment?\n\nCustomer: ${appointment.name || appointment.customerName || 'Unknown'}\nDate: ${startDateTime.toFormat('MMMM d, yyyy h:mm a')}`);
        
        if (!confirmed) return;
        
        try {
            const response = await fetch(withBaseUrl(`/api/appointments/${appointment.id}/status`), {
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

            if (typeof window !== 'undefined') {
                const detail = {
                    source: 'status-change',
                    appointmentId: appointment.id,
                    status: 'cancelled'
                };

                if (typeof window.emitAppointmentsUpdated === 'function') {
                    window.emitAppointmentsUpdated(detail);
                } else {
                    window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
                }
            }
            
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            if (this.scheduler.dragDropManager) {
                this.scheduler.dragDropManager.showToast('Failed to cancel appointment', 'error');
            } else {
                alert('Failed to cancel appointment. Please try again.');
            }
        }
    }
    
    /**
     * Handle WhatsApp action - opens WhatsApp with pre-filled message
     */
    handleWhatsApp(appointment) {
        // Get phone number from appointment data
        const phone = appointment.customer_phone || appointment.customerPhone || appointment.phone || '';
        
        if (!phone) {
            if (this.scheduler.dragDropManager) {
                this.scheduler.dragDropManager.showToast('No phone number available for this customer', 'error');
            } else {
                alert('No phone number available for this customer.');
            }
            return;
        }
        
        // Clean up phone number (remove spaces, dashes)
        const cleanPhone = phone.replace(/[\s\-\(\)]/g, '').replace(/^\+/, '');
        
        // Get appointment details for message
        const customerName = appointment.name || appointment.customerName || 'Customer';
        const serviceName = appointment.service_name || appointment.serviceName || appointment.service || 'your service';
        const providerName = appointment.provider_name || appointment.providerName || '';
        
        // Format date/time
        const startDateTime = appointment.startDateTime || DateTime.fromISO(appointment.start_time);
        const dateStr = startDateTime.toFormat('EEEE, MMMM d, yyyy');
        const timeStr = startDateTime.toFormat('h:mm a');
        
        // Get business name from settings if available
        const businessName = window.__BUSINESS_NAME__ || 'our business';
        
        // Build WhatsApp message based on status
        let message = '';
        const status = appointment.status || 'confirmed';
        
        if (status === 'confirmed' || status === 'pending' || status === 'booked') {
            message = `✅ *Appointment Confirmed*\n\n`;
            message += `Hi ${customerName}!\n\n`;
            message += `Your appointment has been confirmed:\n\n`;
            message += `📅 *Date:* ${dateStr}\n`;
            message += `🕐 *Time:* ${timeStr}\n`;
            message += `💼 *Service:* ${serviceName}\n`;
            if (providerName) {
                message += `👤 *With:* ${providerName}\n`;
            }
            message += `\nThank you for booking with ${businessName}!\n`;
            message += `\n_Reply to this message if you need to make any changes._`;
        } else if (status === 'cancelled') {
            message = `❌ *Appointment Cancelled*\n\n`;
            message += `Hi ${customerName},\n\n`;
            message += `Your appointment has been cancelled:\n\n`;
            message += `📅 *Date:* ${dateStr}\n`;
            message += `🕐 *Time:* ${timeStr}\n`;
            message += `💼 *Service:* ${serviceName}\n`;
            message += `\nWe hope to see you again soon!\n`;
            message += `\n_${businessName}_`;
        } else {
            message = `Hi ${customerName}! This is a message regarding your appointment on ${dateStr} at ${timeStr}. - ${businessName}`;
        }
        
        // Build WhatsApp URL
        const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
        
        // Open WhatsApp in new tab
        window.open(waUrl, '_blank');
        
        // Show feedback
        if (this.scheduler.dragDropManager) {
            this.scheduler.dragDropManager.showToast('Opening WhatsApp...', 'info');
        }
    }
}
