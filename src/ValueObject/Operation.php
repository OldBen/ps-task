<?php
declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\Currency;
use App\Enum\OperationType; 
use App\Enum\UserType;

class Operation
{
    public function __construct(
        readonly private DateTimeImmutable $operationDate,
        readonly private int $userId,
        readonly private UserType $userType,
        readonly private OperationType $operationType,
        readonly private float $operationAmount,
        readonly private Currency $operationCurrency
    ) {
    }

    public function getOperationDate(): DateTimeImmutable
    {
        return $this->operationDate;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserType(): UserType
    {
        return $this->userType;
    }

    public function getOperationType(): OperationType
    {
        return $this->operationType;
    }

    public function getOperationAmount(): float
    {
        return $this->operationAmount;
    }

    public function getOperationCurrency(): Currency
    {
        return $this->operationCurrency;
    }
}