<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function move(
        Product $product,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?User $user = null,
        ?float $unitCost = null,
    ): StockMovement {
        if ($quantity < 0 || ($type !== 'adjustment' && $quantity <= 0)) {
            throw ValidationException::withMessages([
                'quantity' => 'Informe uma quantidade maior que zero.',
            ]);
        }

        if (! in_array($type, ['entry', 'exit', 'adjustment', 'loss'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de movimento invalido.',
            ]);
        }

        return DB::transaction(function () use ($product, $type, $quantity, $reason, $user, $unitCost) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $quantityBefore = $locked->quantity;

            $nextQuantity = match ($type) {
                'entry' => $locked->quantity + $quantity,
                'adjustment' => $quantity,
                'loss', 'exit' => $locked->quantity - $quantity,
            };

            if ($nextQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Saida maior que o saldo disponivel em estoque.',
                ]);
            }

            $currentCost = (float) $locked->unit_cost;
            $movementCost = $unitCost !== null && $unitCost > 0 ? $unitCost : $currentCost;
            $storedQuantity = $type === 'adjustment' ? abs($nextQuantity - $quantityBefore) : $quantity;
            $nextUnitCost = $currentCost;

            if ($type === 'entry' && $unitCost !== null && $unitCost > 0 && $nextQuantity > 0) {
                $nextUnitCost = (($quantityBefore * $currentCost) + ($quantity * $unitCost)) / $nextQuantity;
            }

            if ($type === 'adjustment' && $unitCost !== null && $unitCost > 0) {
                $nextUnitCost = $unitCost;
            }

            $locked->update([
                'quantity' => $nextQuantity,
                'unit_cost' => round($nextUnitCost, 2),
            ]);

            return StockMovement::create([
                'product_id' => $locked->id,
                'movement_code' => $this->nextMovementCode(),
                'type' => $type,
                'quantity' => $storedQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $nextQuantity,
                'unit_cost' => round($movementCost, 2),
                'total_cost' => round($storedQuantity * $movementCost, 2),
                'created_by_user_id' => $user?->id,
                'reason' => $reason,
            ]);
        });
    }

    private function nextMovementCode(): string
    {
        do {
            $code = 'MOV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (StockMovement::where('movement_code', $code)->exists());

        return $code;
    }
}
