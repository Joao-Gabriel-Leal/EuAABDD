<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'Carvao premium' => [26.90, 'Almoxarifado das churrasqueiras', 'Distribuidora Brasilia Grill'],
            'Pulseiras de convidados' => [0.42, 'Guarita principal', 'Grafica credenciada'],
            'Produtos de limpeza piscina' => [38.50, 'Casa de bombas', 'Manutencao piscinas DF'],
            'Agua mineral 500ml' => [1.35, 'Deposito eventos', 'Bebidas Planalto'],
        ];

        foreach ($defaults as $name => [$cost, $location, $supplier]) {
            DB::table('products')
                ->where('name', $name)
                ->where(function ($query) {
                    $query->whereNull('unit_cost')->orWhere('unit_cost', 0);
                })
                ->update([
                    'unit_cost' => $cost,
                    'location' => $location,
                    'supplier' => $supplier,
                    'description' => 'Item de uso operacional do clube com controle por QR Code, custo e auditoria.',
                ]);
        }

        DB::table('stock_movements')
            ->where('type', 'entry')
            ->where('total_cost', 0)
            ->orderBy('id')
            ->get(['id', 'product_id', 'quantity'])
            ->each(function (object $movement): void {
                $unitCost = (float) DB::table('products')
                    ->where('id', $movement->product_id)
                    ->value('unit_cost');

                DB::table('stock_movements')
                    ->where('id', $movement->id)
                    ->update([
                        'unit_cost' => $unitCost,
                        'total_cost' => $unitCost * (int) $movement->quantity,
                    ]);
            });
    }

    public function down(): void
    {
        // Dados de backfill do demo nao precisam ser revertidos.
    }
};
