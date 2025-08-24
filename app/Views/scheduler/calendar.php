<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="main-content p-4">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 material-shadow rounded-lg p-4 mb-6 transition-colors duration-300">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">
                    <md-icon class="mr-2 text-blue-500">event</md-icon>
                    Scheduler
                </h1>
                <p class="text-gray-600 dark:text-gray-400 transition-colors duration-300">Manage appointments and calendar</p>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- View Toggle -->
                <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                    <button class="view-toggle px-3 py-1 rounded text-sm font-medium <?= $view_type === 'day' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600' ?>" data-view="day">Day</button>
                    <button class="view-toggle px-3 py-1 rounded text-sm font-medium <?= $view_type === 'week' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600' ?>" data-view="week">Week</button>
                    <button class="view-toggle px-3 py-1 rounded text-sm font-medium <?= $view_type === 'month' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600' ?>" data-view="month">Month</button>
                </div>
                
                <!-- New Appointment Button -->
                <md-filled-button onclick="openBookingForm()">
                    <md-icon slot="icon">add</md-icon>
                    New Appointment
                </md-filled-button>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <!-- Error Message -->
    <md-outlined-card class="p-4 mb-6 border-red-200 bg-red-50 dark:bg-red-900/20">
        <div class="flex items-center text-red-700 dark:text-red-400">
            <md-icon class="mr-2">error</md-icon>
            <span><?= esc($error) ?></span>
        </div>
    </md-outlined-card>
    <?php endif; ?>

    <!-- Calendar View -->
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <!-- Calendar -->
        <div class="xl:col-span-3">
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        <?= ucfirst($view_type) ?> View - <?= date('F Y', strtotime($current_date)) ?>
                    </h2>
                    
                    <!-- Date Navigation -->
                    <div class="flex items-center space-x-2">
                        <md-icon-button onclick="navigateDate('prev')">
                            <md-icon>chevron_left</md-icon>
                        </md-icon-button>
                        <md-filled-button onclick="goToToday()">Today</md-filled-button>
                        <md-icon-button onclick="navigateDate('next')">
                            <md-icon>chevron_right</md-icon>
                        </md-icon-button>
                    </div>
                </div>

                <!-- Calendar Content -->
                <?php if ($view_type === 'month'): ?>
                    <!-- Month View -->
                    <div class="grid grid-cols-7 gap-1 mb-4">
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
                        <div class="p-2 text-center font-medium text-gray-600 dark:text-gray-400 text-sm border-b">
                            <?= $day ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Calendar Grid -->
                    <div class="grid grid-cols-7 gap-1">
                        <?php
                        $firstDay = date('Y-m-01', strtotime($current_date));
                        $startOfWeek = date('Y-m-d', strtotime('sunday last week', strtotime($firstDay)));
                        $endOfWeek = date('Y-m-d', strtotime($startOfWeek . ' +41 days'));
                        
                        $currentDate = $startOfWeek;
                        while ($currentDate <= $endOfWeek):
                            $isCurrentMonth = date('m', strtotime($currentDate)) === date('m', strtotime($current_date));
                            $isToday = $currentDate === date('Y-m-d');
                            
                            $dayAppointments = array_filter($appointments, function($apt) use ($currentDate) {
                                return date('Y-m-d', strtotime($apt['start_time'])) === $currentDate;
                            });
                            $appointmentCount = count($dayAppointments);
                        ?>
                        <div class="calendar-day min-h-[100px] p-2 border rounded cursor-pointer transition-all duration-200 
                                    <?= $isCurrentMonth ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600' : 'bg-gray-50 dark:bg-gray-700 border-gray-100 dark:border-gray-600 opacity-60' ?> 
                                    <?= $isToday ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' ?> 
                                    hover:shadow-md hover:scale-[1.02]"
                             onclick="selectDate('<?= $currentDate ?>')">
                            
                            <div class="text-sm font-medium mb-1 <?= $isCurrentMonth ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400' ?> <?= $isToday ? 'text-blue-600 dark:text-blue-400' : '' ?>">
                                <?= date('j', strtotime($currentDate)) ?>
                            </div>
                            
                            <?php if ($appointmentCount > 0): ?>
                            <div class="text-xs bg-blue-500 text-white rounded px-1 py-0.5 inline-block mb-1">
                                <?= $appointmentCount ?> apt<?= $appointmentCount !== 1 ? 's' : '' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                        endwhile; 
                        ?>
                    </div>
                    
                <?php elseif ($view_type === 'week'): ?>
                    <!-- Week View Placeholder -->
                    <div class="text-center py-12">
                        <md-icon class="text-6xl text-gray-300 mb-4">calendar_view_week</md-icon>
                        <h3 class="text-lg font-medium text-gray-600 mb-2">Week View</h3>
                        <p class="text-gray-500 mb-4">Weekly calendar view coming soon</p>
                        <md-outlined-button onclick="changeView('month')">Switch to Month View</md-outlined-button>
                    </div>
                    
                <?php else: ?>
                    <!-- Day View -->
                    <div class="space-y-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                            <md-icon class="mr-2 text-blue-500">calendar_today</md-icon>
                            <?= date('l, F j, Y', strtotime($current_date)) ?>
                        </h3>
                        
                        <?php
                        $dayAppointments = array_filter($appointments, function($apt) use ($current_date) {
                            return date('Y-m-d', strtotime($apt['start_time'])) === $current_date;
                        });
                        ?>
                        
                        <?php if (!empty($dayAppointments)): ?>
                            <?php foreach ($dayAppointments as $appointment): ?>
                            <div class="appointment-item p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-blue-900 dark:text-blue-100">
                                            <?= date('g:i A', strtotime($appointment['start_time'])) ?> - 
                                            <?= date('g:i A', strtotime($appointment['end_time'])) ?>
                                        </p>
                                        <p class="text-gray-600 text-sm">Service ID: <?= $appointment['service_id'] ?></p>
                                        <p class="text-gray-500 text-sm">Customer ID: <?= $appointment['user_id'] ?></p>
                                    </div>
                                    <span class="status-badge status-<?= $appointment['status'] ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <md-icon class="text-8xl text-gray-300 mb-6">event_available</md-icon>
                                <h4 class="text-lg font-medium text-gray-600 mb-2">No Appointments Today</h4>
                                <p class="text-gray-500 mb-6">This day is completely free</p>
                                <md-filled-button onclick="openBookingForm('<?= $current_date ?>')">
                                    <md-icon slot="icon">add_circle</md-icon>
                                    Schedule Appointment
                                </md-filled-button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </md-outlined-card>
        </div>

        <!-- Sidebar -->
        <div class="xl:col-span-1">
            <!-- Today's Appointments -->
            <md-outlined-card class="p-4 mb-6 bg-white dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <md-icon class="mr-2 text-blue-500">today</md-icon>
                    Today's Appointments
                </h3>
                
                <div class="space-y-3">
                    <?php 
                    $todaysAppointments = array_filter($appointments, function($apt) {
                        return date('Y-m-d', strtotime($apt['start_time'])) === date('Y-m-d');
                    });
                    ?>
                    
                    <?php if (!empty($todaysAppointments)): ?>
                        <?php foreach ($todaysAppointments as $appointment): ?>
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200">
                            <p class="font-medium text-blue-900 text-sm">
                                <?= date('g:i A', strtotime($appointment['start_time'])) ?>
                            </p>
                            <p class="text-gray-600 text-xs">Service #<?= $appointment['service_id'] ?></p>
                            <span class="status-badge status-<?= $appointment['status'] ?>">
                                <?= ucfirst($appointment['status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <md-icon class="text-4xl text-gray-400 mb-2">event_busy</md-icon>
                            <p class="text-gray-500 text-sm">No appointments today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </md-outlined-card>

            <!-- Quick Actions -->
            <md-outlined-card class="p-4 bg-white dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <md-icon class="mr-2 text-green-500">bolt</md-icon>
                    Quick Actions
                </h3>
                
                <div class="space-y-3">
                    <md-outlined-button class="w-full" onclick="openBookingForm()">
                        <md-icon slot="icon">add_circle</md-icon>
                        Book Appointment
                    </md-outlined-button>
                    
                    <md-outlined-button class="w-full" onclick="changeView('week')">
                        <md-icon slot="icon">schedule</md-icon>
                        View Upcoming
                    </md-outlined-button>
                </div>
            </md-outlined-card>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div id="booking-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Book New Appointment</h3>
                <md-icon-button onclick="closeBookingForm()">
                    <md-icon>close</md-icon>
                </md-icon-button>
            </div>
            
            <form id="booking-form">
                <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                
                <!-- Service and Provider Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service</label>
                        <select name="service_id" id="service_id" class="w-full p-3 border rounded-lg" required>
                            <option value="">Select a service</option>
                            <?php foreach ($services as $service): ?>
                            <option value="<?= $service['id'] ?>" data-duration="<?= $service['duration_min'] ?>">
                                <?= esc($service['name']) ?> (<?= $service['duration_min'] ?> min)
                                <?php if ($service['price']): ?> - $<?= number_format($service['price'], 2) ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Provider</label>
                        <select name="provider_id" id="provider_id" class="w-full p-3 border rounded-lg" required>
                            <option value="">Select a provider</option>
                            <?php foreach ($providers as $provider): ?>
                            <option value="<?= $provider['id'] ?>"><?= esc($provider['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Date and Time Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                        <input type="date" name="appointment_date" id="appointment_date" 
                               class="w-full p-3 border rounded-lg" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time</label>
                        <select name="appointment_time" id="appointment_time" class="w-full p-3 border rounded-lg" required>
                            <option value="">Select time</option>
                        </select>
                    </div>
                </div>

                <!-- Customer Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Customer Name</label>
                        <input type="text" name="customer_name" id="customer_name" class="w-full p-3 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Customer Email</label>
                        <input type="email" name="customer_email" id="customer_email" class="w-full p-3 border rounded-lg" required>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3" class="w-full p-3 border rounded-lg" placeholder="Any special requirements..."></textarea>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3">
                    <md-outlined-button onclick="closeBookingForm()">Cancel</md-outlined-button>
                    <md-filled-button type="submit">
                        <md-icon slot="icon">event_available</md-icon>
                        Book Appointment
                    </md-filled-button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}
.status-booked { background-color: rgb(34, 197, 94); color: white; }
.status-cancelled { background-color: rgb(239, 68, 68); color: white; }
.status-completed { background-color: rgb(59, 130, 246); color: white; }
.status-rescheduled { background-color: rgb(245, 158, 11); color: white; }

.calendar-day:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View toggle functionality
    document.querySelectorAll('.view-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('view', view);
            window.location.href = currentUrl.toString();
        });
    });

    // Form handlers
    document.getElementById('service_id')?.addEventListener('change', updateAvailableSlots);
    document.getElementById('provider_id')?.addEventListener('change', updateAvailableSlots);
    document.getElementById('appointment_date')?.addEventListener('change', updateAvailableSlots);
    document.getElementById('booking-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        submitBooking();
    });
});

