<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidSku;
use App\Inventory\Domain\ValueObject\Sku;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SkuTest extends TestCase
{
    #[DataProvider('validSkuProvider')]
    public function testItStoresNormalizedValidSku(string $input, string $expected): void
    {
        $sku = new Sku($input);

        self::assertSame($expected, $sku->getSku());
    }

    public static function validSkuProvider(): iterable
    {
        yield 'uppercase remains unchanged' => ['ABC-123', 'ABC-123'];
        yield 'lowercase is normalized' => ['abc-123', 'ABC-123'];
        yield 'mixed case is normalized' => ['AbC-123', 'ABC-123'];
        yield 'minimum length' => ['A-1', 'A-1'];
        yield 'maximum length' => [str_repeat('a', 64), str_repeat('A', 64)];
        yield 'digits only' => ['123', '123'];
    }

    #[DataProvider('invalidSkuProvider')]
    public function testItRejectsInvalidSku(string $input): void
    {
        $this->expectException(InvalidSku::class);

        new Sku($input);
    }

    public static function invalidSkuProvider(): iterable
    {
        yield 'empty value' => [''];
        yield 'below minimum length' => ['AB'];
        yield 'above maximum length' => [str_repeat('A', 65)];
        yield 'underscore' => ['ABC_123'];
        yield 'leading space' => [' ABC-123'];
        yield 'trailing space' => ['ABC-123 '];
        yield 'internal space' => ['ABC 123'];
        yield 'trailing newline' => ["ABC-123\n"];
        yield 'non-ASCII letter' => ['ĄBC-123'];
        yield 'punctuation' => ['ABC/123'];
    }
}
