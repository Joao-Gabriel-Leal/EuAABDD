<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('card_token')->nullable()->unique()->after('photo_url');
            $table->timestamp('card_issued_at')->nullable()->after('card_token');
            $table->timestamp('card_revoked_at')->nullable()->after('card_issued_at');
        });

        DB::table('members')
            ->whereNull('card_token')
            ->orderBy('id')
            ->select(['id'])
            ->each(function (object $member): void {
                DB::table('members')
                    ->where('id', $member->id)
                    ->update([
                        'card_token' => (string) Str::uuid(),
                        'card_issued_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['card_token', 'card_issued_at', 'card_revoked_at']);
        });
    }
};
