<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operations and hybrid tracking (PRD 8, 10.1, 10.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table): void {
            $table->id();
            $table->string('registration', 20)->unique();
            $table->string('type', 30);
            $table->decimal('capacity_tonnes', 5, 1);
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('tracker_device_id')->nullable()->unique();
            $table->string('status', 20)->default('available')->index();
            $table->date('insurance_expires_on')->nullable();
            $table->date('roadworthiness_expires_on')->nullable();
            $table->date('licence_expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('licence_number', 40)->nullable();
            $table->date('licence_expires_on')->nullable();
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('has_smartphone')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->date('done_on');
            $table->string('description');
            $table->unsignedBigInteger('cost_kobo')->default(0);
            $table->string('workshop')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('state', 40)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->boolean('is_checkpoint')->default(true);
            $table->timestamps();
            $table->unique(['name', 'state']);
        });

        Schema::create('geocode_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('query')->unique();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('tracking_code', 16)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cargo_description');
            $table->decimal('tonnage', 6, 2)->nullable();
            $table->string('origin');
            $table->decimal('origin_lat', 10, 7)->nullable();
            $table->decimal('origin_lng', 10, 7)->nullable();
            $table->string('destination');
            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();
            $table->json('waypoints')->nullable();
            $table->date('pickup_date')->nullable();
            $table->string('waybill_number', 40)->nullable();
            $table->string('stage', 20)->default('booked')->index();
            $table->string('delay_reason')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_update_at')->nullable()->index();
            $table->timestamp('stale_alerted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20)->nullable();
            $table->string('source', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('place_label')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('client_event_id', 64)->nullable()->unique();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->unsignedSmallInteger('speed_kmh')->nullable();
            $table->unsignedSmallInteger('heading')->nullable();
            $table->string('place_label')->nullable();
            $table->string('client_event_id', 64)->nullable()->unique();
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->index(['shipment_id', 'recorded_at']);
            $table->index(['truck_id', 'recorded_at']);
        });

        Schema::create('proof_of_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('receiver_name');
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('document_path')->nullable();
            $table->string('source', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tracking_connectors', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 40)->unique();
            $table->boolean('enabled')->default(false);
            $table->text('settings')->nullable();
            $table->text('secret')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tracking_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();
            $table->unique(['shipment_id', 'email']);
        });
    }

    public function down(): void
    {
        foreach (['tracking_subscriptions', 'tracking_connectors', 'proof_of_deliveries', 'positions', 'shipment_events', 'shipments',
            'geocode_cache', 'places', 'maintenance_records', 'drivers', 'trucks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
