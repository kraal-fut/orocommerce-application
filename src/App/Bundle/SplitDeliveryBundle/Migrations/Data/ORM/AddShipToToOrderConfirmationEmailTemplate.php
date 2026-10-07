<?php

namespace App\Bundle\SplitDeliveryBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\CheckoutBundle\Migrations\Data\ORM\UpdateShippingMethodInOrderConfirmationEmailTemplate;
use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\OrderBundle\Entity\Order;

/**
 * Adds the shipping address of each line item to the "order_confirmation_email" email template.
 */
class AddShipToToOrderConfirmationEmailTemplate extends AbstractFixture implements DependentFixtureInterface
{
    private const string LINE_ITEMS_LOOP = '<!--{% for item in data.lineItems %}-->';
    private const string PRODUCT_NAME = '<p style="{{ styleProductName }}">{{ item.product_name }}</p>';
    private const string SHIP_ADDRESSES_VAR = 'appShipAddresses';

    #[\Override]
    public function getDependencies(): array
    {
        return [UpdateShippingMethodInOrderConfirmationEmailTemplate::class];
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        /** @var EmailTemplate|null $template */
        $template = $manager->getRepository(EmailTemplate::class)->findOneBy([
            'name' => 'order_confirmation_email',
            'entityName' => Order::class,
        ]);
        $content = $template?->getContent();
        if (
            !$content
            || str_contains($content, self::SHIP_ADDRESSES_VAR)
            || substr_count($content, self::LINE_ITEMS_LOOP) !== 1
            || substr_count($content, self::PRODUCT_NAME) !== 1
        ) {
            return;
        }

        $content = str_replace(
            self::LINE_ITEMS_LOOP,
            sprintf(
                '<!--{%% set %s = app_split_delivery_ship_addresses(entity) %%}-->%s%s',
                self::SHIP_ADDRESSES_VAR,
                "\n                ",
                self::LINE_ITEMS_LOOP
            ),
            $content
        );
        $content = str_replace(
            self::PRODUCT_NAME,
            self::PRODUCT_NAME . sprintf(
                '<!--{%% if %1$s[item.id] is defined %%}-->'
                . '<p style="{{ styleLightTextColor }}">Ship to: {{ %1$s[item.id] }}</p><!--{%% endif %%}-->',
                self::SHIP_ADDRESSES_VAR
            ),
            $content
        );

        $template->setContent($content);
        $manager->flush();
    }
}
