<?php

namespace App\Services;

use App\DTOs\SaleReturnData;
use App\Models\SaleReturn;

class SaleReturnService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
    ) {}

    public function createReturn(SaleReturnData $data): SaleReturn
    {
        throw new \LogicException('Not implemented');
    }
}
