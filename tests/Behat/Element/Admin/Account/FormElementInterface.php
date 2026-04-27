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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\Account;

use Sylius\Behat\Element\Admin\Crud\FormElementInterface as BaseFormElementInterface;

interface FormElementInterface extends BaseFormElementInterface
{
    public function setName(string $name): void;

    public function setCode(string $code): void;

    public function setEnvironment(string $environment): void;

    public function setApiUsername(string $value): void;

    public function setApiPassword(string $value): void;

    public function setSecretPassphrase(string $value): void;

    public function setTestApiUsername(string $value): void;

    public function setTestApiPassword(string $value): void;

    public function setTestSecretPassphrase(string $value): void;

    public function setTestPublicUsername(string $value): void;

    public function setTestPublicPassword(string $value): void;

    public function isCodeDisabled(): bool;
}
