<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'email']);
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 80)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->string('commercial_condition', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            $table->index(['company_id', 'status', 'name']);
            $table->index(['company_id', 'tax_id']);
            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        // Restoring uniqueness must refuse conflicting real data, never delete it.
        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['company_id', 'email']);
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'status', 'name']);
            $table->dropIndex(['company_id', 'tax_id']);
            $table->dropIndex(['company_id', 'email']);
            $table->dropColumn(['legal_name', 'tax_id', 'phone', 'whatsapp', 'commercial_condition', 'notes', 'status']);
        });
    }
};
