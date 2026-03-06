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
    class="relative inline-flex items-center justify-center w-11 h-11 rounded-lg border transition-all duration-200"
    style="
        border-color: var(--md-sys-color-outline);
        background-color: var(--md-sys-color-surface);
        color: var(--md-sys-color-on-surface-variant);
    "
    aria-label="Toggle theme"
    title="Toggle between light and dark mode"
>
    <!-- Light mode icon (shown in dark theme) -->
    <span data-theme-icon="light" class="material-symbols-outlined align-middle" style="display: none;">light_mode</span>
    
    <!-- Dark mode icon (shown in light theme) -->
    <span data-theme-icon="dark" class="material-symbols-outlined align-middle">dark_mode</span>
    
    <span class="sr-only">Toggle dark mode</span>
</button>

<script>
// Initialize toggle button icon visibility
(function() {
    const updateToggleIcon = () => {
        const theme = document.documentElement.getAttribute('data-theme');
        const lightIcon = document.querySelector('[data-theme-icon="light"]');
        const darkIcon = document.querySelector('[data-theme-icon="dark"]');
        
        if (lightIcon && darkIcon) {
            if (theme === 'dark') {
                lightIcon.style.display = 'inline-block';
                darkIcon.style.display = 'none';
            } else {
                lightIcon.style.display = 'none';
                darkIcon.style.display = 'inline-block';
            }
        }
    };
    
    // Update on page load and when theme changes
    updateToggleIcon();
    document.addEventListener('xs:theme-changed', updateToggleIcon);
})();
</script>

<noscript>
    <p class="sr-only">Dark mode requires JavaScript.</p>
</noscript>
