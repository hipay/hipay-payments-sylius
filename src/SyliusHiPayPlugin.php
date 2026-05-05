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

namespace HiPay\SyliusHiPayPlugin;

use HiPay\SyliusHiPayPlugin\Command\BackgroundProcessableCommandInterface;
use HiPay\SyliusHiPayPlugin\Command\BrowserInteractionRequiredCommandInterface;
use HiPay\SyliusHiPayPlugin\RefundPlugin\Compiler\AddRefundGatewayPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\RefundPlugin\SyliusRefundPlugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SyliusHiPayPlugin extends Bundle
{
    use SyliusPluginTrait;

    /**
     * Return the bundle root directory (one level above src/).
     * Used by Symfony to resolve config, templates, and other resource paths.
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Override the default container extension resolution.
     *
     * In the Symfony bundle lifecycle, each bundle can expose a container extension
     * (an implementation of ExtensionInterface) that is responsible for loading
     * and processing the bundle's semantic configuration (e.g. from config/packages/*.yaml).
     *
     * By default, Symfony auto-discovers the extension by convention: it looks for a class
     * named <BundleName>\DependencyInjection\<BundleName>Extension. If found, that extension
     * processes the `my_bundle:` configuration key and registers services accordingly.
     *
     * Returning null here explicitly disables this mechanism, meaning:
     * - No semantic configuration key is exposed for this bundle.
     * - No DependencyInjection Extension class is loaded.
     * - Service registration and configuration are handled manually in build() instead,
     *   giving full control over how and when services are loaded.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return null;
    }

    /**
     * Hook into the Symfony container build phase.
     *
     * This method is called once during the kernel boot process, before the container
     * is compiled. It is part of the Symfony bundle lifecycle and is the primary place
     * for a bundle to:
     * - Register compiler passes that modify service definitions before compilation.
     * - Load service definitions (DI configuration) into the container.
     * - Prepend configuration to other bundles (using the container's prepend mechanism).
     *
     * In the full Symfony lifecycle, the order is:
     *   1. Kernel::boot() registers all bundles
     *   2. Each bundle's build() method is called (this method)
     *   3. Container extensions process semantic configuration
     *   4. Compiler passes run (optimization, validation, tag resolution, etc.)
     *   5. The container is compiled and dumped to a cached PHP class
     *
     * Since this bundle returns null from getContainerExtension(), all service loading
     * and configuration prepending is performed here directly.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->loadServices($container);
        $this->prependSyliusResource($container);
        $this->prependSyliusGrid($container);
        $this->prependDoctrineMigrations($container);
        $this->prependMessenger($container);
        $this->addRefundGatewayPass($container);
        $this->prependSyliusMailer($container);
        $this->prependMonolog($container);
    }

    /**
     * Load the plugin's service definitions from config/services.php
     * into the DI container using Symfony's PhpFileLoader.
     */
    protected function loadServices(ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader(
            $container,
            new FileLocator($this->getPath() . '/config'),
        );
        $loader->load('services.php');
    }

    /**
     * Register the plugin's entity directory with SyliusResourceBundle so it can
     * discover resources declared via #[AsResource] attributes (e.g. Account).
     * Without this, the resource alias (hipay.account) is never registered and
     * the sylius.resource route loader cannot resolve it.
     */
    protected function prependSyliusResource(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_resource')) {
            return;
        }

        $container->prependExtensionConfig('sylius_resource', [
            'mapping' => [
                'paths' => [
                    $this->getPath() . '/src/Entity',
                ],
            ],
        ]);
    }

    /**
     * Register admin Twig template for grid filter type "enum" (not defined in Sylius Admin defaults).
     */
    protected function prependSyliusGrid(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_grid')) {
            return;
        }

        $container->prependExtensionConfig('sylius_grid', [
            'templates' => [
                'filter' => [
                    'enum' => '@SyliusAdmin/shared/grid/filter/select.html.twig',
                ],
            ],
        ]);
    }

    /**
     * Register HiPay-specific Messenger transports and routing.
     *
     * Two transports carry the Payment Request commands:
     *   - hipay_payment_sync: forced sync:// for commands that require an immediate HTTP
     *     response (checkout flow: the customer is waiting for a redirect).
     *   - hipay_payment_async: follows the shop's payment_request transport DSN for
     *     commands that can run in the background.
     *
     * Webhook notifications (ConsumeRemoteEventMessage) are intentionally left on the
     * default sync:// transport: the Consumer synchronously writes the incoming payload
     * to the hipay_pending_notification table, then the scheduler worker picks it up
     * in priority order after a configurable buffer window (see HiPayNotificationsSchedule
     * and docs/webhook-async-processing.md).
     */
    protected function prependMessenger(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    'hipay_payment_sync' => [
                        'dsn' => 'sync://',
                    ],
                    'hipay_payment_async' => [
                        'dsn' => '%sylius_messenger_transport_payment_request_dsn%',
                        'failure_transport' => 'payment_request_failed',
                    ],
                ],
                'routing' => [
                    BrowserInteractionRequiredCommandInterface::class => 'hipay_payment_sync',
                    BackgroundProcessableCommandInterface::class => 'hipay_payment_async',
                ],
            ],
        ]);
    }

    /**
     * Prepend Doctrine migration paths so the plugin's migrations are discovered.
     *
     * Doctrine Migrations tracks database schema changes through versioned migration
     * classes. Each migration represents an incremental change (e.g. CREATE TABLE,
     * ALTER TABLE) and is stored as a PHP class in a specific directory.
     *
     * By prepending the 'doctrine_migrations' configuration, this method registers
     * the plugin's own migration namespace and directory:
     *   HiPay\SyliusHiPayPlugin\Migrations  =>  src/Migrations/
     *
     * This allows the host application to run `doctrine:migrations:migrate` and
     * automatically include this plugin's migrations alongside its own, without
     * needing to manually reference the plugin's migration path in the app config.
     *
     * The hasExtension() guard ensures this is skipped if DoctrineMigrationsBundle
     * is not registered in the application kernel.
     */
    protected function prependDoctrineMigrations(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine_migrations')) {
            return;
        }

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'HiPay\SyliusHiPayPlugin\Migrations' => $this->getPath() . '/src/Migrations',
            ],
        ]);
    }

    protected function addRefundGatewayPass(ContainerBuilder $container): void
    {
        if (false === class_exists(SyliusRefundPlugin::class)) {
            return;
        }
        $container->addCompilerPass(new AddRefundGatewayPass());
    }

    /**
     * Register the fraud suspicion email with SyliusMailerBundle so it can be
     * sent via the standard Sylius email pipeline (SenderInterface).
     */
    protected function prependSyliusMailer(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_mailer')) {
            return;
        }

        $container->prependExtensionConfig('sylius_mailer', [
            'emails' => [
                'hipay_fraud_suspicion' => [
                    'subject' => 'sylius_hipay_plugin.email.fraud_suspicion.subject',
                    'template' => '@SyliusHiPayPlugin/email/fraud_suspicion.html.twig',
                ],
                'hipay_multibanco_reference' => [
                    'subject' => 'sylius_hipay_plugin.email.multibanco_reference.subject',
                    'template' => '@SyliusHiPayPlugin/email/multibanco_reference.html.twig',
                ],
            ],
        ]);
    }

    /**
     * Register the dedicated `hipay` Monolog channel so the plugin's logger
     * service (sylius_hipay_plugin.logger.hipay) can be wired immediately after
     * the bundle is added to bundles.php — without waiting for the host
     * application to import config/monolog.yaml from the plugin.
     *
     * Without this prepend, the container compilation fails on a fresh
     * `composer require` with:
     *   The service "sylius_hipay_plugin.logger.hipay" has a dependency on a
     *   non-existent service "monolog.logger.hipay".
     *
     * Handler configuration (file path, level, formatter) intentionally remains
     * in config/monolog.yaml so users who import the plugin's config get a
     * pre-tuned handler for free, while users who don't still get a working
     * bundle (the channel falls back to the default handler chain).
     */
    protected function prependMonolog(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('monolog')) {
            return;
        }

        $container->prependExtensionConfig('monolog', [
            'channels' => ['hipay'],
        ]);
    }
}
