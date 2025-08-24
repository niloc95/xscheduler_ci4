<!-- Week View Component -->
<div class="space-y-4">
    <div class="text-center py-8">
        <md-icon class="text-6xl text-gray-300 dark:text-gray-600 mb-4">calendar_view_week</md-icon>
        <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">Week View</h3>
        <p class="text-gray-500 dark:text-gray-400 mb-4">Weekly calendar view is coming soon</p>
        <div class="flex justify-center space-x-2">
            <md-outlined-button onclick="changeView('day')">
                <md-icon slot="icon">calendar_view_day</md-icon>
                Switch to Day View
            </md-outlined-button>
            <md-outlined-button onclick="changeView('month')">
                <md-icon slot="icon">calendar_view_month</md-icon>
                Switch to Month View
            </md-outlined-button>
        </div>
    </div>
</div>

<script>
function changeView(view) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('view', view);
    window.location.href = currentUrl.toString();
}
</script>