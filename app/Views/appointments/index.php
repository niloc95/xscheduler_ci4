<?php
/**
 * Appointments Dashboard View
 *
 * This file renders the main appointments dashboard for all user roles (admin, provider, staff, customer).
 * It provides:
 *   - Sidebar navigation
 *   - Page title and subtitle
 *   - Action buttons (e.g., create/book appointment)
 *   - Stats summary cards
 *   - Filter controls
 *   - Appointment list with status, actions, and notes
 *
 * Sections are injected into the main dashboard layout.
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?php // Override stats grid to two responsive columns ?>
<?= $this->section('dashboard_stats_class') ?>grid grid-cols-1 md:grid-cols-2 gap-6<?= $this->endSection() ?>

<?php // Sidebar navigation: highlights the Appointments section ?>
<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'appointments']) ?>
<?= $this->endSection() ?>

<?php // Page title and subtitle: dynamic based on user role ?>
<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= $user_role === 'customer' ? 'View and manage your upcoming and past appointments' : 'Manage appointments for your business' ?><?= $this->endSection() ?>

<?php // Action button handled with filters; no standalone actions block ?>
<?= $this->section('dashboard_actions') ?><?= $this->endSection() ?>

<?php // Stats summary cards: condensed to Upcoming and Completed ?>
<?= $this->section('dashboard_stats') ?><?= $this->endSection() ?>

<?php // Filter controls and primary action with date picker alignment ?>
<?= $this->section('dashboard_filters') ?>
    <?php $upcomingCount = ($stats['pending'] ?? 0) + ($stats['today'] ?? 0); ?>
    <?php $completedCount = $stats['completed'] ?? 0; ?>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-wrap items-center gap-4 w-full lg:w-auto">
            <div class="stat-card min-w-[12rem] rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Upcoming Appointments</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"><?= $upcomingCount ?></p>
            </div>
            <div class="stat-card min-w-[12rem] rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed Appointments</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"><?= $completedCount ?></p>
            </div>
        </div>

        <div class="flex w-full flex-col gap-3 items-stretch lg:flex-1 lg:items-end">
            <div class="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
                <!-- View Selection Buttons - Material Design 3 Styled -->
                <button type="button" data-calendar-action="today" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Today</button>
                <button type="button" data-calendar-action="day" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Day</button>
                <button type="button" data-calendar-action="week" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Week</button>
                <button type="button" data-calendar-action="month" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-blue-600 text-white shadow-sm hover:bg-blue-700 hover:shadow-md transition-all duration-200">Month</button>
                
                <!-- Status Filter Buttons -->
                <button type="button" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm" title="Show pending appointments">Pending</button>
                <button type="button" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm" title="Show completed appointments">Completed</button>
            </div>

                <?php if (has_role(['customer', 'staff', 'provider', 'admin'])): ?>
                <a href="<?= base_url('/appointments/create') ?>"
                    class="btn btn-primary inline-flex items-center justify-center gap-2 px-4 py-2 w-full sm:w-auto lg:self-end">
                <span class="material-symbols-outlined text-base">add</span>
                <?= $user_role === 'customer' ? 'Book Appointment' : 'New Appointment' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <!-- Calendar Toolbar -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
            <!-- Navigation Controls -->
            <div class="flex items-center gap-2">
                <button type="button" data-calendar-action="prev" 
                        class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Previous">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button type="button" data-calendar-action="next" 
                        class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Next">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>

            <!-- Current Date Display -->
            <div id="scheduler-date-display" class="text-lg font-semibold text-gray-900 dark:text-white">
                <?= date('F Y') ?>
            </div>

            <!-- Provider Legend (will be populated by JavaScript) -->
            <div id="provider-legend" class="flex items-center gap-2 flex-wrap">
                <!-- Dynamically populated -->
            </div>
        </div>
        
        <!-- Calendar Container -->
        <div
            id="appointments-inline-calendar"
            class="w-full"
            data-initial-date="<?= esc($selectedDate ?? date('Y-m-d')) ?>"
        ></div>
    </div>
<?= $this->endSection() ?>

<?php // Main content: appointment list with status, actions, and notes ?>
<?= $this->section('dashboard_content') ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Appointments</h3>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $appointment): ?>
                    <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-400 text-2xl">person</span>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <?= esc($appointment['customer_name']) ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <?= esc($appointment['service']) ?> with <?= esc($appointment['provider']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">
                                        <?= date('F j, Y', strtotime($appointment['date'])) ?> at <?= date('g:i A', strtotime($appointment['time'])) ?>
                                        (<?= $appointment['duration'] ?> min)
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 text-xs font-medium rounded-full
                                    <?php if ($appointment['status'] === 'confirmed'): ?>
                                        bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    <?php elseif ($appointment['status'] === 'pending'): ?>
                                        bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                    <?php elseif ($appointment['status'] === 'completed'): ?>
                                        bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    <?php else: ?>
                                        bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                    <?php endif; ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span>

                                <div class="flex items-center space-x-1">
                                    <button type="button"
                                            data-appointment-view="<?= $appointment['id'] ?>"
                                            class="appointment-view-btn p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200"
                                            title="View Details">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </button>

                                    <?php if (has_role(['admin', 'provider', 'staff'])): ?>
                                    <button class="p-2 text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors duration-200"
                                            title="Edit Appointment">
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($appointment['notes'])): ?>
                        <div class="mt-3 pl-16">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Notes:</span> <?= esc($appointment['notes']) ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-6xl mb-4">event_busy</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No appointments found</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        <?= $user_role === 'customer' ? 'You don\'t have any appointments yet.' : 'No appointments match your current filters.' ?>
                    </p>
                    <?php if ($user_role === 'customer'): ?>
                    <a href="<?= base_url('/appointments/create') ?>"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-outlined mr-2">add</span>
                        Book Your First Appointment
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include Appointment Details Modal -->
    <?= $this->include('appointments/modal') ?>

    <script type="module">
        // Handle clicks on PHP-rendered appointment list view buttons
        document.addEventListener('DOMContentLoaded', () => {
            console.log('[PHP List] DOM loaded, initializing...');
            
            const viewButtons = document.querySelectorAll('.appointment-view-btn');
            console.log('[PHP List] Found', viewButtons.length, 'view buttons');
            
            if (viewButtons.length === 0) {
                console.warn('[PHP List] No appointment view buttons found! Check if appointments are rendered.');
            }
            
            viewButtons.forEach((btn, index) => {
                console.log(`[PHP List] Attaching handler to button ${index + 1}:`, btn.dataset.appointmentView);
                
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const appointmentId = parseInt(btn.dataset.appointmentView, 10);
                    console.log('[PHP List] 🔵 View button clicked for appointment:', appointmentId);
                    
                    // Wait for scheduler to be initialized
                    let attempts = 0;
                    const maxAttempts = 50; // 5 seconds max
                    
                    const checkScheduler = () => {
                        attempts++;
                        console.log(`[PHP List] Checking scheduler (attempt ${attempts}/${maxAttempts})...`);
                        
                        if (window.scheduler?.appointmentDetailsModal) {
                            console.log('[PHP List] ✅ Scheduler found! Fetching appointment data...');
                            
                            // Fetch full appointment data from API
                            fetch(`/api/appointments/${appointmentId}`)
                                .then(res => {
                                    console.log('[PHP List] API response status:', res.status);
                                    return res.json();
                                })
                                .then(response => {
                                    console.log('[PHP List] API response:', response);
                                    
                                    // API returns { data: { appointment } }
                                    if (response.data) {
                                        console.log('[PHP List] 🎉 Opening modal with appointment:', response.data);
                                        window.scheduler.appointmentDetailsModal.open(response.data);
                                    } else if (response.error) {
                                        console.error('[PHP List] ❌ API error:', response.error);
                                        alert('Error loading appointment: ' + response.error.message);
                                    } else {
                                        console.error('[PHP List] ❌ Unexpected API response format:', response);
                                    }
                                })
                                .catch(err => {
                                    console.error('[PHP List] ❌ Fetch error:', err);
                                    alert('Failed to load appointment. Check console for details.');
                                });
                        } else {
                            if (attempts >= maxAttempts) {
                                console.error('[PHP List] ❌ Scheduler not found after', maxAttempts, 'attempts');
                                console.log('[PHP List] window.scheduler:', window.scheduler);
                                alert('Calendar not loaded. Please refresh the page.');
                            } else {
                                setTimeout(checkScheduler, 100);
                            }
                        }
                    };
                    
                    checkScheduler();
                });
            });
            
            // Log scheduler status
            setTimeout(() => {
                console.log('[PHP List] After 1 second - window.scheduler:', window.scheduler);
                console.log('[PHP List] Modal available?', !!window.scheduler?.appointmentDetailsModal);
            }, 1000);
        });
    </script>
<?= $this->endSection() ?>
