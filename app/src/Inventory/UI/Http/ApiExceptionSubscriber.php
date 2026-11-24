<?php

declare(strict_types=1);

namespace App\Inventory\UI\Http;

use App\Inventory\Domain\Exception\InvalidAdjustment;
use App\Inventory\Domain\Exception\InvalidReceiptQuantity;
use App\Inventory\Domain\Exception\InvalidReleaseReservation;
use App\Inventory\Domain\Exception\InvalidReserve;
use App\Inventory\Domain\Exception\InvalidReservationQuantity;
use App\Inventory\Domain\Exception\InvalidSku;
use App\Inventory\Domain\Exception\ReservationNotFound;
use App\Inventory\Domain\Exception\StockAlreadyExists;
use App\Inventory\Domain\Exception\StockNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const ERROR_STATUSES = [
        InvalidApiRequest::class => 400,
        InvalidSku::class => 422,
        InvalidAdjustment::class => 422,
        InvalidReceiptQuantity::class => 422,
        InvalidReleaseReservation::class => 422,
        InvalidReserve::class => 422,
        InvalidReservationQuantity::class => 422,
        StockNotFound::class => 404,
        ReservationNotFound::class => 404,
        StockAlreadyExists::class => 409,
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $status = self::ERROR_STATUSES[$exception::class]
            ?? ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);

        $title = match ($status) {
            400 => 'Bad Request',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Content',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            default => $status >= 500 ? 'Internal Server Error' : 'Request Error',
        };

        $detail = isset(self::ERROR_STATUSES[$exception::class]) || $exception instanceof HttpExceptionInterface
            ? $exception->getMessage()
            : 'An unexpected error occurred.';

        $event->setResponse(new JsonResponse([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $event->getRequest()->getPathInfo(),
        ], $status, ['Content-Type' => 'application/problem+json']));
    }
}
