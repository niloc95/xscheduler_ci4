/**
 * =============================================================================
 * ANALYTICS CHARTS MODULE
 * =============================================================================
 * 
 * @file        resources/js/modules/analytics/analytics-charts.js
 * @description Handles rendering of analytics charts using Chart.js
 * @package     WebScheduler
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

import { Chart, registerables } from 'chart.js';
import { formatCurrency } from '../../currency.js';

// Register Chart.js components
Chart.register(...registerables);

// Dark mode detection
import { isDarkMode } from '../../utils/dark-mode-detector.js';

// Get theme-aware colors
const getChartColors = () => {
    const dark = isDarkMode();
    return {
        text: dark ? '#e5e7eb' : '#374151',
        grid: dark ? '#374151' : '#e5e7eb',
        border: dark ? '#4b5563' : '#d1d5db',
        primary: '#3b82f6',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        purple: '#8b5cf6',
        background: {
            primary: dark ? 'rgba(59, 130, 246, 0.1)' : 'rgba(59, 130, 246, 0.1)',
            success: dark ? 'rgba(16, 185, 129, 0.1)' : 'rgba(16, 185, 129, 0.1)',
        }
    };
};

function formatLocalizedCurrency(amount, currencySymbol = null, decimals = 2) {
    return formatCurrency(amount, {
        currencySymbol,
        decimals,
    });
}

function decodePayload(encodedPayload, fallback = {}) {
    if (!encodedPayload) {
        return fallback;
    }

    try {
        return JSON.parse(atob(encodedPayload));
    } catch {
        return fallback;
    }
}

/**
 * Initialize revenue trend chart
 */
export function initRevenueTrendChart(canvasId, revenueData, type = 'daily', currencySymbol = null) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const ctx = canvas.getContext('2d');
    const colors = getChartColors();

    // Prepare data
    const data = type === 'daily' ? revenueData.daily : revenueData.monthly;
    const labels = data.map(item => item.date || item.month);
    const values = data.map(item => parseFloat(item.revenue) || 0);

    // Destroy existing chart if any
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: values,
                borderColor: colors.success,
                backgroundColor: colors.background.success,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: colors.success,
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: isDarkMode() ? '#1f2937' : '#fff',
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return formatLocalizedCurrency(context.parsed.y, currencySymbol, 2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: colors.text,
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid,
                        drawBorder: false
                    },
                    ticks: {
                        color: colors.text,
                        callback: function(value) {
                            return formatLocalizedCurrency(value, currencySymbol, 0);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Initialize appointments by time slot chart
 */
export function initTimeSlotChart(canvasId, timeSlotData) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const ctx = canvas.getContext('2d');
    const colors = getChartColors();

    // Prepare data
    const labels = timeSlotData.map(item => item.time_slot);
    const values = timeSlotData.map(item => parseInt(item.count) || 0);

    // Destroy existing chart if any
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Appointments',
                data: values,
                backgroundColor: colors.primary,
                borderColor: colors.primary,
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: isDarkMode() ? '#1f2937' : '#fff',
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: colors.text
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid,
                        drawBorder: false
                    },
                    ticks: {
                        color: colors.text,
                        stepSize: 1
                    }
                }
            }
        }
    });
}

/**
 * Initialize service distribution doughnut chart
 */
export function initServiceDistributionChart(canvasId, servicesData) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const ctx = canvas.getContext('2d');
    const colors = getChartColors();

    // Prepare data
    const labels = servicesData.map(item => item.name);
    const values = servicesData.map(item => parseInt(item.bookings) || 0);

    // Generate colors for each service
    const backgroundColors = [
        colors.primary,
        colors.success,
        colors.warning,
        colors.danger,
        colors.purple,
        '#ec4899',
        '#14b8a6',
        '#f97316',
        '#6366f1',
        '#84cc16'
    ];

    // Destroy existing chart if any
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: backgroundColors.slice(0, values.length),
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: colors.text,
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: isDarkMode() ? '#1f2937' : '#fff',
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

export function initNewVsReturningChart(canvasId, customerBreakdown) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const colors = getChartColors();
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    return new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['New', 'Returning'],
            datasets: [{
                data: [customerBreakdown.new || 0, customerBreakdown.returning || 0],
                backgroundColor: [colors.primary, colors.success],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: colors.text,
                        usePointStyle: true,
                        pointStyle: 'circle',
                    },
                },
                tooltip: {
                    backgroundColor: isDarkMode() ? '#1f2937' : '#fff',
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                },
            },
        },
    });
}

export function initPeakHoursChart(canvasId, timeSlotData) {
    const normalizedData = Object.entries(timeSlotData || {}).map(([timeSlot, count]) => ({
        time_slot: timeSlot,
        count,
    }));

    return initTimeSlotChart(canvasId, normalizedData);
}

export function initRevenueByProviderChart(canvasId, providerRows, currencySymbol = null) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const colors = getChartColors();
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    const labels = (providerRows || []).map((row) => row.name || 'Unknown');
    const values = (providerRows || []).map((row) => parseFloat(row.revenue) || 0);

    return new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors.primary,
                borderRadius: 6,
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: isDarkMode() ? '#1f2937' : '#fff',
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return formatLocalizedCurrency(context.parsed.x, currencySymbol, 2);
                        },
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid,
                        drawBorder: false,
                    },
                    ticks: {
                        color: colors.text,
                        callback: function(value) {
                            return formatLocalizedCurrency(value, currencySymbol, 0);
                        },
                    },
                },
                y: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: colors.text,
                    },
                },
            },
        },
    });
}

