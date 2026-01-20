<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'help']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Help & Support" data-page-subtitle="Find answers to your questions, learn how to use features, and get the support you need">
    <!-- Quick Search -->
    <div class="mb-8 max-w-md mx-auto">
        <form action="<?= base_url('/help/search') ?>" method="get" class="relative">
            <input type="text" 
                   name="query" 
                   placeholder="Search for help..."
                   class="w-full pl-12 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-rounded text-gray-400 text-lg">search</span>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Quick Help Categories -->
        <div class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Getting Started -->
                <a href="<?= base_url('/help/getting-started') ?>" 
                   class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-rounded text-green-600 dark:text-green-400 text-2xl">rocket_launch</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white ml-4">Getting Started</h3>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300">Learn the basics and set up your account</p>
                    <div class="mt-4 space-y-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Account setup</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Basic navigation</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• First steps</p>
                    </div>
                </a>

                <!-- Appointments -->
                <a href="<?= base_url('/help/appointments') ?>" 
                   class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-2xl">calendar_month</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white ml-4">Appointments</h3>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300">Book, manage, and track your appointments</p>
                    <div class="mt-4 space-y-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Booking appointments</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Canceling & rescheduling</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Managing schedules</p>
                    </div>
                </a>

                <!-- Services -->
                <a href="<?= base_url('/help/services') ?>" 
                   class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-rounded text-purple-600 dark:text-purple-400 text-2xl">handyman</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white ml-4">Services</h3>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300">Understand our services and pricing</p>
                    <div class="mt-4 space-y-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Service categories</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Pricing information</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Special offers</p>
                    </div>
                </a>

                <!-- Account & Billing -->
                <a href="<?= base_url('/help/account-billing') ?>" 
                   class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-rounded text-amber-600 dark:text-amber-400 text-2xl">account_balance_wallet</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white ml-4">Account & Billing</h3>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300">Manage your account settings and payments</p>
                    <div class="mt-4 space-y-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Account settings</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Payment methods</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">• Billing history</p>
                    </div>
                </a>
            </div>

            <!-- FAQ Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Frequently Asked Questions</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <?php foreach ($faqs as $faq): ?>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 last:border-b-0 last:pb-0">
                                <button class="faq-toggle w-full text-left flex items-center justify-between py-2 focus:outline-none"
                                        onclick="toggleFaq(this)">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white pr-4">
                                        <?= esc($faq['question']) ?>
                                    </h3>
                                    <span class="material-symbols-rounded text-gray-500 dark:text-gray-400 transform transition-transform duration-200">expand_more</span>
                                </button>
                                <div class="faq-content hidden mt-3 text-gray-600 dark:text-gray-300">
                                    <p><?= esc($faq['answer']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support Sidebar -->
        <div class="space-y-6">
            <!-- Contact Support -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Need More Help?</h3>
                <p class="text-gray-600 dark:text-gray-300 mb-4">
                    Can't find what you're looking for? Our support team is here to help.
                </p>
                <div class="space-y-3">
                    <a href="<?= base_url('/help/contact') ?>" 
                       class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-rounded mr-2 text-base align-middle">mail</span>
                        Contact Support
                    </a>
                    <a href="<?= base_url('/help/chat') ?>" 
                       class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-rounded mr-2 text-base align-middle">chat</span>
                        Live Chat
                    </a>
                </div>
            </div>

            <!-- Popular Articles -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Popular Articles</h3>
                <div class="space-y-3">
                    <?php foreach ($popular_articles as $article): ?>
                        <a href="<?= base_url('/help/article/' . $article['slug']) ?>" 
                           class="block p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                            <h4 class="font-medium text-gray-900 dark:text-white text-sm mb-1">
                                <?= esc($article['title']) ?>
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?= $article['views'] ?> views
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- System Status -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">System Status</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        All Systems Operational
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Website</span>
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">API</span>
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Database</span>
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Payments</span>
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    </div>
                </div>
                <a href="<?= base_url('/help/status') ?>" 
                   class="mt-4 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">
                    View detailed status →
                </a>
            </div>

            <!-- Quick Links -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Links</h3>
                <div class="space-y-2">
                    <a href="<?= base_url('/help/keyboard-shortcuts') ?>" 
                       class="block text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        Keyboard Shortcuts
                    </a>
                    <a href="<?= base_url('/help/video-tutorials') ?>" 
                       class="block text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        Video Tutorials
                    </a>
                    <a href="<?= base_url('/help/api-docs') ?>" 
                       class="block text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        API Documentation
                    </a>
                    <a href="<?= base_url('/help/community') ?>" 
                       class="block text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        Community Forum
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFaq(button) {
    const content = button.nextElementSibling;
    const icon = button.querySelector('.material-symbols-rounded');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>
<?= $this->endSection() ?>
