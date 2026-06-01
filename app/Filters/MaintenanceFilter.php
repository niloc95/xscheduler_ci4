<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class MaintenanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $flagPath = WRITEPATH . 'maintenance.flag';

        if (!file_exists($flagPath)) {
            return;
        }

        $data = json_decode(file_get_contents($flagPath) ?: '{}', true) ?? [];

        // Admin session bypasses maintenance mode
        $user = session()->get('user');
        if ($user) {
            $roles = (array) ($user['roles'] ?? [$user['role'] ?? '']);
            if (in_array('admin', $roles, true)) {
                return;
            }
        }

        // Not logged in → redirect to login so an admin can authenticate after
        // an inactivity timeout fires during a maintenance window. Without this,
        // the login page is unreachable and the admin is completely locked out.
        // auth/* routes are also exempted in Filters.php for belt-and-suspenders.
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        return response()
            ->setStatusCode(503)
            ->setHeader('Retry-After', '300')
            ->setBody(view('errors/maintenance', [
                'since'   => $data['since']   ?? '',
                'version' => $data['version'] ?? '',
                'phase'   => $data['phase']   ?? '',
            ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
