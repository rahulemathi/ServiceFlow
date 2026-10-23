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
        Schema::create('membership_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['membership_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_roles');
    }
};
