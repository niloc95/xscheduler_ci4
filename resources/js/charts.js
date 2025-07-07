// Charts configuration and initialization
import Chart from 'chart.js/auto';

// Chart configuration options
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: '#f3f4f6',
            },
        },
        x: {
            grid: {
                color: '#f3f4f6',
            },
        },
    },
};

// User Growth Chart
export function initUserGrowthChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Users',
                data: [1200, 1400, 1600, 1800, 2100, 2345],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
            }]
        },
        options: chartOptions
    });
}

// Activity Overview Chart
export function initActivityChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active Sessions', 'Completed Tasks', 'Pending Tasks', 'Cancelled'],
            datasets: [{
                data: [1789, 856, 456, 123],
                backgroundColor: [
                    'rgb(34, 197, 94)',   // green
                    'rgb(59, 130, 246)',  // blue
                    'rgb(249, 115, 22)',  // orange
                    'rgb(239, 68, 68)',   // red
                ],
                borderWidth: 0,
            }]
        },
        options: {
            ...chartOptions,
            cutout: '60%',
            plugins: {
                ...chartOptions.plugins,
                legend: {
                    position: 'right',
                },
            },
        }
    });
}

// Revenue Chart
export function initRevenueChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Revenue ($)',
                data: [2800, 3200, 2900, 3300],
                backgroundColor: 'rgba(147, 51, 234, 0.8)',
                borderColor: 'rgb(147, 51, 234)',
                borderWidth: 1,
            }]
        },
        options: chartOptions
    });
}

// Initialize all charts when called
export function initAllCharts() {
    const userChart = initUserGrowthChart('userGrowthChart');
    const activityChart = initActivityChart('activityChart');
    const revenueChart = initRevenueChart('revenueChart');
    
    return {
        userChart,
        activityChart,
        revenueChart
    };
}

// Export default for easy import
export default {
    initUserGrowthChart,
    initActivityChart,
    initRevenueChart,
    initAllCharts
};
