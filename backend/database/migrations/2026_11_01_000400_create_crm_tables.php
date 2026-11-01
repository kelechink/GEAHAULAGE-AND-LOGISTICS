<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM entities from PRD 9.1. Phase 1 writes to them from the website forms;
 * Phase 2 adds the pipeline, follow-up assignment and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('address')->nullable();
            $table->string('tin', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('whatsapp', 20)->nullable();
            $table->string('role')->nullable();
            $table->string('source', 30);
            $table->boolean('consent_marketing')->default(false);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30);
            $table->string('service_interest')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->text('cargo')->nullable();
            $table->decimal('tonnage', 8, 2)->nullable();
            $table->date('preferred_date')->nullable();
            $table->unsignedBigInteger('estimated_value_kobo')->nullable();
            $table->string('stage', 20)->default('new')->index();
            $table->string('lost_reason')->nullable();
            $table->unsignedTinyInteger('intent_score')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('ai_summary')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('at');
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->timestamp('due_at')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('open');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('role');
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->string('licence_class', 60)->nullable();
            $table->text('about')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
