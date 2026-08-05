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

namespace HiPay\SyliusHiPayPlugin\Form\Type\Gateway;

use HiPay\SyliusHiPayPlugin\PaymentProduct\Capture\CaptureMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Capture mode selector backed by the boolean `use_authorize` gateway config
 * (instant = Sale, on-demand = Authorization). Shared by the hosted fields and
 * hosted page gateway configuration forms.
 */
final class CaptureModeChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'sylius_hipay_plugin.form.card.capture_mode',
            'help' => 'sylius_hipay_plugin.form.card.capture_mode_help',
            'choices' => CaptureMode::getChoices(),
            'placeholder' => false,
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
