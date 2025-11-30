<?php

namespace App\Controllers\Api\V1;
use App\Models\UserModel;

class Providers extends BaseApiController
{
    // GET /api/v1/providers
    public function index()
    {
        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir] = $this->sortParam(['id','name','email','active'], 'name');
        
        // Check if includeColors parameter is set
        $includeColors = $this->request->getGet('includeColors') === 'true';

        $model = new UserModel();
        // Assuming providers identified by role = 'provider'
        $builder = $model->where('role', 'provider')->orderBy($sortField, strtoupper($sortDir));
        $rows = $builder->findAll($length, $offset);
        $total = $model->where('role', 'provider')->countAllResults();

    $items = array_map(function ($p) use ($includeColors) {
            $item = [
                'id' => (int)$p['id'],
                'name' => $p['name'] ?? ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''),
                'email' => $p['email'] ?? null,
                'phone' => $p['phone'] ?? null,
                'active' => isset($p['active']) ? (bool)$p['active'] : true,
        'profile_image' => $this->providerImageUrl($p),
            ];
            
            // Include color if requested
            if ($includeColors) {
                $item['color'] = $p['color'] ?? '#3B82F6'; // Default blue if no color set
            }
            
            return $item;
        }, $rows);

        return $this->ok($items, [
            'page' => $page,
            'length' => $length,
            'total' => (int)$total,
            'sort' => $sortField . ':' . $sortDir,
        ]);
    }

    /**
     * GET /api/v1/providers/{id}/services
     * Fetch services offered by a specific provider
     */
    public function services($id = null)
    {
        if (!$id) {
            return $this->error(400, 'Provider ID is required');
        }

        $id = (int) $id;
        $userModel = new UserModel();
        $provider = $userModel->find($id);

        if (!$provider || ($provider['role'] ?? null) !== 'provider') {
            return $this->error(404, 'Provider not found');
        }

        // Query services linked to this provider via xs_providers_services
        $db = \Config\Database::connect();
        $builder = $db->table('xs_services s')
            ->select('s.id, s.name, s.description, s.duration_min, s.price, s.category_id, s.active, c.name as category_name')
            ->join('xs_providers_services ps', 'ps.service_id = s.id', 'inner')
            ->join('xs_categories c', 'c.id = s.category_id', 'left')
            ->where('ps.provider_id', $id)
            ->where('s.active', 1)
            ->orderBy('c.name', 'ASC')
            ->orderBy('s.name', 'ASC');

        $services = $builder->get()->getResultArray();

        // Format response
        $items = array_map(function ($s) {
            return [
                'id' => (int) $s['id'],
                'name' => $s['name'],
                'description' => $s['description'] ?? null,
                'duration' => (int) $s['duration_min'],
                'durationMin' => (int) $s['duration_min'], // alias
                'price' => $s['price'] ? (float) $s['price'] : null,
                'categoryId' => $s['category_id'] ? (int) $s['category_id'] : null,
                'categoryName' => $s['category_name'] ?? null,
                'active' => (bool) $s['active'],
            ];
        }, $services);

        return $this->ok($items, [
            'providerId' => $id,
            'providerName' => $provider['name'] ?? ($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''),
            'total' => count($items),
        ]);
    }

    /**
     * GET /api/v1/providers/{id}/appointments
     * Fetch appointments for a specific provider with pagination
     * 
     * Query parameters:
     * - month: YYYY-MM format (defaults to current month)
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 20, max: 100)
     * - status: Filter by status (optional)
     * - service_id: Filter by service (optional)
     * - futureOnly: Only show today + future (default: false for this endpoint)
     */
    public function appointments($id = null)
    {
        if (!$id) {
            return $this->error(400, 'Provider ID is required');
        }

        $id = (int) $id;
        $userModel = new UserModel();
        $provider = $userModel->find($id);

        if (!$provider || ($provider['role'] ?? null) !== 'provider') {
            return $this->error(404, 'Provider not found');
        }

        // Parse query parameters
        $month = $this->request->getGet('month'); // YYYY-MM format
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));
        $offset = ($page - 1) * $perPage;
        $status = $this->request->getGet('status');
        $serviceId = $this->request->getGet('service_id');
        $futureOnly = filter_var($this->request->getGet('futureOnly'), FILTER_VALIDATE_BOOLEAN);

        // Determine date range
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
        } else {
            // Default to current month
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        // Build query
        $db = \Config\Database::connect();
        $builder = $db->table('xs_appointments a')
            ->select('a.*, 
                      CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                      c.email as customer_email,
                      c.phone as customer_phone,
                      s.name as service_name,
                      s.duration_min as service_duration,
                      s.price as service_price')
            ->join('xs_customers c', 'c.id = a.customer_id', 'left')
            ->join('xs_services s', 's.id = a.service_id', 'left')
            ->where('a.provider_id', $id)
            ->where('a.start_time >=', $startDate . ' 00:00:00')
            ->where('a.start_time <=', $endDate . ' 23:59:59')
            ->orderBy('a.start_time', 'ASC');

        // Apply optional filters
        if ($futureOnly) {
            $today = date('Y-m-d 00:00:00');
            $builder->where('a.start_time >=', $today);
        }

        if ($status) {
            $builder->where('a.status', $status);
        }

        if ($serviceId) {
            $builder->where('a.service_id', (int) $serviceId);
        }

        // Get total count
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults(false);

        // Apply pagination
        $appointments = $builder->limit($perPage, $offset)->get()->getResultArray();

        // Format response
        $items = array_map(function ($apt) {
            return [
                'id' => (int) $apt['id'],
                'hash' => $apt['hash'] ?? null,
                'customerId' => (int) $apt['customer_id'],
                'customerName' => $apt['customer_name'] ?? 'Unknown',
                'customerEmail' => $apt['customer_email'] ?? null,
                'customerPhone' => $apt['customer_phone'] ?? null,
                'serviceId' => (int) $apt['service_id'],
                'serviceName' => $apt['service_name'] ?? 'Appointment',
                'serviceDuration' => $apt['service_duration'] ? (int) $apt['service_duration'] : null,
                'servicePrice' => $apt['service_price'] ? (float) $apt['service_price'] : null,
                'start' => $apt['start_time'],
                'end' => $apt['end_time'],
                'status' => $apt['status'],
                'notes' => $apt['notes'] ?? null,
                'location' => $apt['location'] ?? null,
            ];
        }, $appointments);

        $totalPages = ceil($total / $perPage);

        return $this->ok($items, [
            'providerId' => $id,
            'providerName' => $provider['name'] ?? ($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''),
            'month' => substr($startDate, 0, 7),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => (int) $total,
                'totalPages' => (int) $totalPages,
                'hasMore' => $page < $totalPages,
            ],
            'filters' => [
                'status' => $status,
                'serviceId' => $serviceId ? (int) $serviceId : null,
                'futureOnly' => $futureOnly,
            ],
        ]);
    }

    // POST /api/v1/providers/{id}/profile-image
    public function uploadProfileImage($id)
    {
        $id = (int) $id;
        if ($id < 1) return $this->error(400, 'Invalid provider id');

        $userModel = new UserModel();
        $user = $userModel->find($id);
        if (!$user || ($user['role'] ?? null) !== 'provider') {
            return $this->error(404, 'Provider not found');
        }

        $file = $this->request->getFile('image');
        if (!$file) return $this->error(400, 'No file uploaded');
        if ($file->getError() === UPLOAD_ERR_NO_FILE) return $this->error(400, 'No file uploaded');
        if (!$file->isValid()) return $this->error(400, 'Upload failed: ' . $file->getErrorString());
        if ($file->hasMoved()) return $this->error(400, 'Upload failed: file already moved');

        $sizeBytes = (int) $file->getSize();
        if ($sizeBytes > (2 * 1024 * 1024)) {
            return $this->error(400, 'Image too large. Max 2MB');
        }
        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime   = strtolower((string) $file->getMimeType());
        $ext        = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
        $allowedMimes = ['image/png','image/x-png','image/jpeg','image/pjpeg','image/webp','image/gif'];
        $allowedExts = ['png','jpg','jpeg','webp','gif'];
        $mimeOk = in_array($clientMime, $allowedMimes, true) || in_array($realMime, $allowedMimes, true);
        $extOk  = in_array($ext, $allowedExts, true);
        if (!$mimeOk && !$extOk) return $this->error(400, 'Unsupported image format');

        $targetDir = rtrim(FCPATH, '/').'/assets/providers';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return $this->error(500, 'Upload directory not writable');
        }

        $safeName = 'provider_' . $id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!$file->move($targetDir, $safeName)) {
            return $this->error(500, 'Failed to move uploaded file');
        }

        $absolute = rtrim($targetDir, '/').'/'.$safeName;
        try {
            [$w, $h] = @getimagesize($absolute) ?: [null, null];
            if ($w && $w > 600) {
                $ratio = $h / $w;
                $newW = 600; $newH = max(1, (int) round($newW * $ratio));
                $this->resizeImageInPlace($absolute, $realMime, $newW, $newH);
            }
        } catch (\Throwable $e) {}

        $relative = 'assets/providers/' . $safeName;
        $userModel->update($id, ['profile_image' => $relative]);

        return $this->ok([
            'id' => $id,
            'profile_image' => base_url($relative),
        ]);
    }

    private function providerImageUrl(array $user): ?string
    {
        $path = $user['profile_image'] ?? null;
        if (!$path) return null;
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'assets/providers/')) {
            return base_url($path);
        }
        return base_url('writable/' . $path);
    }

    private function resizeImageInPlace(string $path, string $mime, int $newW, int $newH): void
    {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($path); break;
            case 'image/png': $src = @imagecreatefrompng($path); break;
            case 'image/gif': $src = @imagecreatefromgif($path); break;
            case 'image/webp': if (function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($path); } else { return; } break;
            default: return;
        }
        if (!$src) return;
        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png','image/gif'], true)) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        $sw = imagesx($src); $sh = imagesy($src);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $sw, $sh);
        switch ($mime) {
            case 'image/jpeg': @imagejpeg($dst, $path, 85); break;
            case 'image/png': @imagepng($dst, $path, 6); break;
            case 'image/gif': @imagegif($dst, $path); break;
            case 'image/webp': if (function_exists('imagewebp')) { @imagewebp($dst, $path, 85); } break;
        }
        imagedestroy($src); imagedestroy($dst);
    }
}
