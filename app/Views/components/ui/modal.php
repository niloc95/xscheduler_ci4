<?php
/**
 * Modal Component
 * 
 * Standardized modal dialog for confirmations, forms, and content display.
 * Uses Alpine.js for interaction.
 * 
 * Props:
 * @param string $id Required. Modal unique identifier
 * @param string $title Optional. Modal title
 * @param string $size Optional. Size variant (sm, md, lg, xl, full)
 * @param bool $closable Optional. Show close button (default: true)
 * @param bool $closeOnBackdrop Optional. Close when clicking backdrop (default: true)
 * @param string $icon Optional. Title icon
 * @param string $iconColor Optional. Icon color class
 * @param string $footer Optional. Footer content (HTML)
 * @param string $class Optional. Additional modal classes
 * 
 * Usage (include modal in page):
 * <?= $this->include('components/ui/modal', [
 *     'id' => 'confirm-delete',
 *     'title' => 'Confirm Delete',
 *     'icon' => 'warning',
 *     'iconColor' => 'text-red-500',
 *     'size' => 'sm'
 * ]) ?>
 * 
 * Trigger modal (via Alpine.js):
 * <button @click="$dispatch('open-modal', 'confirm-delete')">Open Modal</button>
 * 
 * Or with JavaScript:
 * window.dispatchEvent(new CustomEvent('open-modal', { detail: 'confirm-delete' }));
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$id = $id ?? 'modal';
$title = $title ?? null;
$size = $size ?? 'md';
$closable = $closable ?? true;
$closeOnBackdrop = $closeOnBackdrop ?? true;
$icon = $icon ?? null;
$iconColor = $iconColor ?? 'text-gray-600 dark:text-gray-400';
$footer = $footer ?? null;
$class = $class ?? '';

// Size configurations
$sizes = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-lg',
    'lg' => 'max-w-2xl',
    'xl' => 'max-w-4xl',
    'full' => 'max-w-full mx-4',
];

$sizeClass = $sizes[$size] ?? $sizes['md'];
?>

<div 
    x-data="{ 
        open: false,
        modalId: '<?= esc($id) ?>',
        init() {
            this.$watch('open', value => {
                document.body.classList.toggle('overflow-hidden', value);
            });
        }
    }"
    x-on:open-modal.window="if ($event.detail === modalId) open = true"
    x-on:close-modal.window="if ($event.detail === modalId || !$event.detail) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="xs-modal fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="<?= esc($id) ?>-title"
>
    <!-- Backdrop -->
    <div 
        class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/80 backdrop-blur-sm transition-opacity"
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        <?php if ($closeOnBackdrop): ?>
        @click="open = false"
        <?php endif; ?>
    ></div>
    
    <!-- Modal Panel -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div 
            class="relative w-full <?= $sizeClass ?> bg-white dark:bg-gray-800 rounded-xl shadow-xl transform transition-all <?= esc($class) ?>"
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            @click.stop
        >
            <!-- Header -->
            <?php if ($title || $closable): ?>
            <div class="flex items-start justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                <?php if ($title): ?>
                <div class="flex items-center gap-3">
                    <?php if ($icon): ?>
                    <span class="material-symbols-outlined text-2xl <?= esc($iconColor) ?>"><?= esc($icon) ?></span>
                    <?php endif; ?>
                    <h3 id="<?= esc($id) ?>-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                        <?= esc($title) ?>
                    </h3>
                </div>
                <?php endif; ?>
                
                <?php if ($closable): ?>
                <button 
                    type="button"
                    class="p-1.5 -m-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    @click="open = false"
                    aria-label="Close modal"
                >
                    <span class="material-symbols-outlined">close</span>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Body -->
            <div class="p-5" id="<?= esc($id) ?>-body">
                <?= $this->renderSection('modal_content_' . $id) ?>
            </div>
            
            <!-- Footer -->
            <?php if ($footer): ?>
            <div class="flex items-center justify-end gap-3 p-5 border-t border-gray-200 dark:border-gray-700">
                <?= $footer ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
