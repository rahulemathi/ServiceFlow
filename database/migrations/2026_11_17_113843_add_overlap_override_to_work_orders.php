<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->boolean('has_overlap_override')->default(false)->after('supervisor_signature_notes');
            $table->string('overlap_override_reason')->nullable()->after('has_overlap_override');
            $table->foreignId('overlap_override_by_id')->nullable()->constrained('users')->nullOnDelete()->after('overlap_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropForeign(['overlap_override_by_id']);
            $table->dropColumn([
                'has_overlap_override',
                'overlap_override_reason',
                'overlap_override_by_id',
            ]);
        });
    }
};
