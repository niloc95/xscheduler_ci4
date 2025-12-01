// Charts configuration and initialization
import Chart from 'chart.js/auto';

// Store chart instances to prevent duplicates
const chartInstances = {};

// Get base URL for API calls
const getBaseUrl = () => window.__BASE_URL__ || '';

// Dark mode detection
const isDarkMode = () => document.documentElement.classList.contains('dark');

// Get chart color scheme based on dark mode
const getChartColors = () => {
    const dark = isDarkMode();
    return {
        grid: dark ? 'rgba(75, 85, 99, 0.3)' : 'rgba(243, 244, 246, 0.8)',
        text: dark ? '#9CA3AF' : '#6B7280',
        primary: dark ? 'rgb(96, 165, 250)' : 'rgb(59, 130, 246)',
        primaryBg: dark ? 'rgba(96, 165, 250, 0.2)' : 'rgba(59, 130, 246, 0.1)',
        secondary: dark ? 'rgb(167, 139, 250)' : 'rgb(147, 51, 234)',
        secondaryBg: dark ? 'rgba(167, 139, 250, 0.5)' : 'rgba(147, 51, 234, 0.5)'
    };
};

// Chart configuration options (dynamic for dark mode)
const getChartOptions = () => {
    const colors = getChartColors();
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: colors.text
                }
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
                    color: colors.grid,
                },
                ticks: {
                    color: colors.text
                }
            },
        },
    };
};

// User Growth Chart - fetches real data from API
export async function initUserGrowthChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    const colors = getChartColors();
    let labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    let data = [0, 0, 0, 0, 0, 0];
    
    // Try to fetch real data from API
    try {
        const response = await fetch(`${getBaseUrl()}/dashboard/charts`);
        if (response.ok) {
            const chartData = await response.json();
            if (chartData.userGrowth) {
                labels = chartData.userGrowth.labels || labels;
                data = chartData.userGrowth.data || data;
            }
        }
    } catch (error) {
        console.log('Using fallback chart data for user growth');
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Users',
                data: data,
                borderColor: colors.primary,
                backgroundColor: colors.primaryBg,
                fill: true,
                tension: 0.4,
            }]
        },
        options: getChartOptions()
    });
    
    return chartInstances[canvasId];
}

// Activity Overview Chart (Status Distribution) - fetches real data from API
export async function initActivityChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    const colors = getChartColors();
    let labels = ['No Data'];
    let data = [0];
    
    // Try to fetch real data from API
    try {
        const response = await fetch(`${getBaseUrl()}/dashboard/charts`);
        if (response.ok) {
            const chartData = await response.json();
            if (chartData.activity && chartData.activity.labels && chartData.activity.labels.length > 0) {
                labels = chartData.activity.labels;
                data = chartData.activity.data;
            }
        }
    } catch (error) {
        console.log('Using fallback chart data for activity');
    }
    
    // Use pie chart for status distribution
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                label: 'Appointments by Status',
                data: data,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',   // Pending - blue
                    'rgba(16, 185, 129, 0.8)',   // Confirmed - green
                    'rgba(34, 197, 94, 0.8)',    // Completed - bright green
                    'rgba(239, 68, 68, 0.8)',    // Cancelled - red
                    'rgba(156, 163, 175, 0.8)',  // No-show - gray
                ],
                borderWidth: 0,
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
                        padding: 12
                    }
                },
            },
        }
    });
    
    return chartInstances[canvasId];
}

// Revenue Chart - fetches real data from API
export async function initRevenueChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    const colors = getChartColors();
    let labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
    let data = [0, 0, 0, 0];
    
    // Try to fetch real data from API
    try {
        const response = await fetch(`${getBaseUrl()}/dashboard/charts`);
        if (response.ok) {
            const chartData = await response.json();
            if (chartData.appointments) {
                labels = chartData.appointments.labels || labels;
                data = chartData.appointments.data || data;
            }
        }
    } catch (error) {
        console.log('Using fallback chart data for revenue');
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Appointments',
                data: data,
                backgroundColor: colors.secondaryBg,
                borderColor: colors.secondary,
                borderWidth: 1,
            }]
        },
        options: getChartOptions()
    });
    
    return chartInstances[canvasId];
}

// Initialize all charts when called
export async function initAllCharts() {
    const [userChart, activityChart, revenueChart] = await Promise.all([
        initUserGrowthChart('userGrowthChart'),
        initActivityChart('activityChart'),
        initRevenueChart('revenueChart')
    ]);
    
    return {
        userChart,
        activityChart,
        revenueChart
    };
}

// Re-init charts when dark mode changes
export function setupDarkModeListener() {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                // Refresh all charts with new colors
                initAllCharts();
            }
        });
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
}

// Export default for easy import
export default {
    initUserGrowthChart,
    initActivityChart,
    initRevenueChart,
    initAllCharts,
    setupDarkModeListener
};
