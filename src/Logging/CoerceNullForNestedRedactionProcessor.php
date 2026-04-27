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

namespace HiPay\SyliusHiPayPlugin\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * RedactSensitiveProcessor throws when a context key has nested redaction rules but its value is null.
 * Coerce those nulls to an empty array so nested traversal is a no-op.
 */
final class CoerceNullForNestedRedactionProcessor implements ProcessorInterface
{
    /**
     * @param array<string, int|array<string, mixed>> $sensitiveKeys
     */
    public function __construct(
        private readonly array $sensitiveKeys,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->coerceContext($record->context, $this->sensitiveKeys),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, int|array<string, mixed>> $sensitiveKeys
     *
     * @return array<string, mixed>
     */
    private function coerceContext(array $context, array $sensitiveKeys): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->coerceContext($value, $sensitiveKeys);

                continue;
            }

            if (
                null === $value &&
                array_key_exists($key, $sensitiveKeys) &&
                is_array($sensitiveKeys[$key])
            ) {
                $context[$key] = [];
            }
        }

        return $context;
    }
}
