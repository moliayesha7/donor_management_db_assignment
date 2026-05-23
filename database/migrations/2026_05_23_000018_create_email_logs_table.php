<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->nullable()->constrained('emails')->onDelete('set null');
            $table->foreignId('schedule_id')->nullable()->constrained('email_schedules')->onDelete('set null');

            $table->string('subject');
            $table->string('sent_by');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('status')->default('sent'); // sent, failed, bounced
            $table->string('provider_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedBigInteger('retry_of_log_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
