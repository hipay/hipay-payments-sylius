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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Form\Type;

use function count;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedFieldsConfigurationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class HostedFieldsConfigurationTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testFormHasAccountField(): void
    {
        $form = $this->formFactory->create(HostedFieldsConfigurationType::class);

        $this->assertTrue($form->has('account'));
    }

    public function testFormSubmitWithValidAccountTriggersPaymentProductField(): void
    {
        $container = self::getContainer();
        $accountRepo = $container->get('sylius_hipay_plugin.repository.account');
        $accounts = $accountRepo->findAll();

        if (0 === count($accounts)) {
            $this->markTestSkipped('No HiPay account in database; load fixtures to test dependent payment_product field.');
        }

        $accountCode = $accounts[0]->getCode();
        $form = $this->formFactory->create(HostedFieldsConfigurationType::class, [
            'account' => $accountCode,
        ]);

        $form->submit([
            'account' => $accountCode,
        ]);

        $this->assertTrue($form->has('account'));
        $this->assertTrue($form->isSynchronized());
    }

    public function testFormSubmitWithCardPaymentProductTriggersConfigurationField(): void
    {
        $container = self::getContainer();
        $accountRepo = $container->get('sylius_hipay_plugin.repository.account');
        $accounts = $accountRepo->findAll();

        if (0 === count($accounts)) {
            $this->markTestSkipped('No HiPay account in database; load fixtures to test dependent configuration field.');
        }

        $accountCode = $accounts[0]->getCode();
        $form = $this->formFactory->create(HostedFieldsConfigurationType::class, [
            'account' => $accountCode,
            'payment_product' => 'card',
        ]);

        $form->submit([
            'account' => $accountCode,
            'payment_product' => 'card',
        ]);

        $this->assertTrue($form->has('account'));
        $this->assertTrue($form->isSynchronized());
    }
}
