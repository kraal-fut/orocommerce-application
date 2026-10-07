<?php

namespace App\Bundle\SplitDeliveryBundle\Migrations\Schema;

use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityBundle\EntityConfig\DatagridScope;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareTrait;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AppSplitDeliveryBundleInstaller implements Installation, ExtendExtensionAwareInterface
{
    use ExtendExtensionAwareTrait;

    #[\Override]
    public function getMigrationVersion(): string
    {
        return 'v1_0';
    }

    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->addCheckoutLineItemShipAddressKey($schema);
        $this->addOrderLineItemShippingAddress($schema);
    }

    private function addCheckoutLineItemShipAddressKey(Schema $schema): void
    {
        $table = $schema->getTable('oro_checkout_line_item');
        if ($table->hasColumn(SplitDeliveryFields::CHECKOUT_LINE_ITEM_ADDRESS_KEY)) {
            return;
        }

        $table->addColumn(SplitDeliveryFields::CHECKOUT_LINE_ITEM_ADDRESS_KEY, 'string', [
            'length' => 50,
            'notnull' => false,
            'oro_options' => [
                ExtendOptionsManager::MODE_OPTION => ConfigModel::MODE_READONLY,
                'extend' => ['is_extend' => true, 'owner' => ExtendScope::OWNER_CUSTOM],
                'entity' => ['label' => 'app.split_delivery.checkoutlineitem.ship_address_key.label'],
                'datagrid' => ['is_visible' => DatagridScope::IS_VISIBLE_FALSE],
                'view' => ['is_displayable' => false],
                'form' => ['is_enabled' => false],
                'importexport' => ['excluded' => true],
            ],
        ]);
    }

    private function addOrderLineItemShippingAddress(Schema $schema): void
    {
        $table = $schema->getTable('oro_order_line_item');
        $columnName = $this->extendExtension->getNameGenerator()
            ->generateRelationColumnName(SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS, '_id');
        if ($table->hasColumn($columnName)) {
            return;
        }

        $this->extendExtension->addManyToOneRelation(
            $schema,
            $table,
            SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS,
            'oro_order_address',
            'id',
            [
                ExtendOptionsManager::MODE_OPTION => ConfigModel::MODE_READONLY,
                'extend' => [
                    'is_extend' => true,
                    'owner' => ExtendScope::OWNER_CUSTOM,
                    'without_default' => true,
                    'cascade' => ['persist'],
                    'on_delete' => 'SET NULL',
                ],
                'entity' => ['label' => 'app.split_delivery.orderlineitem.shipping_address.label'],
                'datagrid' => ['is_visible' => DatagridScope::IS_VISIBLE_FALSE],
                'view' => ['is_displayable' => false],
                'form' => ['is_enabled' => false],
                'importexport' => ['excluded' => true],
                'email' => ['available_in_template' => true],
            ]
        );
    }
}
