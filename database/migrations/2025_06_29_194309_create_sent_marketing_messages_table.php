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
    Schema::create('sent_marketing_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('patient_id')->unique();
        $table->string('mobile');
        $table->string('status')->default('staging')->index();
        $table->date('process_date')->index();
        $table->timestamp('message_sent_at')->nullable();
        $table->timestamp('converted_at')->nullable();
        $table->unsignedBigInteger('new_appointment_id')->nullable();
        $table->date('new_appointment_date')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sent_marketing_messages');
    }
};
