<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard Test' ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #3b82f6;
        }
        .stat-label {
            color: #6b7280;
            margin-top: 5px;
        }
        .success {
            color: #10b981;
            padding: 10px;
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">
            ✅ Dashboard is working! CodeIgniter is properly configured.
        </div>
        
        <div class="header">
            <h1><?= $title ?? 'Dashboard Test' ?></h1>
            <p><?= $message ?? 'Welcome to your dashboard!' ?></p>
            <p><strong>Time:</strong> <?= date('Y-m-d H:i:s') ?></p>
            <p><strong>Base URL:</strong> <?= base_url() ?></p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['users'] ?? 0 ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['sessions'] ?? 0 ?></div>
                <div class="stat-label">Active Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['tasks'] ?? 0 ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
        </div>

        <div class="header">
            <h2>Environment Information</h2>
            <p><strong>Environment:</strong> <?= ENVIRONMENT ?></p>
            <p><strong>CodeIgniter Version:</strong> <?= \CodeIgniter\CodeIgniter::CI_VERSION ?></p>
            <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
            
            <h3>Asset Check</h3>
            <p>CSS Path: <?= base_url('build/assets/style.css') ?></p>
            <p>JS Path: <?= base_url('build/assets/main.js') ?></p>
            
            <h3>Available Routes</h3>
            <ul>
                <li><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                <li><a href="<?= base_url('dashboard/simple') ?>">Simple Dashboard</a></li>
                <li><a href="<?= base_url('dashboard/api') ?>">Dashboard API</a></li>
                <li><a href="<?= base_url('dashboard/charts') ?>">Charts API</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
