<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('plan')->default('starter')->after('slug');
            $table->string('subscription_status')->default('trialing')->after('plan');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->timestamp('grace_period_ends_at')->nullable()->after('subscription_ends_at');
            $table->unsignedSmallInteger('max_users')->nullable()->default(5)->after('grace_period_ends_at');
            $table->unsignedSmallInteger('max_branches')->nullable()->default(1)->after('max_users');
            $table->unsignedInteger('max_monthly_work_orders')->nullable()->default(50)->after('max_branches');
        });

        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // support_impersonation, tenant_inspection, plan_override, subscription_change
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhooks');
        Schema::dropIfExists('platform_audit_logs');

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'plan',
                'subscription_status',
                'trial_ends_at',
                'subscription_ends_at',
                'grace_period_ends_at',
                'max_users',
                'max_branches',
                'max_monthly_work_orders',
            ]);
        });
    }
};
