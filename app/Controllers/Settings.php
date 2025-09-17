<?php

namespace App\Controllers;

use App\Models\SettingModel;

class Settings extends BaseController
{
    public function index()
    {
        $this->localUploadLog('index_hit', []);
        
        // Load current settings to pass to the view
        $settingModel = new SettingModel();
        $settings = $settingModel->getByKeys([
            'general.company_name',
            'general.company_email', 
            'general.company_link',
            'general.telephone_number',
            'general.mobile_number',
            'general.business_address',
            'localization.time_format',
            'localization.first_day',
            'localization.language',
            'localization.timezone',
            'localization.currency',
            'booking.custom_fields',
            'booking.statuses',
            'business.work_start',
            'business.work_end',
            'business.break_start', 
            'business.break_end',
            'business.blocked_periods',
            'business.reschedule',
            'business.cancel',
            'business.future_limit',
            'legal.cookie_notice',
            'legal.terms',
            'legal.privacy',
            'integrations.webhook_url',
            'integrations.analytics',
            'integrations.api_integrations',
            'integrations.ldap_enabled',
            'integrations.ldap_host',
            'integrations.ldap_dn'
        ]);
        
        $data = [
            'user' => session()->get('user') ?? [
                'name' => 'System Administrator',
                'role' => 'admin',
                'email' => 'admin@webschedulr.com',
            ],
            'settings' => $settings, // Pass settings to view
        ];

        return view('settings', $data);
    }

    public function save()
    {
        // Log that we reached the save method
        $this->localUploadLog('save_method_reached', [
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getUri()->getPath(),
            'method_upper' => strtoupper($this->request->getMethod())
        ]);
        
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            $this->localUploadLog('not_post_method', ['method' => $this->request->getMethod()]);
            return redirect()->to(base_url('settings'));
        }

        $this->localUploadLog('save_enter', [
            'post_keys' => array_keys($this->request->getPost() ?? []),
            'has_file' => $this->request->getFile('company_logo') && !$this->request->getFile('company_logo')->getError() ? 'yes' : 'no',
            'form_source' => $this->request->getPost('form_source') ?? 'unknown'
        ]);

        $post = $this->request->getPost();
        $model = new SettingModel();
        $userId = session()->get('user_id');

