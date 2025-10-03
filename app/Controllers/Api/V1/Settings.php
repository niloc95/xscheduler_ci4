<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\SettingFileModel;
use App\Models\SettingModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Settings extends BaseController
{
    use ResponseTrait;

    protected $model;

    public function __construct()
    {
        $this->model = new SettingModel();
    }

    /**
     * GET /api/v1/settings?prefix=general.
     * Returns a flat key=>value map.
     */
    public function index()
    {
        $prefix = $this->request->getGet('prefix');
        if ($prefix) {
            $data = $this->model->getByPrefix($prefix);
        } else {
            // Return all settings as a map
            $rows = $this->model->select(['setting_key', 'setting_value', 'setting_type'])->findAll();
            $data = [];
            foreach ($rows as $r) {
                $data[$r['setting_key']] = $this->model->getByKeys([$r['setting_key']])[$r['setting_key']] ?? null;
            }
        }
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /**
     * PUT /api/v1/settings (bulk upsert)
     * Body: { "general.company_name":"Acme", ... }
     */
    public function update()
    {
        $payload = $this->request->getJSON(true) ?? [];
        if (!is_array($payload)) {
            return $this->failValidationErrors('Invalid JSON payload');
        }
        $userId = session()->get('user_id');
        $count = 0;
        foreach ($payload as $key => $value) {
            // Infer type: JSON if array/object; boolean if true/false; else string
            $type = is_array($value) ? 'json' : (is_bool($value) ? 'bool' : 'string');
            if ($this->model->upsert($key, $value, $type, $userId)) {
                $count++;
            }
        }
        return $this->response->setJSON(['ok' => true, 'updated' => $count]);
    }

    /**
     * POST /api/v1/settings/logo
     * Handles company logo uploads independently of full settings form submission.
     */
    public function uploadLogo()
    {
        $file = $this->request->getFile('company_logo');
        if (!$file) {
            return $this->failValidationErrors('No logo file received.');
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->failValidationErrors('Please choose a logo file to upload.');
        }

        if (!$file->isValid()) {
            return $this->fail($file->getErrorString(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        if ($file->hasMoved()) {
            return $this->fail('Upload failed: file has already been moved.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $sizeBytes = (int) $file->getSize();
        if ($sizeBytes > (2 * 1024 * 1024)) {
            return $this->failValidationErrors('Logo upload too large. Maximum size is 2MB.');
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime   = strtolower((string) $file->getMimeType());
        $ext        = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));

        $allowedMimes = [
            'image/png','image/x-png','image/jpeg','image/pjpeg','image/webp','image/svg+xml','image/svg','image/gif'
        ];
        $allowedExts = ['png','jpg','jpeg','webp','svg','gif'];

        $mimeOk = in_array($clientMime, $allowedMimes, true) || in_array($realMime, $allowedMimes, true);
        $extOk  = in_array($ext, $allowedExts, true);
        if (!$mimeOk && !$extOk) {
            return $this->failValidationErrors('Unsupported logo format. Use PNG, JPG, SVG, WebP, or GIF.');
        }

        $targetDir = rtrim(FCPATH, '/').'/assets/settings';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return $this->failServerError('Logo upload directory is not writable.');
        }

        $existing = $this->model->getByKeys(['general.company_logo']);
        $previous = $existing['general.company_logo'] ?? null;
        if ($previous) {
            $prevPath = $this->resolveLogoPath((string) $previous);
            if ($prevPath && is_file($prevPath)) {
                @unlink($prevPath);
            }
        }

        try {
            $safeName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        } catch (\Throwable $e) {
            $safeName = 'logo_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $ext;
        }

        if (!$file->move($targetDir, $safeName)) {
            return $this->failServerError('Unable to store uploaded logo.');
        }

        $absolute = rtrim($targetDir, '/').'/'.$safeName;
        $mimeForResize = $realMime ?: $clientMime;

        if (!in_array($mimeForResize, ['image/svg+xml','image/svg'], true)) {
            [$w, $h] = @getimagesize($absolute) ?: [null, null];
            if ($w && $w > 1200) {
                $ratio = $h ? ($h / $w) : 1;
                $newW = 1200;
                $newH = max(1, (int) round($newW * $ratio));
                $this->resizeImageInPlace($absolute, $mimeForResize, $newW, $newH);
            }
        }

        $relative = 'assets/settings/' . $safeName;
        $userId = session()->get('user_id');

        $this->model->upsert('general.company_logo', $relative, 'string', $userId);

        try {
            $bytes = @file_get_contents($absolute);
            if ($bytes !== false) {
                $fileModel = new SettingFileModel();
                $fileModel->upsert('general.company_logo', $safeName, $mimeForResize ?: $clientMime, $bytes, $userId);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Logo upload: failed storing bytes to DB: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->response->setJSON([
            'ok' => true,
            'path' => $relative,
            'url' => base_url($relative)
        ]);
    }

    private function resolveLogoPath(string $stored): ?string
    {
        $normalized = ltrim($stored, '/');
        if ($normalized === '' || str_starts_with($normalized, 'db://')) {
            return null;
        }
        if (str_starts_with($normalized, 'assets/settings/')) {
            return rtrim(FCPATH, '/').'/'.$normalized;
        }
        if (str_starts_with($normalized, 'uploads/settings/')) {
            return rtrim(WRITEPATH, '/').'/'.$normalized;
        }
        return rtrim(WRITEPATH, '/').'/'.$normalized;
    }

    private function resizeImageInPlace(string $path, string $mime, int $newW, int $newH): void
    {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($path); break;
            case 'image/png': $src = @imagecreatefrompng($path); break;
            case 'image/gif': $src = @imagecreatefromgif($path); break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $src = @imagecreatefromwebp($path);
                } else {
                    return;
                }
                break;
            default:
                return;
        }
        if (!$src) {
            return;
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png','image/gif'], true)) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $sw, $sh);

        switch ($mime) {
            case 'image/jpeg': @imagejpeg($dst, $path, 85); break;
            case 'image/png': @imagepng($dst, $path, 6); break;
            case 'image/gif': @imagegif($dst, $path); break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    @imagewebp($dst, $path, 85);
                }
                break;
        }

        imagedestroy($src);
        imagedestroy($dst);
    }
}
