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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->text('recipients')->comment('Comma-separated email addresses');
            $table->unsignedBigInteger('template_id')->nullable(); // টেমপ্লেট ড্রপডাউনের জন্য
            $table->string('subject');
            $table->longText('body');
            $table->json('selected_projects')->nullable()->comment('Array of project IDs from tree select');
            $table->enum('send_timing', ['Now', 'Later'])->default('Now');
            $table->timestamp('scheduled_at')->nullable()->comment('If send_timing is Later');
            $table->enum('status', ['draft', 'pending', 'sent', 'failed'])->default('draft');
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};