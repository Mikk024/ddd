<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Application\ReleaseReservationHandler;
use App\Inventory\Domain\Exception\InvalidReleaseReservation;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineReservationRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockMovementRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineTransactionManager;
use App\Inventory\Domain\Reservation;
use App\Inventory\Domain\Stock;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ReleaseReservationHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    public function testReleaseUpdatesReservationStockAndMovementAtomicallyAndIsIdempotent(): void
    {
        $stock = new Stock(new Sku('IT-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 12))));
        $stock->receive(5);
        $stock->reserve(2);

        $reservationId = Uuid::v7()->toRfc4122();
        $reservation = new Reservation($reservationId, $stock, 2);

        $this->entityManager->persist($stock);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $handler = new ReleaseReservationHandler(
            new DoctrineReservationRepository($this->entityManager),
            new DoctrineStockMovementRepository($this->entityManager),
            new DoctrineTransactionManager($this->entityManager),
        );
        $handler->releaseReservation($reservationId);
        $handler->releaseReservation($reservationId);

        $this->entityManager->clear();

        $reloadedReservation = $this->entityManager->find(Reservation::class, $reservationId);
        self::assertInstanceOf(Reservation::class, $reloadedReservation);
        self::assertTrue($reloadedReservation->isReleased());

        $reloadedStock = $reloadedReservation->getStock();
        self::assertSame(5, $reloadedStock->getOnHand());
        self::assertSame(0, $reloadedStock->getReserved());
        self::assertSame(5, $reloadedStock->getAvailable());

        $movements = $this->entityManager
            ->getRepository(StockMovement::class)
            ->findBy(['reservation' => $reloadedReservation]);

        self::assertCount(1, $movements);
        self::assertSame(StockMovement::TYPE_RESERVATION_RELEASED, $movements[0]->getType());
        self::assertSame(2, $movements[0]->getQuantity());
    }

    public function testFailedStockReleaseRollsBackReservationAndMovementChanges(): void
    {
        $sku = new Sku('IT-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 12)));
        $stock = new Stock($sku);
        $stock->receive(5);
        $stock->reserve(2);

        $reservationId = Uuid::v7()->toRfc4122();
        $reservation = new Reservation($reservationId, $stock, 2);
        $this->entityManager->persist($stock);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Simulate inconsistent persisted state so releasing the stock fails after Reservation changes in memory.
        $this->connection->executeStatement(
            'UPDATE inventory_stock SET reserved = 0 WHERE sku = :sku',
            ['sku' => $sku->getSku()],
        );

        $handler = new ReleaseReservationHandler(
            new DoctrineReservationRepository($this->entityManager),
            new DoctrineStockMovementRepository($this->entityManager),
            new DoctrineTransactionManager($this->entityManager),
        );

        try {
            $handler->releaseReservation($reservationId);
            self::fail('Expected InvalidReleaseReservation to be thrown.');
        } catch (InvalidReleaseReservation) {
            self::assertSame(
                'active',
                $this->connection->fetchOne(
                    'SELECT status FROM inventory_reservation WHERE id = :id',
                    ['id' => $reservationId],
                ),
            );
            self::assertSame(
                0,
                (int) $this->connection->fetchOne(
                    'SELECT reserved FROM inventory_stock WHERE sku = :sku',
                    ['sku' => $sku->getSku()],
                ),
            );
            self::assertSame(
                0,
                (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM inventory_stock_movement WHERE reservation_id = :id',
                    ['id' => $reservationId],
                ),
            );
        }
    }

    public function testStockRepositoryFindsStockBySkuWithAPessimisticLock(): void
    {
        $sku = new Sku('IT-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 12)));
        $stock = new Stock($sku);
        $stock->receive(4);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $lockedStock = (new DoctrineStockRepository($this->entityManager))->getBySkuForUpdate($sku);

        self::assertSame($sku->getSku(), $lockedStock->getSku()->getSku());
        self::assertSame(4, $lockedStock->getOnHand());
    }
}
