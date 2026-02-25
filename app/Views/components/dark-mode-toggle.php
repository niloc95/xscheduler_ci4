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
    class="relative inline-flex items-center justify-center w-11 h-11 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-all duration-200"
    aria-label="Toggle theme"
    title="Toggle between light and dark mode"
>
    <span data-theme-icon="light" class="material-symbols-outlined align-middle hidden dark:inline-block">light_mode</span>
    <span data-theme-icon="dark" class="material-symbols-outlined align-middle inline-block dark:hidden">dark_mode</span>
    <span class="sr-only">Toggle dark mode</span>
</button>

<noscript>
    <p class="sr-only">Dark mode requires JavaScript.</p>
</noscript>
