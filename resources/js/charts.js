// Charts configuration and initialization
import Chart from 'chart.js/auto';

// Store chart instances to prevent duplicates
const chartInstances = {};

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
    
    // Destroy existing chart instance if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
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
    
    return chartInstances[canvasId];
}

// Activity Overview Chart
export function initActivityChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Appointments',
                data: [12, 19, 15, 17, 14, 8, 11],
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1,
            }]
        },
        options: chartOptions
    });
    
    return chartInstances[canvasId];
}

// Revenue Chart
export function initRevenueChart(canvasId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // Destroy existing chart instance if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
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
    
    return chartInstances[canvasId];
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
