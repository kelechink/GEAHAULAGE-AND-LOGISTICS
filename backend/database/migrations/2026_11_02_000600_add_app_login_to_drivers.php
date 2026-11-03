<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Driver app login (PRD 13): phone number + PIN set by Operations. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('pin_hash')->nullable()->after('phone');
            $table->timestamp('app_last_seen_at')->nullable()->after('is_active');
            $table->string('app_version', 20)->nullable()->after('app_last_seen_at');
        });

        Schema::create('driver_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('client_event_id', 64)->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_issues');
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropColumn(['pin_hash', 'app_last_seen_at', 'app_version']);
        });
    }
};
