<?php

namespace App\Services;

use App\Contracts\AabbPaymentGateway;

class ManualAabbPaymentGateway implements AabbPaymentGateway
{
    public function driver(): string
    {
        return (string) config('aabb_payments.driver', 'manual');
    }

    public function metadata(array $context = []): array
    {
        return [
            'driver' => $this->driver(),
            'status' => 'manual_review',
            'reference_prefix' => config('aabb_payments.manual_reference_prefix'),
            'allowed_methods' => config('aabb_payments.allowed_methods', []),
            'context' => $context,
        ];
    }
}
