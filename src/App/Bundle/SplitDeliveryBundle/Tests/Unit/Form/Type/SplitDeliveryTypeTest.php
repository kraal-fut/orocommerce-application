<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Form\Type;

use App\Bundle\SplitDeliveryBundle\Form\Type\LineItemShipAddressType;
use App\Bundle\SplitDeliveryBundle\Form\Type\SplitDeliveryType;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;

class SplitDeliveryTypeTest extends FormIntegrationTestCase
{
    use SplitDeliveryFieldsTestTrait;

    private const array CHOICES = ['Address 1' => 'a_1', 'Address 2' => 'au_2'];

    #[\Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([new SplitDeliveryType(), new LineItemShipAddressType()], []),
        ];
    }

    public function testBuildFormWithoutAddresses(): void
    {
        $checkout = new Checkout();
        $this->createLineItem(1, null, null, $checkout);
        $this->createLineItem(2, null, null, $checkout);

        $form = $this->factory->create(SplitDeliveryType::class, null, [
            'checkout' => $checkout,
            'address_choices' => self::CHOICES,
        ]);

        self::assertFalse($form->get('enabled')->getData());
        self::assertSame('1', $form->get('rendered')->getData());
        self::assertTrue($form->get('items')->has('li_1'));
        self::assertTrue($form->get('items')->has('li_2'));

        $view = $form->createView();
        self::assertSame($checkout->getLineItems()->first(), $view['items']['li_1']->vars['line_item']);
    }

    public function testBuildFormWithAddresses(): void
    {
        $checkout = new Checkout();
        $this->createLineItem(1, 'au_2', null, $checkout);
        $this->createLineItem(2, 'au_5', null, $checkout);

        $form = $this->factory->create(SplitDeliveryType::class, null, [
            'checkout' => $checkout,
            'address_choices' => self::CHOICES,
        ]);

        self::assertTrue($form->get('enabled')->getData());
        self::assertSame('au_2', $form->get('items')->get('li_1')->getData());
        // not available address is not pre-selected
        self::assertNull($form->get('items')->get('li_2')->getData());
    }

    public function testSubmitAndApply(): void
    {
        $checkout = new Checkout();
        $lineItem1 = $this->createLineItem(1, null, null, $checkout);
        $lineItem2 = $this->createLineItem(2, 'a_1', null, $checkout);

        $form = $this->factory->create(SplitDeliveryType::class, null, [
            'checkout' => $checkout,
            'address_choices' => self::CHOICES,
        ]);
        $form->submit(['enabled' => '1', 'rendered' => '1', 'items' => ['li_1' => 'au_2', 'li_2' => '']]);
        self::assertTrue($form->isValid());

        SplitDeliveryType::applyToCheckout($form, $checkout);

        self::assertSame('au_2', SplitDeliveryFields::getAddressKey($lineItem1));
        self::assertNull(SplitDeliveryFields::getAddressKey($lineItem2));
    }

    public function testSubmitNotAvailableAddress(): void
    {
        $checkout = new Checkout();
        $this->createLineItem(1, null, null, $checkout);

        $form = $this->factory->create(SplitDeliveryType::class, null, [
            'checkout' => $checkout,
            'address_choices' => self::CHOICES,
        ]);
        $form->submit(['enabled' => '1', 'rendered' => '1', 'items' => ['li_1' => 'au_99']]);

        self::assertFalse($form->isValid());
    }

    public function testSubmitDisabledResetsAddresses(): void
    {
        $checkout = new Checkout();
        $lineItem = $this->createLineItem(1, 'au_2', null, $checkout);

        $form = $this->factory->create(SplitDeliveryType::class, null, [
            'checkout' => $checkout,
            'address_choices' => self::CHOICES,
        ]);
        $form->submit(['rendered' => '1', 'items' => ['li_1' => 'au_2']]);

        SplitDeliveryType::applyToCheckout($form, $checkout);

        self::assertNull(SplitDeliveryFields::getAddressKey($lineItem));
    }

    public function testNotRenderedFormDoesNotChangeAddresses(): void
    {
        $checkout = new Checkout();
        $lineItem = $this->createLineItem(1, 'au_2', null, $checkout);

        $form = $this->factory->create(SplitDeliveryType::class, null, [
            'checkout' => $checkout,
            'address_choices' => self::CHOICES,
        ]);
        $form->submit(null);

        SplitDeliveryType::applyToCheckout($form, $checkout);

        self::assertSame('au_2', SplitDeliveryFields::getAddressKey($lineItem));
    }
}
