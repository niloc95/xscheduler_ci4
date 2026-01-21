<?php
/**
 * Unified Card Component
 * 
 * Standardized card component used across all pages.
 * DO NOT create custom card HTML - use this component.
 * 
 * @param string $title       Card title (optional)
 * @param string $subtitle    Card subtitle (optional)
 * @param string $content     Card body content (HTML)
 * @param string $footer      Card footer content (HTML, optional)
 * @param array  $actions     Array of action buttons for header (optional)
 * @param string $variant     Card variant: 'default', 'stat', 'chart' (default: 'default')
 * @param string $bodyClass   Additional classes for card body (e.g., 'compact', 'spacious')
 * @param string $class       Additional classes for card wrapper
 * @param bool   $interactive Make card interactive/clickable
 */

$variant = $variant ?? 'default';
$bodyClass = $bodyClass ?? '';
$class = $class ?? '';
$interactive = $interactive ?? false;
$title = $title ?? null;
$subtitle = $subtitle ?? null;
$footer = $footer ?? null;
$actions = $actions ?? [];

$cardClasses = ['xs-card'];
if ($interactive) {
    $cardClasses[] = 'xs-card-interactive';
}
if ($class) {
    $cardClasses[] = $class;
}

$cardClassString = implode(' ', $cardClasses);
?>

<?php if ($variant === 'stat'): ?>
    <!-- Stat Card Variant -->
    <div class="xs-card-stat <?= esc($class) ?>">
        <?= $content ?>
    </div>
<?php elseif ($variant === 'chart'): ?>
    <!-- Chart Card Variant -->
    <div class="xs-card-chart <?= esc($class) ?>">
        <?php if ($title): ?>
            <div class="xs-card-header">
                <div class="xs-card-header-content">
                    <h3 class="xs-card-title"><?= esc($title) ?></h3>
                    <?php if ($subtitle): ?>
                        <div class="xs-card-subtitle"><?= esc($subtitle) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($actions)): ?>
                    <div class="xs-card-actions">
                        <?= implode('', $actions) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="xs-chart-container">
            <?= $content ?>
        </div>
    </div>
<?php else: ?>
    <!-- Default Card Variant -->
    <div class="<?= $cardClassString ?>">
        <?php if ($title): ?>
            <div class="xs-card-header">
                <div class="xs-card-header-content">
                    <h3 class="xs-card-title"><?= esc($title) ?></h3>
                    <?php if ($subtitle): ?>
                        <div class="xs-card-subtitle"><?= esc($subtitle) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($actions)): ?>
                    <div class="xs-card-actions">
                        <?= implode('', $actions) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="xs-card-body <?= esc($bodyClass) ?>">
            <?= $content ?>
        </div>
        
        <?php if ($footer): ?>
            <div class="xs-card-footer">
                <?= $footer ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
