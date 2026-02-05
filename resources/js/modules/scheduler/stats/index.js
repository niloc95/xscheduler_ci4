/**
 * Stats System - Unified Export
 * 
 * Single entry point for the Stats Bar system.
 * Import from here, not individual files.
 * 
 * Architecture (Car Analogy):
 * 
 * ┌─────────────────────────────────────────────────────────────────┐
 * │                        STATS SYSTEM                             │
 * ├─────────────────────────────────────────────────────────────────┤
 * │                                                                 │
 * │  ┌─────────────────┐    ┌─────────────────┐                    │
 * │  │  Stats Engine   │    │ Stats Definitions│                   │
 * │  │  (The Engine)   │    │  (The Sensors)   │                   │
 * │  │                 │    │                  │                   │
 * │  │ • computeStats  │    │ • STATUS_DEFS    │                   │
 * │  │ • getStatsFor   │    │ • STAT_TYPES     │                   │
 * │  │   View/Range    │    │ • Color tokens   │                   │
 * │  └────────┬────────┘    └────────┬─────────┘                   │
 * │           │                      │                              │
 * │           └──────────┬───────────┘                              │
 * │                      │                                          │
 * │           ┌──────────▼──────────┐                               │
 * │           │      Stats Bar      │                               │
 * │           │   (The Dashboard)   │                               │
 * │           │                     │                               │
 * │           │ • Renders stats     │                               │
 * │           │ • Handles clicks    │                               │
 * │           │ • Dispatches events │                               │
 * │           └──────────┬──────────┘                               │
 * │                      │                                          │
 * │    ┌─────────────────┼─────────────────┐                        │
 * │    │                 │                 │                        │
 * │    ▼                 ▼                 ▼                        │
 * │ ┌──────┐         ┌──────┐         ┌──────┐                      │
 * │ │ Day  │         │ Week │         │Month │   (The Bodies)       │
 * │ │Config│         │Config│         │Config│                      │
 * │ │ BMW  │         │Volvo │         │Toyota│                      │
 * │ └──────┘         └──────┘         └──────┘                      │
 * │                                                                 │
 * └─────────────────────────────────────────────────────────────────┘
 * 
 * Usage:
 * 
 * ```javascript
 * import { 
 *     getStatsForView, 
 *     createStatsBar, 
 *     STATUS_DEFINITIONS 
 * } from './stats/index.js';
 * 
 * // Get stats (engine does computation)
 * const stats = getStatsForView(appointments, 'day', currentDate);
 * 
 * // Create bar (consumes stats, uses view config)
 * const bar = createStatsBar('stats-container');
 * bar.update(stats, 'day', currentDate);
 * 
 * // Listen for filter clicks
 * container.addEventListener('statsbar:filter', (e) => {
 *     scheduler.setStatusFilter(e.detail.status);
 * });
 * ```
 * 
 * @module scheduler/stats
 */

// The Engine - Pure calculation logic
export { 
    getStatsForView, 
    getStatsForRange, 
    calculateDateRange,
    compareStats 
} from './stats-engine.js';

// The Sensors - Single source of truth for definitions
export { 
    STATUS_DEFINITIONS, 
    STAT_TYPES,
    getStatusDef,
    getActiveStatuses,
    getStatusesByPriority,
    getStatusHex,
    getStatusClasses
} from './stats-definitions.js';

// The Body Configs - View-specific preferences
export { 
    DAY_VIEW_CONFIG, 
    WEEK_VIEW_CONFIG, 
    MONTH_VIEW_CONFIG,
    getViewConfig,
    getViewTitle
} from './stats-view-configs.js';

// The Dashboard - UI Component
export { 
    StatsBar, 
    createStatsBar 
} from './stats-bar.js';
