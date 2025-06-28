<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_feedback_requests', function (Blueprint $table) {
            $table->id();
            // This ID comes from the external clinic DB's appointments table.
            $table->unsignedBigInteger('appointment_id')->unique();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_feedback_requests');
    }
};