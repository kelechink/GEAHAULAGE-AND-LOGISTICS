<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('tin', 30)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->timestamp('first_contacted_at')->nullable()->after('next_follow_up_at');
            $table->timestamp('won_at')->nullable()->after('first_contacted_at');
            $table->timestamp('lost_at')->nullable()->after('won_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['first_contacted_at', 'won_at', 'lost_at']);
        });
        Schema::dropIfExists('customers');
    }
};
