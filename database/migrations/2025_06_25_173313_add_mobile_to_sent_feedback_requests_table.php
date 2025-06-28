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
        Schema::table('sent_feedback_requests', function (Blueprint $table) {
            // We add the mobile number to track the patient.
            // It's indexed for fast lookups during the cooldown check.
            $table->string('mobile')->nullable()->after('appointment_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sent_feedback_requests', function (Blueprint $table) {
            $table->dropColumn('mobile');
        });
    }
};