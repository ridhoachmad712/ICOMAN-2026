<?php

namespace App\Services;

use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransGateway
{
    public function create(array $parameters): object
    {
        return Snap::createTransaction($parameters);
    }

    public function status(string $orderId): array
    {
        return (array) Transaction::status($orderId);
    }
}
