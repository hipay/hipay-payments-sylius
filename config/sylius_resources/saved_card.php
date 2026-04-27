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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use HiPay\SyliusHiPayPlugin\Entity\SavedCard;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepository;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('sylius_resource', [
        'resources' => [
            'sylius_hipay_plugin.saved_card' => [
                'classes' => [
                    'model' => SavedCard::class,
                    'repository' => SavedCardRepository::class,
                ],
            ],
        ],
    ]);
};
