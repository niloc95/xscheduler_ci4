/**
 * Unified Sidebar JavaScript
 * Clean, consistent sidebar behavior
 */

class UnifiedSidebar {
    constructor() {
        this.sidebar = document.getElementById('main-sidebar');
        this.backdrop = document.getElementById('sidebar-overlay') || document.getElementById('mobile-backdrop');
        this.menuToggle = document.getElementById('menu-toggle');
        this.closeButton = document.getElementById('sidebar-close-btn');
        
        this.isOpen = false;
        this.isMobile = false;
        
        this.init();
    }
    
    init() {
        if (!this.sidebar) return;
        
        this.checkMobile();
        this.bindEvents();
        this.handleResize();
        
        // Initialize state
        this.updateState();
    }
    
    checkMobile() {
        this.isMobile = window.innerWidth < 1024;
    }
    
    bindEvents() {
        // Menu toggle (mobile)
        if (this.menuToggle) {
            this.menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        }
        
        // Close button
        if (this.closeButton) {
            this.closeButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.close();
            });
        }
        
        // Backdrop click
        if (this.backdrop) {
            this.backdrop.addEventListener('click', () => {
                this.close();
            });
        }

        // Role Switcher
        const roleSwitcher = document.getElementById('roleSwitcher');
        if (roleSwitcher) {
            roleSwitcher.addEventListener('change', (e) => {
                this.handleRoleSwitch(e.target.value);
            });
        }
        
        // Window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });
        
        // ESC key to close on mobile
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && this.isOpen) {
                this.close();
            }
        });
        
        // Handle SPA navigation - update active states
        document.addEventListener('spa:navigated', () => {
            this.updateActiveStates();
            // Close sidebar on mobile after navigation
            if (this.isMobile && this.isOpen) {
                this.close();
            }
        });
    }
    
    handleResize() {
        this.checkMobile();
        
        if (!this.isMobile && this.isOpen) {
            // Close mobile state when switching to desktop
            this.isOpen = false;
            this.updateState();
        }
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        this.isOpen = true;
        this.updateState();
        
        // Prevent body scroll on mobile
        if (this.isMobile) {
            document.body.classList.add('xs-no-scroll');
        }
    }
    
    close() {
        this.isOpen = false;
        this.updateState();
        
        // Restore body scroll
        if (this.isMobile) {
            document.body.classList.remove('xs-no-scroll');
        }
    }
    
    updateState() {
        if (!this.sidebar) return;
        
        // Update sidebar classes
        if (this.isMobile) {
            this.sidebar.classList.toggle('open', this.isOpen);
        } else {
            this.sidebar.classList.remove('open');
        }
        
        // Update backdrop/overlay (matches .xs-sidebar-overlay in layouts/app.php)
        if (this.backdrop) {
            this.backdrop.classList.toggle('active', this.isMobile && this.isOpen);
        }
        
        // Update ARIA attributes
        this.sidebar.setAttribute('aria-hidden', this.isMobile && !this.isOpen ? 'true' : 'false');
    }
    
    updateActiveStates() {
        const currentPath = window.location.pathname;
        const navLinks = this.sidebar?.querySelectorAll('.nav-link');
        
        if (!navLinks) return;
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href) {
                const linkPath = new URL(href, window.location.origin).pathname;
                
                // Check if current path matches or starts with link path
                const isActive = currentPath === linkPath || 
                    (linkPath !== '/' && currentPath.startsWith(linkPath));
                
                link.classList.toggle('active', isActive);
            }
        });
    }

    async handleRoleSwitch(newRole) {
        try {
            const response = await fetch('/api/auth/switch-role', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({ role: newRole })
            });

            if (!response.ok) {
                const error = await response.json();
                console.error('Role switch failed:', error);
                alert('Failed to switch role. Please try again.');
                return;
            }

            const result = await response.json();
            console.log('Role switched successfully:', result);
            
            // Show success message
            if (result.data?.message) {
                this.showNotification(result.data.message, 'success');
            }

            // Reload the page to apply new role context
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } catch (error) {
            console.error('Error switching role:', error);
            alert('An error occurred while switching roles. Please try again.');
        }
    }

    getCsrfToken() {
        // Try to get CSRF token from meta tag
        let token = document.querySelector('meta[name="csrf-token"]')?.content;
        
        // Fallback: try from cookie
        if (!token) {
            const name = 'XSRF-TOKEN';
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) token = parts.pop().split(';').shift();
        }
        
        return token || '';
    }

    showNotification(message, type = 'info') {
        // Create a simple notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-4 py-3 rounded text-white text-sm z-50 ${
            type === 'success' ? 'bg-green-500' : 'bg-blue-500'
        }`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Public API methods
    isVisible() {
        return !this.isMobile || this.isOpen;
    }
    
    isMobileMode() {
        return this.isMobile;
    }
}

// Initialize sidebar when DOM is ready
let unifiedSidebar = null;

function initUnifiedSidebar() {
    if (unifiedSidebar) {
        return unifiedSidebar;
    }
    
    unifiedSidebar = new UnifiedSidebar();
    return unifiedSidebar;
}

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initUnifiedSidebar();
        // Assign instance after creation
        if (unifiedSidebar instanceof UnifiedSidebar) {
            window.unifiedSidebar = unifiedSidebar;
        }
    });
} else {
    initUnifiedSidebar();
    // Assign instance after creation
    if (unifiedSidebar instanceof UnifiedSidebar) {
        window.unifiedSidebar = unifiedSidebar;
    }
}

// Export for global access
window.UnifiedSidebar = UnifiedSidebar;
window.unifiedSidebar = null; // Assigned after initUnifiedSidebar() runs
