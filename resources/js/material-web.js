// Material Web Components Entry Point
import '@material/web/all.js';
import { styles as typescaleStyles } from '@material/web/typography/md-typescale-styles.js';

// Apply Material Design typography styles
document.adoptedStyleSheets.push(typescaleStyles.styleSheet);

// Initialize Material Web Components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Theme values are sourced from SCSS custom properties.

});

// Export for use in other modules if needed
export default {
    init: () => {
        // Material Web Components initialized
    }
};
