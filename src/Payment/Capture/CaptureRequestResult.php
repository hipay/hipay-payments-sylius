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

namespace HiPay\SyliusHiPayPlugin\Payment\Capture;

/**
 * Outcome of a capture trigger: success, or failure with a human-readable reason.
 */
final readonly class CaptureRequestResult
{
    private function __construct(
        private bool $successful,
        private ?string $error = null,
    ) {
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $error): self
    {
        return new self(false, $error);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
