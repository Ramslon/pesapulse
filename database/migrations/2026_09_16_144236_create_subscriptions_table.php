<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('plan')->default('basic');
            $table->string('status')->default('active');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('provider')->nullable();
            $table->string('provider_subscription_id')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['plan', 'status']);

            $table->unique([
                'provider',
                'provider_subscription_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};