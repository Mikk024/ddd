<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidAdjustment;
use App\Inventory\Domain\Exception\InvalidReceiptQuantity;
use App\Inventory\Domain\Exception\InvalidReleaseReservation;
use App\Inventory\Domain\Exception\InvalidReserve;
use App\Inventory\Domain\ValueObject\Sku;

final class Stock
{
    private ?int $id = null;

    private Sku $sku;

    private int $reserved;

    private int $onHand;

    public function __construct(Sku $sku)
    {
        $this->sku = $sku;
        $this->reserved = 0;
        $this->onHand = 0;
    }

    public function getAvailable(): int
    {
        return $this->onHand - $this->reserved;
    }

    public function getSku(): Sku
    {
        return $this->sku;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function receive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidReceiptQuantity();
        }

        $this->onHand += $quantity;
    }

    public function getOnHand(): int
    {
        return $this->onHand;
    }

    public function getReserved(): int
    {
        return $this->reserved;
    }

    public function adjust(int $delta, string $reason): void
    {
        $newOnHand = $this->onHand + $delta;

        if ($delta === 0 || trim($reason) === '' || $newOnHand < $this->reserved) {
            throw new InvalidAdjustment();
        }

        $this->onHand = $newOnHand;
    }

    public function reserve(int $quantity): void
    {
        if ($quantity <= 0 || $quantity > $this->getAvailable()) {
            throw new InvalidReserve();
        }

        $this->reserved += $quantity;
    }

    public function releaseReservation(int $quantity): void
    {
        if ($quantity <= 0 || $quantity > $this->reserved) {
            throw new InvalidReleaseReservation();
        }

        $this->reserved -= $quantity;
    }
}
