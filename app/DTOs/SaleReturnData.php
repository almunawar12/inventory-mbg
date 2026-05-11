<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class SaleReturnData
{
    /** @param SaleReturnItemData[] $items */
    public function __construct(
        public int $sale_id,
        public int $created_by,
        public Carbon $return_date,
        public array $items,
        public ?string $reason = null,
        public ?string $notes  = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sale_id:     (int) $data['sale_id'],
            created_by:  (int) $data['created_by'],
            return_date: isset($data['return_date']) ? Carbon::parse($data['return_date']) : Carbon::now(),
            items:       array_map(fn($i) => SaleReturnItemData::fromArray($i), $data['items']),
            reason:      $data['reason'] ?? null,
            notes:       $data['notes']  ?? null,
        );
    }
}
