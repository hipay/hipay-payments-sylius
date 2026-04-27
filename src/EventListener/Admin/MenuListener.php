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

namespace HiPay\SyliusHiPayPlugin\EventListener\Admin;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class MenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $hipayMenu = $menu
            ->addChild('hipay')
            ->setLabel('sylius_hipay_plugin.ui.menu.hipay')
            ->setLabelAttribute('icon', 'tabler:credit-card')
        ;

        $hipayMenu
            ->addChild('accounts', [
                'route' => 'sylius_hipay_plugin_admin_account_index',
                'extras' => ['routes' => [
                    ['route' => 'sylius_hipay_plugin_admin_account_create'],
                    ['route' => 'sylius_hipay_plugin_admin_account_update'],
                ]],
            ])
            ->setLabel('sylius_hipay_plugin.ui.menu.accounts')
            ->setLabelAttribute('icon', 'tabler:key')
        ;

        // TODO remove return; and phpstan-ignore-next-line to activate Oney feature
        return;
        // @phpstan-ignore-next-line
        $hipayMenu
            ->addChild('oney_categories', [
                'route' => 'sylius_hipay_plugin_admin_oney_category_index',
                'extras' => ['routes' => [
                    ['route' => 'sylius_hipay_plugin_admin_oney_category_create'],
                    ['route' => 'sylius_hipay_plugin_admin_oney_category_update'],
                ]],
            ])
            ->setLabel('sylius_hipay_plugin.ui.menu.oney_categories')
            ->setLabelAttribute('icon', 'tabler:category')
        ;

        $hipayMenu
            ->addChild('oney_shipping_methods', [
                'route' => 'sylius_hipay_plugin_admin_oney_shipping_method_index',
                'extras' => ['routes' => [
                    ['route' => 'sylius_hipay_plugin_admin_oney_shipping_method_create'],
                    ['route' => 'sylius_hipay_plugin_admin_oney_shipping_method_update'],
                ]],
            ])
            ->setLabel('sylius_hipay_plugin.ui.menu.oney_shipping_methods')
            ->setLabelAttribute('icon', 'tabler:truck')
        ;
    }
}
