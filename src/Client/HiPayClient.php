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

use HiPay\Fullservice\Gateway\Client\GatewayClient;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Fullservice\Gateway\Request\Info\AvailablePaymentProductRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Fullservice\HTTP\SimpleHTTPClient;
use function is_array;
use function is_object;
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
    ) {
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        // Determine environment
        $apiEnv = 'test' === $this->environment
            ? Configuration::API_ENV_STAGE
            : Configuration::API_ENV_PRODUCTION;

        // Initialize Configuration with required parameters
        $configuration = new Configuration([
            'apiUsername' => $this->username,
            'apiPassword' => $this->password,
            'apiEnv' => $apiEnv,
        ]);

        $httpClient = new SimpleHTTPClient($configuration);
        $this->gatewayClient = new GatewayClient($httpClient);
    }

    /**
     * @inheritdoc
     */
    public function capturePayment(string $transactionReference, float $amount, string $currency): array
    {
        try {
            // HiPay SDK requestMaintenanceOperation signature:
            // requestMaintenanceOperation($operationType, $transactionReference, $amount = null, $operationId = null, MaintenanceRequest $maintenanceRequest = null)
            $response = $this->gatewayClient->requestMaintenanceOperation(
                'capture',
                $transactionReference,
                $amount,
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
                'cancel',
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
                'refund',
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

    /**
     * Convert HiPay response object to array for storage.
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
