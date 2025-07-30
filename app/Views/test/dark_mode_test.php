<?= $this->extend('components/layout') ?>

<?= $this->section('title') ?>
Dark Mode Test - xScheduler
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Dark Mode Test</h1>
        <p class="text-xl text-gray-600 dark:text-gray-300">Testing the complete dark mode implementation across components</p>
    </div>

    <!-- Dark Mode Controls -->
    <div class="mb-12 p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Theme Controls</h2>
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-700 dark:text-gray-300">Current Theme:</span>
                <span id="current-theme" class="text-sm font-medium text-blue-600 dark:text-blue-400">Light</span>
            </div>
            <div class="flex items-center space-x-4">
                <button id="light-btn" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors duration-200">
                    ☀️ Light Mode
                </button>
                <button id="dark-btn" class="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors duration-200">
                    🌙 Dark Mode
                </button>
                <button id="auto-btn" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200">
                    🔄 Auto
                </button>
            </div>
        </div>
    </div>

    <!-- Component Showcase Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
        
        <!-- Authentication Preview Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Authentication Preview</h3>
            <div class="space-y-4">
                <!-- Mini Login Form -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" 
                               placeholder="user@example.com"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                        <input type="password" 
                               placeholder="••••••••"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200">
                    </div>
                    <button class="w-full px-4 py-2 text-white rounded-lg transition-all duration-200 hover:opacity-90" 
                            style="background-color: var(--md-sys-color-secondary);">
                        Sign In
                    </button>
                </div>
            </div>
        </div>

        <!-- Brand Colors Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Brand Color Palette</h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 rounded-lg" style="background-color: var(--md-sys-color-primary);">
                    <span class="text-white text-sm font-medium">Primary</span>
                </div>
                <div class="p-3 rounded-lg" style="background-color: var(--md-sys-color-secondary);">
                    <span class="text-white text-sm font-medium">Secondary</span>
                </div>
                <div class="p-3 rounded-lg" style="background-color: var(--md-sys-color-tertiary);">
                    <span class="text-white text-sm font-medium">Tertiary</span>
                </div>
                <div class="p-3 rounded-lg" style="background-color: var(--md-sys-color-error);">
                    <span class="text-white text-sm font-medium">Error</span>
                </div>
            </div>
        </div>

        <!-- Alert Components -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Alert Components</h3>
            <div class="space-y-3">
                <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <span class="text-green-700 dark:text-green-300 text-sm">✅ Success message</span>
                </div>
                <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <span class="text-yellow-700 dark:text-yellow-300 text-sm">⚠️ Warning message</span>
                </div>
                <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <span class="text-red-700 dark:text-red-300 text-sm">❌ Error message</span>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <span class="text-blue-700 dark:text-blue-300 text-sm">ℹ️ Info message</span>
                </div>
            </div>
        </div>

        <!-- Button Variants -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Button Variants</h3>
            <div class="space-y-3">
                <button class="w-full px-4 py-2 text-white rounded-lg transition-all duration-200 hover:opacity-90" 
                        style="background-color: var(--md-sys-color-primary);">
                    Primary Button
                </button>
                <button class="w-full px-4 py-2 text-white rounded-lg transition-all duration-200 hover:opacity-90" 
                        style="background-color: var(--md-sys-color-secondary);">
                    Secondary Button
                </button>
                <button class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">
                    Outline Button
                </button>
                <button class="w-full px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200">
                    Ghost Button
                </button>
            </div>
        </div>
    </div>

    <!-- Typography Showcase -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-12">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Typography Scale</h3>
        <div class="space-y-4">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white">Heading 1 - Display Large</h1>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Heading 2 - Display Medium</h2>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Heading 3 - Display Small</h3>
            <h4 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Heading 4 - Headline Large</h4>
            <h5 class="text-lg font-medium text-gray-800 dark:text-gray-200">Heading 5 - Headline Medium</h5>
            <h6 class="text-base font-medium text-gray-800 dark:text-gray-200">Heading 6 - Headline Small</h6>
            <p class="text-base text-gray-700 dark:text-gray-300">Body Large - Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Body Medium - Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
            <p class="text-xs text-gray-500 dark:text-gray-500">Body Small - Ut enim ad minim veniam, quis nostrud exercitation.</p>
        </div>
    </div>

    <!-- Theme Variables Display -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">CSS Variables (Current Theme)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 font-mono text-sm">
            <div class="space-y-2">
                <h4 class="font-semibold text-gray-900 dark:text-white">Primary Colors</h4>
                <div class="space-y-1 text-gray-600 dark:text-gray-400">
                    <div>--md-sys-color-primary</div>
                    <div>--md-sys-color-on-primary</div>
                    <div>--xs-text-primary</div>
                </div>
            </div>
            <div class="space-y-2">
                <h4 class="font-semibold text-gray-900 dark:text-white">Background Colors</h4>
                <div class="space-y-1 text-gray-600 dark:text-gray-400">
                    <div>--xs-bg-primary</div>
                    <div>--xs-bg-secondary</div>
                    <div>--xs-bg-tertiary</div>
                </div>
            </div>
            <div class="space-y-2">
                <h4 class="font-semibold text-gray-900 dark:text-white">Accent Colors</h4>
                <div class="space-y-1 text-gray-600 dark:text-gray-400">
                    <div>--xs-accent</div>
                    <div>--xs-success</div>
                    <div>--xs-error</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Theme testing controls
document.addEventListener('DOMContentLoaded', function() {
    const currentThemeSpan = document.getElementById('current-theme');
    const lightBtn = document.getElementById('light-btn');
    const darkBtn = document.getElementById('dark-btn');
    const autoBtn = document.getElementById('auto-btn');

    function updateCurrentTheme() {
        const theme = window.darkMode ? window.darkMode.getCurrentTheme() : 'light';
        currentThemeSpan.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
    }

    // Initial update
    setTimeout(updateCurrentTheme, 100);

    // Manual theme controls
    lightBtn.addEventListener('click', () => {
        if (window.darkMode) {
            window.darkMode.applyTheme('light');
            updateCurrentTheme();
        }
    });

    darkBtn.addEventListener('click', () => {
        if (window.darkMode) {
            window.darkMode.applyTheme('dark');
            updateCurrentTheme();
        }
    });

    autoBtn.addEventListener('click', () => {
        localStorage.removeItem('xs-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (window.darkMode) {
            window.darkMode.applyTheme(prefersDark ? 'dark' : 'light');
            updateCurrentTheme();
        }
    });

    // Listen for theme changes
    document.addEventListener('xs:theme-changed', updateCurrentTheme);
});
</script>

<?= $this->endSection() ?>
