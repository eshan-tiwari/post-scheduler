<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('scheduled_posts', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('scheduled_posts', 'platforms')) {
                $table->text('platforms')->nullable(); // JSON array of platforms (e.g. ['Instagram', 'LinkedIn'])
            }
            if (!Schema::hasColumn('scheduled_posts', 'timezone')) {
                $table->string('timezone')->default('UTC');
            }
            if (!Schema::hasColumn('scheduled_posts', 'recurrence')) {
                $table->string('recurrence')->default('once'); // once, daily, weekly, monthly, custom
            }
            if (!Schema::hasColumn('scheduled_posts', 'recurrence_rules')) {
                $table->text('recurrence_rules')->nullable(); // JSON configuration
            }
            if (!Schema::hasColumn('scheduled_posts', 'failed_reason')) {
                $table->text('failed_reason')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'platforms', 'timezone', 'recurrence', 'recurrence_rules', 'failed_reason']);
        });
    }
};
