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
       Schema::create('students', function (Blueprint $table) {
            $table->id();
            // Student Information
            $table->string('student_name');
            $table->string('student_id')->unique(); // Custom Unique Student ID
            
            // Guardian & Address
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('post_code')->nullable(); 
            
            // Educational & Funding
            $table->string('educational_level')->nullable(); // e.g., Primary, Secondary, Higher
            $table->string('institution_name')->nullable();
            $table->string('funding_status')->default('unfunded'); // unfunded, partially_funded, fully_funded
           

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
