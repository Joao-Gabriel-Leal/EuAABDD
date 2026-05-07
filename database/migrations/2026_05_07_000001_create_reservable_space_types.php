<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservable_space_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('pin_color', 7)->default('#e5163d');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('reservable_spaces', function (Blueprint $table) {
            $table->foreignId('reservable_space_type_id')
                ->nullable()
                ->after('type')
                ->constrained()
                ->nullOnDelete();
        });

        $now = now();
        $types = [
            'churrasqueira' => ['Churrasqueira', '#e65a24'],
            'evento' => ['Salao de festa', '#d89b12'],
            'lazer' => ['Piscina', '#0ea5c6'],
            'quadra' => ['Quadra', '#12845b'],
        ];

        foreach ($types as $slug => [$name, $pinColor]) {
            $id = DB::table('reservable_space_types')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'pin_color' => $pinColor,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('reservable_spaces')
                ->where('type', $slug)
                ->update(['reservable_space_type_id' => $id]);
        }

        $fallbackId = DB::table('reservable_space_types')->where('slug', 'churrasqueira')->value('id');

        if ($fallbackId) {
            DB::table('reservable_spaces')
                ->whereNull('reservable_space_type_id')
                ->update(['reservable_space_type_id' => $fallbackId]);
        }
    }

    public function down(): void
    {
        Schema::table('reservable_spaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservable_space_type_id');
        });

        Schema::dropIfExists('reservable_space_types');
    }
};
