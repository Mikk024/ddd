<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use DateTimeImmutable;

final class StockMovement
{
    public const TYPE_RESERVATION_RELEASED = 'reservation_released';

    public const TYPE_RESERVATION_CREATED = 'reservation_created';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ADJUSTMENT = 'adjustment';

    private ?int $id = null;

    private Stock $stock;

    private string $type;

    private int $quantity;

    private DateTimeImmutable $occurredAt;

    private ?Reservation $reservation;

    private ?string $reason;

    public static function reservationReleased(Stock $stock, Reservation $reservation): self
    {
        return new self(
            $stock,
            self::TYPE_RESERVATION_RELEASED,
            $reservation->getQuantity(),
            new DateTimeImmutable(),
            $reservation,
        );
    }

    public static function reservationCreated(Stock $stock, Reservation $reservation): self
    {
        return new self(
            $stock,
            self::TYPE_RESERVATION_CREATED,
            $reservation->getQuantity(),
            new DateTimeImmutable(),
            $reservation,
        );
    }

    public static function receipt(Stock $stock, int $quantity): self
    {
        return new self($stock, self::TYPE_RECEIPT, $quantity, new DateTimeImmutable());
    }

    public static function adjustment(Stock $stock, int $delta, string $reason): self
    {
        return new self($stock, self::TYPE_ADJUSTMENT, $delta, new DateTimeImmutable(), reason: $reason);
    }

    public function getStock(): Stock
    {
        return $this->stock;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getReservationId(): ?string
    {
        return $this->reservation?->getId();
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    private function __construct(
        Stock $stock,
        string $type,
        int $quantity,
        DateTimeImmutable $occurredAt,
        ?Reservation $reservation = null,
        ?string $reason = null,
    ) {
        $this->stock = $stock;
        $this->type = $type;
        $this->quantity = $quantity;
        $this->occurredAt = $occurredAt;
        $this->reservation = $reservation;
        $this->reason = $reason;
    }
}
