<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('bank_statement_uploads')->onDelete('cascade');
            $table->unsignedInteger('row_number');

            // Source/external identifiers
            $table->string('source_id', 64)->nullable()->index();
            $table->string('order_id', 64)->nullable()->index();

            // Transaction details
            $table->date('transaction_date')->nullable();
            $table->time('transaction_time')->nullable();
            $table->string('transaction_status', 32)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('payment_method', 64)->nullable();
            $table->string('reference', 128)->nullable();

            // Donor identification fields from row
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 64)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('address_line_1')->nullable();

            // Optional project assignment hint from row
            $table->string('project_code', 64)->nullable();

            // Matching state
            $table->enum('match_status', [
                'unmatched', 'matched', 'donor_created', 'duplicate', 'skipped', 'error',
            ])->default('unmatched')->index();
            $table->foreignId('matched_donor_id')->nullable()->constrained('donors')->onDelete('set null');
            $table->foreignId('created_donation_id')->nullable()->constrained('donations')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['upload_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
