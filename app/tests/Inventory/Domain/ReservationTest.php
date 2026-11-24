<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidReservationQuantity;
use App\Inventory\Domain\Reservation;
use App\Inventory\Domain\Stock;
use App\Inventory\Domain\ValueObject\Sku;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservationTest extends TestCase
{
    public function testCreatingNewReservation(): void
    {
        $reservation = new Reservation(
            '00000000-0000-7000-8000-000000000001',
            $this->reservedStock('ABC123', 1),
            1,
        );

        self::assertSame('active', $reservation->getStatus());
        self::assertSame(1, $reservation->getQuantity());
        self::assertSame('ABC123', $reservation->getSku()->getSku());
    }

    public function testReleaseReservation(): void
    {
        $reservation = new Reservation(
            '00000000-0000-7000-8000-000000000001',
            $this->reservedStock('ABC123', 1),
            1,
        );

        self::assertSame(true, $reservation->release());
        self::assertSame(false, $reservation->release());
        self::assertSame(true, $reservation->isReleased());
    }

    #[DataProvider('invalidQuantityProvider')]
    public function testItRejectsInvalidQuantity(int $quantity): void
    {
        $this->expectException(InvalidReservationQuantity::class);

        new Reservation(
            '00000000-0000-7000-8000-000000000001',
            $this->reservedStock('ABC123', max(1, $quantity)),
            $quantity,
        );
    }

    public static function invalidQuantityProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    private function reservedStock(string $sku, int $quantity): Stock
    {
        $stock = new Stock(new Sku($sku));
        $stock->receive($quantity);
        $stock->reserve($quantity);

        return $stock;
    }
}
