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

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

interface AccountInterface extends ResourceInterface, TimestampableInterface
{
    public const ENVIRONMENT_TEST = 'test';

    public const ENVIRONMENT_PRODUCTION = 'production';

    public function getName(): string;

    public function setName(?string $name): void;

    public function getCode(): string;

    public function setCode(?string $code): void;

    public function getApiUsername(): string;

    public function setApiUsername(?string $apiUsername): void;

    public function getApiPassword(): string;

    public function setApiPassword(?string $apiPassword): void;

    public function getSecretPassphrase(): string;

    public function setSecretPassphrase(?string $secretPassphrase): void;

    public function getTestApiUsername(): string;

    public function setTestApiUsername(?string $testApiUsername): void;

    public function getTestApiPassword(): string;

    public function setTestApiPassword(?string $testApiPassword): void;

    public function getTestSecretPassphrase(): string;

    public function setTestSecretPassphrase(?string $testSecretPassphrase): void;

    public function getEnvironment(): string;

    public function setEnvironment(?string $environment): void;

    public function isTestMode(): bool;

    public function isDebugMode(): bool;

    public function setDebugMode(bool $debugMode): void;

    public function getApiUsernameForCurrentEnv(): string;

    public function getApiPasswordForCurrentEnv(): string;

    public function getSecretPassphraseForCurrentEnv(): string;

    public function getPublicUsername(): string;

    public function setPublicUsername(?string $publicUsername): void;

    public function getPublicPassword(): string;

    public function setPublicPassword(?string $publicPassword): void;

    public function getTestPublicUsername(): string;

    public function setTestPublicUsername(?string $testPublicUsername): void;

    public function getTestPublicPassword(): string;

    public function setTestPublicPassword(?string $testPublicPassword): void;

    public function getPublicUsernameForCurrentEnv(): string;

    public function getPublicPasswordForCurrentEnv(): string;
}
