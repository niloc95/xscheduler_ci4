<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="main-content p-4">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 material-shadow rounded-lg p-4 mb-6 transition-colors duration-300">
        <div class="flex justify-between items-center">
            <div class="flex items-center">
                <a href="/scheduler" class="mr-4 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    <md-icon>arrow_back</md-icon>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">
                        <md-icon class="mr-2 text-blue-500">event_available</md-icon>
                        Book Appointment
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 transition-colors duration-300">Schedule a new appointment</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Form -->
    <div class="max-w-4xl mx-auto">
        <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300">
            <form id="booking-form" class="space-y-6">
                <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                
                <!-- Service and Provider Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Service Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Select Service *
                        </label>
                        <select name="service_id" id="service_id" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="">Choose a service</option>
                            <?php foreach ($services as $service): ?>
                            <option value="<?= $service['id'] ?>" data-duration="<?= $service['duration_min'] ?>">
                                <?= esc($service['name']) ?> 
                                (<?= $service['duration_min'] ?> min)
                                <?php if ($service['price']): ?>
                                    - $<?= number_format($service['price'], 2) ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="service-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                    </div>

                    <!-- Provider Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Select Provider *
                        </label>
                        <select name="provider_id" id="provider_id" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="">Choose a provider</option>
                            <?php foreach ($providers as $provider): ?>
                            <option value="<?= $provider['id'] ?>">
                                <?= esc($provider['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="provider-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                    </div>
                </div>

                <!-- Date and Time Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Date Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Appointment Date *
                        </label>
                        <input type="date" name="appointment_date" id="appointment_date" 
                               value="<?= $selected_date ?? date('Y-m-d') ?>"
                               min="<?= date('Y-m-d') ?>"
                               class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               required>
                        <div id="date-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                    </div>

                    <!-- Time Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Appointment Time *
                        </label>
                        <select name="appointment_time" id="appointment_time" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="">Select time (choose service and provider first)</option>
                        </select>
                        <div id="time-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="border-t border-gray-200 dark:border-gray-600 pt-6">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Customer Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Customer Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Full Name *
                            </label>
                            <input type="text" name="customer_name" id="customer_name" 
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter customer's full name" required>
                            <div id="name-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                        </div>

                        <!-- Customer Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Email Address *
                            </label>
                            <input type="email" name="customer_email" id="customer_email" 
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="customer@email.com" required>
                            <div id="email-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                        </div>
                    </div>

                    <!-- Customer Phone -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Phone Number (Optional)
                        </label>
                        <input type="tel" name="customer_phone" id="customer_phone" 
                               class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="(555) 123-4567">
                        <div id="phone-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Additional Notes (Optional)
                        </label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Any special requirements, allergies, or additional information..."></textarea>
                        <div id="notes-error" class="text-red-600 dark:text-red-400 text-xs mt-1 hidden"></div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-600">
                    <a href="/scheduler" class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                        <md-icon class="mr-2">cancel</md-icon>
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        <md-icon class="mr-2">event_available</md-icon>
                        <span id="submit-text">Book Appointment</span>
                    </button>
                </div>
            </form>
        </md-outlined-card>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pre-select time if provided in URL
    const urlParams = new URLSearchParams(window.location.search);
    const selectedTime = urlParams.get('time');
    if (selectedTime) {
        document.getElementById('appointment_time').value = selectedTime;
    }

    // Service selection handler
    document.getElementById('service_id').addEventListener('change', function() {
        updateAvailableSlots();
    });

    // Provider selection handler
    document.getElementById('provider_id').addEventListener('change', function() {
        updateAvailableSlots();
    });

    // Date selection handler
    document.getElementById('appointment_date').addEventListener('change', function() {
        updateAvailableSlots();
    });

    // Form submission
    document.getElementById('booking-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitBooking();
    });

    // Initial load of available slots if service and provider are pre-selected
    if (document.getElementById('service_id').value && document.getElementById('provider_id').value) {
        updateAvailableSlots();
    }
});

async function updateAvailableSlots() {
    const providerId = document.getElementById('provider_id').value;
    const date = document.getElementById('appointment_date').value;
    const serviceId = document.getElementById('service_id').value;
    const timeSelect = document.getElementById('appointment_time');
    
    // Clear existing slots
    timeSelect.innerHTML = '<option value="">Select time</option>';
    
    if (!providerId || !date || !serviceId) {
        timeSelect.innerHTML = '<option value="">Please select service, provider, and date first</option>';
        return;
    }

    try {
        // Show loading state
        timeSelect.innerHTML = '<option value="">Loading available times...</option>';
        timeSelect.disabled = true;
        
        const response = await fetch(`/scheduler/available-slots?provider_id=${providerId}&date=${date}&service_id=${serviceId}`);
        const result = await response.json();
        
        if (result.success && result.slots.length > 0) {
            timeSelect.innerHTML = '<option value="">Select time</option>';
            result.slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.display;
                timeSelect.appendChild(option);
            });
        } else {
            timeSelect.innerHTML = '<option value="">No available time slots for this date</option>';
        }
        
        timeSelect.disabled = false;
    } catch (error) {
        console.error('Error fetching available slots:', error);
        timeSelect.innerHTML = '<option value="">Error loading available times</option>';
        timeSelect.disabled = false;
    }
}

async function submitBooking() {
    // Clear previous errors
    document.querySelectorAll('[id$="-error"]').forEach(el => el.classList.add('hidden'));
    
    const form = document.getElementById('booking-form');
    const formData = new FormData(form);
    
    try {
        // Add loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submit-text');
        const originalText = submitText.textContent;
        submitText.textContent = 'Booking...';
        submitButton.disabled = true;

        const response = await fetch('/scheduler/process-booking', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Show success message
            showSuccessMessage('Appointment booked successfully!');
            
            // Redirect after a short delay
            setTimeout(() => {
                window.location.href = result.redirect || '/scheduler';
            }, 1500);
        } else {
            // Show error message
            showErrorMessage(result.message);
            
            // Display field-specific errors
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    const errorElement = document.getElementById(field + '-error');
                    if (errorElement) {
                        errorElement.textContent = result.errors[field];
                        errorElement.classList.remove('hidden');
                    }
                });
            }
        }
    } catch (error) {
        console.error('Booking error:', error);
        showErrorMessage('An error occurred while booking the appointment. Please try again.');
    } finally {
        // Restore button state
        const submitButton = form.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submit-text');
        submitText.textContent = originalText;
        submitButton.disabled = false;
    }
}

function showSuccessMessage(message) {
    // Create success notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg z-50 transition-all duration-300';
    notification.innerHTML = `
        <div class="flex items-center">
            <md-icon class="mr-2">check_circle</md-icon>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function showErrorMessage(message) {
    // Create error notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50 transition-all duration-300';
    notification.innerHTML = `
        <div class="flex items-center">
            <md-icon class="mr-2">error</md-icon>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}
</script>

<style>
/* Form styling enhancements */
.form-group {
    margin-bottom: 1.5rem;
}

.required::after {
    content: ' *';
    color: #ef4444;
}

/* Loading animation for dropdowns */
select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Success/Error message animations */
.notification-enter {
    opacity: 0;
    transform: translateX(100%);
}

.notification-enter-active {
    opacity: 1;
    transform: translateX(0);
}
</style>
<?= $this->endSection() ?>