<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidSku;

final readonly class Sku
{
    private const VALIDATION_REGEX = '/\A[A-Z0-9-]{3,64}\z/';

    private string $sku;

    public function __construct(string $sku)
    {
        $this->sku = strtoupper($sku);
        $this->validateRegex();
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    private function validateRegex(): void
    {
        if (!preg_match(self::VALIDATION_REGEX, $this->getSku())) {
            throw new InvalidSku();
        }
    }
}
