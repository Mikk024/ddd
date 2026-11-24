<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidReservationQuantity;
use App\Inventory\Domain\ValueObject\Sku;

final class Reservation
{
    private string $id;

    private Stock $stock;

    private int $quantity;

    private string $status;

    public function __construct(
        string $id,
        Stock $stock,
        int $quantity,
    ) {
        $this->validateQuantity($quantity);
        $this->id = $id;
        $this->quantity = $quantity;
        $this->stock = $stock;
        $this->status = 'active';
    }

    public function release(): bool
    {
        if ($this->status === 'released') {
            return false;
        }

        $this->status = 'released';

        return true;
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSku(): Sku
    {
        return $this->stock->getSku();
    }

    public function getStock(): Stock
    {
        return $this->stock;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidReservationQuantity();
        }
    }
}
