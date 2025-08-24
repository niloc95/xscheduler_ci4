<!-- Day View Component -->
<div class="space-y-4">
    <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
        <md-icon class="mr-2 text-blue-500">calendar_today</md-icon>
        <?= date('l, F j, Y', strtotime($current_date)) ?>
    </h3>
    
    <?php
    $dayAppointments = array_filter($appointments, function($apt) use ($current_date) {
        return date('Y-m-d', strtotime($apt['start_time'])) === $current_date;
    });
    
    // Sort appointments by start time
    usort($dayAppointments, function($a, $b) {
        return strtotime($a['start_time']) - strtotime($b['start_time']);
    });
    ?>
    
    <?php if (!empty($dayAppointments)): ?>
        <!-- Time slots with appointments -->
        <div class="space-y-3">
            <?php foreach ($dayAppointments as $appointment): ?>
            <div class="appointment-block p-4 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg border-l-4 border-blue-500 shadow-sm hover:shadow-md transition-all duration-200">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <md-icon class="text-blue-600 dark:text-blue-400 mr-2">schedule</md-icon>
                            <span class="font-semibold text-blue-900 dark:text-blue-100">
                                <?= date('g:i A', strtotime($appointment['start_time'])) ?> - 
                                <?= date('g:i A', strtotime($appointment['end_time'])) ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Service:</span>
                                <span class="text-gray-800 dark:text-gray-200 font-medium">Service #<?= $appointment['service_id'] ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Customer:</span>
                                <span class="text-gray-800 dark:text-gray-200 font-medium">Customer #<?= $appointment['user_id'] ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Provider:</span>
                                <span class="text-gray-800 dark:text-gray-200 font-medium">Provider #<?= $appointment['provider_id'] ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($appointment['notes'])): ?>
                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            <md-icon class="text-sm mr-1">note</md-icon>
                            <?= esc($appointment['notes']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center space-x-2 ml-4">
                        <!-- Status badge -->
                        <span class="status-badge status-<?= $appointment['status'] ?>">
                            <?= ucfirst($appointment['status']) ?>
                        </span>
                        
                        <!-- Action buttons -->
                        <div class="flex space-x-1">
                            <md-icon-button class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit appointment">
                                <md-icon>edit</md-icon>
                            </md-icon-button>
                            <md-icon-button class="text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="Cancel appointment">
                                <md-icon>cancel</md-icon>
                            </md-icon-button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Add appointment button for this day -->
        <div class="mt-6 text-center">
            <md-filled-button onclick="openBookingForm('<?= $current_date ?>')">
                <md-icon slot="icon">add</md-icon>
                Add Appointment for This Day
            </md-filled-button>
        </div>
        
    <?php else: ?>
        <!-- Empty day view -->
        <div class="text-center py-12">
            <md-icon class="text-8xl text-gray-300 dark:text-gray-600 mb-6">event_available</md-icon>
            <h4 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">No Appointments Scheduled</h4>
            <p class="text-gray-500 dark:text-gray-400 mb-6">This day is completely free</p>
            
            <!-- Time slot grid for available times -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 mb-6 max-w-4xl mx-auto">
                <?php 
                // Show available time slots for the day
                for ($hour = 9; $hour < 17; $hour++): 
                    for ($minute = 0; $minute < 60; $minute += 30):
                        $timeSlot = sprintf('%02d:%02d', $hour, $minute);
                        $displayTime = date('g:i A', strtotime($timeSlot));
                ?>
                <button class="time-slot p-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-300 dark:hover:border-blue-600 transition-colors duration-200"
                        onclick="openBookingForm('<?= $current_date ?>', '<?= $timeSlot ?>')">
                    <?= $displayTime ?>
                </button>
                <?php 
                    endfor;
                endfor; 
                ?>
            </div>
            
            <md-filled-button onclick="openBookingForm('<?= $current_date ?>')">
                <md-icon slot="icon">add_circle</md-icon>
                Schedule First Appointment
            </md-filled-button>
        </div>
    <?php endif; ?>
</div>

<style>
.time-slot:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.appointment-block:hover {
    transform: translateX(2px);
}
</style>