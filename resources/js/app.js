// Import CoreUI components
import { Sidebar, Toast, Modal } from '@coreui/coreui';

// Import charts functionality
import Charts from './charts.js';

// Initialize CoreUI components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize CoreUI components
    const sidebarElements = document.querySelectorAll('.sidebar');
    sidebarElements.forEach(sidebar => {
        new Sidebar(sidebar);
    });
    
    // Initialize charts if available
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
    }
});

console.log('CoreUI and Charts initialized!');
