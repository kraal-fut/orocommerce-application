<?php

namespace App\Bundle\SplitDeliveryBundle\EventListener\Datagrid;

use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\OrderBundle\Entity\OrderLineItem;

/**
 * Adds the "Ship To" column to order line items grids of orders that have line items
 * shipped to addresses other than the order shipping address.
 */
class OrderLineItemsShipToListener
{
    private const string COLUMN_NAME = 'appShipTo';
    private const string ADDRESS_ID_COLUMN = 'appShipAddressId';
    private const string ADDRESS_ALIAS = 'app_ship_address';

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly ShipAddressProvider $shipAddressProvider
    ) {
    }

    public function onBuildBefore(BuildBefore $event): void
    {
        $orderId = $event->getDatagrid()->getParameters()->get('order_id');
        if (!$orderId || !$this->hasLineItemsWithShippingAddress((int)$orderId)) {
            return;
        }

        $config = $event->getConfig();
        $config->getOrmQuery()
            ->addLeftJoin(
                'order_item.' . SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS,
                self::ADDRESS_ALIAS
            )
            ->addSelect(sprintf('%s.id as %s', self::ADDRESS_ALIAS, self::ADDRESS_ID_COLUMN));
        $config->addColumn(self::COLUMN_NAME, [
            'label' => 'app.split_delivery.datagrid.ship_to.label',
            'type' => 'field',
            'frontend_type' => 'string',
            'translatable' => true,
        ]);
    }

    public function onResultAfter(OrmResultAfter $event): void
    {
        /** @var ResultRecord[] $records */
        $records = $event->getRecords();
        $addressIds = [];
        foreach ($records as $record) {
            $addressId = $record->getValue(self::ADDRESS_ID_COLUMN);
            if ($addressId) {
                $addressIds[] = $addressId;
            }
        }
        if (!$addressIds) {
            return;
        }

        $formattedAddresses = [];
        /** @var OrderAddress[] $addresses */
        $addresses = $this->doctrine->getRepository(OrderAddress::class)->findBy(['id' => array_unique($addressIds)]);
        foreach ($addresses as $address) {
            $formattedAddresses[$address->getId()] = $this->shipAddressProvider->formatAddress($address);
        }

        foreach ($records as $record) {
            $addressId = $record->getValue(self::ADDRESS_ID_COLUMN);
            if ($addressId && isset($formattedAddresses[$addressId])) {
                $record->setValue(self::COLUMN_NAME, $formattedAddresses[$addressId]);
            }
        }
    }

    private function hasLineItemsWithShippingAddress(int $orderId): bool
    {
        $result = $this->doctrine->getRepository(OrderLineItem::class)
            ->createQueryBuilder('order_item')
            ->select('order_item.id')
            ->where(':orderId MEMBER OF order_item.orders')
            ->andWhere(sprintf('order_item.%s IS NOT NULL', SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS))
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }
}
