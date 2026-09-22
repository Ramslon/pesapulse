<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider');
            $table->string('provider_transaction_id')->nullable();

            $table->string('reference')->unique();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');

            $table->string('status')->default('pending');

            $table->string('payment_method')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'provider',
                'status',
            ]);

            $table->index([
                'provider',
                'provider_transaction_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};