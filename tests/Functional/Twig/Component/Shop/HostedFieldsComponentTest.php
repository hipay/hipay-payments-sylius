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

namespace Tests\HiPay\SyliusHiPayPlugin\Functional\Twig\Component\Shop;

use function count;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class HostedFieldsComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testComponentRendersWithoutPaymentMethod(): void
    {
        self::bootKernel();
        $component = $this->createLiveComponent('hipay_hosted_fields', []);

        $rendered = $component->render();

        $this->assertNotEmpty($rendered->__toString());
    }

    public function testProcessPaymentActionDispatchesEventWhenPaymentSet(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $paymentRepo = $container->get('sylius.repository.payment');
        $payments = $paymentRepo->findBy([], ['id' => 'DESC'], 1);

        if (0 === count($payments)) {
            $this->markTestSkipped('No payment in database; load fixtures to test processPayment LiveAction.');
        }

        $payment = $payments[0];
        $component = $this->createLiveComponent('hipay_hosted_fields', [
            'paymentMethod' => $payment->getMethod(),
            'payment' => $payment,
        ]);

        $component->call('processPayment', ['response' => '{"transaction_reference":"ref-123"}']);

        $this->assertComponentDispatchBrowserEvent($component, 'hipay:payment:processed');
    }
}
