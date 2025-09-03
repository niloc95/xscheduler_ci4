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
    class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-all duration-200"
    aria-label="Toggle theme"
    title="Toggle between light and dark mode"
>
    <!-- Light Mode Icon (Sun) - Show when in dark mode -->
    <svg 
        data-theme-icon="light" 
        class="w-5 h-5" 
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
        style="display: none;"
    >
        <path 
            stroke-linecap="round" 
            stroke-linejoin="round" 
            stroke-width="2" 
            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
        />
    </svg>
    
    <!-- Dark Mode Icon (Moon) - Show when in light mode -->
    <svg 
        data-theme-icon="dark" 
        class="w-5 h-5" 
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
        style="display: block;"
    >
        <path 
            stroke-linecap="round" 
            stroke-linejoin="round" 
            stroke-width="2" 
            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
        />
    </svg>
</button>

<noscript>
    <style>
        /* Ensure readable defaults when JS is disabled */
        html { background: #ffffff; color: #003049; }
    </style>
    <!-- Dark mode requires JavaScript -->
</noscript>
