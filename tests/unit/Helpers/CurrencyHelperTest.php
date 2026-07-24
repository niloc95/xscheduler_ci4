<?php

namespace Tests\Unit\Helpers;

use App\Models\SettingModel;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionProperty;

/**
 * Regression lock for the localization currency contract.
 *
 * `format_currency()` previously read `setting('Localization.currency')` with a
 * capital L. `SettingModel::getByKeys()` matches keys exactly, so the lookup
 * could never resolve and every bare call fell through to the 'ZAR' default —
 * hardcoding the dashboard, analytics, services and payment surfaces to Rand
 * regardless of what the business had selected.
 *
 * @internal
 */
final class CurrencyHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('currency');
        $this->seedSettingCache([]);
    }

    protected function tearDown(): void
    {
        $this->seedSettingCache([]);
        parent::tearDown();
    }

    /**
     * Seed SettingModel's request cache so no DB access is needed. Clearing first
     * is what makes these assertions order-independent — the cache is process-static.
     */
    private function seedSettingCache(array $values): void
    {
        SettingModel::clearRequestCache();

        if ($values === []) {
            return;
        }

        $property = new ReflectionProperty(SettingModel::class, 'requestCache');
        $property->setAccessible(true);
        $property->setValue(null, $values);
    }

    public function testFormatCurrencyUsesTheConfiguredCurrencyRatherThanTheZarDefault(): void
    {
        $this->seedSettingCache(['localization.currency' => 'GBP']);

        $this->assertSame('£150.00', format_currency(150.00));
        $this->assertSame('£', get_app_currency_symbol());
        $this->assertSame('GBP', get_app_currency());
    }

    public function testFormatCurrencyFollowsTheSettingWhenItChanges(): void
    {
        $this->seedSettingCache(['localization.currency' => 'USD']);

        $this->assertSame('$1,250.50', format_currency(1250.50));
        $this->assertSame('$', get_app_currency_symbol());

        // A London business relocating to New York changes one setting; every
        // bare format_currency() call must follow it.
        $this->seedSettingCache(['localization.currency' => 'EUR']);

        $this->assertSame('€1,250.50', format_currency(1250.50));
    }

    public function testCurrencyCodeIsNormalisedToUppercase(): void
    {
        $this->seedSettingCache(['localization.currency' => 'gbp']);

        $this->assertSame('GBP', get_app_currency());
        $this->assertSame('£150.00', format_currency(150.00));
    }

    public function testFallsBackToZarOnlyWhenTheSettingIsAbsent(): void
    {
        $this->seedSettingCache(['localization.currency' => null]);

        $this->assertSame('ZAR', get_app_currency());
        $this->assertSame('R150.00', format_currency(150.00));
    }

    public function testExplicitCurrencyCodeOverridesTheSetting(): void
    {
        $this->seedSettingCache(['localization.currency' => 'ZAR']);

        $this->assertSame('$150.00', format_currency(150.00, 'USD'));
        $this->assertSame('R150', format_currency(150.00, null, 0));
    }

    public function testUnknownCurrencyCodeDegradesToTheBareCode(): void
    {
        $this->assertSame('XYZ ', get_currency_symbol('XYZ'));
        $this->assertSame('XYZ 150.00', format_currency(150.00, 'XYZ'));
    }

    public function testKnownSymbolsCoverEveryCodeOfferedInTheLocalizationPicker(): void
    {
        // Mirrors app/Views/settings/tabs/localization.php — a code offered in the
        // dropdown but missing here would render as a bare "XXX 150.00", and would
        // also be rejected by GeneralSettingsService validation on save.
        $offered = ['ZAR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'CHF', 'CNY', 'INR', 'BRL'];

        foreach ($offered as $code) {
            $this->assertTrue(
                is_supported_currency($code),
                "Currency {$code} is offered in the settings picker but has no symbol mapping."
            );
        }
    }

    public function testCurrencySupportIsDecidedByMembershipNotBySymbolShape(): void
    {
        // CHF's symbol is the code itself, so inferring support from the returned
        // string would wrongly classify it as unmapped.
        $this->assertSame('CHF ', get_currency_symbol('CHF'));
        $this->assertTrue(is_supported_currency('CHF'));
        $this->assertFalse(is_supported_currency('XYZ'));
        $this->assertTrue(is_supported_currency('gbp'));
    }

    public function testCalculateDepositAmountRoundsAndRejectsInvalidInput(): void
    {
        $this->assertSame(30.0, calculate_deposit_amount(300.0, 10.0));
        $this->assertSame(33.33, calculate_deposit_amount(99.99, 33.33));
        $this->assertSame(0.0, calculate_deposit_amount(null, 10.0));
        $this->assertSame(0.0, calculate_deposit_amount(300.0, 0.0));
        $this->assertSame(0.0, calculate_deposit_amount(-10.0, 10.0));
    }

    public function testParseCurrencyStripsSymbolsAndGrouping(): void
    {
        $this->assertSame(1250.50, parse_currency('£1,250.50'));
        $this->assertSame(1250.50, parse_currency('$1,250.50'));
        $this->assertSame(-99.0, parse_currency('R-99.00'));
    }
}
