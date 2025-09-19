// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

// Import charts functionality
import Charts from './charts.js';

// Initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
    }
});

console.log('Charts initialized');
