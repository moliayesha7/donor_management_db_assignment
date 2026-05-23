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
        Schema::create('email_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->nullable()->constrained('emails')->onDelete('set null');
            $table->string('subject'); 
            $table->timestamp('deadline')->nullable();
            $table->string('status')->default('Completed'); // Pending, Processing, Completed, Failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_schedules');
    }
};
