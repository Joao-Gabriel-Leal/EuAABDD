<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('monthly_due_day')->default(8)->after('extra_guest_price');
            $table->decimal('dependent_extra_price', 10, 2)->default(0)->after('monthly_due_day');
            $table->decimal('annual_discount_percent', 5, 2)->default(0)->after('dependent_extra_price');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_due_day')->nullable()->after('category');
            $table->string('membership_type')->default('associate')->after('billing_due_day');
            $table->text('notes')->nullable()->after('address');
            $table->timestamp('imported_at')->nullable()->after('notes');
            $table->date('cancelled_at')->nullable()->after('joined_at');
        });

        Schema::table('dependents', function (Blueprint $table) {
            $table->boolean('is_free')->default(true)->after('relationship');
            $table->decimal('monthly_fee', 10, 2)->default(0)->after('is_free');
            $table->string('access_status')->default('allowed')->after('status');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('converted_member_id')->nullable()->after('status')->constrained('members')->nullOnDelete();
            $table->string('signature_status')->default('pending')->after('converted_member_id');
            $table->timestamp('approved_at')->nullable()->after('signature_status');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('signed_at')->nullable()->after('rejected_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('billing_month')->nullable()->after('type');
            $table->string('source_type')->nullable()->after('metadata');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->timestamp('issued_at')->nullable()->after('paid_at');
            $table->timestamp('cancelled_at')->nullable()->after('issued_at');
            $table->string('payment_proof_path')->nullable()->after('payment_method');
            $table->string('manual_reference')->nullable()->after('payment_proof_path');
            $table->foreignId('confirmed_by_user_id')->nullable()->after('manual_reference')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('confirmed_by_user_id');

            $table->unique(['member_id', 'type', 'billing_month'], 'invoices_member_type_billing_month_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('confirmed_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('proof_path')->nullable()->after('transaction_code');
            $table->text('notes')->nullable()->after('proof_path');
            $table->timestamp('received_at')->nullable()->after('paid_at');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('guest_quota')->default(4)->after('total_amount');
            $table->timestamp('confirmed_at')->nullable()->after('guest_quota');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->string('cancelled_reason')->nullable()->after('cancelled_at');

            $table->index(['reservable_space_id', 'reservation_date', 'status']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->string('invitation_code')->nullable()->after('status');
            $table->timestamp('checked_in_at')->nullable()->after('invitation_code');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('type')->default('club_access')->after('invoice_id');
            $table->string('code')->nullable()->unique()->after('type');
            $table->decimal('amount', 10, 2)->default(0)->after('is_extra');
            $table->timestamp('used_at')->nullable()->after('amount');
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('invitation_id')->nullable()->after('guest_id')->constrained()->nullOnDelete();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('members');
            $table->string('filename');
            $table->string('status')->default('processing');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status');
            $table->string('identifier')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');

        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invitation_id');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['type', 'code', 'amount', 'used_at']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['invitation_code', 'checked_in_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['reservable_space_id', 'reservation_date', 'status']);
            $table->dropColumn(['guest_quota', 'confirmed_at', 'cancelled_at', 'cancelled_reason']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by_user_id');
            $table->dropColumn(['proof_path', 'notes', 'received_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_member_type_billing_month_unique');
            $table->dropConstrainedForeignId('confirmed_by_user_id');
            $table->dropColumn([
                'billing_month',
                'source_type',
                'source_id',
                'issued_at',
                'cancelled_at',
                'payment_proof_path',
                'manual_reference',
                'reviewed_at',
            ]);
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_member_id');
            $table->dropColumn(['signature_status', 'approved_at', 'rejected_at', 'signed_at']);
        });

        Schema::table('dependents', function (Blueprint $table) {
            $table->dropColumn(['is_free', 'monthly_fee', 'access_status']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['billing_due_day', 'membership_type', 'notes', 'imported_at', 'cancelled_at']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_due_day', 'dependent_extra_price', 'annual_discount_percent']);
        });
    }
};
