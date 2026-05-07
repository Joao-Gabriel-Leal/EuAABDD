<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('contact_channel')->nullable()->after('phone');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('sent_to_phone')->nullable()->after('sent_to_email');
            $table->string('delivery_channel')->nullable()->after('sent_to_phone');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['sent_to_phone', 'delivery_channel']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['phone', 'contact_channel']);
        });
    }
};
