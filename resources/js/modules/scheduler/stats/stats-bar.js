/**
 * Stats Bar Component - The Dashboard Display
 * 
 * Reusable UI component that renders statistics.
 * Consumes data from Stats Engine, respects View Configurations.
 * 
 * Car Analogy: This is the DASHBOARD - displays readings from sensors,
 * but the layout/styling adapts to the car body (view configuration).
 * 
 * RULES:
 * - Never computes stats (only displays)
 * - Never contains view-specific logic
 * - Reads configuration, not if-statements
 * 
 * @module scheduler/stats-bar
 */

import { STATUS_DEFINITIONS, STAT_TYPES, getStatusDef } from './stats-definitions.js';
import { getViewConfig, getViewTitle } from './stats-view-configs.js';

/**
 * Stats Bar UI Component
 */
export class StatsBar {
    /**
     * @param {HTMLElement} container - Container element for the stats bar
     * @param {Object} options - Configuration options
     */
    constructor(container, options = {}) {
        this.container = container;
        this.options = options;
        this.currentStats = null;
        this.currentConfig = null;
        this.currentDate = null;
    }
    
    /**
     * Update the stats bar with new data
     * 
     * @param {Object} stats - Stats from StatsEngine.getStatsForView()
     * @param {string} viewType - Current view type
     * @param {DateTime} date - Current date context
     */
    update(stats, viewType, date) {
        this.currentStats = stats;
        this.currentConfig = getViewConfig(viewType);
        this.currentDate = date;
        this.render();
    }
    
    /**
     * Render the stats bar
     */
    render() {
        if (!this.container || !this.currentStats) return;
        
        const config = this.currentConfig;
        const stats = this.currentStats;
        
        this.container.innerHTML = `
            <div class="stats-bar stats-bar--${config.viewType}" data-view="${config.viewType}">
                ${this.renderHeader()}
                <div class="stats-bar__content">
                    ${this.renderPrimaryStats()}
                    ${this.renderStatusBreakdown()}
                    ${config.display.showTrends ? this.renderSecondaryStats() : ''}
                </div>
            </div>
        `;
        
        this.bindEvents();
    }
    
    /**
     * Render header with title and total
     */
    renderHeader() {
        const title = getViewTitle(this.currentConfig.viewType, this.currentDate);
        const total = this.currentStats.total;
        
        return `
            <div class="stats-bar__header flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">calendar_today</span>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">${title}</h3>
                    </div>
                </div>
                ${this.currentConfig.display.showTotal ? `
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">${this.formatNumber(total)}</span>
                ` : ''}
            </div>
        `;
    }
    
    /**
     * Render primary statistics
     */
    renderPrimaryStats() {
        const items = this.currentConfig.primaryStats || [];
        if (!items.length) return '';
        
        const statsHtml = items.map(item => {
            const statDef = STAT_TYPES[item.key];
            if (!statDef) return '';
            
            const value = this.currentStats[item.key];
            const formattedValue = this.formatValue(value, statDef.format);
            const emphasisClass = this.getEmphasisClass(item.emphasis);
            
            return `
                <div class="stats-bar__primary-stat ${emphasisClass}">
                    ${item.showIcon ? `<span class="material-symbols-outlined text-gray-500 dark:text-gray-400">${statDef.icon}</span>` : ''}
                    <span class="stats-bar__value">${formattedValue}</span>
                    <span class="stats-bar__label">${statDef.shortLabel}</span>
                </div>
            `;
        }).join('');
        
        return `<div class="stats-bar__primary flex items-center gap-4 px-4 py-2">${statsHtml}</div>`;
    }
    
