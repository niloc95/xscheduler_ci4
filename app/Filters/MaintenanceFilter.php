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
