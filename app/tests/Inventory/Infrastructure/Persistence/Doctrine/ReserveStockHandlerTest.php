<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Application\ReserveStockHandler;
use App\Inventory\Domain\Exception\InvalidReserve;
use App\Inventory\Domain\Reservation;
use App\Inventory\Domain\Stock;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineReservationRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockMovementRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineTransactionManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReserveStockHandlerTest extends KernelTestCase
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

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    public function testReservationUpdatesStockAndCreatesMovement(): void
    {
        $sku = new Sku($this->uniqueSku());
        $stock = new Stock($sku);
        $stock->receive(5);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reservation = $this->handler()->reserveStock($sku, 2);
        $reservationId = $reservation->getId();
        $this->entityManager->clear();

        $storedReservation = $this->entityManager->find(Reservation::class, $reservationId);
        self::assertInstanceOf(Reservation::class, $storedReservation);
        self::assertSame('active', $storedReservation->getStatus());
        self::assertSame(2, $storedReservation->getQuantity());
        self::assertSame(2, $storedReservation->getStock()->getReserved());
        self::assertSame(3, $storedReservation->getStock()->getAvailable());

        $movements = $this->entityManager
            ->getRepository(StockMovement::class)
            ->findBy(['reservation' => $storedReservation]);

        self::assertCount(1, $movements);
        self::assertSame(StockMovement::TYPE_RESERVATION_CREATED, $movements[0]->getType());
        self::assertSame(2, $movements[0]->getQuantity());
    }

    public function testFailedReservationDoesNotPersistPartialChanges(): void
    {
        $sku = new Sku($this->uniqueSku());
        $stock = new Stock($sku);
        $stock->receive(1);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();
        $this->entityManager->clear();

        try {
            $this->handler()->reserveStock($sku, 2);
            self::fail('Expected InvalidReserve to be thrown.');
        } catch (InvalidReserve) {
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
                    'SELECT COUNT(*) FROM inventory_reservation r '
                    .'INNER JOIN inventory_stock s ON s.id = r.stock_id WHERE s.sku = :sku',
                    ['sku' => $sku->getSku()],
                ),
            );
            self::assertSame(
                0,
                (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM inventory_stock_movement m '
                    .'INNER JOIN inventory_stock s ON s.id = m.stock_id WHERE s.sku = :sku',
                    ['sku' => $sku->getSku()],
                ),
            );
        }
    }

    public function testConcurrentRequestsCannotReserveTheLastUnitTwice(): void
    {
        $sku = new Sku($this->uniqueSku());
        $stock = new Stock($sku);
        $stock->receive(1);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();
        $this->connection->commit();
        $this->entityManager->clear();

        $processes = [];
        $lockHeld = false;

        try {
            $this->connection->beginTransaction();
            $this->connection->fetchOne(
                'SELECT id FROM inventory_stock WHERE sku = :sku FOR UPDATE',
                ['sku' => $sku->getSku()],
            );
            $lockHeld = true;

            $workerPrefix = 'sf-rsv-'.substr(bin2hex(random_bytes(8)), 0, 12);
            $processes[] = $this->startWorker($sku->getSku(), $workerPrefix.'-1');
            $processes[] = $this->startWorker($sku->getSku(), $workerPrefix.'-2');

            $waitingWorkers = 0;
            $deadline = microtime(true) + 5.0;
            do {
                $waitingWorkers = (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM pg_stat_activity "
                    ."WHERE application_name LIKE :prefix AND wait_event_type = 'Lock'",
                    ['prefix' => $workerPrefix.'-%'],
                );

                if ($waitingWorkers === 2) {
                    break;
                }

                usleep(25_000);
            } while (microtime(true) < $deadline);

            self::assertSame(2, $waitingWorkers, 'Both worker connections should wait for the locked stock row.');

            $this->connection->commit();
            $lockHeld = false;

            $results = [
                $this->finishWorker($processes[0]),
                $this->finishWorker($processes[1]),
            ];
            sort($results);

            self::assertCount(2, $results);
            self::assertSame('REJECTED', $results[0]);
            self::assertStringStartsWith('RESERVED ', $results[1]);

            self::assertSame(
                1,
                (int) $this->connection->fetchOne(
                    'SELECT reserved FROM inventory_stock WHERE sku = :sku',
                    ['sku' => $sku->getSku()],
                ),
            );
            self::assertSame(
                1,
                (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM inventory_reservation r '
                    .'INNER JOIN inventory_stock s ON s.id = r.stock_id WHERE s.sku = :sku',
                    ['sku' => $sku->getSku()],
                ),
            );
            self::assertSame(
                1,
                (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM inventory_stock_movement m '
                    .'INNER JOIN inventory_stock s ON s.id = m.stock_id WHERE s.sku = :sku',
                    ['sku' => $sku->getSku()],
                ),
            );
        } finally {
            if ($lockHeld && $this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            foreach ($processes as $process) {
                $this->stopWorker($process);
            }

            $this->cleanupSku($sku);
        }
    }

    private function handler(): ReserveStockHandler
    {
        return new ReserveStockHandler(
            new DoctrineStockRepository($this->entityManager),
            new DoctrineReservationRepository($this->entityManager),
            new DoctrineStockMovementRepository($this->entityManager),
            new DoctrineTransactionManager($this->entityManager),
        );
    }

    private function uniqueSku(): string
    {
        return 'IT-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
    }

    /** @return array{resource, array{0: resource, 1: resource, 2: resource}} */
    private function startWorker(string $sku, string $applicationName): array
    {
        $workerScript = dirname(__DIR__, 4).'/Support/reserve_stock_worker.php';
        $applicationDirectory = dirname(__DIR__, 5);
        $process = proc_open(
            [PHP_BINARY, $workerScript, $sku, $applicationName],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $applicationDirectory,
        );

        if (!is_resource($process)) {
            self::fail('Could not start a PostgreSQL reservation worker.');
        }

        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 5);
        stream_set_timeout($pipes[2], 5);
        $ready = trim((string) fgets($pipes[1]));

        if ($ready !== 'READY') {
            $error = stream_get_contents($pipes[2]);
            $this->stopWorker([$process, $pipes]);
            self::fail('Reservation worker did not start: '.$error);
        }

        return [$process, $pipes];
    }

    /** @param array{resource, array{0: resource, 1: resource, 2: resource}} $worker */
    private function finishWorker(array $worker): string
    {
        [$process, $pipes] = $worker;
        $result = trim((string) fgets($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'Reservation worker failed: '.$error);

        return $result;
    }

    /** @param array{resource, array{0: resource, 1: resource, 2: resource}} $worker */
    private function stopWorker(array $worker): void
    {
        [$process, $pipes] = $worker;

        if (!is_resource($process)) {
            return;
        }

        foreach ([1, 2] as $pipeIndex) {
            if (isset($pipes[$pipeIndex]) && is_resource($pipes[$pipeIndex])) {
                fclose($pipes[$pipeIndex]);
            }
        }

        proc_terminate($process);
        proc_close($process);
    }

    private function cleanupSku(Sku $sku): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->connection->beginTransaction();
        $this->connection->executeStatement(
            'DELETE FROM inventory_stock_movement WHERE stock_id IN '
            .'(SELECT id FROM inventory_stock WHERE sku = :sku)',
            ['sku' => $sku->getSku()],
        );
        $this->connection->executeStatement(
            'DELETE FROM inventory_reservation WHERE stock_id IN '
            .'(SELECT id FROM inventory_stock WHERE sku = :sku)',
            ['sku' => $sku->getSku()],
        );
        $this->connection->executeStatement(
            'DELETE FROM inventory_stock WHERE sku = :sku',
            ['sku' => $sku->getSku()],
        );
        $this->connection->commit();
    }
}
