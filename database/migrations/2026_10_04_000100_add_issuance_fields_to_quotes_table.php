<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('company_name_snapshot')->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('sent_at');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->timestamp('expired_at')->nullable()->after('rejected_at');
            $table->text('rejection_reason')->nullable()->after('expired_at');
            $table->index(['company_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'status', 'expires_at']);
            $table->dropColumn([
                'company_name_snapshot', 'sent_at', 'accepted_at', 'rejected_at', 'expired_at', 'rejection_reason',
            ]);
        });
    }
};
