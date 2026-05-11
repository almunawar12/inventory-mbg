<?php

namespace App\Exceptions;

use Exception;

class SaleReturnException extends Exception
{
    public array $context = [];

    public static function invalidSaleStatus(string $status, array $ctx = []): self
    {
        $e = new self("Sale status '{$status}' is not eligible for return. Only COMPLETED sales can be returned.");
        $e->context = $ctx;
        return $e;
    }

    public static function saleItemMismatch(int $saleItemId, int $saleId): self
    {
        return new self("Sale item #{$saleItemId} does not belong to sale #{$saleId}.");
    }

    public static function exceedsAvailableQty(string $product, int $requested, int $available): self
    {
        return new self("Cannot return {$requested} of '{$product}'. Only {$available} remaining to return.");
    }

    public static function productNotFound(int $id): self
    {
        return new self("Product #{$id} not found.");
    }

    public static function cannotReverseStock(string $product, int $needed, int $available): self
    {
        return new self("Cannot reverse return: product '{$product}' has only {$available} in stock but {$needed} needed.");
    }

    public static function creationFailed(string $msg, array $ctx = []): self
    {
        $e = new self("Failed to create sale return: {$msg}");
        $e->context = $ctx;
        return $e;
    }
}
