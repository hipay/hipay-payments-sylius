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

use Behat\Mink\Element\NodeElement;
use Sylius\Behat\Behaviour\ChecksCodeImmutability;
use Sylius\Behat\Element\Admin\Crud\FormElement as BaseFormElement;

class FormElement extends BaseFormElement implements FormElementInterface
{
    use ChecksCodeImmutability;

    public function setName(string $name): void
    {
        $this->getElement('name')->setValue($name);
    }

    public function setCode(string $code): void
    {
        $this->getElement('code')->setValue($code);
    }

    public function setEnvironment(string $environment): void
    {
        $this->getElement('environment')->selectOption($environment);
    }

    public function setApiUsername(string $value): void
    {
        $this->getElement('api_username')->setValue($value);
    }

    public function setApiPassword(string $value): void
    {
        $this->getElement('api_password')->setValue($value);
    }

    public function setSecretPassphrase(string $value): void
    {
        $this->getElement('secret_passphrase')->setValue($value);
    }

    public function setTestApiUsername(string $value): void
    {
        $this->getElement('test_api_username')->setValue($value);
    }

    public function setTestApiPassword(string $value): void
    {
        $this->getElement('test_api_password')->setValue($value);
    }

    public function setTestSecretPassphrase(string $value): void
    {
        $this->getElement('test_secret_passphrase')->setValue($value);
    }

    public function setTestPublicUsername(string $value): void
    {
        $this->getElement('test_public_username')->setValue($value);
    }

    public function setTestPublicPassword(string $value): void
    {
        $this->getElement('test_public_password')->setValue($value);
    }

    public function isCodeDisabled(): bool
    {
        return $this->getElement('code')->hasAttribute('disabled');
    }

    protected function getCodeElement(): NodeElement
    {
        return $this->getElement('code');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'api_password' => '[data-test-api-password]',
            'api_username' => '[data-test-api-username]',
            'code' => '[data-test-code]',
            'debug_mode' => '[data-test-debug-mode]',
            'environment' => '[data-test-environment]',
            'name' => '[data-test-name]',
            'secret_passphrase' => '[data-test-secret-passphrase]',
            'test_api_password' => '[data-test-test-api-password]',
            'test_api_username' => '[data-test-test-api-username]',
            'test_public_password' => '[data-test-test-public-password]',
            'test_public_username' => '[data-test-test-public-username]',
            'test_secret_passphrase' => '[data-test-test-secret-passphrase]',
        ]);
    }
}
