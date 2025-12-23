/**
 * Chart.js integration for xScheduler Dashboard
 * Optimized with Material Design 3 color tokens, caching, and debouncing
 */
import Chart from 'chart.js/auto';

// Store chart instances to prevent duplicates
const chartInstances = {};

// API data cache
let chartDataCache = null;
let cacheTimestamp = null;
const CACHE_DURATION = 30000; // 30 seconds

// Debounce timer for filter changes
let filterDebounceTimer = null;
const DEBOUNCE_DELAY = 300;

// Current filter period
let currentPeriod = 'month';

// Get base URL for API calls
const getBaseUrl = () => (window.__BASE_URL__ || '').replace(/\/+$/, '');

// Dark mode detection
const isDarkMode = () => document.documentElement.classList.contains('dark');

/**
 * Get computed CSS variable value
 * @param {string} varName - CSS variable name
 * @param {string} fallback - Fallback value
 * @returns {string} Computed color value
 */
function getCssVar(varName, fallback = '#000') {
    const value = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
    return value || fallback;
}

/**
 * Convert color to rgba with opacity
 * @param {string} color - Color value
 * @param {number} opacity - Opacity (0-1)
 * @returns {string} RGBA color string
 */
function withOpacity(color, opacity) {
    if (color.startsWith('rgba')) {
        return color.replace(/[\d.]+\)$/, `${opacity})`);
    }
    if (color.startsWith('rgb(')) {
        return color.replace('rgb(', 'rgba(').replace(')', `, ${opacity})`);
    }
    if (color.startsWith('#')) {
        const hex = color.slice(1);
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }
    return color;
}

/**
 * Get chart colors using Material Design 3 tokens
 * @returns {Object} Color configuration
 */
const getChartColors = () => {
    const dark = isDarkMode();
    
    // Try Material Design 3 tokens first, with theme-appropriate fallbacks
    const primary = getCssVar('--md-sys-color-primary', dark ? '#a0c4ff' : '#1a73e8');
    const secondary = getCssVar('--md-sys-color-secondary', dark ? '#c9b8ff' : '#7c4dff');
    const tertiary = getCssVar('--md-sys-color-tertiary', dark ? '#ffb4ab' : '#ff6d00');
    const outline = getCssVar('--md-sys-color-outline', dark ? '#8e918f' : '#747775');
    const onSurface = getCssVar('--md-sys-color-on-surface', dark ? '#e3e3e3' : '#1f1f1f');
    
    return {
        grid: withOpacity(outline, 0.3),
        text: onSurface,
        primary: primary,
        primaryBg: withOpacity(primary, dark ? 0.25 : 0.15),
        secondary: secondary,
        secondaryBg: withOpacity(secondary, 0.5),
        // Extended palette for bar charts
        palette: [
            getCssVar('--md-sys-color-primary', dark ? '#a0c4ff' : '#1a73e8'),
            getCssVar('--md-sys-color-tertiary', dark ? '#ffb4ab' : '#ff6d00'),
            getCssVar('--md-sys-color-secondary', dark ? '#c9b8ff' : '#7c4dff'),
            getCssVar('--md-sys-color-success', dark ? '#81c995' : '#34a853'),
            getCssVar('--md-sys-color-warning', dark ? '#fdd663' : '#fbbc04'),
            getCssVar('--md-sys-color-error', dark ? '#f28b82' : '#ea4335'),
            dark ? '#80deea' : '#00acc1', // Cyan
            dark ? '#ce93d8' : '#ab47bc', // Purple
            dark ? '#a5d6a7' : '#66bb6a', // Green
            dark ? '#bcaaa4' : '#8d6e63', // Brown
        ]
    };
};

/**
 * Get chart configuration options
 * @returns {Object} Chart.js options
 */
