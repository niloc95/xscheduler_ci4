<?= $this->extend('components/layout') ?>

<?= $this->section('header_title') ?>Design System<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="content-wrapper" data-page-title="Design System" data-page-subtitle="A comprehensive guide to our UI components and patterns">
    <div class="content-main space-y-8">
        
    <!-- Header removed in favor of dynamic page header -->
        
        <!-- Dashboard Layout -->
        <div id="dashboard-layout" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Dashboard Layout</h3>
            </div>
            <div class="card-body p-6 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                <p>The shared <code>layouts/dashboard</code> template keeps dashboard-style pages consistent. Every dashboard view should follow the same vertical rhythm: stats → actions → filters → main content.</p>
                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4 text-xs text-gray-700 dark:text-gray-200 overflow-x-auto">
<pre class="whitespace-pre"><code>&lt;?= $this-&gt;extend('layouts/dashboard') ?&gt;

&lt;?= $this-&gt;section('page_title') ?&gt;Appointments&lt;?= $this-&gt;endSection() ?&gt;
&lt;?= $this-&gt;section('page_subtitle') ?&gt;Manage upcoming bookings&lt;?= $this-&gt;endSection() ?&gt;

&lt;?= $this-&gt;section('dashboard_stats_class') ?&gt;grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6&lt;?= $this-&gt;endSection() ?&gt;
&lt;?= $this-&gt;section('dashboard_stats') ?&gt;
    &lt;!-- stat cards (no wrapper needed) --&gt;
&lt;?= $this-&gt;endSection() ?&gt;

&lt;?= $this-&gt;section('dashboard_actions') ?&gt;
    &lt;a class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg no-underline"&gt;Primary Action&lt;/a&gt;
&lt;?= $this-&gt;endSection() ?&gt;

&lt;?= $this-&gt;section('dashboard_filters') ?&gt;
    &lt;!-- optional pills, tabs, selects --&gt;
&lt;?= $this-&gt;endSection() ?&gt;

&lt;?= $this-&gt;section('dashboard_content_top') ?&gt;
    &lt;!-- flash messages, cards --&gt;
&lt;?= $this-&gt;endSection() ?&gt;

&lt;?= $this-&gt;section('dashboard_content') ?&gt;
    &lt;!-- tables, charts, lists --&gt;
