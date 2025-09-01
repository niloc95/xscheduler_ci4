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

        $model = new UserModel();
        // Assuming providers identified by role = 'provider'
        $builder = $model->where('role', 'provider')->orderBy($sortField, strtoupper($sortDir));
        $rows = $builder->findAll($length, $offset);
        $total = $model->where('role', 'provider')->countAllResults();

    $items = array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'] ?? ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''),
                'email' => $p['email'] ?? null,
                'phone' => $p['phone'] ?? null,
                'active' => isset($p['active']) ? (bool)$p['active'] : true,
        'profile_image' => $this->providerImageUrl($p),
            ];
        }, $rows);

        return $this->ok($items, [
            'page' => $page,
            'length' => $length,
            'total' => (int)$total,
            'sort' => $sortField . ':' . $sortDir,
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
