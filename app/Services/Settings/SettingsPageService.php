<?php

namespace App\Services\Settings;

use App\Models\SettingModel;

class SettingsPageService
{
    private SettingModel $settingModel;
    private NotificationSettingsService $notificationSettingsService;

    public function __construct(
        ?SettingModel $settingModel = null,
        ?NotificationSettingsService $notificationSettingsService = null,
    ) {
        $this->settingModel = $settingModel ?? new SettingModel();
        $this->notificationSettingsService = $notificationSettingsService ?? new NotificationSettingsService();
    }

    public function buildIndexData(?array $sessionUser = null): array
    {
        return [
            'user' => $sessionUser ?? [
                'name' => 'System Administrator',
                'role' => 'admin',
                'email' => 'admin@webschedulr.com',
            ],
            'settings' => $this->settingModel->getByKeys($this->settingsKeys()),
            ...$this->notificationSettingsService->getIndexData(),
        ];
    }

    private function settingsKeys(): array
    {
        return [
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
            'booking.first_names_display',
            'booking.first_names_required',
            'booking.surname_display',
            'booking.surname_required',
            'booking.email_display',
            'booking.email_required',
            'booking.phone_display',
            'booking.phone_required',
            'booking.address_display',
            'booking.address_required',
            'booking.notes_display',
            'booking.notes_required',
            'booking.custom_field_1_enabled',
            'booking.custom_field_1_title',
            'booking.custom_field_1_type',
            'booking.custom_field_1_required',
            'booking.custom_field_2_enabled',
            'booking.custom_field_2_title',
            'booking.custom_field_2_type',
            'booking.custom_field_2_required',
            'booking.custom_field_3_enabled',
            'booking.custom_field_3_title',
            'booking.custom_field_3_type',
            'booking.custom_field_3_required',
            'booking.custom_field_4_enabled',
            'booking.custom_field_4_title',
            'booking.custom_field_4_type',
            'booking.custom_field_4_required',
            'booking.custom_field_5_enabled',
            'booking.custom_field_5_title',
            'booking.custom_field_5_type',
            'booking.custom_field_5_required',
            'booking.custom_field_6_enabled',
            'booking.custom_field_6_title',
            'booking.custom_field_6_type',
            'booking.custom_field_6_required',
            'booking.fields',
            'booking.custom_fields',
            'booking.statuses',
            'booking.default_appointment_status',
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
            'legal.cancellation_policy',
            'legal.rescheduling_policy',
            'legal.terms_url',
            'legal.privacy_url',
            'integrations.webhook_url',
            'integrations.analytics',
            'integrations.api_integrations',
            'integrations.ldap_enabled',
            'integrations.ldap_host',
            'integrations.ldap_dn',
            'notifications.default_language',
        ];
    }
}