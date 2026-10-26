<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Las acciones del panel (liberar salas, cambiar PIN, ajustes) se lanzan con
 * fetch. Todo POST bajo /admin, salvo el login (que ya trae su propio token),
 * tiene que llevar la cabecera X-CSRF-Token que pinta base_admin.html.twig.
 */
class AdminCsrfSubscriber implements EventSubscriberInterface
{
    public const TOKEN_ID = 'admin';

    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Después del firewall (prioridad 8), para que el login lo resuelva él.
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()
            || !$request->isMethod('POST')
            || !str_starts_with($request->getPathInfo(), '/admin/')
            || $request->getPathInfo() === '/admin/login') {
            return;
        }

        $token = new CsrfToken(self::TOKEN_ID, (string) $request->headers->get('X-CSRF-Token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $event->setResponse(new JsonResponse(['exito' => false, 'error' => 'Token CSRF no válido. Recarga la página.'], 403));
        }
    }
}
