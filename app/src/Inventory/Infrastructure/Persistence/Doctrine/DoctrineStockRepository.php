<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Exception\StockNotFound;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use App\Inventory\Domain\Stock;
use App\Inventory\Domain\ValueObject\Sku;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineStockRepository implements StockRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function add(Stock $stock): void
    {
        $this->entityManager->persist($stock);
    }

    public function getBySku(Sku $sku): Stock
    {
        $stock = $this->entityManager->createQueryBuilder()
            ->select('stock')
            ->from(Stock::class, 'stock')
            ->where('stock.sku.sku = :sku')
            ->setParameter('sku', $sku->getSku())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$stock instanceof Stock) {
            throw new StockNotFound($sku->getSku());
        }

        return $stock;
    }

    public function getBySkuForUpdate(Sku $sku): Stock
    {
        $stock = $this->entityManager->createQueryBuilder()
            ->select('stock')
            ->from(Stock::class, 'stock')
            ->where('stock.sku.sku = :sku')
            ->setParameter('sku', $sku->getSku())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (!$stock instanceof Stock) {
            throw new StockNotFound($sku->getSku());
        }

        return $stock;
    }
}
