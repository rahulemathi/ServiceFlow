<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // financial, work_orders, technician_performance
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('file_path')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
