<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('membership_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('membership_id');
            $table->foreignId('branch_id');
            $table->timestamps();

            $table->unique(['membership_id', 'branch_id']);
            $table->index(['organization_id', 'membership_id']);
            $table->index(['organization_id', 'branch_id']);

            $table->foreign(['organization_id', 'membership_id'])
                ->references(['organization_id', 'id'])
                ->on('memberships')
                ->cascadeOnDelete();

            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_branches');
    }
};
