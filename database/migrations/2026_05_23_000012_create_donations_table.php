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
       Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained('donors')->onDelete('cascade'); 
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade'); 
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('set null'); // Optional 
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->text('amount');
            $table->string('payment_method'); // Bank, Stripe, bKash etc 
            $table->dateTime('transaction_date'); 
            $table->string('receipt_number')->unique(); 
            $table->enum('status', ['pending', 'confirmed', 'failed'])->default('pending');
            $table->string('stripe_session_id')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->boolean('gift_aid')->default(false);
            $table->timestamp('gift_aid_at')->nullable();

            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable();
            $table->timestamp('recurrence_next_at')->nullable();
            $table->timestamp('recurrence_ends_at')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('donations');

         
            // Indexing for performance
            $table->index('donor_id');
            $table->index('project_id');
            $table->index('student_id');
            $table->index('campaign_id');
            $table->index(['is_recurring', 'recurrence_next_at']);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
