<?php

namespace App\Enums;

enum ExternalDebtDirection: string
{
    case BusinessOwes = 'business_owes';
    case PersonOwes = 'person_owes';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
