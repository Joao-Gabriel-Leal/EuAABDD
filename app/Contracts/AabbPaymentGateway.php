<?php

namespace App\Contracts;

interface AabbPaymentGateway
{
    public function driver(): string;

    public function metadata(array $context = []): array;
}
