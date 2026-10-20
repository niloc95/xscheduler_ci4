/**
 * Status stats bar for SchedulerCore: per-view counts rendered as clickable
 * status pills that drive the status filter.
 *
 * Mixed into SchedulerCore.prototype — methods use `this` as the scheduler.
 */

import { getStatsForView, STATUS_DEFINITIONS, getStatusDef, getViewTitle } from './stats/index.js';

export const statsBarMethods = {
    /**
     * Initialize the Stats Bar component
     * Uses simple inline counting (same approach as Day Summary which works 100%)
     */
    initStatsBar() {
        this.statsBarContainer = document.getElementById('scheduler-stats-bar');
        if (!this.statsBarContainer) {
            this.debugLog('📊 Stats bar container not found, skipping stats bar init');
            return;
        }
        
        // Listen for filter clicks from stats bar pills
        this.statsBarContainer.addEventListener('click', (e) => {
            const pill = e.target.closest('[data-filter-status]');
            if (pill) {
                const status = pill.dataset.filterStatus;
                this.debugLog('📊 Stats bar filter clicked:', status);
                
                // Toggle filter - if already active, clear it
                const currentStatus = this.activeFilters?.status;
                this.setStatusFilter(status === currentStatus ? null : status);
            }
        });
        
        this.debugLog('📊 Stats bar initialized');
    },

    /**
     * Update stats bar with current data
     * Uses Stats Engine for consistent calculation (Car Analogy: Engine powers all views)
     */
    updateStatsBar() {
        if (!this.statsBarContainer) return;
        
        // Use Stats Engine for calculation (single source of truth)
        const stats = getStatsForView(this.appointments, this.currentView, this.currentDate);
        const activeFilter = this.activeFilters?.status || null;
        
        // Get view-appropriate title from config
        const title = getViewTitle(this.currentView, this.currentDate);
        
        // Get date range display (shared with updateDateDisplay)
        const dateRange = this.getDateRangeText();
        
        // Render the stats bar using definitions from Stats Definitions
        const statusPills = Object.keys(STATUS_DEFINITIONS).map(statusKey => {
            const def = getStatusDef(statusKey);
            const count = stats.byStatus[statusKey] || 0;
            return this.renderStatusPill(statusKey, def.label, count, activeFilter, def);
        }).join('');
        
        // Compact inline rendering — pills only (no card wrapper)
        this.statsBarContainer.innerHTML = `
            ${statusPills}
            ${activeFilter ? `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-xs text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-500">
                    Showing: <span class="font-medium capitalize">${activeFilter.replace('-', ' ')}</span>
                    <button type="button" data-filter-status="${activeFilter}" class="ml-0.5 font-bold hover:text-blue-900 dark:hover:text-blue-100" title="Clear filter">&times;</button>
                </span>
            ` : ''}
        `;
        
        this.debugLog('📊 Stats bar updated:', stats.byStatus);
    },

    /**
     * Render a status pill for the stats bar
     * Uses STATUS_DEFINITIONS for consistent colors (Car Analogy: Sensors)
     * 
     * @param {string} status - Status key
     * @param {string} label - Display label
     * @param {number} count - Count to display
     * @param {string|null} activeFilter - Currently active filter
     * @param {Object} def - Status definition from STATUS_DEFINITIONS
     */
    renderStatusPill(status, label, count, activeFilter, def) {
        const isActive = activeFilter === status;
        const ringClass = isActive ? 'ring-2 ring-blue-500' : '';
        
        // Use colors from status definition
        const colors = def.colors;
        const bgClass = `${colors.light.bg} ${colors.dark.bg}`;
        const borderClass = `${colors.light.border} ${colors.dark.border}`;
        const textClass = `${colors.light.text} ${colors.dark.text}`;
        const dotClass = `${colors.light.dot} ${colors.dark.dot}`;
        
        return `
            <button type="button" 
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border cursor-pointer transition-all
                           ${bgClass} ${borderClass} ${textClass}
                           hover:ring-2 hover:ring-offset-1 ${ringClass}"
                    data-filter-status="${status}"
                    title="Filter by ${label}">
                <span class="w-1.5 h-1.5 rounded-full ${dotClass}"></span>
                <span class="text-xs font-medium">${label}</span>
                <span class="text-xs font-bold">${count}</span>
            </button>
        `;
    }
};
