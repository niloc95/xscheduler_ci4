<?php
/**
 * Flash Messages Component
 * 
 * Displays session flash messages with appropriate styling.
 * Supports success, error, warning, and info types.
 * Auto-dismissible with close button.
 * 
 * Usage:
 * <?= $this->include('components/ui/flash-messages') ?>
 * 
 * Flash message types (set in controller):
 * - session()->setFlashdata('success', 'Record saved successfully')
 * - session()->setFlashdata('error', 'An error occurred')
 * - session()->setFlashdata('warning', 'Please review your input')
 * - session()->setFlashdata('info', 'New features available')
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$types = [
    'success' => [
        'bg' => 'bg-green-50 dark:bg-green-900/20',
        'border' => 'border-green-200 dark:border-green-800',
        'text' => 'text-green-800 dark:text-green-200',
        'icon' => 'check_circle',
        'iconColor' => 'text-green-500 dark:text-green-400'
    ],
    'error' => [
        'bg' => 'bg-red-50 dark:bg-red-900/20',
        'border' => 'border-red-200 dark:border-red-800',
        'text' => 'text-red-800 dark:text-red-200',
        'icon' => 'error',
        'iconColor' => 'text-red-500 dark:text-red-400'
    ],
    'warning' => [
        'bg' => 'bg-amber-50 dark:bg-amber-900/20',
        'border' => 'border-amber-200 dark:border-amber-800',
        'text' => 'text-amber-800 dark:text-amber-200',
        'icon' => 'warning',
        'iconColor' => 'text-amber-500 dark:text-amber-400'
    ],
    'info' => [
        'bg' => 'bg-blue-50 dark:bg-blue-900/20',
        'border' => 'border-blue-200 dark:border-blue-800',
        'text' => 'text-blue-800 dark:text-blue-200',
        'icon' => 'info',
        'iconColor' => 'text-blue-500 dark:text-blue-400'
    ]
];

$session = session();
$hasMessages = false;

foreach (array_keys($types) as $type) {
    if ($session->getFlashdata($type)) {
        $hasMessages = true;
        break;
    }
}

// Also check for 'message' type (generic)
if ($session->getFlashdata('message')) {
    $hasMessages = true;
}
?>

<?php if ($hasMessages): ?>
<div class="xs-flash-messages space-y-3 mb-6">
    <?php foreach ($types as $type => $config): ?>
        <?php if ($msg = $session->getFlashdata($type)): ?>
        <div class="xs-alert flex items-start gap-3 p-4 rounded-lg border <?= $config['bg'] ?> <?= $config['border'] ?> <?= $config['text'] ?>" role="alert" data-flash-message>
            <span class="material-symbols-outlined flex-shrink-0 <?= $config['iconColor'] ?>"><?= $config['icon'] ?></span>
            <div class="flex-1 text-sm font-medium">
                <?php if (is_array($msg)): ?>
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($msg as $m): ?>
                        <li><?= esc($m) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <?= esc($msg) ?>
                <?php endif; ?>
            </div>
            <button type="button" class="flex-shrink-0 p-1 -m-1 rounded hover:bg-black/10 dark:hover:bg-white/10 transition-colors" onclick="this.parentElement.remove()" aria-label="Dismiss">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <?php // Handle generic 'message' type (defaults to info styling) ?>
    <?php if ($msg = $session->getFlashdata('message')): ?>
    <div class="xs-alert flex items-start gap-3 p-4 rounded-lg border <?= $types['info']['bg'] ?> <?= $types['info']['border'] ?> <?= $types['info']['text'] ?>" role="alert" data-flash-message>
        <span class="material-symbols-outlined flex-shrink-0 <?= $types['info']['iconColor'] ?>"><?= $types['info']['icon'] ?></span>
        <div class="flex-1 text-sm font-medium">
            <?= esc($msg) ?>
        </div>
        <button type="button" class="flex-shrink-0 p-1 -m-1 rounded hover:bg-black/10 dark:hover:bg-white/10 transition-colors" onclick="this.parentElement.remove()" aria-label="Dismiss">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-dismiss flash messages after 8 seconds
document.querySelectorAll('[data-flash-message]').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.3s ease-out';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, 8000);
});
// Scroll to top so success/error flash messages are visible on form pages
if (document.querySelector('[data-flash-message]')) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
<?php endif; ?>
