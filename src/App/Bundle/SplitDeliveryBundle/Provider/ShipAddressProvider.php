<?php

namespace App\Bundle\SplitDeliveryBundle\Provider;

use Oro\Bundle\AddressBundle\Entity\AbstractAddress;
use Oro\Bundle\AddressBundle\Entity\AddressType;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\LocaleBundle\Formatter\AddressFormatter;
use Oro\Bundle\LocaleBundle\Model\AddressInterface;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\OrderBundle\Manager\OrderAddressManager;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides address book shipping addresses a checkout line item may be shipped to.
 * Addresses are identified by the order address manager identifiers ("a_1" - customer address,
 * "au_1" - customer user address) and are limited to the ones the customer user is allowed to use.
 */
class ShipAddressProvider implements ResetInterface
{
    /** @var array<string,array<string,AbstractAddress>> */
    private array $addresses = [];
    /** @var array<string,array<string,string>> */
    private array $addressGroups = [];

    public function __construct(
        private readonly OrderAddressManager $orderAddressManager,
        private readonly AddressFormatter $addressFormatter,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[\Override]
    public function reset(): void
    {
        $this->addresses = [];
        $this->addressGroups = [];
    }

    /**
     * @return array<string,AbstractAddress> [address key => address, ...]
     */
    public function getAddresses(Checkout $checkout): array
    {
        if (!$checkout->getCustomerUser() || $checkout->getCustomerUser()->isGuest()) {
            return [];
        }

        $cacheKey = $this->getCacheKey($checkout);
        if (!isset($this->addresses[$cacheKey])) {
            $addresses = [];
            $addressGroups = [];
            $groupedAddresses = $this->orderAddressManager
                ->getGroupedAddresses($checkout, AddressType::TYPE_SHIPPING, 'oro.checkout.')
                ->toArray();
            foreach ($groupedAddresses as $groupLabel => $groupAddresses) {
                foreach ($groupAddresses as $key => $address) {
                    $addresses[(string)$key] = $address;
                    $addressGroups[(string)$key] = (string)$groupLabel;
                }
            }
            $this->addresses[$cacheKey] = $addresses;
            $this->addressGroups[$cacheKey] = $addressGroups;
        }

        return $this->addresses[$cacheKey];
    }

    /**
     * Returns choices with unique labels: when several addresses have the same text (e.g. a customer user address
     * copied from a customer address), the address book name is added to their labels.
     *
     * @return array<string,string> [address label => address key, ...]
     */
    public function getChoices(Checkout $checkout): array
    {
        $labels = [];
        foreach ($this->getAddresses($checkout) as $key => $address) {
            $labels[$key] = $this->formatAddress($address);
        }

        $labelCounts = array_count_values($labels);
        $addressGroups = $this->addressGroups[$this->getCacheKey($checkout)] ?? [];
        $choices = [];
        foreach ($labels as $key => $label) {
            if ($labelCounts[$label] > 1) {
                $label = sprintf('%s (%s)', $label, $this->translator->trans($addressGroups[$key] ?? ''));
            }
            while (isset($choices[$label])) {
                $label .= ' ';
            }
            $choices[$label] = $key;
        }

        return $choices;
    }

    public function resolve(Checkout $checkout, ?string $key): ?AbstractAddress
    {
        if (!$key) {
            return null;
        }

        return $this->getAddresses($checkout)[$key] ?? null;
    }

    /**
     * Returns the key of the address book address the checkout shipping address was created from.
     */
    public function getMainAddressKey(Checkout $checkout): ?string
    {
        $shippingAddress = $checkout->getShippingAddress();
        $address = $shippingAddress?->getCustomerUserAddress() ?? $shippingAddress?->getCustomerAddress();

        return $address ? $this->orderAddressManager->getIdentifier($address) : null;
    }

    public function createOrderAddress(Checkout $checkout, ?string $key): ?OrderAddress
    {
        $address = $this->resolve($checkout, $key);

        return $address ? $this->orderAddressManager->updateFromAbstract($address) : null;
    }

    public function formatAddress(AddressInterface $address): string
    {
        return $this->addressFormatter->format($address, null, ', ');
    }

    private function getCacheKey(Checkout $checkout): string
    {
        return sprintf('%s|%s', $checkout->getCustomer()?->getId(), $checkout->getCustomerUser()?->getId());
    }
}
