/**
 * Unified Sidebar JavaScript
 * Clean, consistent sidebar behavior
 */

class UnifiedSidebar {
    constructor() {
        this.sidebar = document.getElementById('main-sidebar');
        this.backdrop = document.getElementById('mobile-backdrop');
        this.menuToggle = document.getElementById('menuToggle');
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
            document.body.style.overflow = 'hidden';
        }
    }
    
    close() {
        this.isOpen = false;
        this.updateState();
        
        // Restore body scroll
        if (this.isMobile) {
            document.body.style.overflow = '';
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
        
        // Update backdrop
        if (this.backdrop) {
            if (this.isMobile && this.isOpen) {
                this.backdrop.classList.remove('hidden');
                this.backdrop.classList.add('open');
            } else {
                this.backdrop.classList.add('hidden');
                this.backdrop.classList.remove('open');
            }
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
    document.addEventListener('DOMContentLoaded', initUnifiedSidebar);
} else {
    initUnifiedSidebar();
}

// Export for global access
window.UnifiedSidebar = UnifiedSidebar;
window.unifiedSidebar = unifiedSidebar;
