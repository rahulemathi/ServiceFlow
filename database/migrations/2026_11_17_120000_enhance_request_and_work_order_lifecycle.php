<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->text('status_reason')->nullable()->after('status');
            $table->text('cancellation_reason')->nullable()->after('status_reason');
            $table->timestamp('reviewed_at')->nullable()->after('cancellation_reason');
            $table->timestamp('cancelled_at')->nullable()->after('reviewed_at');
        });

        Schema::create('service_request_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->foreign(['organization_id', 'service_request_id'])
                ->references(['organization_id', 'id'])
                ->on('service_requests')
                ->cascadeOnDelete();
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->text('status_reason')->nullable()->after('status');
            $table->string('customer_signature_name')->nullable()->after('completion_notes');
            $table->timestamp('customer_signed_at')->nullable()->after('customer_signature_name');
            $table->foreignId('supervisor_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('customer_signed_at');
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->text('supervisor_signature_notes')->nullable()->after('supervisor_approved_at');
        });

        Schema::create('work_order_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id');
            $table->string('item_text');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->foreign(['organization_id', 'work_order_id'])
                ->references(['organization_id', 'id'])
                ->on('work_orders')
                ->cascadeOnDelete();
        });

        Schema::create('work_order_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->foreign(['organization_id', 'work_order_id'])
                ->references(['organization_id', 'id'])
                ->on('work_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_status_logs');
        Schema::dropIfExists('work_order_checklists');

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropForeign(['supervisor_approved_by']);
            $table->dropColumn([
                'status_reason',
                'customer_signature_name',
                'customer_signed_at',
                'supervisor_approved_by',
                'supervisor_approved_at',
                'supervisor_signature_notes',
            ]);
        });

        Schema::dropIfExists('service_request_comments');

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['status_reason', 'cancellation_reason', 'reviewed_at', 'cancelled_at']);
        });
    }
};
