<?php
/**
 * Dashboard Booking System Status Component (Owner Only)
 * 
 * Displays high-level operational status of booking system.
 * 
 * Props:
 * - bookingStatus: Array with system status
 *   [
 *     'booking_enabled' => bool,
 *     'confirmation_enabled' => bool,
 *     'email_enabled' => bool,
 *     'whatsapp_enabled' => bool,
 *     'booking_url' => string
 *   ]
 */

$bookingStatus = $bookingStatus ?? null;

if (!$bookingStatus) {
    return;
}
?>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden" data-booking-status>
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Booking System Status
            </h3>
            <a href="<?= base_url('/settings#booking') ?>" 
               class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center">
                Configure
                <span class="material-symbols-outlined ml-1 text-sm">settings</span>
            </a>
        </div>
    </div>
    
    <div class="p-6 space-y-4">
        <!-- Booking Page Status -->
        <div class="flex items-center justify-between p-4 rounded-lg 
                    <?= $bookingStatus['booking_enabled'] 
                        ? 'bg-green-50 dark:bg-green-900/20' 
                        : 'bg-red-50 dark:bg-red-900/20' ?>">
            <div class="flex items-center space-x-3">
                <span class="material-symbols-outlined text-2xl 
                            <?= $bookingStatus['booking_enabled'] 
                                ? 'text-green-600 dark:text-green-400' 
                                : 'text-red-600 dark:text-red-400' ?>">
                    <?= $bookingStatus['booking_enabled'] ? 'check_circle' : 'cancel' ?>
                </span>
                <div>
                    <p class="text-sm font-medium 
                              <?= $bookingStatus['booking_enabled'] 
                                  ? 'text-green-900 dark:text-green-100' 
                                  : 'text-red-900 dark:text-red-100' ?>">
                        Booking Page
                    </p>
                    <p class="text-xs 
                              <?= $bookingStatus['booking_enabled'] 
                                  ? 'text-green-700 dark:text-green-300' 
                                  : 'text-red-700 dark:text-red-300' ?>">
                        <?= $bookingStatus['booking_enabled'] ? 'Active' : 'Disabled' ?>
                    </p>
                </div>
            </div>
            <?php if ($bookingStatus['booking_enabled'] && isset($bookingStatus['booking_url'])): ?>
            <a href="<?= esc($bookingStatus['booking_url']) ?>" 
               target="_blank"
               class="text-xs text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 
                      flex items-center px-3 py-1.5 bg-white dark:bg-gray-800 rounded-md border border-green-200 dark:border-green-700">
                View Page
                <span class="material-symbols-outlined ml-1 text-sm">open_in_new</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Notification Channels -->
        <div class="space-y-2">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                Notification Channels
            </p>
            
            <div class="grid grid-cols-2 gap-3">
                <!-- Email Status -->
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900">
                    <div class="flex items-center space-x-2">
                        <span class="material-symbols-outlined text-sm text-gray-600 dark:text-gray-400">
                            email
                        </span>
                        <span class="text-sm text-gray-900 dark:text-white">Email</span>
                    </div>
                    <span class="material-symbols-outlined text-sm 
                                <?= $bookingStatus['email_enabled'] 
                                    ? 'text-green-600 dark:text-green-400' 
                                    : 'text-gray-400 dark:text-gray-500' ?>">
                        <?= $bookingStatus['email_enabled'] ? 'check_circle' : 'cancel' ?>
                    </span>
                </div>
                
                <!-- WhatsApp Status -->
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900">
                    <div class="flex items-center space-x-2">
                        <span class="material-symbols-outlined text-sm text-gray-600 dark:text-gray-400">
                            chat
                        </span>
                        <span class="text-sm text-gray-900 dark:text-white">WhatsApp</span>
                    </div>
                    <span class="material-symbols-outlined text-sm 
                                <?= $bookingStatus['whatsapp_enabled'] 
                                    ? 'text-green-600 dark:text-green-400' 
                                    : 'text-gray-400 dark:text-gray-500' ?>">
                        <?= $bookingStatus['whatsapp_enabled'] ? 'check_circle' : 'cancel' ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Confirmation Mode -->
        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900">
            <div class="flex items-center space-x-2">
                <span class="material-symbols-outlined text-sm text-gray-600 dark:text-gray-400">
                    task_alt
                </span>
                <span class="text-sm text-gray-900 dark:text-white">Manual Confirmation</span>
            </div>
            <span class="text-xs font-medium px-2 py-1 rounded 
                        <?= $bookingStatus['confirmation_enabled'] 
                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' 
                            : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' ?>">
                <?= $bookingStatus['confirmation_enabled'] ? 'Required' : 'Auto' ?>
            </span>
        </div>
    </div>
</div>
