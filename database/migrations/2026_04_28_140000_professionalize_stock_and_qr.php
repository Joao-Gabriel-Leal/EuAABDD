<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('id');
            $table->string('qr_token')->nullable()->unique()->after('sku');
            $table->text('description')->nullable()->after('unit');
            $table->string('location')->nullable()->after('description');
            $table->string('supplier')->nullable()->after('location');
            $table->decimal('unit_cost', 10, 2)->default(0)->after('supplier');
            $table->boolean('is_active')->default(true)->after('unit_cost');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('movement_code')->nullable()->unique()->after('id');
            $table->integer('quantity_before')->default(0)->after('quantity');
            $table->integer('quantity_after')->default(0)->after('quantity_before');
            $table->decimal('unit_cost', 10, 2)->default(0)->after('quantity_after');
            $table->decimal('total_cost', 10, 2)->default(0)->after('unit_cost');
            $table->foreignId('created_by_user_id')->nullable()->after('total_cost')->constrained('users')->nullOnDelete();
        });

        DB::table('products')
            ->orderBy('id')
            ->get(['id', 'category'])
            ->each(function (object $product, int $index): void {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'sku' => 'AABB-EST-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'qr_token' => (string) Str::uuid(),
                        'location' => $product->category ?: 'Almoxarifado',
                        'supplier' => 'Fornecedor demo',
                        'description' => 'Produto operacional cadastrado para o controle interno da AABB Brasilia.',
                    ]);
            });

        DB::table('stock_movements')
            ->orderBy('id')
            ->get(['id', 'quantity', 'type'])
            ->each(function (object $movement, int $index): void {
                $quantityAfter = $movement->type === 'entry' ? (int) $movement->quantity : 0;

                DB::table('stock_movements')
                    ->where('id', $movement->id)
                    ->update([
                        'movement_code' => 'MOV-'.now()->format('Ymd').'-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                        'quantity_before' => 0,
                        'quantity_after' => $quantityAfter,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn([
                'movement_code',
                'quantity_before',
                'quantity_after',
                'unit_cost',
                'total_cost',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sku',
                'qr_token',
                'description',
                'location',
                'supplier',
                'unit_cost',
                'is_active',
            ]);
        });
    }
};
