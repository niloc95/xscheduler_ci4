<?php

namespace App\Commands;

use App\Models\SettingModel;
use App\Services\NotificationTemplateService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class BackfillNotificationTemplates extends BaseCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:backfill-templates';

    protected $description = 'Backfill legacy notification templates missing required placeholders.';

    protected $usage = 'notifications:backfill-templates [--apply]';

    protected $options = [
        '--apply' => 'Apply updates. Without this flag the command runs in dry-run mode.',
    ];

    private const LEGACY_BODIES = [
        // v2 rich templates (2026-04-18, before business hours/maps links/contact enhancements)
        'notification_template.appointment_pending.email' => [
            "Hi {customer_first_name},\n\nWe've received your booking request! We will confirm your appointment shortly.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nWe will notify you as soon as your appointment is confirmed.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}",
        ],
        'notification_template.appointment_confirmed.email' => [
            // v1 original (before 2026-04-18 rich templates)
            "Hi {customer_name},\n\nYour appointment has been confirmed!\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\nImportant Information:\n{cancellation_policy}\n{rescheduling_policy}\n\nThank you for booking with {business_name}!\n\nView our Terms & Conditions: {terms_link}\nPrivacy Policy: {privacy_link}",
            // v2 rich template (2026-04-18)
            "Hi {customer_first_name},\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Bring any relevant documentation.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}",
        ],
        'notification_template.appointment_reminder.email' => [
            "Hi {customer_first_name},\n\nDon't forget — you have an upcoming appointment!\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}",
        ],
        'notification_template.appointment_cancelled.email' => [
            "Hi {customer_first_name},\n\nYour appointment has been cancelled.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n☎ Enquiries: {business_email} | Tel: {business_phone}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\n{business_name}\n{terms_link} | {privacy_link}",
        ],
        'notification_template.appointment_rescheduled.email' => [
            "Hi {customer_first_name},\n\nYour appointment has been moved to a new date and time.\n\n── NEW DATE & TIME ───────────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}",
        ],
    ];

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $apply = CLI::getOption('apply') !== null;
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        CLI::newLine();
        CLI::write('WebScheduler - Notification Template Backfill', 'yellow');
        CLI::write('Mode: ' . $mode, 'yellow');

        $settingModel = new SettingModel();
        $templateService = new NotificationTemplateService();
        $defaultTemplates = $templateService->getDefaultTemplates();

        $rows = $settingModel->getByPrefix('notification_template.');
        $stats = [
            'scanned' => 0,
            'matched' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $key => $rawValue) {
            $stats['scanned']++;

            if (!isset(self::LEGACY_BODIES[$key])) {
                $stats['skipped']++;
                continue;
            }

            $parts = explode('.', (string) $key);
            if (count($parts) !== 3) {
                $stats['skipped']++;
                continue;
            }

            $eventType = $parts[1];
            $channel = $parts[2];
            $stored = is_string($rawValue) ? json_decode($rawValue, true) : $rawValue;

            if (!is_array($stored) || !isset($stored['body'])) {
                $stats['skipped']++;
                continue;
            }

            $storedBody = $this->normalizeBody((string) $stored['body']);
            $matchesLegacy = false;
            foreach (self::LEGACY_BODIES[$key] as $legacyBody) {
                if ($storedBody === $this->normalizeBody($legacyBody)) {
                    $matchesLegacy = true;
                    break;
                }
            }

            if (!$matchesLegacy) {
                $stats['skipped']++;
                continue;
            }

            $defaultTemplate = $defaultTemplates[$eventType][$channel] ?? null;
            if (!is_array($defaultTemplate) || empty($defaultTemplate['body'])) {
                $stats['skipped']++;
                continue;
            }

            $stats['matched']++;
            $newPayload = [
                'subject' => $stored['subject'] ?? ($defaultTemplate['subject'] ?? null),
                'body' => $defaultTemplate['body'],
            ];

            CLI::write('Matched: ' . $key, 'cyan');

            if (!$apply) {
                continue;
            }

            $ok = $settingModel->upsert($key, $newPayload, 'json', null);
            if ($ok) {
                $stats['updated']++;
                CLI::write('Updated: ' . $key, 'green');
            } else {
                CLI::write('Failed to update: ' . $key, 'red');
            }
        }

        CLI::newLine();
        CLI::write('Summary:', 'cyan');
        CLI::write('Scanned: ' . $stats['scanned']);
        CLI::write('Matched legacy: ' . $stats['matched']);
        CLI::write('Updated: ' . $stats['updated']);
        CLI::write('Skipped: ' . $stats['skipped']);
        CLI::newLine();
    }

    private function normalizeBody(string $body): string
    {
        return trim(str_replace("\r\n", "\n", $body));
    }
}
