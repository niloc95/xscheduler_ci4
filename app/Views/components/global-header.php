<?php
/**
 * Global Header Component
 * 
 * Single source of truth for the application header bar.
 * Displays across all authenticated pages in a consistent manner.
 * 
 * This component should be used in the layout (app.php) ONLY.
 * Individual views should NOT create their own headers.
 * 
 * @param string $title       Page title (H1) - passed from controller or view
 * @param string $subtitle    Page subtitle/description (optional)
 * @param array  $actions     Array of action buttons/links (optional)
 * @param array  $breadcrumbs Array of breadcrumb items (optional)
 * @param string $userRole    Current user's role (admin, provider, staff, customer)
 * @param string $userName    Current user's name
 */

$title = $title ?? 'Dashboard';
$subtitle = $subtitle ?? null;
$actions = $actions ?? [];
$breadcrumbs = $breadcrumbs ?? [];
$userRole = $userRole ?? session()->get('user')['role'] ?? 'user';
$userName = $userName ?? session()->get('user')['name'] ?? 'User';

// Format role display name
$displayRole = ucfirst($userRole);
if ($userRole === 'admin') $displayRole = 'Administrator';
elseif ($userRole === 'provider') $displayRole = 'Service Provider';
elseif ($userRole === 'staff') $displayRole = 'Staff Member';
?>

<header class="xs-header bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 transition-colors duration-200">
    <div class="xs-header-content">
        <!-- Left Section: Mobile Menu + Title + Breadcrumbs -->
        <div class="xs-header-left">
            <!-- Mobile Menu Toggle -->
            <button id="menu-toggle" type="button" class="lg:hidden p-2 -ml-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" onclick="toggleSidebar()" aria-label="Toggle menu">
                <span class="material-symbols-outlined">menu</span>
            </button>
            
            <!-- Title Section -->
            <div class="xs-header-title-section">
                <!-- Breadcrumbs (if provided) -->
                <?php if (!empty($breadcrumbs)): ?>
                    <nav class="xs-breadcrumbs" aria-label="Breadcrumb">
                        <ol class="flex items-center gap-2 text-xs mb-2">
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <li class="flex items-center gap-2">
                                    <?php if ($index > 0): ?>
                                        <span class="text-gray-300 dark:text-gray-600">/</span>
                                    <?php endif; ?>
                                    <?php if (isset($crumb['url'])): ?>
                                        <a href="<?= esc($crumb['url']) ?>" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            <?= esc($crumb['label']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-900 dark:text-gray-100 font-medium">
                                            <?= esc($crumb['label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>
                
                <!-- Page Title (H1) -->
                <h1 id="header-title" class="xs-header-title">
                    <?= esc($title) ?>
                </h1>
                
                <!-- Page Subtitle + User Context -->
                <div class="xs-header-meta">
                    <?php if ($subtitle): ?>
                        <p class="xs-header-subtitle">
                            <?= esc($subtitle) ?>
                        </p>
                    <?php else: ?>
                        <!-- Default: Show current user role -->
                        <p class="xs-header-subtitle">
                            <span class="text-blue-600 dark:text-blue-400 font-medium"><?= esc($displayRole) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Section: Actions + Controls -->
        <div class="xs-header-right">
            <?php if (!empty($actions)): ?>
                <div class="xs-header-actions">
                    <?= implode('', $actions) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
