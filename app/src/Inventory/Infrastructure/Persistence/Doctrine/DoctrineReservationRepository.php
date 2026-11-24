<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Exception\ReservationNotFound;
use App\Inventory\Domain\Repository\ReservationRepositoryInterface;
use App\Inventory\Domain\Reservation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function add(Reservation $reservation): void
    {
        $this->entityManager->persist($reservation);
    }

    public function getByIdWithStockForUpdate(string $id): Reservation
    {
        $reservation = $this->entityManager->createQueryBuilder()
            ->select('reservation', 'stock')
            ->from(Reservation::class, 'reservation')
            ->join('reservation.stock', 'stock')
            ->where('reservation.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (!$reservation instanceof Reservation) {
            throw new ReservationNotFound($id);
        }

        return $reservation;
    }
}
