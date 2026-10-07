<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\DataProvider;

use App\Bundle\SplitDeliveryBundle\DataProvider\SplitDeliveryCheckoutDataProvider;
use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Component\Checkout\DataProvider\CheckoutDataProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SplitDeliveryCheckoutDataProviderTest extends TestCase
{
    use SplitDeliveryFieldsTestTrait;

    private CheckoutDataProviderInterface&MockObject $innerDataProvider;
    private SplitDeliveryConfigProvider&MockObject $configProvider;
    private ShipAddressProvider&MockObject $shipAddressProvider;
    private SplitDeliveryCheckoutDataProvider $dataProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->innerDataProvider = $this->createMock(CheckoutDataProviderInterface::class);
        $this->configProvider = $this->createMock(SplitDeliveryConfigProvider::class);
        $this->shipAddressProvider = $this->createMock(ShipAddressProvider::class);

        $this->dataProvider = new SplitDeliveryCheckoutDataProvider(
            $this->innerDataProvider,
            $this->configProvider,
            $this->shipAddressProvider
        );
    }

    public function testIsEntitySupported(): void
    {
        $entity = new Checkout();
        $this->innerDataProvider->expects(self::once())
            ->method('isEntitySupported')
            ->with(self::identicalTo($entity))
            ->willReturn(true);

        self::assertTrue($this->dataProvider->isEntitySupported($entity));
    }

    public function testGetDataWhenFeatureDisabled(): void
    {
        $checkout = new Checkout();
        $this->createLineItem(1, 'au_2', null, $checkout);
        $data = [['checksum' => 'checksum1']];

        $this->innerDataProvider->expects(self::once())->method('getData')->willReturn($data);
        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(false);
        $this->shipAddressProvider->expects(self::never())->method('createOrderAddress');

        self::assertSame($data, $this->dataProvider->getData($checkout));
    }

    public function testGetDataAddsShippingAddresses(): void
    {
        $checkout = new Checkout();
        $this->createLineItem(1, 'au_2', null, $checkout);
        $this->createLineItem(2, null, null, $checkout);
        $this->createLineItem(3, 'au_2', '', $checkout);
        $this->createLineItem(4, 'a_5', null, $checkout);

        $address2 = new OrderAddress();
        $address5 = new OrderAddress();
        $this->innerDataProvider->expects(self::exactly(2))
            ->method('getData')
            ->willReturn([
                ['checksum' => 'checksum1'],
                ['checksum' => 'checksum2'],
                ['checksum' => '', 'productSku' => 'SKU3', 'productUnitCode' => 'item'],
                ['checksum' => 'checksum4'],
            ]);
        $this->configProvider->expects(self::exactly(2))->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::exactly(2))
            ->method('createOrderAddress')
            ->willReturnMap([
                [$checkout, 'au_2', $address2],
                [$checkout, 'a_5', $address5],
            ]);

        $data = $this->dataProvider->getData($checkout);
        self::assertSame($address2, $data[0][SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS]);
        self::assertArrayNotHasKey(SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS, $data[1]);
        self::assertSame($address2, $data[2][SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS]);
        self::assertSame($address5, $data[3][SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS]);

        // order addresses are reused for the same checkout
        $data = $this->dataProvider->getData($checkout);
        self::assertSame($address2, $data[0][SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS]);
    }
}
