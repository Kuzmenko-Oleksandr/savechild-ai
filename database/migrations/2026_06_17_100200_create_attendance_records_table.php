<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();

            // Attendance model features
            $table->unsignedInteger('absent_days')->default(0);
            $table->unsignedInteger('unexcused_absent_days')->default(0);
            $table->unsignedInteger('late_days')->default(0);
            $table->float('attendance_rate')->default(0);
            $table->unsignedInteger('max_consecutive_absent_days')->default(0);
            $table->unsignedInteger('absence_days_last_14d')->default(0);
            $table->unsignedInteger('absence_days_last_30d')->default(0);
            $table->unsignedInteger('monday_friday_absences')->default(0);

            // Training reference
            $table->string('synthetic_pattern')->nullable();
            $table->boolean('expected_anomaly_flag')->nullable();

            // Model output
            $table->integer('isolation_forest_prediction')->nullable();
            $table->boolean('anomaly_flag')->nullable();
            $table->float('raw_anomaly_score')->nullable();
            $table->float('anomaly_priority_score')->nullable();
            $table->unsignedInteger('anomaly_rank')->nullable();
            $table->timestamp('scored_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
