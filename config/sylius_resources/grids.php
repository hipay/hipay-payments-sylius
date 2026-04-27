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

use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyCategory;
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethod;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\DateTimeField;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\BooleanFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\EnumFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\Filter;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Builder\GridBuilder;
use Sylius\Bundle\GridBundle\Config\GridConfig;

return static function (GridConfig $grid): void {
    $grid->addGrid(
        GridBuilder::create('hipay_admin_account', Account::class)
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('name')
                    ->setLabel('sylius.ui.name')
                    ->setSortable(true),
                StringField::create('code')
                    ->setLabel('sylius.ui.code')
                    ->setSortable(true),
                TwigField::create('environment', '@SyliusHiPayPlugin/admin/grid/field/environment.html.twig')
                    ->setLabel('sylius_hipay_plugin.ui.environment')
                    ->setSortable(true),
                TwigField::create('debugMode', '@SyliusAdmin/shared/grid/field/boolean.html.twig')
                    ->setLabel('sylius_hipay_plugin.ui.debug_mode')
                    ->setSortable(true),
                DateTimeField::create('createdAt')
                    ->setLabel('sylius.ui.created_at')
                    ->setSortable(true),
                DateTimeField::create('updatedAt')
                    ->setLabel('sylius.ui.updated_at')
                    ->setSortable(true),
            )
            ->withFilters(
                StringFilter::create('name', ['name'], StringFilter::TYPE_CONTAINS)
                    ->setLabel('sylius.ui.name'),
                StringFilter::create('code', ['code'], StringFilter::TYPE_CONTAINS)
                    ->setLabel('sylius.ui.code'),
                BooleanFilter::create('debugMode')
                    ->setLabel('sylius_hipay_plugin.ui.debug_mode'),
                SelectFilter::create('environment', [
                    'sylius_hipay_plugin.ui.test' => AccountInterface::ENVIRONMENT_TEST,
                    'sylius_hipay_plugin.ui.production' => AccountInterface::ENVIRONMENT_PRODUCTION,
                ], null, 'environment')
                    ->setLabel('sylius_hipay_plugin.ui.environment'),
            )
            ->addActionGroup(
                MainActionGroup::create(
                    CreateAction::create(),
                ),
            )
            ->addActionGroup(
                ItemActionGroup::create(
                    UpdateAction::create(),
                    DeleteAction::create(),
                ),
            ),
    );

    $grid->addGrid(
        GridBuilder::create('hipay_admin_oney_category', OneyCategory::class)
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('taxon.code')
                    ->setLabel('sylius_hipay_plugin.ui.sylius_category')
                    ->setSortable(true),
                TwigField::create('oneyCategory', '@SyliusHiPayPlugin/admin/grid/field/oney_standard_category.html.twig')
                    ->setLabel('sylius_hipay_plugin.ui.oney_category')
                    ->setSortable(false),
                DateTimeField::create('createdAt')
                    ->setLabel('sylius.ui.created_at')
                    ->setSortable(true),
                DateTimeField::create('updatedAt')
                    ->setLabel('sylius.ui.updated_at')
                    ->setSortable(true),
            )
            ->withFilters(
                Filter::create('taxon', 'ux_translatable_autocomplete')
                    ->setLabel('sylius.ui.taxon')
                    ->addFormOption('multiple', false)
                    ->addFormOption('extra_options', [
                        'class' => '%sylius.model.taxon.class%',
                        'translation_fields' => ['name'],
                        'choice_label' => 'fullname',
                    ])
                    ->addOption('fields', ['taxon.id']),
                EnumFilter::create('oneyCategory', OneyStandardCategory::class)
                    ->setLabel('sylius_hipay_plugin.ui.oney_category')
                    ->addFormOption('choice_label', [OneyStandardCategory::class, 'choiceTranslationKey']),
            )
            ->addActionGroup(
                MainActionGroup::create(
                    CreateAction::create(),
                ),
            )
            ->addActionGroup(
                ItemActionGroup::create(
                    UpdateAction::create(),
                    DeleteAction::create(),
                ),
            ),
    );

    $grid->addGrid(
        GridBuilder::create('hipay_admin_oney_shipping_method', OneyShippingMethod::class)
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('shippingMethod.code')
                    ->setLabel('sylius_hipay_plugin.ui.sylius_shipping_method')
                    ->setSortable(true),
                TwigField::create('oneyShippingMethod', '@SyliusHiPayPlugin/admin/grid/field/oney_standard_shipping_method.html.twig')
                    ->setLabel('sylius_hipay_plugin.ui.oney_shipping_method')
                    ->setSortable(false),
                StringField::create('oneyPreparationTime')
                    ->setLabel('sylius_hipay_plugin.ui.oney_preparation_time')
                    ->setSortable(true),
                StringField::create('oneyDeliveryTime')
                    ->setLabel('sylius_hipay_plugin.ui.oney_delivery_time')
                    ->setSortable(true),
                DateTimeField::create('createdAt')
                    ->setLabel('sylius.ui.created_at')
                    ->setSortable(true),
                DateTimeField::create('updatedAt')
                    ->setLabel('sylius.ui.updated_at')
                    ->setSortable(true),
            )
            ->withFilters(
                Filter::create('shipping_method', 'ux_translatable_autocomplete')
                    ->setLabel('sylius.ui.shipping_method')
                    ->addFormOption('extra_options', [
                        'class' => '%sylius.model.shipping_method.class%',
                        'translation_fields' => ['name'],
                        'choice_label' => 'name',
                    ])
                    ->addOption('fields', ['shippingMethod.id']),
                EnumFilter::create('oneyShippingMethod', OneyStandardShippingMethod::class)
                    ->setLabel('sylius_hipay_plugin.ui.oney_shipping_method')
                    ->addFormOption('choice_label', [OneyStandardShippingMethod::class, 'choiceTranslationKey']),
            )
            ->addActionGroup(
                MainActionGroup::create(
                    CreateAction::create(),
                ),
            )
            ->addActionGroup(
                ItemActionGroup::create(
                    UpdateAction::create(),
                    DeleteAction::create(),
                ),
            ),
    );

    $grid->addGrid(
        GridBuilder::create('sylius_admin_payment', '%sylius.model.payment.class%')
            ->extends('sylius_admin_payment')
            ->withFields(
                TwigField::create('state', '@SyliusHiPayPlugin/admin/grid/field/payment_state.html.twig')
                    ->setLabel('sylius.ui.state'),
            ),
    );
};
