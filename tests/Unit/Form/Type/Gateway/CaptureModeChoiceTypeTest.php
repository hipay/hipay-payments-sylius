<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Form\Type\Gateway;

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\CaptureModeChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

/**
 * The capture mode is persisted in the gateway config as the boolean `use_authorize`
 * flag (read natively by Sylius and by CaptureModeProcessorPayment), so the submitted
 * value must resolve to a real bool — never null or a string.
 */
final class CaptureModeChoiceTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
    }

    public function testItStoresOnDemandAsBooleanTrue(): void
    {
        $form = $this->factory->create(CaptureModeChoiceType::class);
        $form->submit('1');

        $this->assertTrue($form->isSynchronized());
        $this->assertIsBool($form->getData());
        $this->assertTrue($form->getData());
    }

    public function testItStoresInstantAsBooleanFalse(): void
    {
        $form = $this->factory->create(CaptureModeChoiceType::class);
        $form->submit('0');

        $this->assertTrue($form->isSynchronized());
        $this->assertIsBool($form->getData());
        $this->assertFalse($form->getData());
    }
}