function openBookingForm(date = '') {
    if (date) document.getElementById('appointment_date').value = date;
    document.getElementById('booking-modal').classList.remove('hidden');
    updateAvailableSlots();
}

function closeBookingForm() {
    document.getElementById('booking-modal').classList.add('hidden');
    document.getElementById('booking-form').reset();
}

function selectDate(date) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('date', date);
    currentUrl.searchParams.set('view', 'day');
    window.location.href = currentUrl.toString();
}

function navigateDate(direction) {
    const currentDate = '<?= $current_date ?>';
    const viewType = '<?= $view_type ?>';
    
    let newDate = new Date(currentDate);
    if (direction === 'prev') {
        if (viewType === 'month') newDate.setMonth(newDate.getMonth() - 1);
        else if (viewType === 'week') newDate.setDate(newDate.getDate() - 7);
        else newDate.setDate(newDate.getDate() - 1);
    } else {
        if (viewType === 'month') newDate.setMonth(newDate.getMonth() + 1);
        else if (viewType === 'week') newDate.setDate(newDate.getDate() + 7);
        else newDate.setDate(newDate.getDate() + 1);
    }
    
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('date', newDate.toISOString().split('T')[0]);
    window.location.href = currentUrl.toString();
}

function goToToday() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('date', new Date().toISOString().split('T')[0]);
    window.location.href = currentUrl.toString();
}

