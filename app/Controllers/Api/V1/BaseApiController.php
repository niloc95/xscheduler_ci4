<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;

class BaseApiController extends BaseController
{
    protected function ok($data = null, array $meta = [])
    {
        return $this->response->setJSON([
            'data' => $data,
            'meta' => (object) $meta,
        ]);
    }

    protected function created($data = null, array $meta = [])
    {
        return $this->response->setStatusCode(201)->setJSON([
            'data' => $data,
            'meta' => (object) $meta,
        ]);
    }

    protected function error(int $status, string $message, ?string $code = null, $details = null)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details,
            ],
        ]);
    }

    protected function paginationParams(): array
    {
        $req = $this->request;
        $page = max(1, (int) ($req->getGet('page') ?? 1));
        $length = (int) ($req->getGet('length') ?? 25);
        if ($length < 1) $length = 25;
        if ($length > 100) $length = 100;
        $offset = ($page - 1) * $length;
        return [$page, $length, $offset];
    }

    protected function sortParam(array $allowed, string $default): array
    {
        $sortRaw = (string) ($this->request->getGet('sort') ?? $default);
        $parts = explode(':', $sortRaw, 2);
        $field = $parts[0] ?? $default;
        $dir = strtolower($parts[1] ?? 'asc');
        if (!in_array($field, $allowed, true)) {
            $field = $default;
        }
        if (!in_array($dir, ['asc','desc'], true)) {
            $dir = 'asc';
        }
        return [$field, $dir];
    }
}