        $upsert = function (string $key, $value) use ($model, $userId) {
            $type = 'string';
            if (is_string($value) && in_array(strtolower($value), ['on','true','1','yes'], true)) {
                $boolFlags = [
                    'integrations.ldap_enabled',
                ];
                if (in_array($key, $boolFlags, true)) {
                    $value = true;
                    $type = 'bool';
                }
            } elseif (is_array($value)) {
                $type = 'json';
            } elseif (is_string($value)) {
                $trim = trim($value);
                if (($trim !== '') && (($trim[0] === '{' && substr($trim, -1) === '}') || ($trim[0] === '[' && substr($trim, -1) === ']'))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                        $type = 'json';
                    }
                }
            }
            $model->upsert($key, $value, $type, $userId);
        };

        $map = [
            'general.company_name'  => 'company_name',
            'general.company_email' => 'company_email',
            'general.company_link'  => 'company_link',
            'general.telephone_number' => 'telephone_number',
            'general.mobile_number' => 'mobile_number',
            'general.business_address' => 'business_address',
            'localization.time_format' => 'time_format',
            'localization.first_day'   => 'first_day',
            'localization.language'    => 'language',
            'localization.timezone'    => 'timezone',
            'localization.currency'    => 'currency',
            'booking.custom_fields' => 'custom_fields',
            'booking.statuses'      => 'statuses',
            'business.work_start'     => 'work_start',
            'business.work_end'       => 'work_end',
            'business.break_start'    => 'break_start',
            'business.break_end'      => 'break_end',
            'business.blocked_periods'=> 'blocked_periods',
            'business.reschedule'     => 'reschedule',
            'business.cancel'         => 'cancel',
            'business.future_limit'   => 'future_limit',
            'legal.cookie_notice' => 'cookie_notice',
            'legal.terms'         => 'terms',
            'legal.privacy'       => 'privacy',
            'integrations.webhook_url'  => 'webhook_url',
            'integrations.analytics'    => 'analytics',
            'integrations.api_integrations' => 'api_integrations',
            'integrations.ldap_enabled' => 'ldap_enabled',
            'integrations.ldap_host'    => 'ldap_host',
            'integrations.ldap_dn'      => 'ldap_dn',
        ];

        if (isset($post['fields']) && is_array($post['fields'])) {
            $upsert('booking.fields', $post['fields']);
        }

        // Process regular settings fields
        foreach ($map as $settingKey => $postKey) {
            if (array_key_exists($postKey, $post)) {
                $upsert($settingKey, $post[$postKey]);
            }
        }

        // Handle company logo upload with validation
    $file = $this->request->getFile('company_logo');
    if ($file) {
            $this->localUploadLog('begin', [
                'err' => $file->getError(),
                'name' => $file->getName(),
                'size' => (int) $file->getSize(),
                'cm' => (string) $file->getClientMimeType(),
                'rm' => (string) $file->getMimeType(),
            ]);
            log_message('debug', 'Logo upload: error={err} name={name} size={size} clientMime={cm} realMime={rm}', [
                'err' => $file->getError(),
                'name' => $file->getName(),
                'size' => $file->getSize(),
                'cm' => $file->getClientMimeType(),
                'rm' => $file->getMimeType(),
            ]);
            // If no file provided, skip silently
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                // nothing to do
            } elseif (!$file->isValid()) {
                session()->setFlashdata('error', 'Logo upload failed: ' . $file->getErrorString());
                log_message('error', 'Logo upload failed: {err}', ['err' => $file->getErrorString()]);
            } elseif ($file->hasMoved()) {
                // Already moved by PHP for some reason
                session()->setFlashdata('error', 'Logo upload failed: file already moved.');
            } else {
                $sizeBytes = (int) $file->getSize();
                if ($sizeBytes > (2 * 1024 * 1024)) {
                    session()->setFlashdata('error', 'Logo upload too large. Max 2MB.');
                    return redirect()->to(base_url('settings'));
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
                    session()->setFlashdata('error', 'Unsupported logo format. Use PNG, JPG, SVG, WebP, or GIF.');
                    return redirect()->to(base_url('settings'));
                }

                // Store under public assets to serve directly
                $targetDir = rtrim(FCPATH, '/').'/assets/settings';
                if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
                if (!is_dir($targetDir) || !is_writable($targetDir)) {
                    log_message('error', 'Logo upload: target dir not writable: {dir}', ['dir' => $targetDir]);
                }

                // Remove previous logo if exists
                $existing = $model->getByKeys(['general.company_logo']);
                $prevRel = $existing['general.company_logo'] ?? null;
                if ($prevRel) {
                    $prev = ltrim((string)$prevRel, '/');
                    if (str_starts_with($prev, 'assets/settings/')) {
                        $prevPath = rtrim(FCPATH, '/').'/'.$prev;
                    } elseif (str_starts_with($prev, 'uploads/settings/')) {
                        $prevPath = rtrim(WRITEPATH, '/').'/'.$prev;
                    } else {
                        $prevPath = rtrim(WRITEPATH, '/').'/'.$prev;
                    }
                    if (is_file($prevPath)) { @unlink($prevPath); }
                }

                $safeName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if ($file->move($targetDir, $safeName)) {
                    $absolute = rtrim($targetDir, '/').'/'.$safeName;
                    $this->localUploadLog('moved', ['path' => $absolute]);
                    log_message('debug', 'Logo upload: moved to {path}', ['path' => $absolute]);
                    // Basic downscale for very large raster images to max width 1200px
                    try {
                        if (!in_array($realMime, ['image/svg+xml','image/svg'], true)) {
                            [$w, $h] = @getimagesize($absolute) ?: [null, null];
                            if ($w && $w > 1200) {
                                $ratio = $h / $w;
                                $newW = 1200;
                                $newH = max(1, (int) round($newW * $ratio));
                                $this->resizeImageInPlace($absolute, $realMime, $newW, $newH);
                            }
                        }
                    } catch (\Throwable $e) {}

                    // 1) File-based path under public assets
                    $relative = 'assets/settings/' . $safeName;
                    $model->upsert('general.company_logo', $relative, 'string', $userId);

                    // 2) Additionally persist bytes to DB for environments without public writable access
                    try {
                        $bytes = @file_get_contents($absolute);
                        if ($bytes !== false) {
                            $fileModel = new \App\Models\SettingFileModel();
                            $okDb = $fileModel->upsert('general.company_logo', $safeName, $realMime ?: $clientMime, $bytes, $userId);
                            $this->localUploadLog('db_store_after_move', ['status' => $okDb ? 'OK' : 'FAIL']);
                            log_message('debug', 'Logo upload: DB store {status}', ['status' => $okDb ? 'OK' : 'FAIL']);
                        } else {
                            $this->localUploadLog('db_read_after_move_fail', ['path' => $absolute]);
                            log_message('warning', 'Logo upload: could not read moved file for DB store: {path}', ['path' => $absolute]);
                        }
                    } catch (\Throwable $e) {
                        $this->localUploadLog('db_store_exception', ['msg' => $e->getMessage()]);
                        log_message('error', 'Logo upload: exception during DB store: {msg}', ['msg' => $e->getMessage()]);
                    }
                } else {
                    // Move failed – attempt to capture more diagnostics and still persist to DB directly from temp file
                    $tmpPath = method_exists($file, 'getTempName') ? $file->getTempName() : ($file->getRealPath() ?: '[unknown]');
                    $errMsg = $file->getErrorString();
                    $perms = @substr(sprintf('%o', @fileperms($targetDir)), -4) ?: '----';
                    $this->localUploadLog('move_failed', [
                        'dir' => $targetDir,
                        'name' => $safeName,
                        'tmp' => $tmpPath,
                        'err' => $errMsg,
                        'perms' => $perms,
                    ]);
                    log_message('error', 'Logo upload: move() failed for {dir}/{name} tmp={tmp} err={err} targetPerms={perms}', [
                        'dir' => $targetDir,
                        'name' => $safeName,
                        'tmp' => $tmpPath,
                        'err' => $errMsg,
                        'perms' => $perms,
                    ]);

                    // Try DB store from temp file as a fallback
                    try {
                        $tmpFile = method_exists($file, 'getTempName') ? $file->getTempName() : $file->getRealPath();
                        if ($tmpFile && is_file($tmpFile)) {
                            $bytes = @file_get_contents($tmpFile);
                            if ($bytes !== false) {
                                $fileModel = new \App\Models\SettingFileModel();
                                $okDb = $fileModel->upsert('general.company_logo', $file->getName(), $realMime ?: $clientMime, $bytes, $userId);
                                $this->localUploadLog('db_fallback_from_tmp', ['status' => $okDb ? 'OK' : 'FAIL']);
                                log_message('warning', 'Logo upload: persisted to DB from temp file due to move failure. status={status}', ['status' => $okDb ? 'OK' : 'FAIL']);
                                if ($okDb) {
                                    // Update setting to reference DB-backed asset
                                    $model->upsert('general.company_logo', 'db://' . $file->getName(), 'string', $userId);
                                    session()->setFlashdata('success', 'Settings saved. Logo stored in database due to filesystem issue.');
                                    return redirect()->to(base_url('settings'));
                                }
                            } else {
                                $this->localUploadLog('db_fallback_read_tmp_failed', []);
                                log_message('error', 'Logo upload: failed reading temp file for DB fallback');
                            }
                        } else {
                            $this->localUploadLog('db_fallback_tmp_missing', []);
                            log_message('error', 'Logo upload: temp file missing for DB fallback');
                        }
                    } catch (\Throwable $e) {
                        $this->localUploadLog('db_fallback_exception', ['msg' => $e->getMessage()]);
                        log_message('error', 'Logo upload: exception during DB fallback: {msg}', ['msg' => $e->getMessage()]);
                    }

                    session()->setFlashdata('error', 'Failed to save uploaded logo.');
                }
            }
        } else {
            $this->localUploadLog('no_file_in_request', []);
        }

        session()->setFlashdata('success', 'Settings saved successfully.');
        return redirect()->to(base_url('settings'));
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

    private function localUploadLog(string $event, array $ctx = []): void
    {
        try {
            $line = '[' . date('Y-m-d H:i:s') . "] settings.upload " . $event . ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES) . "\n";
            @file_put_contents(WRITEPATH . 'logs/upload-debug.log', $line, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
