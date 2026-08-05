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

namespace HiPay\SyliusHiPayPlugin\Controller\Admin;

use HiPay\SyliusHiPayPlugin\Event\RequestCaptureEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CapturePaymentAction
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RouterInterface $router,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, int|string $orderId, int|string $id): RedirectResponse
    {
        $redirect = new RedirectResponse(
            $this->router->generate('sylius_admin_order_show', ['id' => $orderId]),
        );

        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken((string) $id, $token))) {
            // @phpstan-ignore-next-line
            $request->getSession()->getFlashBag()->add('error', 'hipay.error.invalid_csrf_token');

            return $redirect;
        }

        $event = new RequestCaptureEvent((int) $id);
        $this->eventDispatcher->dispatch($event);

        // @phpstan-ignore-next-line
        $request->getSession()->getFlashBag()->add(
            $event->getResult()?->isSuccessful() ? 'success' : 'error',
            $event->getResult()?->isSuccessful() ? 'hipay.success.capture_requested' : 'hipay.error.capture_failed',
        );

        return $redirect;
    }
}
