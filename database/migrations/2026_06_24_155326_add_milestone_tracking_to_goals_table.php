<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {

            $table->boolean('milestone_25_notified')
                ->default(false);

            $table->boolean('milestone_50_notified')
                ->default(false);

            $table->boolean('milestone_75_notified')
                ->default(false);

            $table->boolean('milestone_100_notified')
                ->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {

            $table->dropColumn([
                'milestone_25_notified',
                'milestone_50_notified',
                'milestone_75_notified',
                'milestone_100_notified',
            ]);
        });
    }
};