<?php

namespace Tests\Unit\Views;

use CodeIgniter\Test\CIUnitTestCase;

final class FrontendInitContractsTest extends CIUnitTestCase
{
    public function testAppointmentFormViewHandsConfigToBundledInitializerWithoutInlineScript(): void
    {
        $contents = (string) file_get_contents(APPPATH . 'Views/appointments/form.php');

        $this->assertStringContainsString('data-appointment-form="true"', $contents);
        $this->assertStringContainsString('data-search-url=', $contents);
        $this->assertStringContainsString('data-time-format=', $contents);
        $this->assertStringNotContainsString('function initAppointmentForm()', $contents);
        $this->assertStringNotContainsString('<script>', $contents);
    }

    public function testProviderScheduleViewUsesBundledInitializerContractWithoutInlineScript(): void
    {
        $contents = (string) file_get_contents(APPPATH . 'Views/user-management/components/provider-schedule.php');

        $this->assertStringContainsString('data-provider-schedule-section', $contents);
        $this->assertStringNotContainsString('<script>', $contents);
        $this->assertStringNotContainsString('function initProviderSchedule', $contents);
    }

    public function testBundledAppInitializesExtractedAppointmentAndProviderScheduleModules(): void
    {
        $contents = (string) file_get_contents(ROOTPATH . 'resources/js/app.js');

        $this->assertStringContainsString("import { initAppointmentForm } from './modules/appointments/appointments-form.js';", $contents);
        $this->assertStringContainsString("import { initProviderSchedule } from './modules/user-management/provider-schedule.js';", $contents);
        $this->assertStringContainsString('initAppointmentForm();', $contents);
        $this->assertStringContainsString('initProviderSchedule();', $contents);
    }
}