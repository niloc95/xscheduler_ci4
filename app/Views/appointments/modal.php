<?php
/**
 * Appointment Details Modal
 * 
 * Reusable modal component for displaying appointment details.
 * Can be included in any view that needs to show appointment information.
 * 
 * Features:
 * - View appointment details (customer, service, provider, date/time)
 * - Role-based action buttons (edit, complete, cancel)
 * - Loading state
 * - Dark mode support
 * 
 * JavaScript Integration:
 * - Modal opened via: document.getElementById('appointment-details-modal').classList.remove('hidden')
 * - Modal closed via: closeAppointmentModal()
 * - Data populated via JS after loading appointment details from API
 */
?>

<!-- Appointment Details Modal -->
<div id="appointment-details-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="closeAppointmentModal()"></div>
    
    <!-- Modal Panel -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full border border-gray-200 dark:border-gray-700">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="modal-title">
                    Appointment Details
                </h3>
                <button type="button" onclick="closeAppointmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-4 space-y-4" id="appointment-modal-content">
                <!-- Loading State -->
                <div id="modal-loading" class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>

                <!-- Content (populated by JS) -->
                <div id="modal-data" class="hidden space-y-4">
                    <!-- Customer Info -->
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-3xl">person</span>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-customer-name"></h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400" id="modal-customer-email"></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400" id="modal-customer-phone"></p>
                        </div>
                        <div>
                            <span id="modal-status" class="px-3 py-1 text-xs font-medium rounded-full"></span>
                        </div>
                    </div>

                    <!-- Appointment Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Service</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="modal-service"></p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Provider</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="modal-provider"></p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date & Time</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="modal-datetime"></p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Duration</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="modal-duration"></p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="modal-price"></p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Location</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" id="modal-location"></p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700" id="modal-notes-section">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</label>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300" id="modal-notes"></p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between" id="modal-footer">
                <div class="flex items-center gap-2">
                    <!-- Action buttons (populated by JS based on role) -->
                    <button type="button" id="btn-edit-appointment" class="hidden px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <span class="material-symbols-outlined text-base align-middle mr-1">edit</span>
                        Edit
                    </button>
                    <button type="button" id="btn-complete-appointment" class="hidden px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                        <span class="material-symbols-outlined text-base align-middle mr-1">check_circle</span>
                        Complete
                    </button>
                    <button type="button" id="btn-cancel-appointment" class="hidden px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        <span class="material-symbols-outlined text-base align-middle mr-1">cancel</span>
                        Cancel
                    </button>
                </div>
                <button type="button" onclick="closeAppointmentModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
