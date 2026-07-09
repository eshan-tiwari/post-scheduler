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
        Schema::create('publish_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_id')->constrained('scheduled_posts')->onDelete('cascade');
            $table->foreignId('connected_account_id')->nullable()->constrained('connected_accounts')->onDelete('set null');
            $table->string('platform');
            $table->string('status'); // Success, Failed
            $table->string('response_id')->nullable(); // post ID returned by remote API
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_logs');
    }
};
