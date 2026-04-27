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

namespace HiPay\SyliusHiPayPlugin\Webhook;

use HiPay\SyliusHiPayPlugin\Validator\HmacValidatorInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\ExpressionRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Parses HiPay server-to-server webhook requests.
 *
 * HiPay sends notifications as HTTP POST with application/x-www-form-urlencoded body.
 * Field names match HiPay docs: attempt_id, transaction_reference, status, state, mid, etc.
 *
 * @see https://developer.hipay.com/payment-fundamentals/requirements/notifications
 * @see https://developer.hipay.com/payment-fundamentals/requirements/signature-verification
 */
final class RequestParser extends AbstractRequestParser
{
    private const CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded';

    private const MANDATORY_FIELDS = ['transaction_reference', 'status'];

    public function __construct(
        private readonly HmacValidatorInterface $hmacValidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        $expression = sprintf(
            'request.getContent() !== "" and request.headers.get("Content-Type", "") === "%s"',
            self::CONTENT_TYPE_FORM,
        );

        return new ChainRequestMatcher([
            new MethodRequestMatcher(Request::METHOD_POST),
            new ExpressionRequestMatcher(new ExpressionLanguage(), $expression),
        ]);
    }

    /**
     * @inheritDoc
     *
     * @throws RejectWebhookException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     *
     * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter - We don't use $secret we get it in account.
     */
    protected function doParse(Request $request, #[SensitiveParameter] string $secret): ?RemoteEvent
    {
        if (false === $this->hmacValidator->validate($request)) {
            $this->logger->error('[Hipay][RequestParser] Invalid signature.');

            throw new RejectWebhookException(Response::HTTP_FORBIDDEN, 'Invalid signature.');
        }

        $payload = $this->extractPayload($request);
        if (!is_array($payload) || empty(array_intersect(self::MANDATORY_FIELDS, array_keys($payload)))) {
            $this->logger->error('[Hipay][RequestParser] Invalid payload.');

            throw new RejectWebhookException(Response::HTTP_BAD_REQUEST, 'Invalid payload.');
        }

        $attemptId = $payload['attempt_id'] ?? null;
        $eventId = is_string($attemptId) ? $attemptId : uniqid('hipay_', true);

        return new RemoteEvent('hipay.notification', $eventId, $payload);
    }

    /**
     * Extracts payload from request body. HiPay sends application/x-www-form-urlencoded.
     *
     * @return array<int|string, array|string>|null
     */
    protected function extractPayload(Request $request): ?array
    {
        $content = (string) $request->getContent();
        if ('' === $content) {
            return null;
        }
        /** @var array<int|string, array|string> $payload - parse_str with form body produces string keys */
        $payload = [];
        parse_str($content, $payload);

        return $payload;
    }
}
