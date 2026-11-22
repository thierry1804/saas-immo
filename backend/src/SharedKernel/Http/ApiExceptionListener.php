<?php

declare(strict_types=1);

namespace App\SharedKernel\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 20)]
final class ApiExceptionListener
{
    public function __construct(private readonly ApiResponder $responder)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $path = $event->getRequest()->getPathInfo();
        if ($throwable instanceof ApiException) {
            $event->setResponse($this->responder->error($throwable));

            return;
        }
        if (!str_starts_with($path, '/api')) {
            return;
        }
        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                409 => 'CONFLICT',
                default => 'HTTP_ERROR',
            };
            $message = match ($status) {
                401 => 'Authentification requise.',
                403 => 'Action non autorisée.',
                404 => 'Ressource introuvable.',
                405 => 'Méthode non autorisée.',
                default => 'Requête refusée.',
            };
            $event->setResponse($this->responder->failure($status, $code, $message, $status >= 500));

            return;
        }
        $event->setResponse($this->responder->failure(500, 'INTERNAL', 'Erreur interne.', true));
    }
}
