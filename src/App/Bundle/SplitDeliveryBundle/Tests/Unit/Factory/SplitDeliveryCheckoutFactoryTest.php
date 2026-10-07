<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Factory;

use App\Bundle\SplitDeliveryBundle\Factory\SplitDeliveryCheckoutFactory;
use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Factory\MultiShipping\CheckoutFactoryInterface;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SplitDeliveryCheckoutFactoryTest extends TestCase
{
    use SplitDeliveryFieldsTestTrait;

    private CheckoutFactoryInterface&MockObject $innerFactory;
    private SplitDeliveryConfigProvider&MockObject $configProvider;
    private ShipAddressProvider&MockObject $shipAddressProvider;
    private SplitDeliveryCheckoutFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->innerFactory = $this->createMock(CheckoutFactoryInterface::class);
        $this->configProvider = $this->createMock(SplitDeliveryConfigProvider::class);
        $this->shipAddressProvider = $this->createMock(ShipAddressProvider::class);

        $this->factory = new SplitDeliveryCheckoutFactory(
            $this->innerFactory,
            $this->configProvider,
            $this->shipAddressProvider
        );
    }

    private function expectSplitCheckout(Checkout $source, array $lineItems): Checkout
    {
        $mainAddress = new OrderAddress();
        $splitCheckout = new Checkout();
        $splitCheckout->setShippingAddress($mainAddress);
        foreach ($lineItems as $lineItem) {
            $splitCheckout->addLineItem($lineItem);
        }
        $this->innerFactory->expects(self::once())
            ->method('createCheckout')
            ->with($source, $lineItems)
            ->willReturn($splitCheckout);

        return $splitCheckout;
    }

    public function testCreateCheckoutWhenFeatureDisabled(): void
    {
        $source = new Checkout();
        $lineItems = [$this->createLineItem(1, 'au_2')];
        $splitCheckout = $this->expectSplitCheckout($source, $lineItems);
        $mainAddress = $splitCheckout->getShippingAddress();

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(false);
        $this->shipAddressProvider->expects(self::never())->method('createOrderAddress');

        self::assertSame($splitCheckout, $this->factory->createCheckout($source, $lineItems));
        self::assertSame($mainAddress, $splitCheckout->getShippingAddress());
    }

    public function testCreateCheckoutWhenAllLineItemsShippedToSameAddress(): void
    {
        $source = new Checkout();
        $lineItems = [$this->createLineItem(1, 'au_2'), $this->createLineItem(2, 'au_2')];
        $splitCheckout = $this->expectSplitCheckout($source, $lineItems);
        $groupAddress = new OrderAddress();

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::once())
            ->method('createOrderAddress')
            ->with(self::identicalTo($source), 'au_2')
            ->willReturn($groupAddress);

        $this->factory->createCheckout($source, $lineItems);
        self::assertSame($groupAddress, $splitCheckout->getShippingAddress());
    }

    public function testCreateCheckoutWhenLineItemsShippedToDifferentAddresses(): void
    {
        $source = new Checkout();
        $lineItems = [$this->createLineItem(1, 'au_2'), $this->createLineItem(2, 'a_3')];
        $splitCheckout = $this->expectSplitCheckout($source, $lineItems);
        $mainAddress = $splitCheckout->getShippingAddress();

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::never())->method('createOrderAddress');

        $this->factory->createCheckout($source, $lineItems);
        self::assertSame($mainAddress, $splitCheckout->getShippingAddress());
    }

    public function testCreateCheckoutWhenLineItemsShippedToMainAddress(): void
    {
        $source = new Checkout();
        $lineItems = [$this->createLineItem(1), $this->createLineItem(2)];
        $splitCheckout = $this->expectSplitCheckout($source, $lineItems);
        $mainAddress = $splitCheckout->getShippingAddress();

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::never())->method('createOrderAddress');

        $this->factory->createCheckout($source, $lineItems);
        self::assertSame($mainAddress, $splitCheckout->getShippingAddress());
    }

    public function testCreateCheckoutWhenAddressIsNotAvailable(): void
    {
        $source = new Checkout();
        $lineItems = [$this->createLineItem(1, 'au_2')];
        $splitCheckout = $this->expectSplitCheckout($source, $lineItems);
        $mainAddress = $splitCheckout->getShippingAddress();

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::once())
            ->method('createOrderAddress')
            ->with(self::identicalTo($source), 'au_2')
            ->willReturn(null);

        $this->factory->createCheckout($source, $lineItems);
        self::assertSame($mainAddress, $splitCheckout->getShippingAddress());
    }
}
