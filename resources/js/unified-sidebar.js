/**
 * Unified Sidebar JavaScript
 * Clean, consistent sidebar behavior
 */

import { apiRequest } from './core/api.js';
import { onDomReady } from './core/lifecycle.js';
import { SEL } from './core/selectors.js';

class UnifiedSidebar {
    constructor() {
        this.sidebar     = document.querySelector(SEL.SIDEBAR);
        this.backdrop    = document.querySelector(SEL.SIDEBAR_OVERLAY);
        this.menuToggle  = document.querySelector(SEL.MENU_TOGGLE);
        this.closeButton = document.querySelector(SEL.SIDEBAR_CLOSE);
        this.shareWrapper = null;
        this.shareToggle = null;
        this.shareMenu = null;
        this.shareUrl = '';
        this.handleShareOutsideClickBound = null;
        this.handleShareEscapeBound = null;
        
        this.isOpen = false;
        this.isMobile = false;
        
        this.init();
    }
    
    init() {
        if (!this.sidebar) return;
        
        this.checkMobile();
        this.bindEvents();
        this.handleResize();
        this.initShareActions();
        
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
        const roleSwitcher = document.querySelector(SEL.ROLE_SWITCHER);
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

    initShareActions() {
        if (!this.sidebar) return;

        this.shareWrapper = this.sidebar.querySelector('#booking-share-wrapper');
        this.shareToggle = this.sidebar.querySelector('#booking-share-toggle');
        this.shareMenu = this.sidebar.querySelector('#booking-share-menu');
        this.shareUrl = this.shareWrapper?.dataset?.bookingUrl || `${window.location.origin}/booking`;

        if (!this.shareWrapper || !this.shareToggle || !this.shareMenu) {
            return;
        }

        this.shareToggle.addEventListener('click', (event) => {
            event.preventDefault();
            this.toggleShareMenu();
        });

        const actionButtons = this.shareWrapper.querySelectorAll('.booking-share-action');
        actionButtons.forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const action = button.dataset.action;
                await this.handleShareAction(action);
                this.closeShareMenu();
            });
        });
    }

    toggleShareMenu() {
        if (!this.shareMenu) return;
        if (this.shareMenu.classList.contains('hidden')) {
            this.openShareMenu();
            return;
        }
        this.closeShareMenu();
    }

    openShareMenu() {
        if (!this.shareMenu || !this.shareToggle) return;

        this.shareMenu.classList.remove('hidden');
        this.shareToggle.setAttribute('aria-expanded', 'true');

        if (!this.handleShareOutsideClickBound) {
            this.handleShareOutsideClickBound = (event) => {
                if (!this.shareWrapper?.contains(event.target)) {
                    this.closeShareMenu();
                }
            };
        }

        if (!this.handleShareEscapeBound) {
            this.handleShareEscapeBound = (event) => {
                if (event.key === 'Escape') {
                    this.closeShareMenu();
                }
            };
        }

        document.addEventListener('click', this.handleShareOutsideClickBound);
        document.addEventListener('keydown', this.handleShareEscapeBound);
    }

    closeShareMenu() {
        if (!this.shareMenu || !this.shareToggle) return;

        this.shareMenu.classList.add('hidden');
        this.shareToggle.setAttribute('aria-expanded', 'false');

        if (this.handleShareOutsideClickBound) {
            document.removeEventListener('click', this.handleShareOutsideClickBound);
        }
        if (this.handleShareEscapeBound) {
            document.removeEventListener('keydown', this.handleShareEscapeBound);
        }
    }

    async handleShareAction(action) {
        const url = this.shareUrl;
        const message = `Book your appointment here: ${url}`;

        if (action === 'email') {
            const subject = encodeURIComponent('Book an appointment');
            const body = encodeURIComponent(message);
            window.location.href = `mailto:?subject=${subject}&body=${body}`;
            return;
        }

        if (action === 'whatsapp') {
            const text = encodeURIComponent(message);
            window.open(`https://wa.me/?text=${text}`, '_blank', 'noopener');
            return;
        }

        if (action === 'copy') {
            const copied = await this.copyText(url);
            if (copied) {
                this.showToast('success', 'Booking link copied to clipboard.');
                return;
            }

            this.showToast('error', 'Could not copy link. Please copy it manually.', false);
        }
    }

    async copyText(text) {
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (error) {
                // Fallback to legacy copy path.
            }
        }

        try {
            const input = document.createElement('input');
            input.type = 'text';
            input.value = text;
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.focus();
            input.select();
            const success = document.execCommand('copy');
            document.body.removeChild(input);
            return success;
        } catch (error) {
            return false;
        }
    }

    showToast(type, message, autoClose = true) {
        if (window.XSNotify?.toast) {
            window.XSNotify.toast({ type, message, autoClose });
            return;
        }

        if (type === 'error') {
            window.alert(message);
            return;
        }

        console.log(message);
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
        this.closeShareMenu();
        
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
        const navLinks = this.sidebar?.querySelectorAll(SEL.NAV_LINKS);
        
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
            const { response, payload } = await apiRequest('/api/auth/switch-role', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: { role: newRole },
            });

            if (!response.ok) {
                const error = payload || {};
                console.error('Role switch failed:', error);
                alert('Failed to switch role. Please try again.');
                return;
            }

            const result = payload || {};
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
onDomReady(() => {
    initUnifiedSidebar();
    // Assign instance after creation
    if (unifiedSidebar instanceof UnifiedSidebar) {
        window.unifiedSidebar = unifiedSidebar;
    }
});

// Export for global access
window.UnifiedSidebar = UnifiedSidebar;
window.unifiedSidebar = null; // Assigned after initUnifiedSidebar() runs
