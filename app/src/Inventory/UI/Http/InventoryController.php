<?php

declare(strict_types=1);

namespace App\Inventory\UI\Http;

use App\Inventory\Application\AdjustStockHandler;
use App\Inventory\Application\ReceiveStockHandler;
use App\Inventory\Application\RegisterStockHandler;
use App\Inventory\Application\ReleaseReservationHandler;
use App\Inventory\Application\ReserveStockHandler;
use App\Inventory\Domain\Repository\StockMovementRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use App\Inventory\Domain\ValueObject\Sku;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventory')]
final class InventoryController
{
    public function __construct(
        private readonly RegisterStockHandler $registerStock,
        private readonly ReceiveStockHandler $receiveStock,
        private readonly AdjustStockHandler $adjustStock,
        private readonly ReserveStockHandler $reserveStock,
        private readonly ReleaseReservationHandler $releaseReservation,
        private readonly StockRepositoryInterface $stocks,
        private readonly StockMovementRepositoryInterface $movements,
    ) {
    }

    #[Route('/stocks', name: 'inventory_register_stock', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $this->jsonObject($request);
        $this->requireKeys($data, ['sku']);
        $sku = new Sku($this->string($data, 'sku'));
        $this->registerStock->register($sku);

        return new JsonResponse(['sku' => $sku->getSku(), 'onHand' => 0, 'reserved' => 0, 'available' => 0], Response::HTTP_CREATED, [
            'Location' => '/api/inventory/stocks/'.$sku->getSku(),
        ]);
    }

    #[Route('/stocks/{sku}', name: 'inventory_get_stock', requirements: ['sku' => '[A-Za-z0-9-]{3,64}'], methods: ['GET'])]
    public function getStock(string $sku): JsonResponse
    {
        $stock = $this->stocks->getBySku(new Sku($sku));

        return new JsonResponse($this->stockData($stock));
    }

    #[Route('/stocks/{sku}/receipts', name: 'inventory_receive_stock', requirements: ['sku' => '[A-Za-z0-9-]{3,64}'], methods: ['POST'])]
    public function receive(string $sku, Request $request): JsonResponse
    {
        $data = $this->jsonObject($request);
        $this->requireKeys($data, ['quantity']);
        $skuValue = new Sku($sku);
        $this->receiveStock->receive($skuValue, $this->integer($data, 'quantity'));

        return new JsonResponse($this->stockData($this->stocks->getBySku($skuValue)));
    }

    #[Route('/stocks/{sku}/adjustments', name: 'inventory_adjust_stock', requirements: ['sku' => '[A-Za-z0-9-]{3,64}'], methods: ['POST'])]
    public function adjust(string $sku, Request $request): JsonResponse
    {
        $data = $this->jsonObject($request);
        $this->requireKeys($data, ['quantity', 'reason']);
        $skuValue = new Sku($sku);
        $this->adjustStock->adjust(
            $skuValue,
            $this->integer($data, 'quantity'),
            $this->string($data, 'reason'),
        );

        return new JsonResponse($this->stockData($this->stocks->getBySku($skuValue)));
    }

    #[Route('/reservations', name: 'inventory_reserve_stock', methods: ['POST'])]
    public function reserve(Request $request): JsonResponse
    {
        $data = $this->jsonObject($request);
        $this->requireKeys($data, ['sku', 'quantity']);
        $reservation = $this->reserveStock->reserveStock(
            new Sku($this->string($data, 'sku')),
            $this->integer($data, 'quantity'),
        );

        return new JsonResponse([
            'id' => $reservation->getId(),
            'sku' => $reservation->getSku()->getSku(),
            'quantity' => $reservation->getQuantity(),
            'status' => $reservation->getStatus(),
        ], Response::HTTP_CREATED, [
            'Location' => '/api/inventory/reservations/'.$reservation->getId(),
        ]);
    }

    #[Route('/reservations/{id}/release', name: 'inventory_release_reservation', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function release(string $id): Response
    {
        $this->releaseReservation->releaseReservation($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/stocks/{sku}/movements', name: 'inventory_stock_movements', requirements: ['sku' => '[A-Za-z0-9-]{3,64}'], methods: ['GET'])]
    public function movements(string $sku): JsonResponse
    {
        $skuValue = new Sku($sku);
        $this->stocks->getBySku($skuValue);

        $items = array_map(static fn ($movement): array => [
            'id' => $movement->getId(),
            'type' => $movement->getType(),
            'quantity' => $movement->getQuantity(),
            'occurredAt' => $movement->getOccurredAt()->format(DATE_ATOM),
            'reservationId' => $movement->getReservationId(),
            'reason' => $movement->getReason(),
        ], $this->movements->getBySku($skuValue));

        return new JsonResponse(['sku' => strtoupper($sku), 'items' => $items]);
    }

    /** @return array<string, mixed> */
    private function jsonObject(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidApiRequest('Request body must contain valid JSON.');
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidApiRequest('Request body must be a JSON object.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param list<string> $keys */
    private function requireKeys(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidApiRequest(sprintf('Missing required field "%s".', $key));
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key): string
    {
        if (!is_string($data[$key])) {
            throw new InvalidApiRequest(sprintf('Field "%s" must be a string.', $key));
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function integer(array $data, string $key): int
    {
        if (!is_int($data[$key])) {
            throw new InvalidApiRequest(sprintf('Field "%s" must be an integer.', $key));
        }

        return $data[$key];
    }

    /** @return array{sku: string, onHand: int, reserved: int, available: int} */
    private function stockData(\App\Inventory\Domain\Stock $stock): array
    {
        return [
            'sku' => $stock->getSku()->getSku(),
            'onHand' => $stock->getOnHand(),
            'reserved' => $stock->getReserved(),
            'available' => $stock->getAvailable(),
        ];
    }
}
