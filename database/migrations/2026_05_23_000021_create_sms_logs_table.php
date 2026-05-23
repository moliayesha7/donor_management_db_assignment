<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('sms_templates')->onDelete('set null');
            $table->foreignId('schedule_id')->nullable()->constrained('sms_schedules')->onDelete('set null');
            $table->text('text');
            $table->string('sent_by')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_number');
            $table->string('status')->default('delivered'); // delivered, sent, failed
            $table->string('provider_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedBigInteger('retry_of_log_id')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