function changeView(view) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('view', view);
    window.location.href = currentUrl.toString();
}

async function updateAvailableSlots() {
    const providerId = document.getElementById('provider_id')?.value;
    const date = document.getElementById('appointment_date')?.value;
    const serviceId = document.getElementById('service_id')?.value;
    const timeSelect = document.getElementById('appointment_time');
    
    if (!providerId || !date || !serviceId || !timeSelect) return;

    try {
        timeSelect.innerHTML = '<option value="">Loading...</option>';
        const response = await fetch(`/scheduler/available-slots?provider_id=${providerId}&date=${date}&service_id=${serviceId}`);
        const result = await response.json();
        
        if (result.success) {
            timeSelect.innerHTML = '<option value="">Select time</option>';
            result.slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.display;
                timeSelect.appendChild(option);
            });
        } else {
            timeSelect.innerHTML = '<option value="">No slots available</option>';
        }
    } catch (error) {
        timeSelect.innerHTML = '<option value="">Error loading slots</option>';
    }
}

async function submitBooking() {
    const form = document.getElementById('booking-form');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/scheduler/process-booking', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            alert('Appointment booked successfully!');
            closeBookingForm();
            window.location.reload();
        } else {
            alert('Booking failed: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred while booking the appointment.');
    }
}
</script>
<?= $this->endSection() ?>