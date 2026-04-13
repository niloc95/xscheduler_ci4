<?php

namespace App\Services\Settings;

use App\Models\SettingModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class GeneralSettingsService
{
    private const MAX_UPLOAD_BYTES = 2097152;
    private const ALLOWED_LOGO_MIMES = [
        'image/png',
        'image/x-png',
        'image/jpeg',
        'image/pjpeg',
        'image/webp',
        'image/svg+xml',
        'image/svg',
        'image/gif',
    ];
    private const ALLOWED_LOGO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];
    private const ALLOWED_ICON_MIMES = [
        'image/png',
        'image/x-png',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'image/svg+xml',
        'image/svg',
    ];
    private const ALLOWED_ICON_EXTENSIONS = ['png', 'ico', 'svg'];

    private SettingModel $settingModel;

    public function __construct(?SettingModel $settingModel = null)
    {
        $this->settingModel = $settingModel ?? new SettingModel();
    }

    public function save(array $post, ?UploadedFile $logoFile, ?int $userId): array
    {
        $this->localUploadLog('save_enter', [
            'post_keys' => array_keys($post),
            'has_file' => $logoFile && !$logoFile->getError() ? 'yes' : 'no',
            'form_source' => $post['form_source'] ?? 'unknown',
        ]);

        $this->persistSettings($post, $userId);

        $uploadResult = $this->handleCompanyLogoUpload($logoFile, $userId);
        if ($uploadResult !== null) {
            return $uploadResult;
        }

        return [
            'type' => 'success',
            'message' => 'Settings saved successfully.',
        ];
    }

    public function uploadLogoForApi(?UploadedFile $file, ?int $userId): array
    {
        if (!$file) {
            return ['status' => 'validation_error', 'message' => 'No logo file received.'];
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['status' => 'validation_error', 'message' => 'Please choose a logo file to upload.'];
        }

        if (!$file->isValid()) {
            return ['status' => 'bad_request', 'message' => $file->getErrorString()];
        }

        if ($file->hasMoved()) {
            return ['status' => 'bad_request', 'message' => 'Upload failed: file has already been moved.'];
        }

        if ((int) $file->getSize() > self::MAX_UPLOAD_BYTES) {
            return ['status' => 'validation_error', 'message' => 'Logo upload too large. Maximum size is 2MB.'];
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
        $mimeAllowed = in_array($clientMime, self::ALLOWED_LOGO_MIMES, true) || in_array($realMime, self::ALLOWED_LOGO_MIMES, true);
        $extensionAllowed = in_array($extension, self::ALLOWED_LOGO_EXTENSIONS, true);

        if (!$mimeAllowed && !$extensionAllowed) {
            return ['status' => 'validation_error', 'message' => 'Unsupported logo format. Use PNG, JPG, SVG, WebP, or GIF.'];
        }

        $targetDir = rtrim(FCPATH, '/') . '/assets/settings';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return ['status' => 'server_error', 'message' => 'Logo upload directory is not writable.'];
        }

        $existing = $this->settingModel->getByKeys(['general.company_logo']);
        $previous = $existing['general.company_logo'] ?? null;
        if ($previous) {
            $previousPath = $this->resolveStoredAssetPath((string) $previous);
            if ($previousPath && is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        try {
            $safeName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        } catch (\Throwable $e) {
            $safeName = 'logo_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $extension;
        }

        if (!$file->move($targetDir, $safeName)) {
            return ['status' => 'server_error', 'message' => 'Unable to store uploaded logo.'];
        }

        $absolute = rtrim($targetDir, '/') . '/' . $safeName;
        try {
            if (!in_array($realMime, ['image/svg+xml', 'image/svg'], true)) {
                [$width, $height] = @getimagesize($absolute) ?: [null, null];
                if ($width && $width > 1200) {
                    $ratio = $height ? ($height / $width) : 1;
                    $this->resizeImageInPlace($absolute, $realMime ?: $clientMime, 1200, max(1, (int) round(1200 * $ratio)));
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Logo upload resize skipped: {msg}', ['msg' => $e->getMessage()]);
        }

        $relative = 'assets/settings/' . $safeName;
        $this->settingModel->upsert('general.company_logo', $relative, 'string', $userId);

        return [
            'status' => 'ok',
            'data' => [
                'path' => $relative,
                'url' => base_url($relative),
            ],
        ];
    }

    public function uploadIconForApi(?UploadedFile $file, ?int $userId): array
    {
        if (!$file) {
            return ['status' => 'validation_error', 'message' => 'No icon file received.'];
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['status' => 'validation_error', 'message' => 'Please choose an icon file to upload.'];
        }

        if (!$file->isValid()) {
            return ['status' => 'bad_request', 'message' => $file->getErrorString()];
        }

        if ($file->hasMoved()) {
            return ['status' => 'bad_request', 'message' => 'Upload failed: file has already been moved.'];
        }

        if ((int) $file->getSize() > self::MAX_UPLOAD_BYTES) {
            return ['status' => 'validation_error', 'message' => 'Icon upload too large. Maximum size is 2MB.'];
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
        $mimeAllowed = in_array($clientMime, self::ALLOWED_ICON_MIMES, true) || in_array($realMime, self::ALLOWED_ICON_MIMES, true);
        $extensionAllowed = in_array($extension, self::ALLOWED_ICON_EXTENSIONS, true);

        if (!$mimeAllowed && !$extensionAllowed) {
            return ['status' => 'validation_error', 'message' => 'Unsupported icon format. Use ICO, PNG, or SVG.'];
        }

        $targetDir = rtrim(FCPATH, '/') . '/assets/settings';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return ['status' => 'server_error', 'message' => 'Icon upload directory is not writable.'];
        }

        $existing = $this->settingModel->getByKeys(['general.company_icon']);
        $previous = $existing['general.company_icon'] ?? null;
        if ($previous) {
            $previousPath = $this->resolveStoredAssetPath((string) $previous);
            if ($previousPath && is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        try {
            $safeName = 'icon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        } catch (\Throwable $e) {
            $safeName = 'icon_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $extension;
        }

        if (!$file->move($targetDir, $safeName)) {
            return ['status' => 'server_error', 'message' => 'Unable to store uploaded icon.'];
        }

        $absolute = rtrim($targetDir, '/') . '/' . $safeName;
        $relative = 'assets/settings/' . $safeName;
        $this->settingModel->upsert('general.company_icon', $relative, 'string', $userId);

        return [
            'status' => 'ok',
            'data' => [
                'path' => $relative,
                'url' => base_url($relative),
            ],
        ];
    }

    private function persistSettings(array $post, ?int $userId): void
    {
        $upsert = function (string $key, $value) use ($userId): void {
            $type = 'string';

            if (is_string($value) && in_array(strtolower($value), ['on', 'true', '1', 'yes'], true)) {
                if (in_array($key, ['integrations.ldap_enabled'], true)) {
                    $value = true;
                    $type = 'bool';
                }
            } elseif (is_array($value)) {
                $type = 'json';
            } elseif (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '' && (($trimmed[0] === '{' && substr($trimmed, -1) === '}') || ($trimmed[0] === '[' && substr($trimmed, -1) === ']'))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                        $type = 'json';
                    }
                }
            }

            $this->settingModel->upsert($key, $value, $type, $userId);
        };

        $map = [
            'general.company_name' => 'company_name',
            'general.company_email' => 'company_email',
            'general.company_link' => 'company_link',
            'general.telephone_number' => 'telephone_number',
            'general.mobile_number' => 'mobile_number',
            'general.business_address' => 'business_address',
            'localization.time_format' => 'time_format',
            'localization.first_day' => 'first_day',
            'localization.language' => 'language',
            'localization.timezone' => 'timezone',
            'localization.currency' => 'currency',
            'booking.custom_field_1_title' => 'booking_custom_field_1_title',
            'booking.custom_field_1_type' => 'booking_custom_field_1_type',
            'booking.custom_field_2_title' => 'booking_custom_field_2_title',
            'booking.custom_field_2_type' => 'booking_custom_field_2_type',
            'booking.custom_field_3_title' => 'booking_custom_field_3_title',
            'booking.custom_field_3_type' => 'booking_custom_field_3_type',
            'booking.custom_field_4_title' => 'booking_custom_field_4_title',
            'booking.custom_field_4_type' => 'booking_custom_field_4_type',
            'booking.custom_field_5_title' => 'booking_custom_field_5_title',
            'booking.custom_field_5_type' => 'booking_custom_field_5_type',
            'booking.custom_field_6_title' => 'booking_custom_field_6_title',
            'booking.custom_field_6_type' => 'booking_custom_field_6_type',
            'booking.custom_fields' => 'custom_fields',
            'booking.statuses' => 'statuses',
            'booking.default_appointment_status' => 'booking_default_appointment_status',
            'business.work_start' => 'work_start',
            'business.work_end' => 'work_end',
            'business.break_start' => 'break_start',
            'business.break_end' => 'break_end',
            'business.blocked_periods' => 'blocked_periods',
            'business.reschedule' => 'reschedule',
            'business.cancel' => 'cancel',
            'business.future_limit' => 'future_limit',
            'legal.cookie_notice' => 'cookie_notice',
            'legal.terms' => 'terms',
            'legal.privacy' => 'privacy',
            'legal.cancellation_policy' => 'cancellation_policy',
            'legal.rescheduling_policy' => 'rescheduling_policy',
            'legal.terms_url' => 'terms_url',
            'legal.privacy_url' => 'privacy_url',
            'integrations.webhook_url' => 'webhook_url',
            'integrations.analytics' => 'analytics',
            'integrations.api_integrations' => 'api_integrations',
            'integrations.ldap_enabled' => 'ldap_enabled',
            'integrations.ldap_host' => 'ldap_host',
            'integrations.ldap_dn' => 'ldap_dn',
        ];

        if (isset($post['fields']) && is_array($post['fields'])) {
            $upsert('booking.fields', $post['fields']);
        }

        $checkboxFields = [
            'booking.first_names_display' => 'booking_first_names_display',
            'booking.first_names_required' => 'booking_first_names_required',
            'booking.surname_display' => 'booking_surname_display',
            'booking.surname_required' => 'booking_surname_required',
            'booking.email_display' => 'booking_email_display',
            'booking.email_required' => 'booking_email_required',
            'booking.phone_display' => 'booking_phone_display',
            'booking.phone_required' => 'booking_phone_required',
            'booking.address_display' => 'booking_address_display',
            'booking.address_required' => 'booking_address_required',
            'booking.notes_display' => 'booking_notes_display',
            'booking.notes_required' => 'booking_notes_required',
        ];

        for ($index = 1; $index <= 6; $index++) {
            $checkboxFields["booking.custom_field_{$index}_enabled"] = "booking_custom_field_{$index}_enabled";
            $checkboxFields["booking.custom_field_{$index}_required"] = "booking_custom_field_{$index}_required";
        }

        $this->localUploadLog('checkbox_processing', [
            'total_checkboxes' => count($checkboxFields),
            'posted_checkboxes' => array_keys(array_filter($post, static function ($key): bool {
                return strpos($key, 'booking_') === 0
                    && (strpos($key, '_display') !== false || strpos($key, '_required') !== false || strpos($key, '_enabled') !== false);
            }, ARRAY_FILTER_USE_KEY)),
        ]);

        foreach ($checkboxFields as $settingKey => $postKey) {
            $value = isset($post[$postKey]) && $post[$postKey] === '1' ? '1' : '0';
            $upsert($settingKey, $value);

            log_message('debug', 'Checkbox field: {key} = {value} (POST key: {post}, present: {present})', [
                'key' => $settingKey,
                'value' => $value,
                'post' => $postKey,
                'present' => isset($post[$postKey]) ? 'yes' : 'no',
            ]);
        }

        if (isset($post['blocked_periods'])) {
            $upsert('business.blocked_periods', $this->normalizeBlockedPeriods($post['blocked_periods']));
        }

        // Validate and normalise booking.default_appointment_status before generic save
        if (array_key_exists('booking_default_appointment_status', $post)) {
            $allowedStatuses = ['pending', 'confirmed'];
            $submitted = strtolower(trim((string) ($post['booking_default_appointment_status'] ?? '')));
            if (in_array($submitted, $allowedStatuses, true)) {
                $upsert('booking.default_appointment_status', $submitted);
            }
            // Silently ignore invalid values — the setting retains its previous value
        }

        foreach ($map as $settingKey => $postKey) {
            if ($settingKey === 'business.blocked_periods') {
                continue;
            }
            // Already handled explicitly above
            if ($settingKey === 'booking.default_appointment_status') {
                continue;
            }

            if (array_key_exists($postKey, $post)) {
                $upsert($settingKey, $post[$postKey]);
            }
        }
    }

    private function handleCompanyLogoUpload(?UploadedFile $file, ?int $userId): ?array
    {
        if (!$file) {
            $this->localUploadLog('no_file_in_request', []);
            return null;
        }

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

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (!$file->isValid()) {
            log_message('error', 'Logo upload failed: {err}', ['err' => $file->getErrorString()]);
            return ['type' => 'error', 'message' => 'Logo upload failed: ' . $file->getErrorString()];
        }

        if ($file->hasMoved()) {
            return ['type' => 'error', 'message' => 'Logo upload failed: file already moved.'];
        }

        if ((int) $file->getSize() > self::MAX_UPLOAD_BYTES) {
            return ['type' => 'error', 'message' => 'Logo upload too large. Max 2MB.'];
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
        $mimeAllowed = in_array($clientMime, self::ALLOWED_LOGO_MIMES, true) || in_array($realMime, self::ALLOWED_LOGO_MIMES, true);
        $extensionAllowed = in_array($extension, self::ALLOWED_LOGO_EXTENSIONS, true);
        if (!$mimeAllowed && !$extensionAllowed) {
            return ['type' => 'error', 'message' => 'Unsupported logo format. Use PNG, JPG, SVG, WebP, or GIF.'];
        }

        $targetDir = rtrim(FCPATH, '/') . '/assets/settings';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            log_message('error', 'Logo upload: target dir not writable: {dir}', ['dir' => $targetDir]);
        }

        $existing = $this->settingModel->getByKeys(['general.company_logo']);
        $previous = $existing['general.company_logo'] ?? null;
        if ($previous) {
            $previousPath = $this->resolveStoredAssetPath((string) $previous);
            if ($previousPath && is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        $safeName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if ($file->move($targetDir, $safeName)) {
            return $this->persistMovedLogo($targetDir, $safeName, $realMime, $clientMime, $userId);
        }

        return $this->persistLogoFallbackFromTemp($file, $targetDir, $safeName, $realMime, $clientMime, $userId);
    }

    private function persistMovedLogo(string $targetDir, string $safeName, string $realMime, string $clientMime, ?int $userId): array
    {
        $absolute = rtrim($targetDir, '/') . '/' . $safeName;
        $this->localUploadLog('moved', ['path' => $absolute]);
        log_message('debug', 'Logo upload: moved to {path}', ['path' => $absolute]);

        try {
            if (!in_array($realMime, ['image/svg+xml', 'image/svg'], true)) {
                [$width, $height] = @getimagesize($absolute) ?: [null, null];
                if ($width && $width > 1200) {
                    $ratio = $height / $width;
                    $newWidth = 1200;
                    $newHeight = max(1, (int) round($newWidth * $ratio));
                    $this->resizeImageInPlace($absolute, $realMime, $newWidth, $newHeight);
                }
            }
        } catch (\Throwable $e) {
            log_message('debug', 'Logo image resize skipped: ' . $e->getMessage());
        }

        $relative = 'assets/settings/' . $safeName;
        $this->settingModel->upsert('general.company_logo', $relative, 'string', $userId);
        return ['type' => 'success', 'message' => 'Settings saved successfully.'];
    }

    private function persistLogoFallbackFromTemp(UploadedFile $file, string $targetDir, string $safeName, string $realMime, string $clientMime, ?int $userId): array
    {
        $tempPath = method_exists($file, 'getTempName') ? $file->getTempName() : ($file->getRealPath() ?: '[unknown]');
        $errorMessage = $file->getErrorString();
        $permissions = @substr(sprintf('%o', @fileperms($targetDir)), -4) ?: '----';

        $this->localUploadLog('move_failed', [
            'dir' => $targetDir,
            'name' => $safeName,
            'tmp' => $tempPath,
            'err' => $errorMessage,
            'perms' => $permissions,
        ]);
        log_message('error', 'Logo upload: move() failed for {dir}/{name} tmp={tmp} err={err} targetPerms={perms}', [
            'dir' => $targetDir,
            'name' => $safeName,
            'tmp' => $tempPath,
            'err' => $errorMessage,
            'perms' => $permissions,
        ]);

        return ['type' => 'error', 'message' => 'Failed to save uploaded logo.'];
    }

    private function normalizeBlockedPeriods($raw): array
    {
        $parsed = [];

        if (!is_string($raw)) {
            return $parsed;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $parsed;
        }

        foreach ($decoded as $item) {
            if (is_array($item) && isset($item['start'], $item['end'])) {
                $parsed[] = [
                    'start' => $item['start'],
                    'end' => $item['end'],
                    'notes' => $item['notes'] ?? '',
                ];
            }
        }

        return $parsed;
    }

    private function resolveStoredAssetPath(string $stored): ?string
    {
        $normalized = ltrim($stored, '/');
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'assets/settings/')) {
            return rtrim(FCPATH, '/') . '/' . $normalized;
        }

        if (str_starts_with($normalized, 'uploads/settings/')) {
            return rtrim(WRITEPATH, '/') . '/' . $normalized;
        }

        return rtrim(WRITEPATH, '/') . '/' . $normalized;
    }

    private function resizeImageInPlace(string $path, string $mime, int $newWidth, int $newHeight): void
    {
        switch ($mime) {
            case 'image/jpeg':
                $source = @imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $source = @imagecreatefrompng($path);
                break;
            case 'image/gif':
                $source = @imagecreatefromgif($path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $source = @imagecreatefromwebp($path);
                    break;
                }
                return;
            default:
                return;
        }

        if (!$source) {
            return;
        }

        $destination = imagecreatetruecolor($newWidth, $newHeight);
        if (in_array($mime, ['image/png', 'image/gif'], true)) {
            imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

        switch ($mime) {
            case 'image/jpeg':
                @imagejpeg($destination, $path, 85);
                break;
            case 'image/png':
                @imagepng($destination, $path, 6);
                break;
            case 'image/gif':
                @imagegif($destination, $path);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    @imagewebp($destination, $path, 85);
                }
                break;
        }

        imagedestroy($source);
        imagedestroy($destination);
    }

    private function localUploadLog(string $event, array $context = []): void
    {
        try {
            $line = '[' . date('Y-m-d H:i:s') . '] settings.upload ' . $event . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . "\n";
            @file_put_contents(WRITEPATH . 'logs/upload-debug.log', $line, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}