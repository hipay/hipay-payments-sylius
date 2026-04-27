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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration;

/**
 * Default card widget / gateway configuration values (HiPay JS SDK material theme).
 *
 * @see CardConfigurationType
 * @see \HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\CardHandler
 */
interface CardConfigurationDefaultsInterface
{
    public const TEXT_COLOR = '#000000';

    public const PLACEHOLDER_COLOR = '#BFBFBF';

    public const ICON_COLOR = '#737373';

    public const INVALID_TEXT_COLOR = '#CB2B0B';

    public const VALID_TEXT_COLOR = '#000000';

    public const ONECLICK_HIGHLIGHT_COLOR = '#02A17B';

    public const SAVE_BUTTON_COLOR = '#02A17B';

    public const FONT_FAMILY = 'Roboto,"Helvetica Neue",Helvetica,Arial,sans-serif';

    public const FONT_SIZE = '14px';

    public const FONT_STYLE = 'normal';

    public const FONT_WEIGHT = 'normal';

    public const TEXT_DECORATION = 'none';
}