export function initMoMComparisonChart(canvasId, comparisonData, currencySymbol = null) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const colors = getChartColors();
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    return new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Revenue Window'],
            datasets: [
                {
                    label: 'Current',
                    data: [parseFloat(comparisonData.current_total) || 0],
                    backgroundColor: colors.success,
                    borderRadius: 6,
                    borderWidth: 0,
                },
                {
                    label: 'Previous',
                    data: [parseFloat(comparisonData.previous_total) || 0],
                    backgroundColor: colors.primary,
                    borderRadius: 6,
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: colors.text,
                    },
                },
                tooltip: {
                    backgroundColor: isDarkMode() ? '#1f2937' : '#fff',
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.border,
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${formatLocalizedCurrency(context.parsed.y, currencySymbol, 2)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: colors.text,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid,
                        drawBorder: false,
                    },
                    ticks: {
                        color: colors.text,
                        callback: function(value) {
                            return formatLocalizedCurrency(value, currencySymbol, 0);
                        },
                    },
                },
            },
        },
    });
}

export function initAnalyticsDashboardPage() {
    const tabsRoot = document.querySelector('[data-analytics-tabs]');
    if (!tabsRoot || tabsRoot.dataset.initialized === 'true') {
        return;
    }

    tabsRoot.dataset.initialized = 'true';

    const revenueData = decodePayload(tabsRoot.dataset.revenuePayload, { daily: [], monthly: [] });
    const detailedRevenueData = decodePayload(tabsRoot.dataset.detailedRevenuePayload, {});
    const comparisons = decodePayload(tabsRoot.dataset.comparisonsPayload, {});
    const customerData = decodePayload(tabsRoot.dataset.customerPayload, {});
    const appointmentData = decodePayload(tabsRoot.dataset.appointmentPayload, {});
    const providerBusyHoursData = decodePayload(tabsRoot.dataset.providerBusyHoursPayload, {});
    const currencySymbol = tabsRoot.dataset.currencySymbol || null;
    const renderedTabs = new Set();
    let currentChartType = 'daily';

    const updateRevenueChart = (type) => {
        initRevenueTrendChart('revenueChart', revenueData, type, currencySymbol);
        currentChartType = type;
    };

    const renderRevenueTabCharts = () => {
        initMoMComparisonChart('momComparisonChart', comparisons, currencySymbol);
        initRevenueByProviderChart('revenueByProviderChart', detailedRevenueData.by_staff || [], currencySymbol);
    };

    const renderCustomerTabCharts = () => {
        initNewVsReturningChart('newVsReturningChart', customerData.new_vs_returning || {});
        initPeakHoursChart('peakHoursChart', appointmentData.by_time_slot || {});
    };

    const renderProvidersTabCharts = () => {
        initPeakHoursChart('providerBusyHoursChart', providerBusyHoursData || {});
    };

    const activateTab = (tabName) => {
        tabsRoot.querySelectorAll('[data-analytics-tab-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.analyticsTabPanel !== tabName);
        });

        tabsRoot.querySelectorAll('[data-analytics-tab-trigger]').forEach((button) => {
            const active = button.dataset.analyticsTabTrigger === tabName;
            button.classList.toggle('bg-blue-600', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-gray-600', !active);
            button.classList.toggle('border', !active);
            button.classList.toggle('border-gray-200', !active);
            button.classList.toggle('dark:bg-gray-800', !active);
            button.classList.toggle('dark:text-gray-300', !active);
            button.classList.toggle('dark:border-gray-700', !active);
        });

        if (!renderedTabs.has(tabName)) {
            if (tabName === 'overview') {
                updateRevenueChart(currentChartType);
            }

            if (tabName === 'revenue') {
                renderRevenueTabCharts();
            }

            if (tabName === 'customers') {
                renderCustomerTabCharts();
            }

            if (tabName === 'providers') {
                renderProvidersTabCharts();
            }

            renderedTabs.add(tabName);
        }

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url.toString());
    };

    const chartTypeEl = document.getElementById('revenueChartType');
    if (chartTypeEl) {
        chartTypeEl.addEventListener('change', function() {
            updateRevenueChart(this.value);
        });
    }

    const timeframeEl = document.getElementById('timeframe');
    if (timeframeEl) {
        timeframeEl.addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('timeframe', this.value);
            url.searchParams.set('tab', tabsRoot.dataset.activeTab || 'overview');

            if (window.xsSPA) {
                window.xsSPA.navigate(url.toString());
            } else {
                window.location.href = url.toString();
            }
        });
    }

    tabsRoot.querySelectorAll('[data-analytics-tab-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.analyticsTabTrigger;
            tabsRoot.dataset.activeTab = tabName;
            activateTab(tabName);
        });
    });

    activateTab(tabsRoot.dataset.activeTab || 'overview');
}

