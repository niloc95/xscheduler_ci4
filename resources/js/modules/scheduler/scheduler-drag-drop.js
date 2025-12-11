/**
 * Custom Scheduler - Drag & Drop Module
 * 
 * Handles drag-and-drop functionality for rescheduling appointments.
 * Includes validation, visual feedback, and API integration.
 */

import { DateTime } from 'luxon';

export class DragDropManager {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.draggedAppointment = null;
        this.dragOverSlot = null;
        this.originalPosition = null;
    }

    /**
     * Enable drag-and-drop for appointment elements
     */
    enableDragDrop(container) {
        // Make appointments draggable
        container.querySelectorAll('.appointment-block, .inline-appointment, .appointment-card').forEach(el => {
            if (!el.dataset.appointmentId) return;

            el.setAttribute('draggable', 'true');
            el.classList.add('cursor-move');

            // Drag start
            el.addEventListener('dragstart', (e) => {
                this.handleDragStart(e, el);
            });

            // Drag end
            el.addEventListener('dragend', (e) => {
                this.handleDragEnd(e);
            });
        });

        // Make time slots and provider lanes drop targets
        // Include both top-level cells with [data-date] and provider lanes within them
        const dropTargets = container.querySelectorAll('[data-date][data-time], [data-provider-lane], .time-slot, .scheduler-day-cell');
        dropTargets.forEach(slot => {
            // Drag over
            slot.addEventListener('dragover', (e) => {
                e.preventDefault();
                this.handleDragOver(e, slot);
            });

            // Drag leave
            slot.addEventListener('dragleave', (e) => {
                this.handleDragLeave(e, slot);
            });

            // Drop
            slot.addEventListener('drop', (e) => {
                e.preventDefault();
                this.handleDrop(e, slot);
            });
        });
    }

    handleDragStart(e, element) {
        const appointmentId = parseInt(element.dataset.appointmentId);
        this.draggedAppointment = this.scheduler.appointments.find(a => a.id === appointmentId);

        if (!this.draggedAppointment) {
            e.preventDefault();
            return;
        }

        // Store original position for potential revert
        this.originalPosition = {
            date: this.draggedAppointment.startDateTime.toISODate(),
            time: this.draggedAppointment.startDateTime.toFormat('HH:mm')
        };

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', appointmentId);

        // Add dragging class with delay (to allow drag image to be created)
        setTimeout(() => {
            element.classList.add('opacity-50', 'scale-95');
        }, 0);
    }

    handleDragOver(e, slot) {
        if (!this.draggedAppointment) return;

        e.dataTransfer.dropEffect = 'move';
        this.dragOverSlot = slot;

        // Add visual feedback
        slot.classList.add('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
    }

    handleDragLeave(e, slot) {
        // Only remove highlight if we're actually leaving the slot
        if (e.relatedTarget && slot.contains(e.relatedTarget)) {
            return;
        }

        slot.classList.remove('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
    }

    async handleDrop(e, slot) {
        if (!this.draggedAppointment) return;

        // Get target date and time - check parent elements for provider lane drops
        let targetDate = slot.dataset.date;
        let targetTime = slot.dataset.time;
        
        // If dropped on a provider lane, get date/time from parent cell
        if (!targetDate && slot.dataset.providerLane) {
            const parentCell = slot.closest('[data-date]');
            if (parentCell) {
                targetDate = parentCell.dataset.date;
                targetTime = parentCell.dataset.time;
            }
        }
        
        // Fallback to hour data attribute if time is not set
        if (!targetTime && slot.dataset.hour) {
            targetTime = `${slot.dataset.hour}:00`;
        }

        if (!targetDate) {
            console.error('❌ Drop target has no date');
            this.resetDrag();
            return;
        }

        // Calculate new start datetime
        let newStartDateTime;
        if (targetTime) {
            // Week/Day view with time slots
            newStartDateTime = DateTime.fromISO(`${targetDate}T${targetTime}`, { 
                zone: this.scheduler.options.timezone 
            });
        } else {
            // Month view - keep original time, just change date
            const originalTime = this.draggedAppointment.startDateTime.toFormat('HH:mm');
            newStartDateTime = DateTime.fromISO(`${targetDate}T${originalTime}`, { 
                zone: this.scheduler.options.timezone 
            });
        }

        // Calculate duration
        const duration = this.draggedAppointment.endDateTime.diff(
            this.draggedAppointment.startDateTime, 
            'minutes'
        ).minutes;

        const newEndDateTime = newStartDateTime.plus({ minutes: duration });

        // Validate the move
        const validation = this.validateReschedule(newStartDateTime, newEndDateTime);
        if (!validation.valid) {
            this.showError(validation.message);
            this.resetDrag();
            return;
        }

        // Show confirmation dialog
        const confirmed = await this.confirmReschedule(
            this.draggedAppointment,
            newStartDateTime,
            newEndDateTime
        );

        if (!confirmed) {
            this.resetDrag();
            return;
        }

        // Perform the reschedule
        await this.rescheduleAppointment(
            this.draggedAppointment.id,
            newStartDateTime,
            newEndDateTime
        );

        this.resetDrag();
    }

    handleDragEnd(e) {
        // Remove dragging styles
        e.target.classList.remove('opacity-50', 'scale-95');
        
        // Clean up any remaining highlights
        document.querySelectorAll('.ring-blue-500').forEach(el => {
            el.classList.remove('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
        });
    }

    validateReschedule(newStart, newEnd) {
        // Check if in the past
        const now = DateTime.now().setZone(this.scheduler.options.timezone);
        if (newStart < now) {
            return {
                valid: false,
                message: 'Cannot schedule appointments in the past'
            };
        }

        // Check business hours
        const config = this.scheduler.calendarConfig;
        if (config?.businessHours) {
            const [startHour] = config.businessHours.startTime.split(':').map(Number);
            const [endHour] = config.businessHours.endTime.split(':').map(Number);
            
            if (newStart.hour < startHour || newEnd.hour > endHour) {
                return {
                    valid: false,
                    message: `Appointments must be within business hours (${config.businessHours.startTime} - ${config.businessHours.endTime})`
                };
            }
        }

        // Check for conflicts (excluding the current appointment)
        const conflicts = this.scheduler.appointments.filter(apt => {
            if (apt.id === this.draggedAppointment.id) return false;
            if (apt.providerId !== this.draggedAppointment.providerId) return false;

            const aptStart = apt.startDateTime;
            const aptEnd = apt.endDateTime;

            // Check for overlap
            return (newStart < aptEnd && newEnd > aptStart);
        });

        if (conflicts.length > 0) {
            return {
                valid: false,
                message: 'This time slot conflicts with another appointment for this provider'
            };
        }

        return { valid: true };
    }

    async confirmReschedule(appointment, newStart, newEnd) {
        const customerName = appointment.customerName || 'this customer';
        const oldTime = `${appointment.startDateTime.toFormat('EEE, MMM d')} at ${appointment.startDateTime.toFormat('h:mm a')}`;
        const newTime = `${newStart.toFormat('EEE, MMM d')} at ${newStart.toFormat('h:mm a')}`;

        return confirm(
            `Reschedule appointment for ${customerName}?\n\n` +
            `From: ${oldTime}\n` +
            `To: ${newTime}\n\n` +
            `This will update the appointment and notify the customer.`
        );
    }

    async rescheduleAppointment(appointmentId, newStart, newEnd) {
        try {
            // Show loading indicator
            this.showLoading();

            const response = await fetch(`/api/appointments/${appointmentId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    start: newStart.toISO(),
                    end: newEnd.toISO(),
                    date: newStart.toISODate(),
                    time: newStart.toFormat('HH:mm')
                })
            });

            if (!response.ok) {
                throw new Error('Failed to reschedule appointment');
            }

            const result = await response.json();

            // Reload appointments and re-render
            await this.scheduler.loadAppointments();
            this.scheduler.render();

            this.showSuccess('Appointment rescheduled successfully');

            if (typeof window !== 'undefined') {
                const detail = {
                    source: 'drag-drop',
                    action: 'reschedule',
                    appointmentId
                };

                if (typeof window.emitAppointmentsUpdated === 'function') {
                    window.emitAppointmentsUpdated(detail);
                } else {
                    window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
                }
            }
        } catch (error) {
            console.error('❌ Reschedule failed:', error);
            this.showError('Failed to reschedule appointment. Please try again.');
            
            // Reload to ensure data is consistent
            await this.scheduler.loadAppointments();
            this.scheduler.render();
        } finally {
            this.hideLoading();
        }
    }

    resetDrag() {
        this.draggedAppointment = null;
        this.dragOverSlot = null;
        this.originalPosition = null;

        // Remove any remaining drag highlights
        document.querySelectorAll('.ring-blue-500').forEach(el => {
            el.classList.remove('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
        });
    }

    showLoading() {
        // Create or show loading overlay
        let loader = document.getElementById('scheduler-loading');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'scheduler-loading';
            loader.className = 'fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70 flex items-center justify-center z-50';
            loader.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-xl">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p class="mt-4 text-gray-700 dark:text-gray-300">Rescheduling...</p>
                </div>
            `;
            document.body.appendChild(loader);
        } else {
            loader.classList.remove('hidden');
        }
    }

    hideLoading() {
        const loader = document.getElementById('scheduler-loading');
        if (loader) {
            loader.classList.add('hidden');
        }
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-600' : type === 'success' ? 'bg-green-600' : 'bg-blue-600';
        
        toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-in`;
        toast.textContent = message;
        
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('animate-slide-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
