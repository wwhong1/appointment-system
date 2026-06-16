<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('start_datetime', 'working_start_time');
            $table->renameColumn('end_datetime', 'working_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('working_start_time', 'start_datetime');
            $table->renameColumn('working_end_time', 'end_datetime');
        });
    }
};
