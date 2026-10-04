<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('status', 16)->default('pending');
            $table->text('note')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->text('outcome_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('previous_follow_up_id')->nullable();
            // MySQL has no partial unique indexes. Pending rows mirror quote_id
            // here; terminal rows set it to null, which permits their history.
            $table->unsignedBigInteger('pending_quote_id')->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'scheduled_at']);
            $table->index(['company_id', 'assigned_to', 'scheduled_at']);
            $table->foreign(['company_id', 'quote_id'])->references(['company_id', 'id'])->on('quotes')->cascadeOnDelete();
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign('previous_follow_up_id')->references('id')->on('follow_ups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
