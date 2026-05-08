<?php

namespace App\Services;

use App\Models\NotificationDeliveryLogModel;
use App\Models\NotificationQueueModel;

class NotificationCenterService
{
    private NotificationDeliveryLogModel $deliveryLogModel;
    private NotificationQueueModel $queueModel;
    private LocalizationSettingsService $localizationSettingsService;
    private NotificationAutomationStatusService $notificationAutomationStatusService;

    public function __construct(
        ?NotificationDeliveryLogModel $deliveryLogModel = null,
        ?NotificationQueueModel $queueModel = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?NotificationAutomationStatusService $notificationAutomationStatusService = null,
    ) {
        $this->deliveryLogModel = $deliveryLogModel ?? new NotificationDeliveryLogModel();
        $this->queueModel = $queueModel ?? new NotificationQueueModel();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->notificationAutomationStatusService = $notificationAutomationStatusService ?? new NotificationAutomationStatusService();
    }

    public function buildIndexData(string|array $filterOrQuery, ?array $currentUser, ?string $currentRole): array
    {
        $query = is_array($filterOrQuery) ? $filterOrQuery : ['filter' => $filterOrQuery];
        $businessId = $this->resolveBusinessId();
        $feedFilter = $this->normalizeFeedFilter((string) ($query['filter'] ?? 'all'));
        $isAdmin = $this->canViewDeliveryLogs($currentUser, $currentRole);
        $notificationTab = $this->resolveTab((string) ($query['tab'] ?? 'activity'), $isAdmin);
        $unreadCount = $this->getUnreadCount();
        $notifications = $this->getNotifications($feedFilter);
        $deliveryLogFilters = $this->normalizeDeliveryLogFilters($query);
        $businessOptions = $isAdmin ? $this->getNotificationBusinessOptions($businessId) : [];
        $deliveryLogs = $isAdmin && $notificationTab === 'delivery-logs'
            ? $this->getDeliveryLogs($businessId, $deliveryLogFilters)
            : [];
        $ui = $this->buildUiState(
            $businessId,
            $notificationTab,
            $feedFilter,
            $deliveryLogFilters,
            $isAdmin,
            $businessOptions
        );

        return [
            'title' => 'Notifications',
            'current_page' => 'notifications',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'filter' => $feedFilter,
            'notificationTab' => $notificationTab,
            'notificationIsAdmin' => $isAdmin,
            'notificationCurrentBusinessId' => $businessId,
            'notificationBusinessOptions' => $businessOptions,
            'notificationBusinessContext' => $ui['businessContext'],
            'notificationFeedSummary' => $this->summarizeFeed($notifications, $unreadCount),
            'notificationDeliveryLogs' => $deliveryLogs,
            'notificationDeliveryLogFilters' => $deliveryLogFilters,
            'notificationDeliveryLogSummary' => $this->summarizeDeliveryLogs($deliveryLogs),
            'notificationDeliveryLogEventOptions' => NotificationCatalog::EVENTS,
            'notificationAutomationStatus' => $isAdmin
                ? $this->notificationAutomationStatusService->getStatus($businessId)
                : null,
            'notificationPageHeading' => $ui['pageHeading'],
            'notificationPageDescription' => $ui['pageDescription'],
            'notificationUi' => $ui,
        ];
    }

