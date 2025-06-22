// Import CoreUI components
import { Sidebar, Toast, Modal } from '@coreui/coreui';

// Initialize CoreUI components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize CoreUI components
    const sidebarElements = document.querySelectorAll('.sidebar');
    sidebarElements.forEach(sidebar => {
        new Sidebar(sidebar);
    });
});

console.log('CoreUI initialized!');
