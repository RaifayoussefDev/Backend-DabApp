<?php

namespace App\Exceptions\Marketplace;

use Exception;

/**
 * Thrown inside the checkout DB transaction when a locked product/variant row no longer
 * has enough stock for the quantity in the cart — rolls the transaction back cleanly.
 */
class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly ?int $productId,
        public readonly ?int $variantId,
        public readonly string $label,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Not enough stock for \"{$label}\": requested {$requested}, available {$available}");
    }
}
