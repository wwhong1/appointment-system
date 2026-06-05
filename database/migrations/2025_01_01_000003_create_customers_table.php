<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        // Add check constraint: at least one of email or phone must be non-null
        // Use DB-driver-aware syntax for compatibility with SQLite (testing) and MySQL (production)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER TABLE ADD CONSTRAINT, but we can't add it inline after creation.
            // For SQLite, we rely on application-level validation.
        } else {
            DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_contact_check CHECK (email IS NOT NULL OR phone IS NOT NULL)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
