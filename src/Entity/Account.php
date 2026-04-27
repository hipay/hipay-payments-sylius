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

namespace HiPay\SyliusHiPayPlugin\Entity;

use HiPay\SyliusHiPayPlugin\Form\Type\Resource\AccountType;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\BulkDelete;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Update;
use Sylius\Resource\Model\TimestampableTrait;

#[AsResource(
    alias: 'sylius_hipay_plugin.account',
    section: 'admin',
    formType: AccountType::class,
    templatesDir: '@SyliusAdmin/shared/crud',
    routePrefix: '/hipay',
    name: 'account',
    pluralName: 'accounts',
    applicationName: 'sylius_hipay_plugin',
    vars: [
        'subheader' => 'sylius_hipay_plugin.ui.manage_accounts',
    ],
    operations: [
        new Index(
            vars: ['header' => 'sylius_hipay_plugin.ui.accounts'],
            grid: 'hipay_admin_account',
        ),
        new Create(
            validationContext: ['groups' => ['sylius_hipay_plugin', 'sylius_hipay_plugin_account_create']],
            redirectToRoute: 'sylius_hipay_plugin_admin_account_index',
        ),
        new Update(
            validationContext: ['groups' => ['sylius_hipay_plugin', 'sylius_hipay_plugin_account_update']],
            redirectToRoute: 'sylius_hipay_plugin_admin_account_index',
        ),
        new Delete(),
        new BulkDelete(),
    ],
)]
class Account implements AccountInterface
{
    use TimestampableTrait;

    private ?int $id = null;

    private string $name = '';

    private string $code = '';

    private string $apiUsername = '';

    private string $apiPassword = '';

    private string $secretPassphrase = '';

    private string $testApiUsername = '';

    private string $testApiPassword = '';

    private string $testSecretPassphrase = '';

    private string $publicUsername = '';

    private string $publicPassword = '';

    private string $testPublicUsername = '';

    private string $testPublicPassword = '';

    private string $environment = self::ENVIRONMENT_TEST;

    private bool $debugMode = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name ?? '';
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code ?? '';
    }

    public function getApiUsername(): string
    {
        return $this->apiUsername;
    }

    public function setApiUsername(?string $apiUsername): void
    {
        $this->apiUsername = $apiUsername ?? '';
    }

    public function getApiPassword(): string
    {
        return $this->apiPassword;
    }

    public function setApiPassword(?string $apiPassword): void
    {
        $this->apiPassword = $apiPassword ?? '';
    }

    public function getSecretPassphrase(): string
    {
        return $this->secretPassphrase;
    }

    public function setSecretPassphrase(?string $secretPassphrase): void
    {
        $this->secretPassphrase = $secretPassphrase ?? '';
    }

    public function getTestApiUsername(): string
    {
        return $this->testApiUsername;
    }

    public function setTestApiUsername(?string $testApiUsername): void
    {
        $this->testApiUsername = $testApiUsername ?? '';
    }

    public function getTestApiPassword(): string
    {
        return $this->testApiPassword;
    }

    public function setTestApiPassword(?string $testApiPassword): void
    {
        $this->testApiPassword = $testApiPassword ?? '';
    }

    public function getTestSecretPassphrase(): string
    {
        return $this->testSecretPassphrase;
    }

    public function setTestSecretPassphrase(?string $testSecretPassphrase): void
    {
        $this->testSecretPassphrase = $testSecretPassphrase ?? '';
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): void
    {
        $this->environment = $environment ?? self::ENVIRONMENT_TEST;
    }

    public function isTestMode(): bool
    {
        return self::ENVIRONMENT_TEST === $this->environment;
    }

    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function setDebugMode(bool $debugMode): void
    {
        $this->debugMode = $debugMode;
    }

    public function getApiUsernameForCurrentEnv(): string
    {
        return $this->isTestMode() ? $this->testApiUsername : $this->apiUsername;
    }

    public function getApiPasswordForCurrentEnv(): string
    {
        return $this->isTestMode() ? $this->testApiPassword : $this->apiPassword;
    }

    public function getSecretPassphraseForCurrentEnv(): string
    {
        return $this->isTestMode() ? $this->testSecretPassphrase : $this->secretPassphrase;
    }

    public function getPublicUsername(): string
    {
        return $this->publicUsername;
    }

    public function setPublicUsername(?string $publicUsername): void
    {
        $this->publicUsername = $publicUsername ?? '';
    }

    public function getPublicPassword(): string
    {
        return $this->publicPassword;
    }

    public function setPublicPassword(?string $publicPassword): void
    {
        $this->publicPassword = $publicPassword ?? '';
    }

    public function getTestPublicUsername(): string
    {
        return $this->testPublicUsername;
    }

    public function setTestPublicUsername(?string $testPublicUsername): void
    {
        $this->testPublicUsername = $testPublicUsername ?? '';
    }

    public function getTestPublicPassword(): string
    {
        return $this->testPublicPassword;
    }

    public function setTestPublicPassword(?string $testPublicPassword): void
    {
        $this->testPublicPassword = $testPublicPassword ?? '';
    }

    public function getPublicUsernameForCurrentEnv(): string
    {
        return $this->isTestMode() ? $this->testPublicUsername : $this->publicUsername;
    }

    public function getPublicPasswordForCurrentEnv(): string
    {
        return $this->isTestMode() ? $this->testPublicPassword : $this->publicPassword;
    }
}
