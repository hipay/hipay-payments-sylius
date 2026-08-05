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

use Exception;
use HiPay\Fullservice\Gateway\Model\AvailablePaymentProduct;
use HiPay\Fullservice\Gateway\Model\HostedPaymentPage;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use RuntimeException;

interface HiPayClientInterface
{
    /**
     * Capture an authorized payment via the maintenance API (operation = capture).
     * POST /v1/maintenance/transaction/{transaction_reference}.
     *
     * @param string             $transactionReference The HiPay transaction reference from authorization
     * @param MaintenanceRequest $request              Fully built capture request (operation, operation_id, amount, basket, source)
     *
     * @throws Exception If the capture request fails
     *
     * @return array The capture response data
     */
    public function capturePayment(string $transactionReference, MaintenanceRequest $request): array;

    /**
     * @return array<AvailablePaymentProduct>
     */
    public function getPaymentProducts(): array;

    /**
     * Request a new order (Hosted Fields / API-only flow).
     *
     * @param OrderRequest $request The order request (amount, currency, card token, etc.)
     *
     * @throws Exception If the order request fails
     *
     * @return Transaction The transaction response (state, forwardUrl, status, etc.)
     */
    public function requestNewOrder(OrderRequest $request): Transaction;

    /**
     * Cancel an authorized payment.
     *
     * @param string $transactionReference The HiPay transaction reference from authorization
     *
     * @throws RuntimeException If the cancel request fails
     *
     * @return array The cancel response data
     */
    public function cancelPayment(string $transactionReference): array;

    /**
     * Refund a captured payment (full or partial).
     *
     * @param string $transactionReference The HiPay transaction reference
     * @param float $amountInUnits Amount to refund in currency units
     *
     * @throws RuntimeException If the refund request fails
     *
     * @return array The refund response data
     */
    public function refundPayment(string $transactionReference, float $amountInUnits): array;

    /**
     * Retrieve transaction information by reference.
     * Used by the STATUS action to check transaction state after a redirect (3DS, APM).
     *
     * @param string $transactionReference The HiPay transaction reference
     *
     * @throws Exception If the request fails
     *
     * @return Transaction|null The transaction or null if not found
     */
    public function requestTransactionInformation(string $transactionReference): ?Transaction;

    public function requestHostedPaymentPage(HostedPaymentPageRequest $request): HostedPaymentPage;

    /**
     * Fetch Alma basket amount limits from the HiPay API.
     * Returns keys: basketAmountMin3x, basketAmountMax3x, basketAmountMin4x, basketAmountMax4x.
     *
     * @throws RuntimeException If the request fails
     *
     * @return array<string, mixed>|null The options array, or null if no Alma product is found
     */
    public function getAlmaBasketAmounts(): ?array;
}
