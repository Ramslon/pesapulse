<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('client_id', 100)->nullable()->after('id');

            $table->unique(
                ['user_id', 'client_id'],
                'expenses_user_client_unique'
            );
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('client_id', 100)->nullable()->after('id');

            $table->unique(
                ['user_id', 'client_id'],
                'goals_user_client_unique'
            );
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('client_id', 100)->nullable()->after('id');

            $table->unique(
                ['user_id', 'client_id'],
                'budgets_user_client_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_user_client_unique');
            $table->dropColumn('client_id');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropUnique('goals_user_client_unique');
            $table->dropColumn('client_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique('budgets_user_client_unique');
            $table->dropColumn('client_id');
        });
    }
};