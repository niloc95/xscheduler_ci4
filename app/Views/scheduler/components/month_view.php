<!-- Month View Component -->
<div class="grid grid-cols-7 gap-1 mb-4">
    <!-- Days of week header -->
    <?php 
    $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    foreach ($daysOfWeek as $day): 
    ?>
    <div class="p-2 text-center font-medium text-gray-600 dark:text-gray-400 text-sm border-b border-gray-200 dark:border-gray-600">
        <?= $day ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Calendar Grid -->
<div class="grid grid-cols-7 gap-1">
    <?php
    $firstDay = date('Y-m-01', strtotime($current_date));
    $lastDay = date('Y-m-t', strtotime($current_date));
    $startOfWeek = date('Y-m-d', strtotime('sunday last week', strtotime($firstDay)));
    $endOfWeek = date('Y-m-d', strtotime('saturday next week', strtotime($lastDay)));
    
    $currentDate = $startOfWeek;
    while ($currentDate <= $endOfWeek):
        $isCurrentMonth = date('m', strtotime($currentDate)) === date('m', strtotime($current_date));
        $isToday = $currentDate === date('Y-m-d');
        
        // Count appointments for this day
        $dayAppointments = array_filter($appointments, function($apt) use ($currentDate) {
            return date('Y-m-d', strtotime($apt['start_time'])) === $currentDate;
        });
        $appointmentCount = count($dayAppointments);
    ?>
    <div class="calendar-day min-h-[120px] p-2 border border-gray-200 dark:border-gray-600 rounded-lg 
                <?= $isCurrentMonth ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700 opacity-60' ?> 
                <?= $isToday ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' ?> 
                transition-all duration-200 cursor-pointer hover:shadow-md hover:scale-[1.02]"
         onclick="selectDate('<?= $currentDate ?>')">
        
        <!-- Date number -->
        <div class="text-sm font-semibold mb-2 
                    <?= $isCurrentMonth ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' ?> 
                    <?= $isToday ? 'text-blue-600 dark:text-blue-400' : '' ?>">
            <?= date('j', strtotime($currentDate)) ?>
        </div>
        
        <!-- Appointment count badge -->
        <?php if ($appointmentCount > 0): ?>
        <div class="mb-1">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                <?= $appointmentCount ?> apt<?= $appointmentCount !== 1 ? 's' : '' ?>
            </span>
        </div>
        <?php endif; ?>
        
        <!-- Show first few appointments -->
        <?php foreach (array_slice($dayAppointments, 0, 2) as $i => $apt): ?>
        <div class="mb-1">
            <div class="text-xs bg-gray-600 dark:bg-gray-400 text-white dark:text-gray-900 rounded px-1 py-0.5 truncate">
                <?= date('g:iA', strtotime($apt['start_time'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Show "more" indicator if there are additional appointments -->
        <?php if ($appointmentCount > 2): ?>
        <div class="text-xs text-gray-500 dark:text-gray-400">
            +<?= $appointmentCount - 2 ?> more
        </div>
        <?php endif; ?>
    </div>
    <?php 
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    endwhile; 
    ?>
</div>