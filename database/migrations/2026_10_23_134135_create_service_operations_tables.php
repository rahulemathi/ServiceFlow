<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'name']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('label')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('India');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
        });

        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('customer_address_id')->nullable();
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('installed_at')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'serial_number']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_address_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_addresses')
                ->restrictOnDelete();
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('customer_address_id')->nullable();
            $table->foreignId('equipment_id')->nullable();
            $table->string('request_number');
            $table->string('title');
            $table->text('description');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'request_number']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_address_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_addresses')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'equipment_id'])
                ->references(['organization_id', 'id'])
                ->on('equipment')
                ->restrictOnDelete();
        });

        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('service_request_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('customer_address_id')->nullable();
            $table->foreignId('equipment_id')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('work_order_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'service_request_id']);
            $table->unique(['organization_id', 'work_order_number']);
            $table->index(['organization_id', 'status', 'scheduled_start_at']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'service_request_id'])
                ->references(['organization_id', 'id'])
                ->on('service_requests')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_address_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_addresses')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'equipment_id'])
                ->references(['organization_id', 'id'])
                ->on('equipment')
                ->restrictOnDelete();
        });

        Schema::create('work_order_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->unsignedInteger('minutes')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'work_order_id'])
                ->references(['organization_id', 'id'])
                ->on('work_orders')
                ->cascadeOnDelete();
        });

        Schema::create('maintenance_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('equipment_id')->nullable();
            $table->string('contract_number');
            $table->string('name');
            $table->string('frequency');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('next_service_at')->nullable();
            $table->string('status')->default('active');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'contract_number']);
            $table->index(['organization_id', 'status', 'next_service_at']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'equipment_id'])
                ->references(['organization_id', 'id'])
                ->on('equipment')
                ->restrictOnDelete();
        });

        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('work_order_id')->nullable();
            $table->string('estimate_number');
            $table->string('status')->default('draft');
            $table->date('expires_at')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'estimate_number']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'work_order_id'])
                ->references(['organization_id', 'id'])
                ->on('work_orders')
                ->restrictOnDelete();
        });

        Schema::create('estimate_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estimate_id');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign(['organization_id', 'estimate_id'])
                ->references(['organization_id', 'id'])
                ->on('estimates')
                ->cascadeOnDelete();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('work_order_id')->nullable();
            $table->foreignId('estimate_id')->nullable();
            $table->string('invoice_number');
            $table->string('status')->default('draft');
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'invoice_number']);
            $table->unique(['organization_id', 'estimate_id']);
            $table->index(['organization_id', 'status', 'due_at']);
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'work_order_id'])
                ->references(['organization_id', 'id'])
                ->on('work_orders')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'estimate_id'])
                ->references(['organization_id', 'id'])
                ->on('estimates')
                ->restrictOnDelete();
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])
                ->on('invoices')
                ->cascadeOnDelete();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('invoice_id');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('received_at');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])
                ->on('invoices')
                ->restrictOnDelete();
        });

        Schema::create('technician_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'day_of_week']);
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])
                ->on('memberships')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_availabilities');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('maintenance_contracts');
        Schema::dropIfExists('work_order_entries');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
