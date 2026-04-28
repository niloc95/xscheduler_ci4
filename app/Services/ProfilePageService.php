<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\AuditLogModel;
use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;

helper('app');

class ProfilePageService
{
    protected UserModel $userModel;
    protected AppointmentModel $appointmentModel;
    protected AuditLogModel $auditLogModel;
    protected ProviderStaffModel $providerStaffModel;
    protected ?bool $hasStatusColumn = null;
    protected ?bool $hasIsActiveColumn = null;

    public function __construct(
        ?UserModel $userModel = null,
        ?AppointmentModel $appointmentModel = null,
        ?AuditLogModel $auditLogModel = null,
        ?ProviderStaffModel $providerStaffModel = null
    )
    {
        $this->userModel = $userModel ?? new UserModel();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->auditLogModel = $auditLogModel ?? new AuditLogModel();
        $this->providerStaffModel = $providerStaffModel ?? new ProviderStaffModel();
    }

    public function buildViewData(int $userId, string $userRole, array $flash = []): ?array
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            return null;
        }

        $nameParts = $this->splitName($user['name'] ?? '');

        return [
            'title' => 'My Profile',
            'current_page' => 'profile',
            'user_role' => $userRole,
            'user' => $user,
            'profileImageUrl' => avatar_profile_image_url($user),
            'profileInitials' => avatar_initials($user['name'] ?? '', 'U'),
            'profileForm' => [
                'first_name' => old('first_name', $nameParts['first_name']),
                'last_name' => old('last_name', $nameParts['last_name']),
                'email' => old('email', $user['email'] ?? ''),
                'phone' => old('phone', $user['phone'] ?? ''),
            ],
            'account_summary' => $this->buildAccountSummary($user, $userRole),
            'summary_cards' => $this->buildSummaryCards($user, $userRole),
            'recent_activity' => $this->buildRecentActivity($userId),
            'showNotificationsTab' => in_array($userRole, ['provider', 'staff'], true),
            'active_tab' => $flash['active_tab'] ?? 'profile',
            'profile_errors' => $flash['profile_errors'] ?? [],
            'password_errors' => $flash['password_errors'] ?? [],
        ];
    }

    protected function buildAccountSummary(array $user, string $userRole): array
    {
        $statusValue = $this->isUserActive($user) ? 'Active' : 'Inactive';
        $statusClasses = $this->isUserActive($user)
            ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
            : 'bg-amber-100 text-amber-700 border border-amber-200';

        $summary = [
            [
                'label' => 'Role',
                'value' => $this->humanizeRole($userRole),
                'icon' => 'badge',
            ],
            [
                'label' => 'Member since',
                'value' => $this->formatDate($user['created_at'] ?? null, 'M j, Y'),
                'icon' => 'calendar_month',
            ],
            [
                'label' => 'Last sign in',
                'value' => $this->formatDate($user['last_login'] ?? null),
                'icon' => 'history',
            ],
            [
                'label' => 'Account status',
                'value' => $statusValue,
                'icon' => 'verified_user',
                'badge' => true,
                'classes' => $statusClasses,
            ],
        ];

        if (!empty($user['phone'])) {
            $summary[] = [
                'label' => 'Phone',
                'value' => $user['phone'],
                'icon' => 'call',
            ];
        }

        return $summary;
    }

    protected function buildSummaryCards(array $user, string $userRole): array
    {
        if ($userRole === 'admin') {
            $appointmentStats = $this->appointmentModel->getStats();
            $userStats = $this->userModel->getStats();

            return [
                $this->buildCard('Appointments', (int) ($appointmentStats['total'] ?? 0), 'Across the full schedule', 'calendar_month', 'sky'),
                $this->buildCard('Upcoming', (int) ($appointmentStats['upcoming'] ?? 0), 'Scheduled ahead of now', 'event_upcoming', 'amber'),
                $this->buildCard('Users', (int) ($userStats['total'] ?? 0), 'Accounts on this workspace', 'group', 'emerald'),
            ];
        }

        if ($userRole === 'provider') {
            $stats = $this->appointmentModel->getStats(['provider_id' => (int) $user['id']]);

            return [
                $this->buildCard('Appointments', (int) ($stats['total'] ?? 0), 'Booked with you', 'calendar_month', 'sky'),
                $this->buildCard('Upcoming', (int) ($stats['upcoming'] ?? 0), 'Still on your calendar', 'event_upcoming', 'amber'),
                $this->buildCard('Completed', (int) ($stats['completed'] ?? 0), 'Marked complete', 'task_alt', 'emerald'),
            ];
        }

        if ($userRole === 'staff') {
            $providers = $this->providerStaffModel->getProvidersForStaff((int) $user['id'], 'active');
            $providerIds = array_values(array_filter(array_map(static fn(array $provider): int => (int) ($provider['id'] ?? 0), $providers)));
            $stats = !empty($providerIds)
                ? $this->appointmentModel->getStats(['provider_id' => $providerIds])
                : ['upcoming' => 0, 'today' => 0];

            return [
                $this->buildCard('Assigned providers', count($providerIds), 'Providers currently linked to you', 'support_agent', 'sky'),
                $this->buildCard('Upcoming', (int) ($stats['upcoming'] ?? 0), 'Appointments in your provider scope', 'event_upcoming', 'amber'),
                $this->buildCard('Today', (int) ($stats['today'] ?? 0), 'Appointments scheduled today', 'today', 'emerald'),
            ];
        }

        return [];
    }

    protected function buildRecentActivity(int $userId, int $limit = 8): array
    {
        $logs = $this->auditLogModel->getByUser($userId, $limit * 2);
        $activity = [];

        foreach ($logs as $log) {
            $item = $this->mapAuditLogToActivity($log);

            if ($item === null) {
                continue;
            }

            $activity[] = $item;

            if (count($activity) >= $limit) {
                break;
            }
        }

        return $activity;
    }

    protected function mapAuditLogToActivity(array $log): ?array
    {
        $action = strtolower((string) ($log['action'] ?? ''));
        $newValue = $this->decodeAuditPayload($log['new_value'] ?? null);

        switch ($action) {
            case 'user_login':
                return $this->buildActivityItem('login', 'Signed in', 'A successful sign-in was recorded for this account.', $log['created_at'] ?? null);

            case 'user_logout':
                return $this->buildActivityItem('logout', 'Signed out', 'This account was signed out of the application.', $log['created_at'] ?? null);

            case 'user_updated':
                return $this->buildActivityItem('person', 'Profile updated', 'Your account details were updated.', $log['created_at'] ?? null);

            case 'password_changed':
                return $this->buildActivityItem('lock_reset', 'Password changed', 'Your password was changed successfully.', $log['created_at'] ?? null);

            case 'profile_photo_updated':
                return $this->buildActivityItem('photo_camera', 'Profile photo updated', 'Your profile photo was replaced.', $log['created_at'] ?? null);

            case 'notification_preferences_updated':
                $notificationsEnabled = (bool) ($newValue['notify_on_appointments'] ?? false);
                $description = $notificationsEnabled
                    ? 'Appointment notifications were enabled.'
                    : 'Appointment notifications were disabled.';

                return $this->buildActivityItem('notifications', 'Notification preferences updated', $description, $log['created_at'] ?? null);

            case 'login_failed':
                return $this->buildActivityItem('warning', 'Sign-in failed', 'A failed sign-in attempt was recorded for this account.', $log['created_at'] ?? null);

            default:
                return null;
        }
    }

    protected function buildActivityItem(string $icon, string $title, string $description, ?string $createdAt): array
    {
        return [
            'icon' => $icon,
            'title' => $title,
            'description' => $description,
            'time' => $this->formatRelativeTime($createdAt),
            'timestamp' => $this->formatDate($createdAt),
        ];
    }

    protected function buildCard(string $label, int $value, string $description, string $icon, string $tone): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'description' => $description,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    protected function buildProfileImageUrl(array $user): ?string
    {
        return avatar_profile_image_url($user);
    }

    protected function buildProfileInitials(string $name): string
    {
        return avatar_initials($name, 'U');
    }

    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }

    protected function humanizeRole(string $role): string
    {
        return ucfirst(str_replace('_', ' ', $role));
    }

    protected function isUserActive(array $user): bool
    {
        if ($this->usersColumnExists('is_active') && array_key_exists('is_active', $user)) {
            return (bool) $user['is_active'];
        }

        if ($this->usersColumnExists('status') && array_key_exists('status', $user)) {
            return strtolower((string) $user['status']) === 'active';
        }

        return true;
    }

    protected function usersColumnExists(string $column): bool
    {
        $db = db_connect();
        $usersTable = method_exists($db, 'prefixTable') ? $db->prefixTable('users') : 'xs_users';

        if ($column === 'status') {
            $this->hasStatusColumn ??= method_exists($db, 'fieldExists')
                ? ($db->fieldExists('status', $usersTable) || $db->fieldExists('status', 'users'))
                : true;

            return $this->hasStatusColumn;
        }

        if ($column === 'is_active') {
            $this->hasIsActiveColumn ??= method_exists($db, 'fieldExists')
                ? ($db->fieldExists('is_active', $usersTable) || $db->fieldExists('is_active', 'users'))
                : true;

            return $this->hasIsActiveColumn;
        }

        return false;
    }

    protected function decodeAuditPayload(?string $payload): array
    {
        if (!$payload) {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function formatDate(?string $value, string $format = 'M j, Y g:i A'): string
    {
        if (empty($value)) {
            return 'Not available';
        }

        try {
            $timezone = new DateTimeZone(TimezoneService::businessTimezone());
            $dateTime = new DateTimeImmutable($value);

            return $dateTime->setTimezone($timezone)->format($format);
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    protected function formatRelativeTime(?string $value): string
    {
        if (empty($value)) {
            return 'Recently';
        }

        try {
            $timezone = new DateTimeZone(TimezoneService::businessTimezone());
            $timestamp = (new DateTimeImmutable($value))->setTimezone($timezone);
            $now = new DateTimeImmutable('now', $timezone);
            $seconds = max(0, $now->getTimestamp() - $timestamp->getTimestamp());

            if ($seconds < 60) {
                return 'Just now';
            }

            $minutes = (int) floor($seconds / 60);
            if ($minutes < 60) {
                return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
            }

            $hours = (int) floor($minutes / 60);
            if ($hours < 24) {
                return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
            }

            $days = (int) floor($hours / 24);
            if ($days < 7) {
                return $days === 1 ? '1 day ago' : $days . ' days ago';
            }

            return $timestamp->format('M j, Y');
        } catch (\Throwable $exception) {
            return 'Recently';
        }
    }
}