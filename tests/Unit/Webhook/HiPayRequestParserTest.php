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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Webhook;

use HiPay\SyliusHiPayPlugin\Validator\HmacValidatorInterface;
use HiPay\SyliusHiPayPlugin\Webhook\RequestParser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

final class HiPayRequestParserTest extends AbstractRequestParserTestCase
{
    private const TEST_SECRET = 'test_webhook_secret';

    protected function createRequestParser(): RequestParser
    {
        return new RequestParser(
            $this->createHmacValidator(true),
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createHmacValidator(bool $valid): HmacValidatorInterface&MockObject
    {
        $validator = $this->createMock(HmacValidatorInterface::class);
        $validator->method('validate')->willReturn($valid);

        return $validator;
    }

    private function createParser(bool $validSignature): RequestParser
    {
        return new RequestParser(
            $this->createHmacValidator($validSignature),
            $this->createMock(LoggerInterface::class),
        );
    }

    protected function getSecret(): string
    {
        return self::TEST_SECRET;
    }

    /**
     * Builds a request with form-urlencoded body and HiPay X-Allopass-Signature (SHA1 of content + secret).
     */
    protected function createRequest(string $payload): Request
    {
        $payload = trim($payload);

        $signature = 'sha1=' . hash('sha1', $payload . self::TEST_SECRET);

        return Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_ALLOPASS_SIGNATURE' => $signature,
        ], $payload);
    }

    protected static function getFixtureExtension(): string
    {
        return 'form';
    }

    public function testParseRejectsRequestWithInvalidSignature(): void
    {
        $payload = 'attempt_id=1&transaction_reference=ref-456&status=118';
        $request = Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_ALLOPASS_SIGNATURE' => 'sha1=invalid_signature',
        ], $payload);

        $parser = $this->createParser(false);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $parser->parse($request, self::TEST_SECRET);
    }

    public function testParseRejectsRequestWithMissingSignature(): void
    {
        $payload = 'attempt_id=1&transaction_reference=ref-456&status=118';
        $request = Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $payload);

        $parser = $this->createParser(false);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $parser->parse($request, self::TEST_SECRET);
    }

    public function testParseRejectsEmptyPayload(): void
    {
        $payload = '';
        $request = Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $payload);

        $parser = $this->createRequestParser();

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request does not match.');

        $parser->parse($request, self::TEST_SECRET);
    }

    public function testParseRejectsJsonContentType(): void
    {
        $payload = '{"attempt_id":"1","status":"118"}';
        $signature = 'sha1=' . hash('sha1', $payload . self::TEST_SECRET);
        $request = Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ALLOPASS_SIGNATURE' => $signature,
        ], $payload);

        $parser = $this->createRequestParser();

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request does not match.');

        $parser->parse($request, self::TEST_SECRET);
    }
}
