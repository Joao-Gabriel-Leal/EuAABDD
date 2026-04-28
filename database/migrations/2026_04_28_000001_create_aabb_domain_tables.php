<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('segment');
            $table->decimal('monthly_family', 10, 2)->nullable();
            $table->decimal('monthly_individual', 10, 2)->nullable();
            $table->decimal('monthly_under_30', 10, 2)->nullable();
            $table->decimal('monthly_special', 10, 2)->nullable();
            $table->unsignedInteger('included_guests')->default(4);
            $table->unsignedInteger('included_dependents')->default(2);
            $table->decimal('extra_guest_price', 10, 2)->default(28);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('membership_code')->unique();
            $table->string('name');
            $table->string('cpf')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->string('category')->default('Familiar');
            $table->date('joined_at')->nullable();
            $table->string('photo_url')->nullable();
            $table->json('address')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
            $table->foreignId('member_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('relationship')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('cpf')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('documentable');
            $table->string('type');
            $table->string('name');
            $table->string('path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('charge_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('extra');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('type')->default('monthly');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method');
            $table->string('status')->default('paid');
            $table->string('transaction_code')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reservable_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('churrasqueira');
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->default(20);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservable_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('reservation_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('status')->default('pending_payment');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf')->nullable();
            $table->boolean('is_extra')->default(false);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('invited');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('valid_for');
            $table->string('status')->default('available');
            $table->boolean('is_extra')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Insumos');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('minimum_quantity')->default(0);
            $table->string('unit')->default('un');
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dependent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('person_name');
            $table->string('person_type');
            $table->string('gate')->default('Portaria principal');
            $table->string('status')->default('allowed');
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('entry_date');
            $table->string('status')->default('confirmed');
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('Comunicado');
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->string('image_url')->nullable();
            $table->date('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('benefits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('Clube');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'benefits',
            'announcements',
            'cash_entries',
            'access_logs',
            'stock_movements',
            'products',
            'invitations',
            'guests',
            'reservations',
            'reservable_spaces',
            'payments',
            'invoices',
            'charge_items',
            'documents',
            'proposals',
            'dependents',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
            $table->dropColumn('role');
        });

        Schema::dropIfExists('members');
        Schema::dropIfExists('plans');
    }
};
