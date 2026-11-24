<?php

declare(strict_types=1);

use App\Inventory\Application\ReserveStockHandler;
use App\Inventory\Domain\Exception\InvalidReserve;
use App\Inventory\Domain\ValueObject\Sku;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineReservationRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockMovementRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineTransactionManager;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$kernel = new App\Kernel('test', false);
$kernel->boot();

try {
    $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
    $entityManager->getConnection()->executeQuery(
        'SELECT set_config(:name, :value, false)',
        ['name' => 'application_name', 'value' => $argv[2]],
    );

    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);

    $handler = new ReserveStockHandler(
        new DoctrineStockRepository($entityManager),
        new DoctrineReservationRepository($entityManager),
        new DoctrineStockMovementRepository($entityManager),
        new DoctrineTransactionManager($entityManager),
    );

    try {
        $reservation = $handler->reserveStock(new Sku($argv[1]), 1);
        fwrite(STDOUT, 'RESERVED '.$reservation->getId()."\n");
    } catch (InvalidReserve) {
        fwrite(STDOUT, "REJECTED\n");
    }

    fflush(STDOUT);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
} finally {
    $kernel->shutdown();
}