const getChartOptions = () => {
    const colors = getChartColors();
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 400,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                display: false // Hide legend for cleaner look
            },
            tooltip: {
                backgroundColor: isDarkMode() ? '#374151' : '#ffffff',
                titleColor: colors.text,
                bodyColor: colors.text,
                borderColor: colors.grid,
                borderWidth: 1,
                padding: 12,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return `${context.parsed.y} appointments`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: colors.grid,
                },
                ticks: {
                    color: colors.text,
                    stepSize: 5,
                    callback: function(value) {
                        return value; // Just show the number
                    }
                },
                title: {
                    display: true,
                    text: 'Appointments',
                    color: colors.text,
                    font: {
                        size: 11
                    }
                }
            },
            x: {
                grid: {
                    display: false // Cleaner without vertical gridlines
                },
                ticks: {
                    color: colors.text
                }
            },
        },
    };
};

/**
 * Fetch chart data with caching
 * @param {boolean} forceRefresh - Force cache refresh
 * @returns {Promise<Object>} Chart data
 */
async function fetchChartData(forceRefresh = false) {
    const now = Date.now();
    
    // Return cached data if valid
    if (!forceRefresh && chartDataCache && cacheTimestamp && (now - cacheTimestamp) < CACHE_DURATION) {
        return chartDataCache;
    }
    
    try {
        const response = await fetch(`${getBaseUrl()}/dashboard/charts?period=${currentPeriod}`);
        if (response.ok) {
            chartDataCache = await response.json();
            cacheTimestamp = now;
            return chartDataCache;
        }
    } catch (error) {
        // Silent fail - return cached data or null
    }
    
    return chartDataCache || null;
}

/**
 * Clear the data cache (used when period changes)
 */
function clearCache() {
    chartDataCache = null;
    cacheTimestamp = null;
}

/**
 * Period hint text for UI
 */
const periodHints = {
    day: "Today's schedule by hour (* = current)",
    week: "This week Mon-Sun (* = today)",
    month: "Rolling 4-week view (* = current week)",
    year: "Rolling 12-month view (* = current month)"
};

/**
 * Update the period hint text in the UI
 */
function updatePeriodHint(period) {
    const hint = document.getElementById('chartPeriodHint');
    if (hint) {
        hint.textContent = periodHints[period] || periodHints.month;
    }
}

/**
 * Set chart period filter with debouncing
 * @param {string} period - Period: 'day', 'week', 'month', 'year'
 */
export function setChartPeriod(period) {
    if (filterDebounceTimer) {
        clearTimeout(filterDebounceTimer);
    }
    
    // Update hint immediately for better UX
    updatePeriodHint(period);
    
    filterDebounceTimer = setTimeout(async () => {
        currentPeriod = period;
        clearCache();
        await initAllCharts();
        
        // Update active filter button styling
        document.querySelectorAll('[data-chart-period]').forEach(btn => {
            const isActive = btn.dataset.chartPeriod === period;
            btn.classList.toggle('active', isActive);
            // Update button colors
            if (isActive) {
                btn.classList.add('bg-blue-100', 'dark:bg-blue-900', 'text-blue-600', 'dark:text-blue-300');
                btn.classList.remove('text-gray-600', 'dark:text-gray-400');
            } else {
                btn.classList.remove('bg-blue-100', 'dark:bg-blue-900', 'text-blue-600', 'dark:text-blue-300');
                btn.classList.add('text-gray-600', 'dark:text-gray-400');
            }
        });
    }, DEBOUNCE_DELAY);
}

/**
 * Initialize Appointment Volume Chart
 * @param {string} canvasId - Canvas element ID
 * @returns {Promise<Chart|null>} Chart instance
 */
export async function initUserGrowthChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    const colors = getChartColors();
    const chartData = await fetchChartData();
    
    let labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
    let data = [0, 0, 0, 0];
    
    if (chartData?.appointmentGrowth) {
        labels = chartData.appointmentGrowth.labels || labels;
        data = chartData.appointmentGrowth.data || data;
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Appointments',
                data: data,
                borderColor: colors.primary,
                backgroundColor: colors.primaryBg,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: colors.primary,
                pointBorderColor: colors.primary,
                pointHoverRadius: 6
            }]
        },
        options: getChartOptions()
    });
    
    return chartInstances[canvasId];
}

/**
 * Initialize Services by Provider Chart
 * @param {string} canvasId - Canvas element ID
 * @returns {Promise<Chart|null>} Chart instance
 */
