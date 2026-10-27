<?php

namespace App\Exceptions\Marketplace;

use Exception;

class InvalidPromoCodeException extends Exception
{
    public function __construct(
        public readonly ?string $promoCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