    /**
     * Render status breakdown pills
     */
    renderStatusBreakdown() {
        const config = this.currentConfig.statusBreakdown;
        if (!config?.show) return '';
        
        const statuses = config.statuses || Object.keys(STATUS_DEFINITIONS);
        
        const pillsHtml = statuses.map(statusKey => {
            const def = getStatusDef(statusKey);
            const count = this.currentStats.byStatus[statusKey] || 0;
            const label = config.showLabels === 'full' ? def.label : def.shortLabel;
            
            return `
                <button type="button" 
                        class="stats-bar__status-pill group flex items-center gap-1.5 px-2.5 py-1 rounded-full border
                               ${def.colors.light.bg} ${def.colors.dark.bg}
                               ${def.colors.light.border} ${def.colors.dark.border}
                               hover:ring-2 hover:ring-offset-1 hover:ring-blue-400
                               transition-all cursor-pointer"
                        data-filter-status="${statusKey}"
                        title="Filter by ${def.label}">
                    <span class="w-2 h-2 rounded-full ${def.colors.light.dot} ${def.colors.dark.dot}"></span>
                    ${config.showLabels ? `<span class="text-xs font-medium ${def.colors.light.text} ${def.colors.dark.text}">${label}</span>` : ''}
                    ${config.showCounts ? `<span class="text-xs font-bold ${def.colors.light.text} ${def.colors.dark.text}">${count}</span>` : ''}
                </button>
            `;
        }).join('');
        
        const layoutClass = config.layout === 'vertical' ? 'flex-col' : 'flex-row flex-wrap';
        
        return `
            <div class="stats-bar__status-breakdown flex ${layoutClass} items-center gap-2 px-4 py-2">
                ${pillsHtml}
            </div>
        `;
    }
    
    /**
     * Render secondary statistics (rates, trends)
     */
    renderSecondaryStats() {
        const items = this.currentConfig.secondaryStats || [];
        if (!items.length) return '';
        
        const statsHtml = items.map(item => {
            const statDef = STAT_TYPES[item.key];
            if (!statDef) return '';
            
            const value = this.currentStats[item.key];
            const formattedValue = this.formatValue(value, statDef.format);
            
            return `
                <div class="stats-bar__secondary-stat flex items-center gap-1 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">${statDef.shortLabel}:</span>
                    <span class="font-medium text-gray-700 dark:text-gray-200">${formattedValue}</span>
                </div>
            `;
        }).join('');
        
        return `
            <div class="stats-bar__secondary flex items-center gap-4 px-4 py-2 border-t border-gray-100 dark:border-gray-700/50">
                ${statsHtml}
            </div>
        `;
    }
    
    /**
     * Format value based on type
     */
    formatValue(value, format) {
        if (value == null) return '—';
        
        switch (format) {
            case 'percent':
                return `${Math.round(value)}%`;
            case 'number':
            default:
                return this.formatNumber(value);
        }
    }
    
    /**
     * Format number with locale
     */
    formatNumber(value) {
        return new Intl.NumberFormat().format(value || 0);
    }
    
    /**
     * Get CSS class for emphasis level
     */
    getEmphasisClass(emphasis) {
        const classes = {
            high: 'stats-bar__stat--high text-lg font-bold',
            medium: 'stats-bar__stat--medium text-base font-semibold',
            low: 'stats-bar__stat--low text-sm font-medium text-gray-500'
        };
        return classes[emphasis] || classes.medium;
    }
    
    /**
     * Bind click events for status filtering
     */
    bindEvents() {
        const pills = this.container.querySelectorAll('[data-filter-status]');
        pills.forEach(pill => {
            pill.addEventListener('click', (e) => {
                const status = e.currentTarget.dataset.filterStatus;
                this.dispatchFilterEvent(status);
            });
        });
    }
    
    /**
     * Dispatch filter event for status clicks
     */
    dispatchFilterEvent(status) {
        const event = new CustomEvent('statsbar:filter', {
            bubbles: true,
            detail: { status }
        });
        this.container.dispatchEvent(event);
    }
    
    /**
     * Clean up
     */
    destroy() {
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
}

/**
 * Factory function for creating stats bar instances
 * 
 * @param {string|HTMLElement} containerOrId - Container element or ID
 * @param {Object} options - Configuration options
 * @returns {StatsBar} Stats bar instance
 */
export function createStatsBar(containerOrId, options = {}) {
    const container = typeof containerOrId === 'string' 
        ? document.getElementById(containerOrId) 
        : containerOrId;
    
    if (!container) {
        console.warn('[StatsBar] Container not found');
        return null;
    }
    
    return new StatsBar(container, options);
}
