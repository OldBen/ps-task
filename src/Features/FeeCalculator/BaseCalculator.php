<?php
declare(strict_types=1);

namespace App\Features\FeeCalculator;

use App\Enum\Currency;
use App\Enum\UserType;

class BaseCalculator implements FeeCalculatorInterface
{
    protected const FEE_PERCENTAGE = 0.003;

    public function calculate(float $amount, Currency $currency, UserType $userType): float
    {
        return ceil($amount * static::FEE_PERCENTAGE);
    }
}