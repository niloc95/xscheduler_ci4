// Material Web Components Entry Point
import '@material/web/all.js';
import { styles as typescaleStyles } from '@material/web/typography/md-typescale-styles.js';

// Apply Material Design typography styles
document.adoptedStyleSheets.push(typescaleStyles.styleSheet);

// Initialize Material Web Components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Optional: Add any global Material Web configuration here
    
    // Example: Configure theme colors
    const root = document.documentElement;
    root.style.setProperty('--md-sys-color-primary', 'rgb(59, 130, 246)'); // Tailwind blue-500
    root.style.setProperty('--md-sys-color-on-primary', 'rgb(255, 255, 255)');
    root.style.setProperty('--md-sys-color-surface', 'rgb(255, 255, 255)');
    root.style.setProperty('--md-sys-color-on-surface', 'rgb(17, 24, 39)'); // Tailwind gray-900
    
    // Add any other Material Web initialization code here

});

// Export for use in other modules if needed
export default {
    init: () => {
        // Material Web Components initialized
    }
};
