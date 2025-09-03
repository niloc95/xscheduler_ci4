<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/role-based-sidebar', ['current_page' => 'help']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Help & Support">
    <!-- Page Header -->
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white transition-colors duration-300">
            Help Center
        </h1>
        <p class="mt-4 text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
            Find answers to your questions, learn how to use features, and get the support you need
        </p>
        
        <!-- Search Bar -->
        <div class="mt-8 max-w-md mx-auto">
            <form action="<?= base_url('/help/search') ?>" method="get" class="relative">
                <input type="text" 
                       name="query" 
                       placeholder="Search for help..."
                       class="w-full pl-12 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </form>
        </div>
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
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
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
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
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
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
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
                            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
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
                                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" 
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
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
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Contact Support
                    </a>
                    <a href="<?= base_url('/help/chat') ?>" 
                       class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium rounded-lg transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
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
    const icon = button.querySelector('svg');
    
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
