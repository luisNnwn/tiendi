<?php

namespace App\Data;

class OrderValidationResult
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<int, array{product_id: int, quantity: int, unit: string, unit_price: string, subtotal: string}>  $validatedItems
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $validatedItems = [],
    ) {}

    public static function invalid(string ...$errors): self
    {
        return new self(valid: false, errors: $errors);
    }

    public static function success(array $validatedItems): self
    {
        return new self(valid: true, validatedItems: $validatedItems);
    }
}
