<?= $this->extend('components/layout') ?>

<?= $this->section('header_title') ?>Components<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="content-wrapper" data-page-title="Components">
    <div class="content-main space-y-8">
        
        <!-- Design Tokens: Color Codes & Font Sizing -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Design Tokens</h3>
            </div>
            <div class="card-body p-4 space-y-6 text-gray-700 dark:text-gray-300">
                <div>
                    <h4 class="font-semibold mb-2">Color codes</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Resolved from CSS variables, respects dark mode.</p>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="h-16" style="background-color: var(--md-sys-color-primary)"></div>
                            <div class="p-3 text-xs">
                                <div>--md-sys-color-primary</div>
                                <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-primary">rgb()</div>
                            </div>
                        </div>
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="h-16" style="background-color: var(--md-sys-color-secondary)"></div>
                            <div class="p-3 text-xs">
                                <div>--md-sys-color-secondary</div>
                                <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-secondary">rgb()</div>
                            </div>
                        </div>
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="h-16" style="background-color: var(--md-sys-color-tertiary)"></div>
                            <div class="p-3 text-xs">
                                <div>--md-sys-color-tertiary</div>
                                <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-tertiary">rgb()</div>
                            </div>
                        </div>
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="h-16" style="background-color: var(--md-sys-color-surface)"></div>
                            <div class="p-3 text-xs">
                                <div>--md-sys-color-surface</div>
                                <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-surface">rgb()</div>
                            </div>
                        </div>
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="h-16" style="background-color: var(--md-sys-color-on-surface)"></div>
                            <div class="p-3 text-xs">
                                <div>--md-sys-color-on-surface</div>
                                <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-on-surface">rgb()</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="font-semibold mb-2">Font sizing</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="flex items-baseline justify-between"><span class="text-xs">text-xs</span><span class="font-mono text-xs">12px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-sm">text-sm</span><span class="font-mono text-xs">14px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-base">text-base</span><span class="font-mono text-xs">16px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-lg">text-lg</span><span class="font-mono text-xs">18px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-xl">text-xl</span><span class="font-mono text-xs">20px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-2xl">text-2xl</span><span class="font-mono text-xs">24px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-3xl">text-3xl</span><span class="font-mono text-xs">30px</span></div>
                        <div class="flex items-baseline justify-between"><span class="text-4xl">text-4xl</span><span class="font-mono text-xs">36px</span></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Buttons Section -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Buttons</h3>
            </div>
            <div class="card-body p-4 space-y-4 text-gray-700 dark:text-gray-300">
                <div>
                    <h4 class="text-lg font-medium mb-3">Button Types</h4>
                    <div class="flex flex-wrap gap-4 mb-4">
                        <?= ui_button('Primary Button') ?>
                        <?= ui_button('Secondary Button', null, 'secondary') ?>
                        <?= ui_button('Ghost Button', null, 'ghost') ?>
                        <?= ui_button('Pill Button', null, 'pill') ?>
                    </div>
                    <div class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 rounded-md overflow-x-auto text-gray-800 dark:text-gray-200">
                        <code class="text-sm">
                            &lt;?= ui_button('Primary Button') ?&gt;<br>
                            &lt;?= ui_button('Secondary Button', null, 'secondary') ?&gt;<br>
                            &lt;?= ui_button('Ghost Button', null, 'ghost') ?&gt;<br>
                            &lt;?= ui_button('Pill Button', null, 'pill') ?&gt;
                        </code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts Section -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Alerts</h3>
            </div>
            <div class="card-body p-4 space-y-4 text-gray-700 dark:text-gray-300">
                <?= ui_alert('This is an info alert', 'info', 'Information') ?>
                <?= ui_alert('This is a success alert', 'success', 'Success') ?>
                <?= ui_alert('This is a warning alert', 'warning', 'Warning') ?>
                <?= ui_alert('This is an error alert', 'error', 'Error') ?>
                
                <div class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 rounded-md overflow-x-auto text-gray-800 dark:text-gray-200">
                    <code class="text-sm">
                        &lt;?= ui_alert('Message', 'info', 'Title') ?&gt;
                    </code>
                </div>
            </div>
        </div>
        
        <!-- Cards Section -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Cards</h3>
            </div>
            <div class="card-body p-4 text-gray-700 dark:text-gray-300">
                <?= ui_card(
                    'Sample Card Title',
                    '<p class="text-gray-600">This is the card content area. You can put any content here.</p>'
                ) ?>
                
                <div class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 rounded-md mt-4 overflow-x-auto text-gray-800 dark:text-gray-200">
                    <code class="text-sm">
                        &lt;?= ui_card('Title', 'Content', 'Footer') ?&gt;
                    </code>
                </div>
            </div>
        </div>

        <!-- Utilities: Borders, Radius, Elevation -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Utilities</h3>
            </div>
            <div class="card-body p-4 space-y-6">
                <div>
                    <h4 class="font-semibold mb-2">Rounded Corners</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs text-gray-700 dark:text-gray-300">
                        <div class="flex items-center gap-2"><div class="w-10 h-8 bg-gray-100 dark:bg-gray-700 rounded"></div><code>rounded</code> — 4px</div>
                        <div class="flex items-center gap-2"><div class="w-10 h-8 bg-gray-100 dark:bg-gray-700 rounded-md"></div><code>rounded-md</code> — 6px</div>
                        <div class="flex items-center gap-2"><div class="w-10 h-8 bg-gray-100 dark:bg-gray-700 rounded-lg"></div><code>rounded-lg</code> — 8px</div>
                        <div class="flex items-center gap-2"><div class="w-10 h-8 bg-gray-100 dark:bg-gray-700 rounded-xl"></div><code>rounded-xl</code> — 12px</div>
                        <div class="flex items-center gap-2"><div class="w-10 h-8 bg-gray-100 dark:bg-gray-700 rounded-2xl"></div><code>rounded-2xl</code> — 16px</div>
                        <div class="flex items-center gap-2"><div class="w-10 h-8 bg-gray-100 dark:bg-gray-700 rounded-full"></div><code>rounded-full</code> — fully rounded</div>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Borders</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border</code> — 1px</div>
                        </div>
                        <div class="p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border-2 border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border-2</code> — 2px</div>
                        </div>
                        <div class="p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border-4 border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border-4</code> — 4px</div>
                        </div>
                        <div class="p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border-8 border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border-8</code> — 8px</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Elevation</h4>
                    <div class="flex gap-4 items-end">
                        <div class="w-24 h-12 bg-white dark:bg-gray-800 rounded-lg"></div>
                        <div class="w-24 h-12 bg-white dark:bg-gray-800 rounded-lg shadow-brand"></div>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Use <code>shadow-brand</code> for primary elevated surfaces to match footer/header.</p>
                </div>
            </div>
        </div>
        
    </div>
    </div>
    <script>
    // Show CSS variable color values within the tokens card
    function xsUpdateComponentColorCodes() {
        const vars = ['--md-sys-color-primary','--md-sys-color-secondary','--md-sys-color-tertiary','--md-sys-color-surface','--md-sys-color-on-surface'];
        const cs = getComputedStyle(document.documentElement);
        vars.forEach(v => {
            document.querySelectorAll(`.color-code[data-var="${v}"]`).forEach(el => {
                const val = cs.getPropertyValue(v).trim();
                el.textContent = val || '—';
            });
        });
    }
    document.addEventListener('DOMContentLoaded', xsUpdateComponentColorCodes);
    document.addEventListener('spa:navigated', xsUpdateComponentColorCodes);
    document.addEventListener('xs:theme-changed', xsUpdateComponentColorCodes);
</script>
<?= $this->endSection() ?>