export async function initActivityChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    const colors = getChartColors();
    const chartData = await fetchChartData();
    
    let labels = ['No Data'];
    let data = [0];
    
    if (chartData?.servicesByProvider?.labels?.length > 0) {
        labels = chartData.servicesByProvider.labels;
        data = chartData.servicesByProvider.data;
    }
    
    // Generate background colors from Material palette
    const backgroundColors = labels.map((_, i) => 
        withOpacity(colors.palette[i % colors.palette.length], 0.8)
    );
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Appointments by Provider',
                data: data,
                backgroundColor: backgroundColors,
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 400,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid,
                    },
                    ticks: {
                        color: colors.text
                    }
                },
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: colors.text
                    }
                },
            },
        }
    });
    
    return chartInstances[canvasId];
}

/**
 * Initialize Appointment Status Chart (Doughnut)
 * @param {string} canvasId - Canvas element ID
 * @returns {Promise<Chart|null>} Chart instance
 */
export async function initStatusChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    const colors = getChartColors();
    const chartData = await fetchChartData();
    
    let labels = ['No Data'];
    let data = [1];
    let backgroundColors = ['#9aa0a6'];
    
    if (chartData?.statusDistribution?.labels?.length > 0) {
        labels = chartData.statusDistribution.labels;
        data = chartData.statusDistribution.data;
        backgroundColors = chartData.statusDistribution.colors || [
            '#34a853', '#fbbc04', '#1a73e8', '#ea4335', '#9aa0a6'
        ];
    }
    
    // Update the status breakdown numbers
    updateStatusBreakdown(labels, data);
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderWidth: 2,
                borderColor: isDarkMode() ? '#1f2937' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 400,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: colors.text,
                        padding: 12,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
            },
            cutout: '60%'
        }
    });
    
    return chartInstances[canvasId];
}

/**
 * Update the status breakdown numbers in the dashboard
 */
function updateStatusBreakdown(labels, data) {
    const statusMapping = {
        'Confirmed': 'confirmedCount',
        'Pending': 'pendingCount',
        'Completed': 'completedCount',
        'Cancelled': 'cancelledCount',
        'No-show': 'noShowCount',
        'No-Show': 'noShowCount'
    };
    
    // Reset all to 0 first
    Object.values(statusMapping).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '0';
    });
    
    // Update with actual data
    labels.forEach((label, index) => {
        const elementId = statusMapping[label];
        if (elementId) {
            const el = document.getElementById(elementId);
            if (el) el.textContent = data[index].toLocaleString();
        }
    });
}

/**
 * Initialize all charts (uses single API call via cache)
 * @returns {Promise<Object>} Chart instances
 */
export async function initAllCharts() {
    // Prefetch data once for all charts
    await fetchChartData();
    
    const [userChart, activityChart, statusChart] = await Promise.all([
        initUserGrowthChart('userGrowthChart'),
        initActivityChart('activityChart'),
        initStatusChart('statusChart')
    ]);
    
    return {
        userChart,
        activityChart,
        statusChart
    };
}

/**
 * Set up dark mode listener for chart color updates
 * Uses debouncing to prevent excessive re-renders
 */
export function setupDarkModeListener() {
    let darkModeDebounce = null;
    
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                // Debounce dark mode changes
                if (darkModeDebounce) {
                    clearTimeout(darkModeDebounce);
                }
                darkModeDebounce = setTimeout(() => {
                    // Clear cache to refresh colors
                    clearCache();
                    initAllCharts();
                }, 150);
            }
        });
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
}

/**
 * Initialize filter button event listeners
 */
export function initChartFilters() {
    document.querySelectorAll('[data-chart-period]').forEach(btn => {
        btn.addEventListener('click', () => {
            setChartPeriod(btn.dataset.chartPeriod);
        });
    });
}

// Export default for easy import
export default {
    initUserGrowthChart,
    initActivityChart,
    initStatusChart,
    initAllCharts,
    setupDarkModeListener,
    setChartPeriod,
    initChartFilters
};