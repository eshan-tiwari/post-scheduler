<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // twitter, instagram, facebook, linkedin
            // Twitter API v2 (OAuth 1.0a - for posting)
            $table->text('api_key')->nullable();           // encrypted
            $table->text('api_secret')->nullable();        // encrypted
            $table->text('access_token')->nullable();      // encrypted - user's own account token
            $table->text('access_token_secret')->nullable(); // encrypted - for Twitter OAuth 1.0a
            $table->text('bearer_token')->nullable();      // encrypted - for v2 app-only
            // Instagram / Facebook
            $table->text('page_access_token')->nullable(); // encrypted - Facebook Page / Instagram Business
            $table->string('page_id')->nullable();         // Facebook Page ID / Instagram Business Account ID
            // LinkedIn
            $table->text('li_access_token')->nullable();   // encrypted
            $table->string('li_person_urn')->nullable();   // e.g. urn:li:person:xxxx
            // Metadata
            $table->boolean('is_verified')->default(false);
            $table->string('connected_username')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
