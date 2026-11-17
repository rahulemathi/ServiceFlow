<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // work_order_assigned, work_order_updated, maintenance_due
            $table->string('channel'); // mail, database
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'event_type', 'channel'], 'notif_pref_org_user_event_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
