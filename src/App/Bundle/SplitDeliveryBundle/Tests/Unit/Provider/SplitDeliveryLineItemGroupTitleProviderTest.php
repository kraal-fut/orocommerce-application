<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Provider;

use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryLineItemGroupTitleProvider;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\LineItemGroupTitleProvider;
use Oro\Bundle\CustomerBundle\Entity\CustomerUserAddress;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SplitDeliveryLineItemGroupTitleProviderTest extends TestCase
{
    use SplitDeliveryFieldsTestTrait;

    private LineItemGroupTitleProvider&MockObject $innerProvider;
    private ShipAddressProvider&MockObject $shipAddressProvider;
    private SplitDeliveryLineItemGroupTitleProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->innerProvider = $this->createMock(LineItemGroupTitleProvider::class);
        $this->shipAddressProvider = $this->createMock(ShipAddressProvider::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(static fn (string $id, array $params = []) => $id . json_encode($params));
        $this->shipAddressProvider->expects(self::any())
            ->method('formatAddress')
            ->willReturnCallback(static fn (object $address) => $address::class);

        $this->provider = new SplitDeliveryLineItemGroupTitleProvider(
            $this->innerProvider,
            $this->shipAddressProvider,
            $translator
        );
    }

    public function testGetTitleForOtherGrouping(): void
    {
        $lineItem = new CheckoutLineItem();
        $this->innerProvider->expects(self::once())
            ->method('getTitle')
            ->with('product.category:1', self::identicalTo($lineItem))
            ->willReturn('Category 1');

        self::assertSame('Category 1', $this->provider->getTitle('product.category:1', $lineItem));
    }

    public function testGetTitleForAddressGroup(): void
    {
        $checkout = new Checkout();
        $lineItem = $this->createLineItem(1, 'au_2', null, $checkout);
        $this->shipAddressProvider->expects(self::once())
            ->method('resolve')
            ->with(self::identicalTo($checkout), 'au_2')
            ->willReturn(new CustomerUserAddress());
        $this->innerProvider->expects(self::never())->method('getTitle');

        self::assertSame(
            'app.split_delivery.line_item_group.ship_to.title'
            . json_encode(['%address%' => CustomerUserAddress::class]),
            $this->provider->getTitle('app_ship_address_key:au_2', $lineItem)
        );
    }

    public function testGetTitleForMainAddressGroup(): void
    {
        $checkout = new Checkout();
        $checkout->setShippingAddress(new OrderAddress());
        $lineItem = $this->createLineItem(1, null, null, $checkout);
        $this->shipAddressProvider->expects(self::once())->method('resolve')->willReturn(null);

        self::assertSame(
            'app.split_delivery.line_item_group.ship_to.title' . json_encode(['%address%' => OrderAddress::class]),
            $this->provider->getTitle('app_ship_address_key:0', $lineItem)
        );
    }

    public function testGetTitleForMainAddressGroupWithoutShippingAddress(): void
    {
        $lineItem = $this->createLineItem(1, null, null, new Checkout());
        $this->shipAddressProvider->expects(self::once())->method('resolve')->willReturn(null);

        self::assertSame(
            'app.split_delivery.line_item_group.main_address.title[]',
            $this->provider->getTitle('app_ship_address_key:0', $lineItem)
        );
    }
}
