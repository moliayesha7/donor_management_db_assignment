<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->onDelete('set null');
            $table->string('recipient_number');
            $table->string('recipient_name')->nullable();
            $table->text('text');
            $table->string('sent_by')->nullable();
            $table->string('status')->default('sent'); // sent, failed, delivered, read
            $table->string('provider_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedBigInteger('retry_of_log_id')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
