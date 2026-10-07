<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Provider;

use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\ConfigProvider;
use PHPUnit\Framework\TestCase;

class SplitDeliveryConfigProviderTest extends TestCase
{
    /**
     * @dataProvider isEnabledDataProvider
     */
    public function testIsEnabled(bool $groupingEnabled, string $groupBy, bool $perLineItem, bool $expected): void
    {
        $multiShippingConfigProvider = $this->createMock(ConfigProvider::class);
        $multiShippingConfigProvider->expects(self::any())
            ->method('isLineItemsGroupingEnabled')
            ->willReturn($groupingEnabled);
        $multiShippingConfigProvider->expects(self::any())
            ->method('getGroupLineItemsByField')
            ->willReturn($groupBy);
        $multiShippingConfigProvider->expects(self::any())
            ->method('isShippingSelectionByLineItemEnabled')
            ->willReturn($perLineItem);

        self::assertSame($expected, (new SplitDeliveryConfigProvider($multiShippingConfigProvider))->isEnabled());
    }

    public function isEnabledDataProvider(): array
    {
        return [
            'enabled' => [true, 'app_ship_address_key', false, true],
            'grouping disabled' => [false, 'app_ship_address_key', false, false],
            'grouped by category' => [true, 'product.category', false, false],
            'shipping method per line item' => [true, 'app_ship_address_key', true, false],
        ];
    }
}
