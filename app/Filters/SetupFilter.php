<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SetupFilter implements FilterInterface
{
    /**
     * Check if setup is completed before allowing access to protected routes
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if setup is completed
        if (!$this->isSetupCompleted()) {
            // Redirect to setup page
            return redirect()->to('/setup')->with('info', 'Please complete the initial setup first.');
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

    /**
     * Check if the application setup has been completed
     */
    private function isSetupCompleted(): bool
    {
        $flagPath = WRITEPATH . 'setup_completed.flag';
        
        if (!file_exists($flagPath)) {
            return false;
        }

        // Additional check: verify .env file exists
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) {
            return false;
        }

        return true;
    }
}
