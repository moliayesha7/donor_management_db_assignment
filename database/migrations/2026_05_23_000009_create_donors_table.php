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
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->string('donor_id_code')->unique(); // e.g., DNR-1001
            $table->string('name');
            $table->string('address_lookup')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('address_line_3')->nullable();
            $table->string('city');
            $table->string('county')->nullable();
            $table->string('post_code');
            $table->string('phone_number', 20);
            $table->string('email')->nullable();
            $table->string('country')->default('Bangladesh');

            // Notification channel preferences
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_sms')->default(true);
            $table->boolean('notify_whatsapp')->default(true);

            $table->foreignId('donor_source_id')->nullable()->constrained('donor_sources')->onDelete('set null');
            $table->foreignId('preferred_project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};