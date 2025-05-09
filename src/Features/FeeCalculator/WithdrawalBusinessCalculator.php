<?php
declare(strict_types=1);

namespace App\Features\FeeCalculator;

use App\Enum\Currency;
use App\Enum\UserType;

class WithdrawalBusinessCalculator extends BaseCalculator
{
    protected const FEE_PERCENTAGE = 0.005;
}