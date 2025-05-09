<?php
declare(strict_types=1);

namespace App\Features\FeeCalculator;

use App\Enum\Currency;
use App\Enum\UserType;

interface FeeCalculatorInterface
{
    public function calculate(float $amount, Currency $currency, UserType $userType): float;
}