<?php
/**
 * Public Customer Portal - My Appointments View
 * 
 * Customer-facing page for viewing appointment history without login.
 * Access controlled by customer hash in URL.
 */
helper(['vite']);

$customer = $context['customer'] ?? [];
$stats = $context['stats'] ?? [];
$appointments = $context['appointments'] ?? ['data' => [], 'pagination' => []];
$upcoming = $context['upcoming'] ?? [];
$currentTab = $context['currentTab'] ?? 'upcoming';
$currentPage = $context['currentPage'] ?? 1;
$hash = $context['hash'] ?? '';

$customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));

$compiledStyles = [];
try {
    $compiledStyles = vite_css('resources/scss/app-consolidated.scss');
} catch (\Throwable $e) {
    log_message('error', 'My appointments CSS asset missing: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Appointments - <?= esc($customerName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= setting_url('general.company_icon', 'assets/settings/default-icon.svg') ?>">
    <?php foreach ($compiledStyles as $href): ?>
        <link rel="stylesheet" href="<?= esc($href) ?>">
    <?php endforeach; ?>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">My Appointments</h1>
            <p class="text-gray-600">Welcome back, <?= esc($customer['first_name'] ?? 'Customer') ?>!</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-100">
                <div class="text-2xl font-bold text-blue-600"><?= esc($stats['upcoming'] ?? 0) ?></div>
                <div class="text-sm text-gray-500">Upcoming</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-100">
                <div class="text-2xl font-bold text-green-600"><?= esc($stats['completed'] ?? 0) ?></div>
                <div class="text-sm text-gray-500">Completed</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-100">
                <div class="text-2xl font-bold text-gray-600"><?= esc($stats['total'] ?? 0) ?></div>
                <div class="text-sm text-gray-500">Total</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-100">
                <a href="<?= base_url('booking') ?>" class="flex items-center justify-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                    <span class="material-symbols-outlined">add</span>
                    Book New
                </a>
            </div>
        </div>

        <!-- Next Upcoming Appointment (if any) -->
        <?php if (!empty($upcoming)): ?>
            <?php $next = $upcoming[0]; ?>
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white mb-8 shadow-lg">
                <div class="text-sm opacity-80 mb-1">Your Next Appointment</div>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="text-xl font-semibold"><?= esc($next['service_name'] ?? 'Appointment') ?></div>
                        <div class="flex items-center gap-4 mt-2 text-blue-100">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-lg">calendar_today</span>
                                <?= date('l, F j', utc_to_local($next['start_at'])) ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-lg">schedule</span>
                                <?= date('g:i A', utc_to_local($next['start_at'])) ?>
                            </span>
                        </div>
                        <div class="mt-1 text-blue-100">with <?= esc($next['provider_name'] ?? 'Provider') ?></div>
                    </div>
                    <div class="flex gap-2">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-sm font-medium">
                            <?= ucfirst(esc($next['status'] ?? 'booked')) ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex">
                    <a href="?tab=upcoming" 
                       class="px-6 py-4 text-sm font-medium border-b-2 <?= $currentTab === 'upcoming' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                        Upcoming
                    </a>
                    <a href="?tab=past" 
                       class="px-6 py-4 text-sm font-medium border-b-2 <?= $currentTab === 'past' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                        Past Appointments
                    </a>
                    <a href="?tab=all" 
                       class="px-6 py-4 text-sm font-medium border-b-2 <?= $currentTab === 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                        All
                    </a>
                </nav>
            </div>

            <!-- Appointments List -->
            <div class="divide-y divide-gray-100">
                <?php if (!empty($appointments['data'])): ?>
                    <?php foreach ($appointments['data'] as $appt): ?>
                        <?php
                        $startTime = utc_to_local($appt['start_at'] ?? '');
                        $isPast = $startTime < time();
                        $statusClasses = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'confirmed' => 'bg-blue-100 text-blue-800',
                            'completed' => 'bg-green-100 text-green-800',
                            'cancelled' => 'bg-red-100 text-red-800',
                            'no-show' => 'bg-orange-100 text-orange-800',
                        ];
                        $statusClass = $statusClasses[$appt['status'] ?? ''] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors <?= $isPast ? 'opacity-75' : '' ?>">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div class="flex items-start gap-4">
                                    <div class="text-center min-w-[50px] <?= $isPast ? 'text-gray-400' : 'text-blue-600' ?>">
                                        <div class="text-sm font-medium"><?= date('M', $startTime) ?></div>
                                        <div class="text-2xl font-bold"><?= date('j', $startTime) ?></div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?= esc($appt['service_name'] ?? 'Appointment') ?></div>
                                        <div class="text-sm text-gray-500 mt-1">
                                            <?= date('l \a\t g:i A', $startTime) ?>
                                            <?php if (!empty($appt['service_duration'])): ?>
                                                • <?= esc($appt['service_duration']) ?> min
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            with <?= esc($appt['provider_name'] ?? 'Provider') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 md:justify-end flex-wrap">
                                    <span class="px-3 py-1 text-xs font-medium rounded-full <?= $statusClass ?>">
                                        <?= ucfirst(str_replace('-', ' ', esc($appt['status'] ?? ''))) ?>
                                    </span>
                                    <?php
                                        $pStatus = $appt['payment_status'] ?? 'none';
                                        $pAmount = $appt['payment_amount'] ?? null;
                                    ?>
                                    <?php if ($pStatus === 'paid' && $pAmount): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                            <span class="material-symbols-outlined text-xs">check_circle</span>
                                            Deposit paid <?= esc(helper_exists('currency') ? format_currency((float)$pAmount) : 'R'.number_format((float)$pAmount,2)) ?>
                                        </span>
                                    <?php elseif ($pStatus === 'pending'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">
                                            <span class="material-symbols-outlined text-xs">schedule</span>
                                            Payment pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($appt['notes'])): ?>
                                <div class="mt-3 ml-[66px] text-sm text-gray-500 bg-gray-50 rounded p-2">
                                    <span class="font-medium">Notes:</span> <?= esc($appt['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <span class="material-symbols-outlined text-4xl mb-2 block">event_busy</span>
                        <?php if ($currentTab === 'upcoming'): ?>
                            No upcoming appointments. <a href="<?= base_url('booking') ?>" class="text-blue-600 hover:underline">Book one now</a>!
                        <?php else: ?>
                            No appointments found.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (($appointments['pagination']['total_pages'] ?? 1) > 1): ?>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Page <?= esc($currentPage) ?> of <?= esc($appointments['pagination']['total_pages']) ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($currentPage > 1): ?>
                            <a href="?tab=<?= esc($currentTab) ?>&page=<?= $currentPage - 1 ?>" 
                               class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($appointments['pagination']['has_more'] ?? false): ?>
                            <a href="?tab=<?= esc($currentTab) ?>&page=<?= $currentPage + 1 ?>" 
                               class="px-3 py-1 bg-primary-500 text-white rounded text-sm hover:bg-primary-600">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Book New Appointment CTA -->
        <div class="mt-8 text-center">
            <a href="<?= base_url('booking') ?>?customer=<?= esc($hash) ?>" 
               class="inline-flex items-center gap-2 px-6 py-3 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors font-medium">
                <span class="material-symbols-outlined">add_circle</span>
                Book Another Appointment
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-center text-sm text-gray-400">
            <p>Questions? Contact us for assistance.</p>
        </div>
    </div>
</body>
</html>
