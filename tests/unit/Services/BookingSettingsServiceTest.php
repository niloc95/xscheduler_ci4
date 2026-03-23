<?php

namespace Tests\Unit\Services;

use App\Models\SettingModel;
use App\Services\BookingSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BookingSettingsServiceTest extends CIUnitTestCase
{
    public function testGetFieldConfigurationMapsStoredSettingsToBooleans(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('getByKeys')
            ->with($this->callback(static function (array $keys): bool {
                return in_array('booking.first_names_display', $keys, true)
                    && in_array('booking.notes_required', $keys, true);
            }))
            ->willReturn([
                'booking.first_names_display' => '1',
                'booking.first_names_required' => '0',
                'booking.surname_display' => 'yes',
                'booking.surname_required' => 'true',
                'booking.email_display' => '1',
                'booking.email_required' => '1',
                'booking.phone_display' => '0',
                'booking.phone_required' => '0',
                'booking.address_display' => 'on',
                'booking.address_required' => '0',
                'booking.notes_display' => 'false',
                'booking.notes_required' => '1',
            ]);

        $service = new BookingSettingsService($settingModel);

        $config = $service->getFieldConfiguration();

        $this->assertTrue($config['first_name']['display']);
        $this->assertFalse($config['first_name']['required']);
        $this->assertTrue($config['last_name']['display']);
        $this->assertTrue($config['last_name']['required']);
        $this->assertFalse($config['phone']['display']);
        $this->assertTrue($config['address']['display']);
        $this->assertFalse($config['notes']['display']);
        $this->assertTrue($config['notes']['required']);
    }

    public function testGetFieldConfigurationUsesCacheAcrossRepeatedCalls(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('getByKeys')
            ->willReturn([]);

        $service = new BookingSettingsService($settingModel);

        $first = $service->getFieldConfiguration();
        $second = $service->getFieldConfiguration();

        $this->assertSame($first, $second);
    }

    public function testGetCustomFieldConfigurationFiltersDisabledFieldsAndNormalizesTypes(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('getByKeys')
            ->with($this->callback(static function (array $keys): bool {
                return in_array('booking.custom_field_1_enabled', $keys, true)
                    && in_array('booking.custom_field_6_required', $keys, true);
            }))
            ->willReturn([
                'booking.custom_field_1_enabled' => '1',
                'booking.custom_field_1_title' => 'Insurance Plan',
                'booking.custom_field_1_type' => 'select',
                'booking.custom_field_1_required' => '1',
                'booking.custom_field_2_enabled' => '0',
                'booking.custom_field_3_enabled' => '1',
                'booking.custom_field_3_title' => '',
                'booking.custom_field_3_type' => 'unsupported-type',
                'booking.custom_field_3_required' => '0',
            ]);

        $service = new BookingSettingsService($settingModel);

        $config = $service->getCustomFieldConfiguration();

        $this->assertArrayHasKey('custom_field_1', $config);
        $this->assertArrayNotHasKey('custom_field_2', $config);
        $this->assertSame('Insurance Plan', $config['custom_field_1']['title']);
        $this->assertSame('select', $config['custom_field_1']['type']);
        $this->assertTrue($config['custom_field_1']['required']);
        $this->assertSame('Custom Field 3', $config['custom_field_3']['title']);
        $this->assertSame('text', $config['custom_field_3']['type']);
    }

    public function testGetValidationRulesReflectsVisibilityAndCustomFieldTypes(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->exactly(2))
            ->method('getByKeys')
            ->willReturnOnConsecutiveCalls(
                [
                    'booking.first_names_display' => '1',
                    'booking.first_names_required' => '1',
                    'booking.surname_display' => '1',
                    'booking.surname_required' => '0',
                    'booking.email_display' => '1',
                    'booking.email_required' => '1',
                    'booking.phone_display' => '0',
                    'booking.phone_required' => '0',
                    'booking.address_display' => '1',
                    'booking.address_required' => '0',
                    'booking.notes_display' => '1',
                    'booking.notes_required' => '0',
                ],
                [
                    'booking.custom_field_1_enabled' => '1',
                    'booking.custom_field_1_title' => 'Consent',
                    'booking.custom_field_1_type' => 'checkbox',
                    'booking.custom_field_1_required' => '1',
                    'booking.custom_field_2_enabled' => '1',
                    'booking.custom_field_2_title' => 'Reason',
                    'booking.custom_field_2_type' => 'textarea',
                    'booking.custom_field_2_required' => '0',
                ]
            );

        $service = new BookingSettingsService($settingModel);

        $rules = $service->getValidationRules();

        $this->assertSame('required|max_length[100]', $rules['first_name']);
        $this->assertSame('permit_empty|max_length[100]', $rules['last_name']);
        $this->assertSame('required|valid_email|is_unique[customers.email]', $rules['email']);
        $this->assertSame('permit_empty', $rules['phone']);
        $this->assertSame('permit_empty|max_length[255]', $rules['address']);
        $this->assertSame('permit_empty|max_length[1000]', $rules['notes']);
        $this->assertSame('required|in_list[0,1]', $rules['custom_field_1']);
        $this->assertSame('permit_empty|max_length[2000]', $rules['custom_field_2']);
    }

    public function testGetValidationRulesForUpdateExcludesCurrentCustomerFromUniqueEmailCheck(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->exactly(2))
            ->method('getByKeys')
            ->willReturnOnConsecutiveCalls(
                [
                    'booking.first_names_display' => '1',
                    'booking.first_names_required' => '0',
                    'booking.surname_display' => '1',
                    'booking.surname_required' => '0',
                    'booking.email_display' => '1',
                    'booking.email_required' => '1',
                    'booking.phone_display' => '1',
                    'booking.phone_required' => '0',
                    'booking.address_display' => '0',
                    'booking.address_required' => '0',
                    'booking.notes_display' => '0',
                    'booking.notes_required' => '0',
                ],
                []
            );

        $service = new BookingSettingsService($settingModel);

        $rules = $service->getValidationRulesForUpdate(42);

        $this->assertSame('required|valid_email|is_unique[customers.email,id,42]', $rules['email']);
    }

    public function testVisibleAndRequiredFieldHelpersMatchConfiguration(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('getByKeys')
            ->willReturn([
                'booking.first_names_display' => '1',
                'booking.first_names_required' => '1',
                'booking.surname_display' => '0',
                'booking.surname_required' => '0',
                'booking.email_display' => '1',
                'booking.email_required' => '1',
                'booking.phone_display' => '1',
                'booking.phone_required' => '0',
                'booking.address_display' => '0',
                'booking.address_required' => '0',
                'booking.notes_display' => '1',
                'booking.notes_required' => '0',
            ]);

        $service = new BookingSettingsService($settingModel);

        $this->assertTrue($service->isFieldDisplayed('first_name'));
        $this->assertFalse($service->isFieldDisplayed('last_name'));
        $this->assertTrue($service->isFieldRequired('email'));
        $this->assertFalse($service->isFieldRequired('phone'));
        $this->assertSame(['first_name', 'email', 'phone', 'notes'], $service->getVisibleFields());
        $this->assertSame(['first_name', 'email'], $service->getRequiredFields());
    }
}