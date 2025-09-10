<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailwind - WebSchedulr</title>
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="w-full">
        <!-- Main content -->
        <div class="flex-1">
            <main class="min-h-screen">
                <div class="container mx-auto px-4 py-4">
                    <div class="flex justify-center">
                        <div class="w-full max-w-4xl lg:w-2/3">
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                                <!-- Card Header -->
                                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                                    <h4 class="text-xl font-semibold text-gray-900 mb-0">Tailwind CSS Test</h4>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="px-6 py-6">
                                    <p class="text-gray-600 mb-6">This page tests Tailwind CSS styling.</p>
                                    
                                    <!-- Alert Info -->
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6" role="alert">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h5 class="text-sm font-medium text-blue-800 mb-1">Tailwind Working!</h5>
                                                <p class="text-sm text-blue-700 mb-0">If you can see this styled properly, Tailwind CSS is working correctly.</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Divider -->
                                    <hr class="border-gray-200 mb-6">
                                    
                                    <!-- Button Group -->
                                    <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                                        <a href="<?= base_url('/') ?>" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            Back to Home
                                        </a>
                                        <a href="<?= base_url('/setup') ?>" class="inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            Go to Setup
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= base_url('build/assets/main.js') ?>"></script>
</body>
</html>