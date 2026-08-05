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

namespace HiPay\SyliusHiPayPlugin\Client;

use HiPay\Fullservice\Enum\Transaction\Operation;
use HiPay\Fullservice\Gateway\Client\GatewayClient;
use HiPay\Fullservice\Gateway\Model\HostedPaymentPage;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Fullservice\Gateway\Request\Info\AvailablePaymentProductRequest;
use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Fullservice\HTTP\SimpleHTTPClient;
use HiPay\Fullservice\Request\RequestSerializer;
use function is_array;
use function is_object;
use function json_decode;
use RuntimeException;
use function sprintf;
use Throwable;

final class HiPayClient implements HiPayClientInterface
{
    private GatewayClient $gatewayClient;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $environment,
        private readonly bool $hostedPageV2 = false,
    ) {
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        // Determine environment
        $apiEnv = 'test' === $this->environment
            ? Configuration::API_ENV_STAGE
            : Configuration::API_ENV_PRODUCTION;

        $configurationParams = [
            'apiUsername' => $this->username,
            'apiPassword' => $this->password,
            'apiEnv' => $apiEnv,
        ];

        if ($this->hostedPageV2) {
            $configurationParams['hostedPageV2'] = true;
        }

        // Initialize Configuration with required parameters
        $configuration = new Configuration($configurationParams);

        $httpClient = new SimpleHTTPClient($configuration);
        $this->gatewayClient = new GatewayClient($httpClient);
    }

    /**
     * @inheritdoc
     */
    public function capturePayment(string $transactionReference, MaintenanceRequest $request): array
    {
        try {
            $response = $this->gatewayClient->requestMaintenanceOperation(
                Operation::CAPTURE,
                $transactionReference,
                $request->amount,
                $request->operation_id,
                $request,
            );

            // Convert response to array for storage
            // The response is typically a Transaction object
            return $this->convertResponseToArray($response);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to capture HiPay payment: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function getPaymentProducts(): array
    {
        try {
            return $this->gatewayClient->requestAvailablePaymentProduct(new AvailablePaymentProductRequest());
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to find HiPay payment products: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function getAlmaBasketAmounts(): ?array
    {
        try {
            $request = new AvailablePaymentProductRequest();
            $request->payment_product = ['alma-3x', 'alma-4x'];
            $request->with_options = true;

            $products = $this->gatewayClient->requestAvailablePaymentProduct($request);
            foreach ($products as $product) {
                $options = $product->getOptions();
                if (null !== $options && [] !== $options) {
                    return $options;
                }
            }

            return null;
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to fetch Alma basket amounts: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function requestNewOrder(OrderRequest $request): Transaction
    {
        try {
            $transaction = $this->gatewayClient->requestNewOrder($request);
            if (!$transaction instanceof Transaction) {
                throw new RuntimeException('HiPay did not return a valid Transaction');
            }

            return $transaction;
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to request HiPay order: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function cancelPayment(string $transactionReference): array
    {
        try {
            // HiPay SDK requestMaintenanceOperation signature:
            // requestMaintenanceOperation($operationType, $transactionReference, $amount = null, $operationId = null, MaintenanceRequest $maintenanceRequest = null)
            $response = $this->gatewayClient->requestMaintenanceOperation(
                Operation::CANCEL,
                $transactionReference,
            );

            // Convert response to array for storage
            // The response is typically a Transaction object
            return $this->convertResponseToArray($response);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to cancel HiPay payment: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function refundPayment(string $transactionReference, float $amountInUnits): array
    {
        try {
            $response = $this->gatewayClient->requestMaintenanceOperation(
                Operation::REFUND,
                $transactionReference,
                $amountInUnits,
            );

            return $this->convertResponseToArray($response);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to refund HiPay payment: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function requestTransactionInformation(string $transactionReference): ?Transaction
    {
        try {
            $transaction = $this->gatewayClient->requestTransactionInformation($transactionReference);
            if (!$transaction instanceof Transaction) {
                return null;
            }

            return $transaction;
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Failed to get HiPay transaction info: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    public function requestHostedPaymentPage(HostedPaymentPageRequest $request): HostedPaymentPage
    {
        return $this->gatewayClient->requestHostedPaymentPage($request);
    }

    /**
     * Serializes a HiPay request into the clean nested array stored as the
     * PaymentRequest payload. Shared so the payload recorded before the call
     * (see PaymentCaptureHandler) is byte-for-byte the one sent to HiPay.
     *
     * @return array<string, mixed>
     */
    public static function serializeRequest(MaintenanceRequest $request): array
    {
        return self::decodeNestedJson((new RequestSerializer($request))->toArray());
    }

    /**
     * Decodes top-level values that the SDK serialized as JSON strings (e.g. basket,
     * source, custom_data) back into arrays, for a clean nested stored payload.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private static function decodeNestedJson(array $request): array
    {
        foreach ($request as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $request[$key] = $decoded;
            }
        }

        return $request;
    }

    /**
     * Convert HiPay response object to array for storage.
     *
     * @return array<string, mixed>
     */
    private function convertResponseToArray(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            // Use reflection or get_object_vars to convert object to array
            if (method_exists($response, 'toArray')) {
                return $response->toArray();
            }

            // Fallback: convert object properties to array
            $data = [];
            foreach (get_object_vars($response) as $key => $value) {
                $data[$key] = $this->convertValueToArray($value);
            }

            return $data;
        }

        return ['raw' => $response];
    }

    /**
     * Recursively convert values to arrays.
     */
    private function convertValueToArray(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'convertValueToArray'], $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            return get_object_vars($value);
        }

        return $value;
    }
}
