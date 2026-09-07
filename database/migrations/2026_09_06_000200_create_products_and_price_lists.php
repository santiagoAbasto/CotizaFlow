<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 120)->nullable();
            $table->string('unit', 24);
            $table->decimal('base_cost', 20, 6)->nullable();
            $table->decimal('base_price', 20, 6);
            $table->char('currency', 3);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'name']);
            $table->index(['company_id', 'category']);
        });

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('currency', 3);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'name']);
        });

        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_list_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 20, 6);
            $table->timestamps();

            $table->unique(['company_id', 'price_list_id', 'product_id']);
            $table->foreign(['company_id', 'price_list_id'])->references(['company_id', 'id'])->on('price_lists')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('products');
    }
};
