<?php

declare(strict_types=1);

namespace App\Tests\Inventory\UI;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundationRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InventoryApiTest extends KernelTestCase
{
    private Connection $connection;
    private HttpKernelInterface $httpKernel;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->httpKernel = static::$kernel;
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testInventoryApiSupportsTheFullStockLifecycleAndHistory(): void
    {
        $sku = $this->uniqueSku();

        $registered = $this->request('POST', '/api/inventory/stocks', ['sku' => strtolower($sku)]);
        self::assertSame(201, $registered->getStatusCode());
        self::assertSame($sku, $this->decode($registered)['sku']);

        $receipt = $this->request('POST', '/api/inventory/stocks/'.$sku.'/receipts', ['quantity' => 10]);
        self::assertSame(200, $receipt->getStatusCode());
        self::assertSame(10, $this->decode($receipt)['onHand']);
        $adjustment = $this->request('POST', '/api/inventory/stocks/'.$sku.'/adjustments', ['quantity' => -2, 'reason' => 'Damaged items']);
        self::assertSame(200, $adjustment->getStatusCode());
        self::assertSame(8, $this->decode($adjustment)['onHand']);

        $reservationResponse = $this->request('POST', '/api/inventory/reservations', ['sku' => $sku, 'quantity' => 3]);
        self::assertSame(201, $reservationResponse->getStatusCode());
        $reservation = $this->decode($reservationResponse);
        self::assertSame('active', $reservation['status']);

        $released = $this->httpKernel->handle(Request::create(
            '/api/inventory/reservations/'.$reservation['id'].'/release',
            'POST',
        ));
        self::assertSame(204, $released->getStatusCode());
        $this->httpKernel->handle(Request::create('/api/inventory/reservations/'.$reservation['id'].'/release', 'POST'));

        $stockResponse = $this->httpKernel->handle(Request::create('/api/inventory/stocks/'.$sku, 'GET'));
        self::assertSame(200, $stockResponse->getStatusCode());
        self::assertSame([
            'sku' => $sku,
            'onHand' => 8,
            'reserved' => 0,
            'available' => 8,
        ], $this->decode($stockResponse));

        $history = $this->httpKernel->handle(Request::create('/api/inventory/stocks/'.$sku.'/movements', 'GET'));
        $items = $this->decode($history)['items'];
        self::assertCount(4, $items);
        self::assertSame(['reservation_released', 'reservation_created', 'adjustment', 'receipt'], array_column($items, 'type'));
        self::assertSame(-2, $items[2]['quantity']);
        self::assertSame('Damaged items', $items[2]['reason']);
        self::assertSame($reservation['id'], $items[0]['reservationId']);
    }

    public function testApiUsesProblemDetailsAndDoesNotApplyInvalidAdjustment(): void
    {
        $sku = $this->uniqueSku();
        $this->request('POST', '/api/inventory/stocks', ['sku' => $sku]);
        $this->request('POST', '/api/inventory/stocks/'.$sku.'/receipts', ['quantity' => 4]);
        $this->request('POST', '/api/inventory/reservations', ['sku' => $sku, 'quantity' => 3]);

        $response = $this->request('POST', '/api/inventory/stocks/'.$sku.'/adjustments', [
            'quantity' => -2,
            'reason' => 'Would consume reserved stock',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(422, $this->decode($response)['status']);

        $stock = $this->httpKernel->handle(Request::create('/api/inventory/stocks/'.$sku, 'GET'));
        self::assertSame(4, $this->decode($stock)['onHand']);
        self::assertSame(3, $this->decode($stock)['reserved']);

        $history = $this->httpKernel->handle(Request::create('/api/inventory/stocks/'.$sku.'/movements', 'GET'));
        self::assertCount(2, $this->decode($history)['items']);
    }

    public function testDuplicateSkuReturnsConflict(): void
    {
        $sku = $this->uniqueSku();
        $this->request('POST', '/api/inventory/stocks', ['sku' => $sku]);

        $response = $this->request('POST', '/api/inventory/stocks', ['sku' => strtolower($sku)]);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(409, $this->decode($response)['status']);
    }

    public function testInvalidSkuAndMissingStockUseProblemDetails(): void
    {
        $invalidSku = $this->request('POST', '/api/inventory/stocks', ['sku' => 'bad sku']);
        self::assertSame(422, $invalidSku->getStatusCode());
        self::assertSame('application/problem+json', $invalidSku->headers->get('Content-Type'));

        $missingStock = $this->httpKernel->handle(Request::create('/api/inventory/stocks/MISSING-1', 'GET'));
        self::assertSame(404, $missingStock->getStatusCode());
        self::assertSame('Not Found', $this->decode($missingStock)['title']);

        $missingHistory = $this->httpKernel->handle(Request::create('/api/inventory/stocks/MISSING-1/movements', 'GET'));
        self::assertSame(404, $missingHistory->getStatusCode());
    }

    private function request(string $method, string $uri, ?array $data = null): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create(
            $uri,
            $method,
            server: $data === null ? [] : ['CONTENT_TYPE' => 'application/json'],
            content: $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR),
        );

        return $this->httpKernel->handle($request);
    }

    /** @return array<string, mixed> */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function uniqueSku(): string
    {
        return 'API-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
    }
}
