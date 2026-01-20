<?php
/**
 * Dashboard Alert Banner Component
 * 
 * Displays actionable alerts/notifications.
 * 
 * Props:
 * - alerts: Array of alert objects with:
 *   - type: Alert type (confirmation_pending, missing_hours, etc.)
 *   - severity: error, warning, info
 *   - message: Alert message text
 *   - action_label: (optional) Action button label
 *   - action_url: (optional) Action button URL
 */

$alerts = $alerts ?? [];

if (empty($alerts)) {
    return;
}
?>

<div class="space-y-3 mb-6" data-dashboard-alerts>
    <?php foreach ($alerts as $alert): ?>
        <?php
        $severity = $alert['severity'] ?? 'info';
        $message = $alert['message'] ?? '';
        $actionLabel = $alert['action_label'] ?? null;
        $actionUrl = $alert['action_url'] ?? null;
        
        // Severity styling
        $severityClasses = [
            'error' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
            'warning' => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200',
            'info' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
        ];
        
        $severityIcons = [
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
        ];
        
        $classes = $severityClasses[$severity] ?? $severityClasses['info'];
        $icon = $severityIcons[$severity] ?? $severityIcons['info'];
        ?>
        
        <div class="flex items-center justify-between p-4 border rounded-lg <?= $classes ?>" role="alert">
            <div class="flex items-start flex-1">
                <span class="material-symbols-outlined mr-3 text-lg"><?= $icon ?></span>
                <div class="flex-1">
                    <p class="text-sm font-medium"><?= esc($message) ?></p>
                </div>
            </div>
            
            <?php if ($actionLabel && $actionUrl): ?>
            <div class="ml-4 flex-shrink-0">
                <a href="<?= esc($actionUrl) ?>" 
                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md 
                          bg-white dark:bg-gray-800 
                          hover:bg-gray-50 dark:hover:bg-gray-700 
                          border border-current 
                          transition-colors duration-200">
                    <?= esc($actionLabel) ?>
                    <span class="material-symbols-outlined ml-1 text-sm">arrow_forward</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
