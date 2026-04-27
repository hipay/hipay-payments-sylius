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

namespace HiPay\SyliusHiPayPlugin\Validator;

use HiPay\Fullservice\Enum\Helper\HashAlgorithm;
use HiPay\Fullservice\Helper\Signature;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProvider;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class HmacValidator implements HmacValidatorInterface
{
    public function __construct(
        private readonly AccountProvider $accountProvider,
        private readonly HiPayLoggerInterface $logger,
    ) {
    }

    public function validate(Request $request): bool
    {
        $content = $request->getContent();
        if (empty($content)) {
            return false;
        }

        $secretPassphrase = $this->getSecret($content);

        return Signature::isValidHttpSignature($secretPassphrase, HashAlgorithm::SHA256);
    }

    private function getSecret(string $content): string
    {
        parse_str($content, $data);
        /** @var int|null $accountSyliusId */
        $accountSyliusId = $data['custom_data']['account_sylius_id'] ?? null;
        if (null === $accountSyliusId) {
            $message = 'Account sylius id not found in content';
            $this->logger->critical($message);

            throw new InvalidArgumentException($message);
        }
        $account = $this->accountProvider->getById((int) $accountSyliusId);
        if (null === $account) {
            $message = 'No account found for this payment (or transaction reference not found in database)';
            $this->logger->critical($message, ['account_sylius_id' => $accountSyliusId]);

            throw new InvalidArgumentException($message);
        }

        return $account->getSecretPassphraseForCurrentEnv();
    }
}