&lt;?= $this-&gt;endSection() ?&gt;
</code></pre>
                </div>
                <ul class="list-disc pl-5 space-y-2">
                    <li><strong>Stats</strong>: Populate <code>dashboard_stats</code> with cards using <code>bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6</code>. Override the grid with <code>dashboard_stats_class</code> when needed.</li>
                    <li><strong>Actions</strong>: Buttons render inside a <code>flex flex-wrap gap-3 justify-end</code> container. Use <code>inline-flex items-center px-4 py-2 rounded-md</code> with brand colors (<code>bg-indigo-600 hover:bg-indigo-700 text-white</code>) or neutrals (<code>bg-white border border-gray-300 dark:border-gray-600</code>).</li>
                    <li><strong>Filters</strong>: Add form controls in <code>dashboard_filters</code> using the dashed container pattern <code>rounded-lg border border-dashed border-gray-300 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/40</code>. Inputs should use <code>rounded-md border border-gray-300 bg-white px-3 py-2 text-sm</code>.</li>
                    <li><strong>Content</strong>: Wrap main content with <code>bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700</code>. Use <code>dashboard_content_top</code> for flash messages or supporting callouts before the primary card.</li>
                </ul>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Tailwind quick reference</h4>
                    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-gray-800 dark:text-gray-100">Cards</dt>
                            <dd><code>bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6</code></dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800 dark:text-gray-100">Primary buttons</dt>
                            <dd><code>inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700</code></dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800 dark:text-gray-100">Secondary buttons</dt>
                            <dd><code>inline-flex items-center px-4 py-2 rounded-md bg-white border border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-gray-700</code></dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800 dark:text-gray-100">Tables</dt>
                            <dd><code>min-w-full divide-y divide-gray-200 dark:divide-gray-700 [&amp;th]:px-6 [&amp;th]:py-3 [&amp;th]:text-xs [&amp;td]:px-6 [&amp;td]:py-4</code></dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800 dark:text-gray-100">Filters</dt>
                            <dd><code>rounded-lg border border-dashed border-gray-300 bg-gray-50/60 p-4 flex flex-col gap-3 md:flex-row</code></dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800 dark:text-gray-100">Badges</dt>
                            <dd><code>inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold</code> + contextual colors</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Color System -->
        <div id="colors" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Color System</h3>
            </div>
            <div class="card-body p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Our palette is defined via CSS variables and adapts to dark mode.</p>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="h-16" style="background-color: var(--md-sys-color-primary)"></div>
                        <div class="p-3 text-xs text-gray-700 dark:text-gray-300">
                            <div>--md-sys-color-primary</div>
                            <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-primary">rgb()</div>
                        </div>
                    </div>
                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="h-16" style="background-color: var(--md-sys-color-secondary)"></div>
                        <div class="p-3 text-xs text-gray-700 dark:text-gray-300">
                            <div>--md-sys-color-secondary</div>
                            <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-secondary">rgb()</div>
                        </div>
                    </div>
                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="h-16" style="background-color: var(--md-sys-color-tertiary)"></div>
                        <div class="p-3 text-xs text-gray-700 dark:text-gray-300">
                            <div>--md-sys-color-tertiary</div>
                            <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-tertiary">rgb()</div>
                        </div>
                    </div>
                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="h-16" style="background-color: var(--md-sys-color-surface)"></div>
                        <div class="p-3 text-xs text-gray-700 dark:text-gray-300">
                            <div>--md-sys-color-surface</div>
                            <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-surface">rgb()</div>
                        </div>
                    </div>
                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="h-16" style="background-color: var(--md-sys-color-on-surface)"></div>
                        <div class="p-3 text-xs text-gray-700 dark:text-gray-300">
                            <div>--md-sys-color-on-surface</div>
                            <div class="mt-1 font-mono color-code text-gray-600 dark:text-gray-400" data-var="--md-sys-color-on-surface">rgb()</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Typography -->
        <div id="typography" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Typography</h3>
            </div>
            <div class="card-body p-6 text-gray-800 dark:text-gray-200">
                <h1 class="text-3xl font-bold mb-2">Heading 1</h1>
                <h2 class="text-2xl font-semibold mb-2">Heading 2</h2>
                <h3 class="text-xl font-semibold mb-2">Heading 3</h3>
                <p class="mb-3 text-gray-700 dark:text-gray-300">Body text example with adequate contrast in both themes. Use <span class="font-semibold">font-semibold</span> for emphasis and <span class="italic">italic</span> when appropriate.</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Small text and helper notes.</p>
                <div class="mt-4 p-3 bg-gray-100 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 text-sm">
                    <code>code snippet</code>
                </div>

                <!-- Font sizing scale -->
                <div class="mt-6">
                    <h4 class="text-base font-semibold mb-2">Font sizing</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-gray-700 dark:text-gray-300">
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

        <!-- Borders & Radius -->
        <div id="borders-radius" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Borders & Rounded Corners</h3>
            </div>
            <div class="card-body p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="h-16 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded"></div>
                    <div class="h-16 bg-gray-50 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-md"></div>
                    <div class="h-16 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"></div>
                    <div class="h-16 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-full"></div>
                </div>
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Use <code>rounded-lg</code> for primary cards and containers; <code>rounded</code>/<code>rounded-md</code> for internal elements.
                </p>

                <!-- Border sizing -->
                <div class="mt-6">
                    <h4 class="text-base font-semibold mb-2 text-gray-800 dark:text-gray-200">Border sizing</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border</code> — 1px</div>
                        </div>
                        <div class="p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border-2 border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border-2</code> — 2px</div>
                        </div>
                        <div class="p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border-4 border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border-4</code> — 4px</div>
                        </div>
                        <div class="p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded">
                            <div class="h-8 border-8 border-gray-400"></div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400"><code>border-8</code> — 8px</div>
                        </div>
                    </div>
                </div>

                <!-- Radius scale -->
                <div class="mt-6">
                    <h4 class="text-base font-semibold mb-2 text-gray-800 dark:text-gray-200">Rounded radius scale</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs text-gray-700 dark:text-gray-300">
                        <div class="flex items-center gap-2"><div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded"></div><code>rounded</code> — 4px</div>
                        <div class="flex items-center gap-2"><div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-md"></div><code>rounded-md</code> — 6px</div>
                        <div class="flex items-center gap-2"><div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-lg"></div><code>rounded-lg</code> — 8px</div>
                        <div class="flex items-center gap-2"><div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-xl"></div><code>rounded-xl</code> — 12px</div>
                        <div class="flex items-center gap-2"><div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-2xl"></div><code>rounded-2xl</code> — 16px</div>
                        <div class="flex items-center gap-2"><div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-full"></div><code>rounded-full</code> — fully rounded</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="<?= base_url('styleguide/components') ?>" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand hover:shadow-lg transition-shadow">
                <div class="card-body p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Components</h3>
                    <p class="text-gray-600 dark:text-gray-300">Buttons, forms, cards, and more</p>
                </div>
            </a>
            
            <a href="<?= base_url('styleguide/scheduler') ?>" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand hover:shadow-lg transition-shadow">
                <div class="card-body p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Scheduler</h3>
                    <p class="text-gray-600 dark:text-gray-300">Time slots, calendars, appointments</p>
                </div>
            </a>
            
            <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
                <div class="card-body p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Typography</h3>
                    <p class="text-gray-600 dark:text-gray-300">Headings, text, and spacing</p>
                </div>
            </div>
        </div>
        
    </div>
</div>
<script>
    // Populate color codes from CSS variables so dark mode shows correct values
    function updateXsColorCodes() {
        const vars = ['--md-sys-color-primary','--md-sys-color-secondary','--md-sys-color-tertiary','--md-sys-color-surface','--md-sys-color-on-surface'];
        const cs = getComputedStyle(document.documentElement);
        vars.forEach(v => {
            document.querySelectorAll(`.color-code[data-var="${v}"]`).forEach(el => {
                const val = cs.getPropertyValue(v).trim();
                el.textContent = val || '—';
            });
        });
    }
    document.addEventListener('DOMContentLoaded', updateXsColorCodes);
    document.addEventListener('spa:navigated', updateXsColorCodes);
    // Optional: update on theme toggle
    document.addEventListener('xs:theme-changed', updateXsColorCodes);
</script>
<?= $this->endSection() ?>