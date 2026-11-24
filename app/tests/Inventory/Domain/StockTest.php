<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidReceiptQuantity;
use App\Inventory\Domain\Exception\InvalidAdjustment;
use App\Inventory\Domain\Exception\InvalidReleaseReservation;
use App\Inventory\Domain\Exception\InvalidReserve;
use App\Inventory\Domain\Stock;
use App\Inventory\Domain\ValueObject\Sku;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StockTest extends TestCase
{
    public function testNewStockHasZeroQuantities(): void
    {
        $stock = new Stock(new Sku('ABC-123'));

        self::assertSame(0, $stock->getOnHand());
        self::assertSame(0, $stock->getReserved());
        self::assertSame(0, $stock->getAvailable());
    }

    public function testReceivingStockIncreasesOnHandAndAvailableQuantities(): void
    {
        $stock = new Stock(new Sku('ABC-123'));

        $stock->receive(5);

        self::assertSame(5, $stock->getOnHand());
        self::assertSame(0, $stock->getReserved());
        self::assertSame(5, $stock->getAvailable());
    }

    public function testReceivingStockMultipleTimesAddsTheQuantities(): void
    {
        $stock = new Stock(new Sku('ABC-123'));

        $stock->receive(5);
        $stock->receive(3);

        self::assertSame(8, $stock->getOnHand());
        self::assertSame(0, $stock->getReserved());
        self::assertSame(8, $stock->getAvailable());
    }

    #[DataProvider('invalidReceiptQuantityProvider')]
    public function testReceivingInvalidQuantityThrowsAndLeavesStockUnchanged(int $quantity): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        $stock->receive(5);

        try {
            $stock->receive($quantity);
            self::fail('Expected InvalidReceiptQuantity to be thrown.');
        } catch (InvalidReceiptQuantity) {
            self::assertSame(5, $stock->getOnHand());
            self::assertSame(0, $stock->getReserved());
            self::assertSame(5, $stock->getAvailable());
        }
    }

    public static function invalidReceiptQuantityProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative quantity' => [-1];
    }

    #[DataProvider('validAdjustmentProvider')]
    public function testAdjustmentAppliesSignedDelta(
        int $initialOnHand,
        int $delta,
        int $expectedOnHand,
    ): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        if ($initialOnHand > 0) {
            $stock->receive($initialOnHand);
        }

        $stock->adjust($delta, 'Stock count correction');

        self::assertSame($expectedOnHand, $stock->getOnHand());
        self::assertSame(0, $stock->getReserved());
        self::assertSame($expectedOnHand, $stock->getAvailable());
    }

    public static function validAdjustmentProvider(): iterable
    {
        yield 'positive delta from zero stock' => [0, 3, 3];
        yield 'positive delta with existing stock' => [5, 3, 8];
        yield 'negative delta' => [5, -2, 3];
    }

    #[DataProvider('invalidAdjustmentProvider')]
    public function testInvalidAdjustmentThrowsAndLeavesStockUnchanged(
        int $initialOnHand,
        int $delta,
        string $reason,
    ): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        if ($initialOnHand > 0) {
            $stock->receive($initialOnHand);
        }

        try {
            $stock->adjust($delta, $reason);
            self::fail('Expected InvalidAdjustment to be thrown.');
        } catch (InvalidAdjustment) {
            self::assertSame($initialOnHand, $stock->getOnHand());
            self::assertSame(0, $stock->getReserved());
            self::assertSame($initialOnHand, $stock->getAvailable());
        }
    }

    public static function invalidAdjustmentProvider(): iterable
    {
        yield 'zero delta' => [0, 0, 'Stock count correction'];
        yield 'empty reason' => [0, 1, ''];
        yield 'whitespace-only reason' => [0, 1, " \t\n "];
        yield 'negative result' => [5, -6, 'Damaged goods'];
    }

    public static function invalidReserveProvider(): iterable
    {
        yield 'zero quantity' => [5, 0];
        yield 'negative quantity' => [5, -1];
        yield 'more than available' => [5, 6];
    }

    public function testReservationReducesAvailableWithoutChangingOnHand(): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        $stock->receive(5);

        $stock->reserve(2);

        self::assertSame(5, $stock->getOnHand());
        self::assertSame(2, $stock->getReserved());
        self::assertSame(3, $stock->getAvailable());
    }

    public function testMultipleReservationsAccumulateAgainstRemainingAvailability(): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        $stock->receive(10);

        $stock->reserve(4);
        $stock->reserve(3);

        self::assertSame(10, $stock->getOnHand());
        self::assertSame(7, $stock->getReserved());
        self::assertSame(3, $stock->getAvailable());
    }

    #[DataProvider('invalidReserveProvider')]
    public function testInvalidReserveThrowsAndLeavesStockUnchanged(
        int $initialOnHand,
        int $quantity,
    ): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        if ($initialOnHand > 0) {
            $stock->receive($initialOnHand);
        }

        try {
            $stock->reserve($quantity);
            self::fail('Expected InvalidReserve to be thrown.');
        } catch (InvalidReserve) {
            self::assertSame($initialOnHand, $stock->getOnHand());
            self::assertSame(0, $stock->getReserved());
            self::assertSame($initialOnHand, $stock->getAvailable());
        }
    }

        public static function invalidReleaseReservationProvider(): iterable
    {
        yield 'zero quantity' => [0];
        yield 'negative quantity' => [-1];
        yield 'more than reserved' => [3];
    }

    #[DataProvider('invalidReleaseReservationProvider')]
    public function testInvalidReleaseReservationThrowsAndLeavesStockUnchanged(int $quantity): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        $stock->receive(5);
        $stock->reserve(2);

        try {
            $stock->releaseReservation($quantity);
            self::fail('Expected InvalidReleaseReservation to be thrown.');
        } catch (InvalidReleaseReservation) {
            self::assertSame(5, $stock->getOnHand());
            self::assertSame(2, $stock->getReserved());
            self::assertSame(3, $stock->getAvailable());
        }
    }

    public function testReleaseReservationRestoresAvailabilityWithoutChangingOnHand(): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        $stock->receive(5);

        $stock->reserve(2);
        $stock->releaseReservation(2);

        self::assertSame(5, $stock->getOnHand());
        self::assertSame(0, $stock->getReserved());
        self::assertSame(5, $stock->getAvailable());
    }

    public function testReleasingMultipleReservationsRestoresAllAvailability(): void
    {
        $stock = new Stock(new Sku('ABC-123'));
        $stock->receive(10);

        $stock->reserve(4);
        $stock->reserve(3);
        $stock->releaseReservation(4);
        $stock->releaseReservation(3);

        self::assertSame(10, $stock->getOnHand());
        self::assertSame(0, $stock->getReserved());
        self::assertSame(10, $stock->getAvailable());
    }
}
