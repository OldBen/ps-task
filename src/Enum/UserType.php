<?php
declare(strict_types=1);

namespace App\Enum;

enum UserType: string
{
    case PRIVATE = 'private';
    case BUSINESS = 'business';
}