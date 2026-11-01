<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance (PRD 10.3–10.5). Every money column is BIGINT kobo. Nothing is deleted: invoices are voided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table): void {
            $table->id();
            $table->string('series', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('value')->default(0);
            $table->unique(['series', 'year']);
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->bigInteger('subtotal_kobo')->default(0);
            $table->bigInteger('discount_kobo')->default(0);
            $table->unsignedSmallInteger('vat_rate_bp')->default(750);
            $table->bigInteger('vat_kobo')->default(0);
            $table->bigInteger('total_kobo')->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->string('template', 30)->default('haulage_trip');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->bigInteger('subtotal_kobo')->default(0);
            $table->bigInteger('discount_kobo')->default(0);
            $table->unsignedSmallInteger('vat_rate_bp')->default(750);
            $table->bigInteger('vat_kobo')->default(0);
            $table->bigInteger('total_kobo')->default(0);
            $table->bigInteger('amount_paid_kobo')->default(0);
            $table->bigInteger('wht_kobo')->default(0);
            $table->bigInteger('credited_kobo')->default(0);
            $table->bigInteger('balance_kobo')->default(0);
            $table->string('supplier_tin', 30)->nullable();
            $table->string('supplier_vat_number', 30)->nullable();
            $table->string('buyer_tin', 30)->nullable();
            $table->string('irn', 100)->nullable();
            $table->text('qr_payload')->nullable();
            $table->string('stamp', 200)->nullable();
            $table->string('einvoice_status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reminder_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_lines', function (Blueprint $table): void {
            $table->id();
            $table->morphs('document');
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_price_kobo');
            $table->bigInteger('amount_kobo');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('received_on')->index();
            $table->string('method', 20);
            $table->string('reference', 80)->nullable();
            $table->bigInteger('amount_kobo');
            $table->bigInteger('wht_kobo')->default(0);
            $table->bigInteger('unallocated_kobo')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_kobo');
            $table->bigInteger('wht_kobo')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_kobo');
            $table->string('reason');
            $table->date('issued_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 30)->index();
            $table->date('spent_on')->index();
            $table->bigInteger('amount_kobo');
            $table->string('description');
            $table->string('vendor')->nullable();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_record_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'credit_notes', 'payment_allocations', 'payments', 'document_lines', 'invoices', 'quotes', 'counters'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
