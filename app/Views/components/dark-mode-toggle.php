<?php
/**
 * Dark Mode Toggle Component
 * 
 * Reusable component for theme switching throughout the application
 * Can be used in headers, settings panels, or anywhere theme toggle is needed
 */
?>

<!-- Dark Mode Toggle Button -->
<button 
    type="button" 
    data-theme-toggle
    class="xs-theme-toggle relative inline-flex items-center justify-center w-11 h-11 rounded-lg border border-md-outline bg-md-surface text-md-on-surface-variant transition-all duration-200"
    aria-label="Toggle theme"
    title="Toggle between light and dark mode"
>
    <!-- Light mode icon (shown in dark theme) -->
    <span data-theme-icon="light" class="material-symbols-outlined align-middle">light_mode</span>
    
    <!-- Dark mode icon (shown in light theme) -->
    <span data-theme-icon="dark" class="material-symbols-outlined align-middle">dark_mode</span>
    
    <span class="sr-only">Toggle dark mode</span>
</button>

<noscript>
    <p class="sr-only">Dark mode requires JavaScript.</p>
</noscript>
