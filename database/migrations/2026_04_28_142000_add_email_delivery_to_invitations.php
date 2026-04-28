<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('email')->nullable()->after('cpf');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('sent_to_email')->nullable()->after('code');
            $table->timestamp('emailed_at')->nullable()->after('sent_to_email');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['sent_to_email', 'emailed_at']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
