<?php

namespace Tests\Unit\Services\Appointment;

use App\Models\AppointmentModel;
use App\Services\Appointment\AppointmentFormGuardService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

final class AppointmentFormGuardServiceTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }

    public function testRequireLoginReturnsRedirectContractWhenSessionMissing(): void
    {
        $service = new AppointmentFormGuardService(
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(AppointmentModel::class)
        );

        $result = $service->requireLogin('Please log in to continue');

        $this->assertSame('redirect', $result['type']);
        $this->assertSame(base_url('auth/login'), $result['to']);
        $this->assertSame('Please log in to continue', $result['flash']['error']);
    }

    public function testRequireAppointmentHashReturnsNotFoundForMissingHash(): void
    {
        $service = new AppointmentFormGuardService(
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(AppointmentModel::class)
        );

        $result = $service->requireAppointmentHash(null);

        $this->assertSame('not_found', $result['type']);
        $this->assertSame('Appointment not found', $result['message']);
    }

    public function testRequireExistingAppointmentReturnsNotFoundWhenHashUnknown(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('findByHash')->with('missing')->willReturn(null);

        $service = new AppointmentFormGuardService(
            $this->createMock(LocalizationSettingsService::class),
            $appointmentModel
        );

        $result = $service->requireExistingAppointment('missing');

        $this->assertSame('not_found', $result['type']);
        $this->assertSame('Appointment not found', $result['message']);
    }

    public function testValidateNotInPastReturnsRedirectBackContractForNonAjaxRequests(): void
    {
        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('UTC');

        $service = new AppointmentFormGuardService(
            $localization,
            $this->createMock(AppointmentModel::class)
        );

        $result = $service->validateNotInPast('2000-01-01', '09:00', 'Cannot schedule appointments in the past.', false);

        $this->assertSame('redirect_back', $result['type']);
        $this->assertTrue($result['withInput']);
        $this->assertSame('Cannot schedule appointments in the past.', $result['flash']['error']);
    }
}