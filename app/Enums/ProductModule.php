<?php

namespace App\Enums;

enum ProductModule: string
{
    case Tea = 'tea';
    case Rice = 'rice';
    case Detergent = 'detergent';
    case RawMaterial = 'raw_material';
    case FactoryMaterial = 'factory_material';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
