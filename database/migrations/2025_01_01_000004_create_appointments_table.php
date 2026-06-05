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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('staff_id')->constrained('users');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('service_id')->constrained('services');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('status', 20)->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            // Composite index for overlap queries
            $table->index(
                ['staff_id', 'status', 'start_datetime', 'end_datetime'],
                'idx_staff_active_appointments'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
