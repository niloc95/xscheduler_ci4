<?php

/**
 * =============================================================================
 * SETTINGS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Settings.php
 * @description Admin settings controller for configuring all aspects of the
 *              WebScheduler application including business info, localization,
 *              booking fields, notifications, and integrations.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /settings                : Main settings page with tabbed interface
 * POST /settings/save           : Save all settings (AJAX)
 * POST /settings/test-email     : Test email configuration
 * POST /settings/test-sms       : Test SMS configuration
 * POST /settings/test-whatsapp  : Test WhatsApp configuration
 * GET  /settings/notification-logs : View delivery history
 * 
 * SETTINGS CATEGORIES (Tabs):
 * -----------------------------------------------------------------------------
 * 1. General      : Company name, contact info, business address
 * 2. Localization : Timezone, time format (12h/24h), currency, language
 * 3. Booking      : Customer form fields (required/optional), validation
 * 4. Calendar     : Work hours, slot duration, buffer time, advance booking
 * 5. Notifications: Email/SMS/WhatsApp templates and triggers
 * 6. Integrations : Third-party API keys (Twilio, Meta WhatsApp, etc.)
 * 7. Security     : Password policies, session settings
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides administrators with a centralized interface to configure:
 * - Business identity and branding
 * - Localization and regional preferences
 * - Booking rules and customer data collection
 * - Notification channels and message templates
 * - External service integrations
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * Restricted to admin role only via 'role:admin' filter.
 * 
 * DEPENDENCIES:
 * -----------------------------------------------------------------------------
 * - SettingModel                   : Key-value settings storage
 * - NotificationEmailService       : Email testing
 * - NotificationSmsService         : SMS testing
 * - NotificationWhatsAppService    : WhatsApp testing
 * - NotificationDeliveryLogModel   : Delivery history
 * 
 * @see         app/Views/settings/index.php for view template
 * @see         app/Models/SettingModel.php for settings storage
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\Settings\GeneralSettingsService;
use App\Services\Settings\NotificationSettingsService;
use App\Services\Settings\SettingsPageService;

class Settings extends BaseController
{
    private ?GeneralSettingsService $generalSettingsService = null;
    private ?NotificationSettingsService $notificationSettingsService = null;
    private ?SettingsPageService $settingsPageService = null;

    public function __construct(
        ?GeneralSettingsService $generalSettingsService = null,
        ?NotificationSettingsService $notificationSettingsService = null,
        ?SettingsPageService $settingsPageService = null,
    ) {
        $this->generalSettingsService = $generalSettingsService;
        $this->notificationSettingsService = $notificationSettingsService;
        $this->settingsPageService = $settingsPageService;
    }

    public function index()
    {
        $this->localUploadLog('index_hit', []);

        return view('settings/index', $this->getSettingsPageService()->buildIndexData(session()->get('user')));
    }

    public function saveNotifications()
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to(base_url('settings'));
        }

        $result = $this->getNotificationSettingsService()->save(
            $this->request->getPost() ?? [],
            session()->get('user_id')
        );

        $redirect = redirect()->to(base_url('settings') . '#notifications')
            ->with($result['type'], $result['message']);

        if (!empty($result['html'])) {
            $redirect = $redirect->with('success_html', true);
        }

        return $redirect;
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
        $result = $this->getGeneralSettingsService()->save(
            $this->request->getPost() ?? [],
            $this->request->getFile('company_logo'),
            session()->get('user_id')
        );

        return redirect()->to(base_url('settings'))
            ->with($result['type'], $result['message']);
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

    private function getNotificationSettingsService(): NotificationSettingsService
    {
        if ($this->notificationSettingsService === null) {
            $this->notificationSettingsService = new NotificationSettingsService();
        }

        return $this->notificationSettingsService;
    }

    private function getGeneralSettingsService(): GeneralSettingsService
    {
        if ($this->generalSettingsService === null) {
            $this->generalSettingsService = new GeneralSettingsService();
        }

        return $this->generalSettingsService;
    }

    private function getSettingsPageService(): SettingsPageService
    {
        if ($this->settingsPageService === null) {
            $this->settingsPageService = new SettingsPageService(
                new SettingModel(),
                $this->getNotificationSettingsService()
            );
        }

        return $this->settingsPageService;
    }
}
