<?php

namespace App\DTOs;

readonly class SaleReturnItemData
{
    public function __construct(
        public int $sale_item_id,
        public int $quantity,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sale_item_id: (int) $data['sale_item_id'],
            quantity:     (int) $data['quantity'],
        );
    }
}