    public function getNotifications(string $filter = 'all'): array
    {
        $notifications = [];
        $businessId = $this->resolveBusinessId();

        try {
            $logBuilder = $this->deliveryLogModel->builder();

            $logBuilder->select('xs_notification_delivery_logs.*, a.start_at as appointment_start,
                                c.first_name as customer_first_name, c.last_name as customer_last_name,
                                s.name as service_name, u.name as provider_name', false)
                ->join('xs_appointments a', 'a.id = xs_notification_delivery_logs.appointment_id', 'left')
                ->join('xs_customers c', 'c.id = a.customer_id', 'left')
                ->join('xs_services s', 's.id = a.service_id', 'left')
                ->join('xs_users u', 'u.id = a.provider_id', 'left')
                ->where('xs_notification_delivery_logs.business_id', $businessId)
                ->orderBy('xs_notification_delivery_logs.created_at', 'DESC')
                ->limit(50);

            if ($filter === 'appointments') {
                $logBuilder->whereIn('xs_notification_delivery_logs.event_type', [
                    'appointment_pending',
                    'appointment_confirmed',
                    'appointment_reminder',
                    'appointment_cancelled',
                    'appointment_rescheduled',
                ]);
            }

            foreach ($logBuilder->get()->getResultArray() as $log) {
                $notifications[] = $this->formatDeliveryLog($log);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Notification delivery logs unavailable: ' . $e->getMessage());
        }

        try {
            $queueBuilder = $this->queueModel->builder();

            $queueBuilder->select('xs_notification_queue.*, a.start_at as appointment_start,
                                  c.first_name as customer_first_name, c.last_name as customer_last_name,
                                  s.name as service_name, u.name as provider_name', false)
                ->join('xs_appointments a', 'a.id = xs_notification_queue.appointment_id', 'left')
                ->join('xs_customers c', 'c.id = a.customer_id', 'left')
                ->join('xs_services s', 's.id = a.service_id', 'left')
                ->join('xs_users u', 'u.id = a.provider_id', 'left')
                ->where('xs_notification_queue.business_id', $businessId)
                ->whereIn('xs_notification_queue.status', ['queued', 'failed'])
                ->orderBy('xs_notification_queue.created_at', 'DESC')
                ->limit(20);

            foreach ($queueBuilder->get()->getResultArray() as $item) {
                $notifications[] = $this->formatQueueItem($item);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Notification queue unavailable: ' . $e->getMessage());
        }

        usort($notifications, static function (array $a, array $b): int {
            return strtotime($b['raw_time'] ?? 'now') - strtotime($a['raw_time'] ?? 'now');
        });

        if ($filter === 'unread') {
            $notifications = array_filter($notifications, static fn(array $notification): bool => !$notification['read']);
        }

        if ($filter === 'system') {
            $notifications = array_filter($notifications, static fn(array $notification): bool => ($notification['type'] ?? '') === 'system' || ($notification['channel'] ?? '') === 'system');
        }

        if ($notifications === []) {
            return $this->getEmptyStateNotifications();
        }

        return array_values($notifications);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getDeliveryLogs(int $businessId, array $filters): array
    {
        try {
            $builder = $this->deliveryLogModel->builder();

            $builder->select('xs_notification_delivery_logs.*, a.start_at as appointment_start,
                              c.first_name as customer_first_name, c.last_name as customer_last_name,
                              s.name as service_name, u.name as provider_name', false)
                ->join('xs_appointments a', 'a.id = xs_notification_delivery_logs.appointment_id', 'left')
                ->join('xs_customers c', 'c.id = a.customer_id', 'left')
                ->join('xs_services s', 's.id = a.service_id', 'left')
                ->join('xs_users u', 'u.id = a.provider_id', 'left')
                ->where('xs_notification_delivery_logs.business_id', $businessId)
                ->orderBy('xs_notification_delivery_logs.created_at', 'DESC')
                ->limit(100);

            if (($filters['status'] ?? '') !== '') {
                $builder->where('xs_notification_delivery_logs.status', $filters['status']);
            }

            if (($filters['channel'] ?? '') !== '') {
                $builder->where('xs_notification_delivery_logs.channel', $filters['channel']);
            }

            if (($filters['event'] ?? '') !== '') {
                $builder->where('xs_notification_delivery_logs.event_type', $filters['event']);
            }

            return array_map(fn(array $row): array => $this->formatDeliveryLogRow($row), $builder->get()->getResultArray());
        } catch (\Throwable $e) {
            log_message('warning', 'Notification delivery log table unavailable: ' . $e->getMessage());
            return [];
        }
    }

    public function getUnreadCount(): int
    {
        $businessId = $this->resolveBusinessId();
        $count = 0;

        try {
            $count += $this->queueModel
                ->where('business_id', $businessId)
                ->whereIn('status', ['queued', 'failed'])
                ->countAllResults();

            $count += $this->deliveryLogModel
                ->where('business_id', $businessId)
                ->where('status', 'failed')
                ->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->countAllResults();
        } catch (\Throwable $e) {
            log_message('warning', 'Notification count unavailable: ' . $e->getMessage());
        }

        return $count;
    }

    protected function resolveBusinessId(): int
    {
        helper('permissions');

        // Resolve from session/user context only — GET params are intentionally
        // excluded here so that stale ?business_id= query strings cannot inject
        // a foreign business context into the Notifications surface.
        $sessionUser = session()->get('user');
        $sessionUser = is_array($sessionUser) ? $sessionUser : [];

        $candidates = [
            session()->get('business_id'),
            session()->get('active_business_id'),
            $sessionUser['business_id'] ?? null,
            $sessionUser['active_business_id'] ?? null,
            $sessionUser['businessId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return \App\Services\NotificationCatalog::BUSINESS_ID_DEFAULT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getNotificationBusinessOptions(int $selectedBusinessId): array
    {
        return $this->getBusinessOptionsService()->getOptions($selectedBusinessId);
    }

    protected function getBusinessOptionsService(): NotificationBusinessOptionsService
    {
        return new NotificationBusinessOptionsService();
    }

    /**
     * @param array<int, array<string, mixed>> $businessOptions
     * @return array<string, mixed>
     */
    private function buildUiState(
        int $businessId,
        string $notificationTab,
        string $feedFilter,
        array $deliveryLogFilters,
        bool $isAdmin,
        array $businessOptions,
    ): array {
        $pageHeading = $notificationTab === 'delivery-logs' ? 'Delivery Logs' : 'Activity Feed';
        $pageDescription = $notificationTab === 'delivery-logs'
            ? 'Audit recent delivery attempts and review channel-specific notification outcomes.'
            : 'Recent notification activity, including queued retries and recent delivery outcomes.';

        $currentQuery = $notificationTab === 'delivery-logs' && $isAdmin
            ? $this->buildDeliveryLogQuery($businessId, $deliveryLogFilters)
            : $this->buildActivityQuery($businessId, $feedFilter);

        return [
            'pageHeading' => $pageHeading,
            'pageDescription' => $pageDescription,
            'currentPageUrl' => $this->buildUrl('notifications', $currentQuery),
            'settingsNotificationsUrl' => $this->buildUrl('settings', ['business_id' => $businessId], 'notifications'),
            'activityTabUrl' => $this->buildUrl('notifications', $this->buildActivityQuery($businessId, $feedFilter)),
            'deliveryLogsTabUrl' => $this->buildUrl('notifications', $this->buildDeliveryLogQuery($businessId, $deliveryLogFilters)),
            'deliveryLogClearUrl' => $this->buildUrl('notifications', ['tab' => 'delivery-logs', 'business_id' => $businessId]),
            'deliveryLogsFormAction' => base_url('notifications'),
            'activityFilters' => $this->buildActivityFilterOptions($businessId, $feedFilter),
            'businessContext' => [
                'title' => 'Notification Activity',
                'description' => 'Recent notification activity for your business, including queued retries and delivery outcomes.',
                'options' => $this->buildBusinessContextOptions(
                    $businessId,
                    $businessOptions,
                    $notificationTab,
                    $feedFilter,
                    $deliveryLogFilters,
                    $isAdmin
                ),
            ],
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function buildActivityQuery(int $businessId, string $feedFilter): array
    {
        return [
            'tab' => 'activity',
            'filter' => $feedFilter,
            'business_id' => $businessId,
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function buildDeliveryLogQuery(int $businessId, array $deliveryLogFilters): array
    {
        $query = [
            'tab' => 'delivery-logs',
            'business_id' => $businessId,
        ];

        if (($deliveryLogFilters['status'] ?? '') !== '') {
            $query['log_status'] = (string) $deliveryLogFilters['status'];
        }

        if (($deliveryLogFilters['channel'] ?? '') !== '') {
            $query['log_channel'] = (string) $deliveryLogFilters['channel'];
        }

        if (($deliveryLogFilters['event'] ?? '') !== '') {
            $query['log_event'] = (string) $deliveryLogFilters['event'];
        }

        return $query;
    }

    /**
     * @return array<int, array{value: string, label: string, url: string, isActive: bool}>
     */
    private function buildActivityFilterOptions(int $businessId, string $activeFilter): array
    {
        $filters = [
            'all' => 'All',
            'unread' => 'Unread',
            'appointments' => 'Appointments',
            'system' => 'System',
        ];

        $options = [];

        foreach ($filters as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'url' => $this->buildUrl('notifications', $this->buildActivityQuery($businessId, $value)),
                'isActive' => $activeFilter === $value,
            ];
        }

        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $businessOptions
     * @return array<int, array<string, mixed>>
     */
    private function buildBusinessContextOptions(
        int $currentBusinessId,
        array $businessOptions,
        string $notificationTab,
        string $feedFilter,
        array $deliveryLogFilters,
        bool $isAdmin,
    ): array {
        $options = [];

        foreach ($businessOptions as $option) {
            $businessId = (int) ($option['id'] ?? 0);
            if ($businessId <= 0) {
                continue;
            }

            $query = $notificationTab === 'delivery-logs' && $isAdmin
                ? $this->buildDeliveryLogQuery($businessId, $deliveryLogFilters)
                : $this->buildActivityQuery($businessId, $feedFilter);

            $options[] = [
                'id' => $businessId,
                'label' => (string) ($option['label'] ?? ('Business ' . $businessId)),
                'is_active' => $businessId === $currentBusinessId,
                'url' => $this->buildUrl('notifications', $query),
            ];
        }

        return $options;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query = [], string $fragment = ''): string
    {
        $filtered = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        $url = base_url($path);

        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }

        if ($fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }

    private function formatDeliveryLog(array $log): array
    {
        $eventType = $log['event_type'] ?? 'unknown';
        $channel = $log['channel'] ?? 'email';
        $status = $log['status'] ?? 'unknown';
        $customerName = trim(($log['customer_first_name'] ?? '') . ' ' . ($log['customer_last_name'] ?? ''));
        $serviceName = $log['service_name'] ?? 'Service';
        $providerName = $log['provider_name'] ?? 'Provider';
        $recipient = $log['recipient'] ?? '';
        $createdAt = $log['created_at'] ?? date('Y-m-d H:i:s');

        $config = $this->getEventConfig($eventType);
        $message = $this->buildNotificationMessage($eventType, $status, $channel, [
            'customer' => $customerName ?: 'Customer',
            'service' => $serviceName,
            'provider' => $providerName,
            'recipient' => $recipient,
            'appointment_start' => $log['appointment_start'] ?? null,
            'error' => $log['error_message'] ?? null,
        ]);

        return [
            'id' => 'log_' . ($log['id'] ?? 0),
            'type' => $config['type'],
            'title' => $config['title'] . ' - ' . ucfirst($status),
            'message' => $message,
            'time' => $this->timeAgo($createdAt),
            'raw_time' => $createdAt,
            'read' => $status === 'success',
            'icon' => $config['icon'],
            'color' => $status === 'success' ? 'green' : ($status === 'failed' ? 'red' : 'amber'),
            'channel' => $channel,
            'status' => $status,
            'appointment_id' => !empty($log['appointment_id']) ? (int) $log['appointment_id'] : null,
            'event_type' => $eventType,
        ];
    }

    private function formatQueueItem(array $item): array
    {
        $eventType = $item['event_type'] ?? 'unknown';
        $channel = $item['channel'] ?? 'email';
        $status = $item['status'] ?? 'queued';
        $customerName = trim(($item['customer_first_name'] ?? '') . ' ' . ($item['customer_last_name'] ?? ''));
        $serviceName = $item['service_name'] ?? 'Service';
        $createdAt = $item['created_at'] ?? date('Y-m-d H:i:s');

        $config = $this->getEventConfig($eventType);
        $statusLabel = $status === 'queued' ? 'Pending' : 'Retry Scheduled';
        $message = sprintf(
            '%s notification for %s (%s) via %s is %s',
            $config['title'],
            $customerName ?: 'Customer',
            $serviceName,
            strtoupper($channel),
            strtolower($statusLabel)
        );

        if (!empty($item['last_error'])) {
            $message .= '. Last error: ' . $item['last_error'];
        }

        return [
            'id' => 'queue_' . ($item['id'] ?? 0),
            'type' => $config['type'],
            'title' => $config['title'] . ' - ' . $statusLabel,
            'message' => $message,
            'time' => $this->timeAgo($createdAt),
            'raw_time' => $createdAt,
            'read' => false,
            'icon' => $config['icon'],
            'color' => $status === 'queued' ? 'blue' : 'amber',
            'channel' => $channel,
            'status' => $status,
            'appointment_id' => !empty($item['appointment_id']) ? (int) $item['appointment_id'] : null,
            'event_type' => $eventType,
        ];
    }

    private function formatDeliveryLogRow(array $log): array
    {
        $eventType = (string) ($log['event_type'] ?? '');
        $channel = (string) ($log['channel'] ?? '');
        $status = (string) ($log['status'] ?? '');
        $createdAt = (string) ($log['created_at'] ?? '');
        $createdAtDisplay = $createdAt !== '' ? TimezoneService::toDisplay($createdAt) : '';
        $appointmentId = (int) ($log['appointment_id'] ?? 0);
        $customerName = trim((string) ($log['customer_first_name'] ?? '') . ' ' . (string) ($log['customer_last_name'] ?? ''));

        return [
            'id' => (int) ($log['id'] ?? 0),
            'business_id' => (int) ($log['business_id'] ?? 0),
            'appointment_id' => $appointmentId > 0 ? $appointmentId : null,
            'channel' => $channel,
            'channel_label' => strtoupper($channel),
            'event_type' => $eventType,
            'event_label' => NotificationCatalog::EVENTS[$eventType] ?? ucwords(str_replace('_', ' ', $eventType)),
            'status' => $status,
            'status_label' => ucfirst($status),
            'recipient' => (string) ($log['recipient'] ?? ''),
            'recipient_masked' => $this->maskRecipient($log['recipient'] ?? null),
            'correlation_id' => (string) ($log['correlation_id'] ?? ''),
            'provider' => (string) ($log['provider'] ?? ''),
            'error_message' => (string) ($log['error_message'] ?? ''),
            'created_at' => $createdAt,
            'created_at_display' => $createdAtDisplay,
            'time_ago' => $createdAt !== '' ? $this->timeAgo($createdAt) : '',
            'appointment_context' => $this->buildDeliveryLogContext(
                $customerName !== '' ? $customerName : 'Customer',
                (string) ($log['service_name'] ?? 'Service'),
                (string) ($log['provider_name'] ?? 'Provider'),
                $log['appointment_start'] ?? null,
            ),
            'can_resend' => $appointmentId > 0 && in_array($channel, NotificationCatalog::CHANNELS, true),
        ];
    }

    private function getEventConfig(string $eventType): array
    {
        $configs = [
            'appointment_pending' => [
                'type' => 'appointment',
                'title' => 'Appointment Pending',
                'icon' => 'calendar',
            ],
            'appointment_confirmed' => [
                'type' => 'appointment',
                'title' => 'Appointment Confirmed',
                'icon' => 'calendar',
            ],
            'appointment_reminder' => [
                'type' => 'reminder',
                'title' => 'Appointment Reminder',
                'icon' => 'bell',
            ],
            'appointment_cancelled' => [
                'type' => 'cancellation',
                'title' => 'Appointment Cancelled',
                'icon' => 'x-circle',
            ],
            'appointment_rescheduled' => [
                'type' => 'appointment',
                'title' => 'Appointment Rescheduled',
                'icon' => 'calendar',
            ],
        ];

        return $configs[$eventType] ?? [
            'type' => 'system',
            'title' => ucwords(str_replace('_', ' ', $eventType)),
            'icon' => 'cog',
        ];
    }

    private function buildNotificationMessage(string $eventType, string $status, string $channel, array $data): string
    {
        $customer = $data['customer'];
        $service = $data['service'];
        $provider = $data['provider'];
        $recipient = $data['recipient'];
        $appointmentStart = $data['appointment_start'];
        $error = $data['error'];

        $channelLabel = strtoupper($channel);
        $localStart = $appointmentStart ? TimezoneService::toDisplay($appointmentStart) : '';
        $timeStr = $localStart
            ? date('M j, Y', strtotime($localStart)) . ' at ' . $this->localizationSettingsService->formatTimeForDisplay(date('H:i:s', strtotime($localStart)))
            : '';

        if ($status === 'success') {
            return match ($eventType) {
                'appointment_pending' => sprintf('%s pending notification sent to %s for appointment with %s (%s)%s', $channelLabel, $recipient ?: $customer, $provider, $service, $timeStr ? " on {$timeStr}" : ''),
                'appointment_confirmed' => sprintf('%s notification sent to %s for appointment with %s (%s)%s', $channelLabel, $recipient ?: $customer, $provider, $service, $timeStr ? " on {$timeStr}" : ''),
                'appointment_reminder' => sprintf('Reminder sent to %s via %s for upcoming appointment%s', $recipient ?: $customer, $channelLabel, $timeStr ? " on {$timeStr}" : ''),
                'appointment_cancelled' => sprintf('Cancellation notice sent to %s via %s for %s appointment', $recipient ?: $customer, $channelLabel, $service),
                'appointment_rescheduled' => sprintf('Reschedule notice sent to %s via %s for %s%s', $recipient ?: $customer, $channelLabel, $service, $timeStr ? " - new time: {$timeStr}" : ''),
                default => sprintf('%s notification sent to %s via %s', ucwords(str_replace('_', ' ', $eventType)), $recipient ?: $customer, $channelLabel),
            };
        }

        if ($status === 'failed') {
            $base = sprintf('Failed to send %s %s to %s', $channelLabel, str_replace('_', ' ', $eventType), $recipient ?: $customer);
            return $error ? "{$base}: {$error}" : $base;
        }

        if ($status === 'cancelled') {
            return sprintf('%s notification cancelled for %s (%s)', ucwords(str_replace('_', ' ', $eventType)), $customer, $error ?: 'Rule disabled');
        }

        return sprintf('%s %s notification - status: %s', ucwords(str_replace('_', ' ', $eventType)), $channelLabel, $status);
    }

    private function timeAgo(string $datetime): string
    {
        // Database timestamps are stored in UTC. Parse explicitly as UTC so
        // strtotime()-style assumptions about the PHP server's local timezone
        // (e.g. Africa/Johannesburg = UTC+2) do not shift the result.
        try {
            $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
            $time = $dt->getTimestamp();
        } catch (\Throwable $e) {
            $time = time();
        }

        $diff = time() - $time;

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        // Render the fallback date label in the business/display timezone.
        try {
            $displayTz = new \DateTimeZone(TimezoneService::businessTimezone());
            $dt->setTimezone($displayTz);
            return $dt->format('M j, Y');
        } catch (\Throwable $e) {
            return date('M j, Y', $time);
        }
    }

    /**
     * @return array<string, int>
     */
    private function summarizeFeed(array $notifications, int $unreadCount): array
    {
        $realNotifications = array_values(array_filter($notifications, static function (array $notification): bool {
            return !str_starts_with((string) ($notification['id'] ?? ''), 'info_');
        }));

        $total = count($realNotifications);
        $today = count(array_filter($realNotifications, static function (array $notification): bool {
            $rawTime = (string) ($notification['raw_time'] ?? '');
            if ($rawTime === '') {
                return false;
            }
            try {
                $displayTz = new \DateTimeZone(TimezoneService::businessTimezone());
                $dt = new \DateTime($rawTime, new \DateTimeZone('UTC'));
                $dt->setTimezone($displayTz);
                return $dt->format('Y-m-d') === (new \DateTime('now', $displayTz))->format('Y-m-d');
            } catch (\Throwable $e) {
                return false;
            }
        }));

        return [
            'total' => $total,
            'unread' => min($total, $unreadCount),
            'read' => max(0, $total - $unreadCount),
            'today' => $today,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summarizeDeliveryLogs(array $deliveryLogs): array
    {
        $summary = [
            'total' => count($deliveryLogs),
            'success' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'skipped' => 0,
            'resendable' => 0,
        ];

        foreach ($deliveryLogs as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status] += 1;
            }

            if (!empty($row['can_resend'])) {
                $summary['resendable'] += 1;
            }
        }

        return $summary;
    }

    /**
     * @return array{status: string, channel: string, event: string}
     */
    private function normalizeDeliveryLogFilters(array $query): array
    {
        $status = (string) ($query['log_status'] ?? '');
        $channel = (string) ($query['log_channel'] ?? '');
        $event = (string) ($query['log_event'] ?? '');

        return [
            'status' => in_array($status, ['success', 'failed', 'cancelled', 'skipped'], true) ? $status : '',
            'channel' => in_array($channel, NotificationCatalog::CHANNELS, true) ? $channel : '',
            'event' => array_key_exists($event, NotificationCatalog::EVENTS) ? $event : '',
        ];
    }

    private function normalizeFeedFilter(string $filter): string
    {
        return in_array($filter, ['all', 'unread', 'appointments', 'system'], true) ? $filter : 'all';
    }

    private function resolveTab(string $tab, bool $isAdmin): string
    {
        if ($isAdmin && $tab === 'delivery-logs') {
            return 'delivery-logs';
        }

        return 'activity';
    }

    private function canViewDeliveryLogs(?array $currentUser, ?string $currentRole): bool
    {
        $roles = [];

        if (is_array($currentUser['roles'] ?? null)) {
            $roles = $currentUser['roles'];
        }

        foreach ([$currentRole, $currentUser['active_role'] ?? null, $currentUser['role'] ?? null] as $role) {
            if (is_string($role) && $role !== '') {
                $roles[] = $role;
            }
        }

        return in_array('admin', array_values(array_unique($roles)), true);
    }

    private function buildDeliveryLogContext(string $customerName, string $serviceName, string $providerName, mixed $appointmentStart): string
    {
        $context = sprintf('%s | %s | %s', $customerName, $serviceName, $providerName);

        if (!is_string($appointmentStart) || trim($appointmentStart) === '') {
            return $context;
        }

        $localStart = TimezoneService::toDisplay($appointmentStart);
        if ($localStart === '') {
            return $context;
        }

        return $context . ' | ' . date('M j, Y', strtotime($localStart)) . ' ' . $this->localizationSettingsService->formatTimeForDisplay(date('H:i:s', strtotime($localStart)));
    }

    private function maskRecipient(?string $recipient): string
    {
        $recipient = trim((string) $recipient);
        if ($recipient === '') {
            return '';
        }

        if (strpos($recipient, '@') !== false) {
            [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');
            $head = $local !== '' ? substr($local, 0, 1) : '';

            return $head . '***@' . $domain;
        }

        $digits = preg_replace('/\D+/', '', $recipient);
        $last4 = $digits !== '' ? substr($digits, -4) : '';

        return '+***' . $last4;
    }

    private function getEmptyStateNotifications(): array
    {
        return [
            [
                'id' => 'info_1',
                'type' => 'system',
                'title' => 'Notifications Ready',
                'message' => 'Your notification system is configured. Notifications will appear here when appointments are confirmed, reminded, cancelled, or rescheduled.',
                'time' => 'Just now',
                'raw_time' => date('Y-m-d H:i:s'),
                'read' => false,
                'icon' => 'cog',
                'color' => 'blue',
                'channel' => 'system',
                'status' => 'info',
            ],
            [
                'id' => 'info_2',
                'type' => 'system',
                'title' => 'How It Works',
                'message' => 'Enable notification channels (Email, SMS, WhatsApp) in Settings → Notifications, then configure which events trigger notifications using the Event→Channel Matrix.',
                'time' => 'Just now',
                'raw_time' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'read' => true,
                'icon' => 'cog',
                'color' => 'gray',
                'channel' => 'system',
                'status' => 'info',
            ],
        ];
    }
}