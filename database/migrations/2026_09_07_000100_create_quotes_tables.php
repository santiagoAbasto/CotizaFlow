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
            $table->unique(['company_id', 'id']);
        });

        Schema::create('quote_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequence_number');
            $table->string('number', 32);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('price_list_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('currency', 3);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('customer_name_snapshot');
            $table->string('customer_legal_name_snapshot')->nullable();
            $table->string('customer_tax_id_snapshot', 80)->nullable();
            $table->string('customer_email_snapshot')->nullable();
            $table->string('customer_phone_snapshot', 60)->nullable();
            $table->string('price_list_name_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->decimal('subtotal', 20, 6);
            $table->decimal('discount_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('total', 20, 6);
            $table->timestamps();

            $table->unique(['company_id', 'sequence_number']);
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'issued_at']);
            $table->index(['company_id', 'customer_name_snapshot']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['company_id', 'price_list_id'])->references(['company_id', 'id'])->on('price_lists')->restrictOnDelete();
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('source_price_list_id')->nullable();
            $table->unsignedInteger('position');
            $table->string('sku_snapshot', 100);
            $table->string('name_snapshot');
            $table->text('description_snapshot')->nullable();
            $table->string('unit_snapshot', 24);
            $table->char('currency_snapshot', 3);
            $table->decimal('base_cost_snapshot', 20, 6)->nullable();
            $table->decimal('base_price_snapshot', 20, 6);
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_price', 20, 6);
            $table->string('price_source', 24);
            $table->string('source_price_list_name_snapshot')->nullable();
            $table->decimal('discount_percent', 9, 6);
            $table->decimal('discount_amount', 20, 6);
            $table->decimal('tax_percent', 9, 6);
            $table->decimal('tax_amount', 20, 6);
            $table->decimal('line_subtotal', 20, 6);
            $table->decimal('line_total', 20, 6);
            $table->timestamps();

            $table->unique(['quote_id', 'position']);
            $table->index(['company_id', 'product_id']);
            $table->foreign(['company_id', 'quote_id'])->references(['company_id', 'id'])->on('quotes')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'source_price_list_id'])->references(['company_id', 'id'])->on('price_lists')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('quote_sequences');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'id']);
        });
    }
};
