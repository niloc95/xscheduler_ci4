<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Setup extends BaseController
{
    public function __construct()
    {
        helper(['form', 'url']);
    }

    public function index()
    {
        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
            return redirect()->to('/dashboard')->with('error', 'Setup has already been completed.');
        }

        return view('setup');
    }

    public function setup(): string
    {
        return $this->index();
    }

    public function process(): ResponseInterface
    {
        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Setup has already been completed.'
            ])->setStatusCode(400);
        }

        // CSRF protection
        if (!$this->validate(['csrf_token' => 'required'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'CSRF token validation failed.',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        // Validation rules
        $rules = [
            'admin_name' => 'required|min_length[2]|max_length[50]',
            'admin_userid' => 'required|min_length[3]|max_length[20]|alpha_numeric',
            'admin_password' => 'required|min_length[8]',
            'admin_password_confirm' => 'required|matches[admin_password]',
            'database_type' => 'required|in_list[mysql,sqlite]',
        ];

        // Additional MySQL validation
        if ($this->request->getPost('database_type') === 'mysql') {
            $rules = array_merge($rules, [
                'mysql_hostname' => 'required',
                'mysql_port' => 'required|numeric',
                'mysql_database' => 'required',
                'mysql_username' => 'required',
                'mysql_password' => 'permit_empty'
            ]);
        }

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        try {
            // Process setup
            $setupData = [
                'admin' => [
                    'name' => $this->request->getPost('admin_name'),
                    'userid' => $this->request->getPost('admin_userid'),
                    'password' => password_hash($this->request->getPost('admin_password'), PASSWORD_ARGON2ID)
                ],
                'database' => [
                    'type' => $this->request->getPost('database_type')
                ]
            ];

            if ($setupData['database']['type'] === 'mysql') {
                $setupData['database']['mysql'] = [
                    'hostname' => $this->request->getPost('mysql_hostname'),
                    'port' => (int)$this->request->getPost('mysql_port'),
                    'database' => $this->request->getPost('mysql_database'),
                    'username' => $this->request->getPost('mysql_username'),
                    'password' => $this->request->getPost('mysql_password')
                ];

                // Test MySQL connection
                if (!$this->testMySQLConnection($setupData['database']['mysql'])) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Failed to connect to MySQL database. Please check your credentials.'
                    ])->setStatusCode(400);
                }
            } else {
                // Setup SQLite
                $setupData['database']['sqlite'] = [
                    'path' => WRITEPATH . 'database/appdb.sqlite'
                ];
            }

            // Initialize database and create admin user
            $this->initializeDatabase($setupData);

            // Create setup completion flag
            $this->markSetupCompleted($setupData);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Setup completed successfully!',
                'redirect' => '/dashboard'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Setup process failed: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function testConnection(): ResponseInterface
    {
        $dbType = $this->request->getPost('database_type');
        
        if ($dbType === 'mysql') {
            $config = [
                'hostname' => $this->request->getPost('mysql_hostname'),
                'port' => (int)$this->request->getPost('mysql_port'),
                'database' => $this->request->getPost('mysql_database'),
                'username' => $this->request->getPost('mysql_username'),
                'password' => $this->request->getPost('mysql_password')
            ];

            if ($this->testMySQLConnection($config)) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Connection successful!'
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Connection failed. Please check your credentials.'
                ]);
            }
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Invalid database type.'
        ]);
    }

    private function isSetupCompleted(): bool
    {
        return file_exists(WRITEPATH . 'setup_completed.flag');
    }

    private function testMySQLConnection(array $config): bool
    {
        try {
            $dsn = "mysql:host={$config['hostname']};port={$config['port']};dbname={$config['database']}";
            $pdo = new \PDO($dsn, $config['username'], $config['password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function initializeDatabase(array $setupData): void
    {
        // Implementation will depend on your database schema
        // This is a placeholder for database initialization
        log_message('info', 'Database initialization completed for: ' . $setupData['database']['type']);
    }

    private function markSetupCompleted(array $setupData): void
    {
        // Create setup completion flag
        $flagData = [
            'completed_at' => date('Y-m-d H:i:s'),
            'admin_userid' => $setupData['admin']['userid'],
            'database_type' => $setupData['database']['type']
        ];
        
        file_put_contents(WRITEPATH . 'setup_completed.flag', json_encode($flagData));
    }
}
