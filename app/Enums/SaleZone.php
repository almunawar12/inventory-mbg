<?php

namespace App\Enums;

enum SaleZone: string
{
    case KOTA = 'kota';
    case UTARA = 'utara';
    case SELATAN = 'selatan';

    public function label(): string
    {
        return match ($this) {
            self::KOTA => 'Garut Kota',
            self::UTARA => 'Garut Utara',
            self::SELATAN => 'Garut Selatan',
        };
    }

    public function isFixedPrice(): bool
    {
        return $this === self::KOTA;
    }
}
