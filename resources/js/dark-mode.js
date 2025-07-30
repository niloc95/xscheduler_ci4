/**
 * xScheduler Dark Mode System
 * Provides theme switching functionality with localStorage persistence
 */

class DarkModeManager {
    constructor() {
        this.theme = this.getStoredTheme() || this.getPreferredTheme();
        this.init();
    }

    /**
     * Initialize dark mode system
     */
    init() {
        // Apply initial theme
        this.applyTheme(this.theme);
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!this.getStoredTheme()) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Initialize toggle buttons
        this.initToggleButtons();
    }

    /**
     * Get stored theme from localStorage
     */
    getStoredTheme() {
        return localStorage.getItem('xs-theme');
    }

    /**
     * Get user's preferred theme from system
     */
    getPreferredTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /**
     * Store theme preference
     */
    storeTheme(theme) {
        localStorage.setItem('xs-theme', theme);
    }

    /**
     * Apply theme to document
     */
    applyTheme(theme) {
        this.theme = theme;
        
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Update theme-color meta tag
        this.updateThemeColor(theme);
        
        // Store preference
        this.storeTheme(theme);
        
        // Update toggle button states
        this.updateToggleButtons();
        
        // Dispatch custom event for other components
        document.dispatchEvent(new CustomEvent('xs:theme-changed', { detail: { theme } }));
    }

    /**
     * Toggle between light and dark themes
     */
    toggle() {
        const newTheme = this.theme === 'dark' ? 'light' : 'dark';
        this.applyTheme(newTheme);
    }

    /**
     * Update theme-color meta tag for mobile browsers
     */
    updateThemeColor(theme) {
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }

        const colors = {
            light: '#003049', // Ocean blue for light theme
            dark: '#1a202c'   // Dark surface for dark theme
        };

        metaThemeColor.content = colors[theme];
    }

    /**
     * Initialize toggle buttons
     */
    initToggleButtons() {
        const toggleButtons = document.querySelectorAll('[data-theme-toggle]');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', () => this.toggle());
        });

        this.updateToggleButtons();
    }

    /**
     * Update toggle button states
     */
    updateToggleButtons() {
        const toggleButtons = document.querySelectorAll('[data-theme-toggle]');
        
        toggleButtons.forEach(button => {
            const lightIcon = button.querySelector('[data-theme-icon="light"]');
            const darkIcon = button.querySelector('[data-theme-icon="dark"]');
            
            if (lightIcon && darkIcon) {
                if (this.theme === 'dark') {
                    lightIcon.style.display = 'block';
                    darkIcon.style.display = 'none';
                } else {
                    lightIcon.style.display = 'none';
                    darkIcon.style.display = 'block';
                }
            }
            
            // Update aria-label for accessibility
            button.setAttribute('aria-label', 
                this.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'
            );
        });
    }

    /**
     * Get current theme
     */
    getCurrentTheme() {
        return this.theme;
    }

    /**
     * Check if dark mode is enabled
     */
    isDark() {
        return this.theme === 'dark';
    }

    /**
     * Check if light mode is enabled
     */
    isLight() {
        return this.theme === 'light';
    }
}

// Initialize dark mode when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.darkMode = new DarkModeManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DarkModeManager;
}
