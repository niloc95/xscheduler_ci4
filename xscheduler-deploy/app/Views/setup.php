<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - xScheduler</title>
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
</head>
<body class="c-app">
    <div class="c-wrapper">
        <!-- Main content -->
        <div class="c-main">
            <main class="c-main-content">
                <div class="container-fluid p-4">
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">xScheduler Setup</h4>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Welcome to xScheduler! Let's get you set up.</p>
                                    
                                    <div class="alert alert-info" role="alert">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h5 class="text-sm font-medium text-blue-800 mb-1">Getting Started</h5>
                                                <p class="text-sm text-blue-700 mb-0">This setup wizard will help you configure your scheduling application.</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="border-gray-200 mb-6">
                                    
                                    <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                                        <a href="<?= base_url('/') ?>" class="btn-secondary">Back to Home</a>
                                        <button class="btn-primary" type="button">Continue Setup</button>
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