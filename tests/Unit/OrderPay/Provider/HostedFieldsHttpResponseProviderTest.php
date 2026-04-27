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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\OrderPay\Provider;

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\SyliusHiPayPlugin\OrderPay\Handler\HiPayPaymentStateFlashHandlerDecorator;
use HiPay\SyliusHiPayPlugin\OrderPay\Provider\HostedFieldsHttpResponseProvider;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HostedFieldsHttpResponseProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private HostedFieldsHttpResponseProvider $provider;

    private RequestConfiguration&MockObject $requestConfiguration;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(Request::create('/'));
        $this->provider = new HostedFieldsHttpResponseProvider($this->urlGenerator, $this->requestStack, $this->createMock(OrphanPaymentCancellerInterface::class), $this->createMock(OrderAdvisoryLockInterface::class));
        $this->requestConfiguration = $this->createMock(RequestConfiguration::class);

        $this->urlGenerator->method('generate')->willReturnCallback(
            fn (string $route, array $params = []) => match ($route) {
                'sylius_shop_order_thank_you' => '/thank-you',
                'sylius_shop_order_show' => '/order/' . ($params['tokenValue'] ?? ''),
                'sylius_shop_homepage' => '/',
                default => '/' . $route,
            },
        );
    }

    /**
     * @dataProvider supportedActionsProvider
     */
    public function testSupportsAction(string $action): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);

        $this->assertTrue($this->provider->supports($this->requestConfiguration, $paymentRequest));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedActionsProvider(): iterable
    {
        yield 'capture' => [PaymentRequestInterface::ACTION_CAPTURE];
        yield 'authorize' => [PaymentRequestInterface::ACTION_AUTHORIZE];
        yield 'status' => [PaymentRequestInterface::ACTION_STATUS];
        yield 'cancel' => [PaymentRequestInterface::ACTION_CANCEL];
        yield 'capture_request' => ['capture_request'];
        yield 'authorize_request' => ['authorize_request'];
    }

    /**
     * @dataProvider unsupportedActionsProvider
     */
    public function testDoesNotSupportAction(string $action): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);

        $this->assertFalse($this->provider->supports($this->requestConfiguration, $paymentRequest));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedActionsProvider(): iterable
    {
        yield 'refund' => ['refund'];
        yield 'payout' => ['payout'];
        yield 'notify' => ['notify'];
    }

    public function testForwardingStateRedirectsToForwardUrl(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData([
            'state' => TransactionState::FORWARDING,
            'forwardUrl' => 'https://3ds.bank.example/auth',
        ]);

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://3ds.bank.example/auth', $response->getTargetUrl());
    }

    public function testForwardingStateWithoutUrlFallsBackToThankYou(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData([
            'state' => TransactionState::FORWARDING,
        ]);

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/thank-you', $response->getTargetUrl());
    }

    public function testCompletedStateRedirectsToThankYou(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData([
            'state' => TransactionState::COMPLETED,
        ]);

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/thank-you', $response->getTargetUrl());
        $current = $this->requestStack->getCurrentRequest();
        $this->assertNotNull($current);
        $this->assertTrue($current->attributes->get(HiPayPaymentStateFlashHandlerDecorator::REQUEST_ATTR_HIPAY_PAYMENT));
    }

    public function testPendingStateRedirectsToThankYou(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData([
            'state' => TransactionState::PENDING,
        ]);

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/thank-you', $response->getTargetUrl());
    }

    public function testDeclinedStateRedirectsToOrderShow(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData(
            ['state' => TransactionState::DECLINED],
            'order-token-123',
        );

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/order/order-token-123', $response->getTargetUrl());
    }

    public function testErrorStateRedirectsToOrderShow(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData(
            ['state' => TransactionState::ERROR],
            'order-token-err',
        );

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/order/order-token-err', $response->getTargetUrl());
    }

    public function testMissingStateRedirectsToOrderShow(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData([], 'order-token-miss');

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/order/order-token-miss', $response->getTargetUrl());
    }

    public function testNoTokenValueRedirectsToHomepage(): void
    {
        $paymentRequest = $this->createPaymentRequestWithResponseData(
            ['state' => TransactionState::ERROR],
            null,
        );

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/', $response->getTargetUrl());
    }

    public function testErrorReasonMessageStripsErrorPrefix(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $this->requestStack->pop();
        $this->requestStack->push($request);

        $paymentRequest = $this->createPaymentRequestWithResponseData(
            [
                'state' => TransactionState::DECLINED,
                'reason' => ['message' => 'error: Invalid Card Number'],
            ],
            'order-token-flash',
        );

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(['Invalid Card Number'], $session->getFlashBag()->peek('error'));
    }

    public function testErrorReasonMessageWithoutErrorPrefixIsNotMangled(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $this->requestStack->pop();
        $this->requestStack->push($request);

        $paymentRequest = $this->createPaymentRequestWithResponseData(
            [
                'state' => TransactionState::DECLINED,
                'reason' => ['message' => 'Refused'],
            ],
            'order-token-refused',
        );

        $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        $this->assertSame(['Refused'], $session->getFlashBag()->peek('error'));
    }

    private function createPaymentRequestWithResponseData(
        array $responseData,
        ?string $tokenValue = 'order-token',
    ): PaymentRequestInterface&MockObject {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn($tokenValue);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getResponseData')->willReturn($responseData);
        $paymentRequest->method('getPayment')->willReturn($payment);

        return $paymentRequest;
    }
}
