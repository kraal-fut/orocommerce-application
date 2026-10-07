<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Provider;

use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerAddress;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Entity\CustomerUserAddress;
use Oro\Bundle\LocaleBundle\Formatter\AddressFormatter;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\OrderBundle\Manager\OrderAddressManager;
use Oro\Bundle\OrderBundle\Manager\TypedOrderAddressCollection;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShipAddressProviderTest extends TestCase
{
    private OrderAddressManager&MockObject $orderAddressManager;
    private AddressFormatter&MockObject $addressFormatter;
    private ShipAddressProvider $provider;
    private CustomerAddress $customerAddress;
    private CustomerUserAddress $customerUserAddress;

    #[\Override]
    protected function setUp(): void
    {
        $this->orderAddressManager = $this->createMock(OrderAddressManager::class);
        $this->addressFormatter = $this->createMock(AddressFormatter::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(static fn (string $id) => $id . ' (translated)');

        $this->provider = new ShipAddressProvider($this->orderAddressManager, $this->addressFormatter, $translator);

        $this->customerAddress = new CustomerAddress();
        ReflectionUtil::setId($this->customerAddress, 1);
        $this->customerUserAddress = new CustomerUserAddress();
        ReflectionUtil::setId($this->customerUserAddress, 2);
    }

    private function createCheckout(bool $isGuest = false): Checkout
    {
        $customer = new Customer();
        ReflectionUtil::setId($customer, 10);
        $customerUser = new CustomerUser();
        ReflectionUtil::setId($customerUser, 20);
        $customerUser->setIsGuest($isGuest);
        $checkout = new Checkout();
        $checkout->setCustomer($customer);
        $checkout->setCustomerUser($customerUser);

        return $checkout;
    }

    private function expectGroupedAddresses(Checkout $checkout): void
    {
        $this->orderAddressManager->expects(self::once())
            ->method('getGroupedAddresses')
            ->with(self::identicalTo($checkout), 'shipping', 'oro.checkout.')
            ->willReturn(new TypedOrderAddressCollection($checkout->getCustomerUser(), 'shipping', [
                'customer' => ['a_1' => $this->customerAddress],
                'customer_user' => ['au_2' => $this->customerUserAddress],
            ]));
    }

    public function testGetAddressesAndChoices(): void
    {
        $this->addressFormatter->expects(self::any())
            ->method('format')
            ->willReturnCallback(static fn (object $address) => 'address ' . $address->getId());
        $checkout = $this->createCheckout();
        $this->expectGroupedAddresses($checkout);

        self::assertSame(
            ['a_1' => $this->customerAddress, 'au_2' => $this->customerUserAddress],
            $this->provider->getAddresses($checkout)
        );
        // addresses are loaded once
        self::assertSame(['address 1' => 'a_1', 'address 2' => 'au_2'], $this->provider->getChoices($checkout));
    }

    public function testGetChoicesWithSameAddressText(): void
    {
        $this->addressFormatter->expects(self::any())
            ->method('format')
            ->willReturn('Amanda Cole, 801 Scenic Hwy');
        $checkout = $this->createCheckout();
        $this->expectGroupedAddresses($checkout);

        self::assertSame(
            [
                'Amanda Cole, 801 Scenic Hwy (customer (translated))' => 'a_1',
                'Amanda Cole, 801 Scenic Hwy (customer_user (translated))' => 'au_2',
            ],
            $this->provider->getChoices($checkout)
        );
    }

    public function testGetAddressesForGuest(): void
    {
        $this->orderAddressManager->expects(self::never())->method('getGroupedAddresses');

        self::assertSame([], $this->provider->getAddresses($this->createCheckout(true)));
        self::assertSame([], $this->provider->getAddresses(new Checkout()));
    }

    public function testResolve(): void
    {
        $checkout = $this->createCheckout();
        $this->expectGroupedAddresses($checkout);

        self::assertSame($this->customerUserAddress, $this->provider->resolve($checkout, 'au_2'));
        self::assertNull($this->provider->resolve($checkout, 'au_3'));
        self::assertNull($this->provider->resolve($checkout, null));
    }

    public function testCreateOrderAddress(): void
    {
        $checkout = $this->createCheckout();
        $this->expectGroupedAddresses($checkout);
        $orderAddress = new OrderAddress();
        $this->orderAddressManager->expects(self::once())
            ->method('updateFromAbstract')
            ->with(self::identicalTo($this->customerAddress))
            ->willReturn($orderAddress);

        self::assertSame($orderAddress, $this->provider->createOrderAddress($checkout, 'a_1'));
        self::assertNull($this->provider->createOrderAddress($checkout, 'a_5'));
    }

    public function testGetMainAddressKey(): void
    {
        $checkout = $this->createCheckout();
        self::assertNull($this->provider->getMainAddressKey($checkout));

        $shippingAddress = new OrderAddress();
        $shippingAddress->setCustomerUserAddress($this->customerUserAddress);
        $checkout->setShippingAddress($shippingAddress);
        $this->orderAddressManager->expects(self::once())
            ->method('getIdentifier')
            ->with(self::identicalTo($this->customerUserAddress))
            ->willReturn('au_2');

        self::assertSame('au_2', $this->provider->getMainAddressKey($checkout));
    }
}
