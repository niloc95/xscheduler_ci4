/**
 * Scheduler UI Module
 *
 * Handles scheduler toolbar, navigation, and provider legend UI.
 * Manages view switching, date navigation, and provider visibility.
 *
 * @module scheduler/scheduler-ui
 */

/**
 * Update the date display in the toolbar
 * @param {object} scheduler - Scheduler instance
 */
function updateDateDisplay(scheduler) {
    const displayEl = document.getElementById('scheduler-date-display');
    if (!displayEl) return;

    const { currentDate, currentView } = scheduler;
    let displayText = '';

    switch (currentView) {
        case 'day':
            displayText = currentDate.toFormat('EEEE, MMMM d, yyyy');
            break;
        case 'week':
            const weekStart = currentDate.startOf('week');
            const weekEnd = currentDate.endOf('week');
            displayText = `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
            break;
        case 'month':
        default:
            displayText = currentDate.toFormat('MMMM yyyy');
            break;
    }

    displayEl.textContent = displayText;
}

/**
 * Render provider legend with color indicators
 * @param {object} scheduler - Scheduler instance
 */
export function renderProviderLegend(scheduler) {
    const legendEl = document.getElementById('provider-legend');
    if (!legendEl || !scheduler.providers || scheduler.providers.length === 0) return;

    legendEl.innerHTML = scheduler.providers.map(provider => {
        const color = provider.color || '#3B82F6';
        const isVisible = scheduler.visibleProviders.has(provider.id);

        return `
            <button type="button"
                    class="provider-legend-item flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium transition-all duration-200 ${
                        isVisible
                            ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
                            : 'bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500 opacity-50'
                    } hover:bg-gray-200 dark:hover:bg-gray-600"
                    data-provider-id="${provider.id}"
                    title="Toggle ${provider.name}">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: ${color};"></span>
                <span class="truncate max-w-[120px]">${provider.name}</span>
            </button>
        `;
    }).join('');

    // Add click handlers for toggling providers
    legendEl.querySelectorAll('.provider-legend-item').forEach(btn => {
        btn.addEventListener('click', () => {
            const providerId = parseInt(btn.dataset.providerId);
            scheduler.toggleProvider(providerId);
            renderProviderLegend(scheduler); // Re-render to update styles
        });
    });
}

/**
 * Setup the scheduler toolbar with view switching and navigation
 * @param {object} scheduler - Scheduler instance
 * @param {object} options - Additional options
 * @param {Function} options.setupAdvancedFilterPanel - Callback to setup filter panel
 */
export function setupSchedulerToolbar(scheduler, { setupAdvancedFilterPanel } = {}) {
    // View buttons
    document.querySelectorAll('[data-calendar-action="day"], [data-calendar-action="week"], [data-calendar-action="month"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const view = btn.dataset.calendarAction;
            try {
                await scheduler.changeView(view);

                // Update active state
                document.querySelectorAll('[data-calendar-action]').forEach(b => {
                    if (b.dataset.calendarAction === view) {
                        b.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
                        b.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
                    } else if (['day', 'week', 'month'].includes(b.dataset.calendarAction)) {
                        b.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                        b.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
                    }
                });

                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to change view:', error);
            }
        });
    });

    // Today button
    const todayBtn = document.querySelector('[data-calendar-action="today"]');
    if (todayBtn) {
        todayBtn.addEventListener('click', async () => {
            try {
                await scheduler.navigateToToday();
                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to navigate to today:', error);
            }
        });
    }

    // Previous button
    const prevBtn = document.querySelector('[data-calendar-action="prev"]');
    if (prevBtn) {
        prevBtn.addEventListener('click', async () => {
            try {
                await scheduler.navigatePrev();
                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to navigate to previous:', error);
            }
        });
    }

    // Next button
    const nextBtn = document.querySelector('[data-calendar-action="next"]');
    if (nextBtn) {
        nextBtn.addEventListener('click', async () => {
            try {
                await scheduler.navigateNext();
                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to navigate to next:', error);
            }
        });
    }

    // Render provider legend
    renderProviderLegend(scheduler);

    // Setup advanced filter panel
    if (typeof setupAdvancedFilterPanel === 'function') {
        setupAdvancedFilterPanel(scheduler, { renderProviderLegend });
    }

    // Initial date display update
    updateDateDisplay(scheduler);
}
