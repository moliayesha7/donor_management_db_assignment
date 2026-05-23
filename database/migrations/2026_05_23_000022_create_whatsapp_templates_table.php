<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('name')->unique(); // name of meta template(e.g., welcome_template)
            $table->string('trigger_event')->nullable(); // laravel event name(e.g., DonorRegistered)
            $table->string('description')->nullable();
            $table->text('body'); // main text body of template
            $table->string('status')->default('approved'); // Meta status: approved, pending, rejected
            $table->string('language')->default('bn'); // template language code
            $table->boolean('is_default')->default(false); // for switch No/Yes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
