<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Web Components - WebSchedulr</title>
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    
    <!-- Your Tailwind CSS -->
    <link href="<?= base_url('/build/assets/app.css') ?>" rel="stylesheet">
    
    <style>
        /* Material Web Components styling */
        md-outlined-button, md-filled-button, md-text-button {
            --md-sys-color-primary: rgb(59, 130, 246);
            --md-sys-color-on-primary: rgb(255, 255, 255);
        }
        
        md-outlined-card {
            --md-outlined-card-container-color: rgb(255, 255, 255);
            --md-outlined-card-outline-color: rgb(229, 231, 235);
        }
        
        md-navigation-drawer {
            --md-navigation-drawer-container-color: rgb(255, 255, 255);
            --md-navigation-drawer-container-width: 256px;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Navigation Drawer -->
    <div class="flex">
        <!-- Sidebar using Material Web Components -->
        <div class="w-64 h-screen bg-white shadow-lg">
            <div class="p-4">
                <!-- Logo -->
                <div class="flex items-center mb-6">
                    <md-icon-button>
                        <md-icon>schedule</md-icon>
                    </md-icon-button>
                    <span class="text-xl font-semibold text-gray-800 ml-2">WebSchedulr</span>
                </div>
                
                <!-- Navigation List -->
                <div class="space-y-2">
                    <md-list>
                        <md-list-item>
                            <md-icon slot="start">dashboard</md-icon>
                            <div slot="headline">Dashboard</div>
                        </md-list-item>
                        
                        <md-list-item>
                            <md-icon slot="start">event</md-icon>
                            <div slot="headline">Schedule</div>
                        </md-list-item>
                        
                        <md-list-item>
                            <md-icon slot="start">people</md-icon>
                            <div slot="headline">Users</div>
                        </md-list-item>
                        
                        <md-list-item>
                            <md-icon slot="start">analytics</md-icon>
                            <div slot="headline">Analytics</div>
                        </md-list-item>
                        
                        <md-list-item>
                            <md-icon slot="start">settings</md-icon>
                            <div slot="headline">Settings</div>
                        </md-list-item>
                    </md-list>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 p-6">
            <!-- Top App Bar -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
                        <p class="text-gray-600">Material Web Components Example</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Search Field -->
                        <md-outlined-text-field label="Search" type="search">
                            <md-icon slot="leading-icon">search</md-icon>
                        </md-outlined-text-field>
                        
                        <!-- Icon Buttons -->
                        <md-icon-button>
                            <md-icon>notifications</md-icon>
                        </md-icon-button>
                        
                        <md-icon-button>
                            <md-icon>account_circle</md-icon>
                        </md-icon-button>
                    </div>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                <!-- Card 1 -->
                <md-outlined-card class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Total Users</h3>
                            <p class="text-3xl font-bold text-blue-600">2,345</p>
                        </div>
                        <md-icon class="text-4xl text-blue-500">people</md-icon>
                    </div>
                    <div class="flex items-center text-sm text-green-600">
                        <md-icon class="text-sm mr-1">trending_up</md-icon>
                        +12% from last month
                    </div>
                </md-outlined-card>

                <!-- Card 2 -->
                <md-outlined-card class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Active Sessions</h3>
                            <p class="text-3xl font-bold text-green-600">1,789</p>
                        </div>
                        <md-icon class="text-4xl text-green-500">event</md-icon>
                    </div>
                    <div class="flex items-center text-sm text-green-600">
                        <md-icon class="text-sm mr-1">trending_up</md-icon>
                        +8% from last month
                    </div>
                </md-outlined-card>

                <!-- Card 3 -->
                <md-outlined-card class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Pending Tasks</h3>
                            <p class="text-3xl font-bold text-orange-600">456</p>
                        </div>
                        <md-icon class="text-4xl text-orange-500">pending_actions</md-icon>
                    </div>
                    <div class="flex items-center text-sm text-red-600">
                        <md-icon class="text-sm mr-1">trending_down</md-icon>
                        -3% from last month
                    </div>
                </md-outlined-card>
            </div>

            <!-- Data Table Card -->
            <md-outlined-card class="p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Activities</h2>
                    <md-filled-button>
                        <md-icon slot="icon">add</md-icon>
                        Add New
                    </md-filled-button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Activity</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm mr-3">
                                            JD
                                        </div>
                                        John Doe
                                    </div>
                                </td>
                                <td class="px-6 py-4">Scheduled meeting</td>
                                <td class="px-6 py-4">
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                                </td>
                                <td class="px-6 py-4">2025-01-01</td>
                                <td class="px-6 py-4">
                                    <md-icon-button>
                                        <md-icon>edit</md-icon>
                                    </md-icon-button>
                                    <md-icon-button>
                                        <md-icon>delete</md-icon>
                                    </md-icon-button>
                                </td>
                            </tr>
                            <!-- More rows can be added here -->
                        </tbody>
                    </table>
                </div>
            </md-outlined-card>

            <!-- Form Example -->
            <md-outlined-card class="p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New User</h2>
                
                <form class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <md-outlined-text-field label="First Name" required>
                        </md-outlined-text-field>
                        
                        <md-outlined-text-field label="Last Name" required>
                        </md-outlined-text-field>
                    </div>
                    
                    <md-outlined-text-field label="Email" type="email" required class="w-full">
                        <md-icon slot="leading-icon">email</md-icon>
                    </md-outlined-text-field>
                    
                    <md-outlined-text-field label="Phone" type="tel" class="w-full">
                        <md-icon slot="leading-icon">phone</md-icon>
                    </md-outlined-text-field>
                    
                    <div class="flex justify-end space-x-4 pt-4">
                        <md-text-button>Cancel</md-text-button>
                        <md-filled-button type="submit">
                            <md-icon slot="icon">save</md-icon>
                            Save User
                        </md-filled-button>
                    </div>
                </form>
            </md-outlined-card>
        </div>
    </div>

    <!-- Material Web Components Import -->
    <script type="module">
        import '@material/web/all.js';
        import {styles as typescaleStyles} from '@material/web/typography/md-typescale-styles.js';

        document.adoptedStyleSheets.push(typescaleStyles.styleSheet);
    </script>
    
    <!-- Optional: Custom JavaScript for interactivity -->
    <script>
        // Add any custom JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Handle form submission
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Form submitted');
                    // Handle form data here
                });
            }
        });
    </script>

</body>
</html>
