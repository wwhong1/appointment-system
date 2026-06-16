<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('working_start_time')->nullable()->change();
            $table->time('working_end_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('working_start_time')->nullable()->change();
            $table->dateTime('working_end_time')->nullable()->change();
        });
    }
};
