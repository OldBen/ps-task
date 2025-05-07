<?php
declare(strict_types=1);

namespace App\Enum;

enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case JPY = 'JPY';
}