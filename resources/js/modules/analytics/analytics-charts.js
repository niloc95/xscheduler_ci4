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

/**
 * Update all analytics charts on theme change
 */
export function refreshChartsOnThemeChange() {
    // Store chart instances globally if needed to refresh
    const event = new CustomEvent('analytics:refresh-charts');
    document.dispatchEvent(event);
}

/**
 * Initialize all analytics charts on page
 * Consolidates chart initialization into a single entry point
 */
export function initAnalyticsCharts() {
    // Individual chart functions (initRevenueTrendChart, initTimeSlotChart, initServiceDistributionChart)
    // are called directly from PHP template views for better granularity.
    // This function can be expanded later if need a unified initialization pattern.
}
