<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI assistant (PRD 12): knowledge base, website conversations, usage counter, daily digests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 30)->index();
            $table->string('title');
            $table->string('keywords')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('status', 20)->default('bot')->index(); // bot, needs_human, human, closed
            $table->string('ip_hash', 64)->nullable();
            $table->string('page')->nullable();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_phone', 20)->nullable();
            $table->string('visitor_email')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handoff_reason')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('turns')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // visitor, assistant, staff, system
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('provider', 20);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('cache_hits')->default(0);
            $table->unique(['day', 'provider']);
        });

        Schema::create('ai_digests', function (Blueprint $table): void {
            $table->id();
            $table->date('day')->unique();
            $table->text('summary');
            $table->json('facts');
            $table->string('provider', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_digests');
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('knowledge_items');
    }
};
