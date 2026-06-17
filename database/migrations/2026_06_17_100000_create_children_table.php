<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();

            // Display fields (synthetic — not used by the model)
            $table->string('name');
            $table->string('sex')->nullable();
            $table->string('school')->nullable();
            $table->string('grade')->nullable();
            $table->string('guardians')->nullable();
            $table->string('contact')->nullable();
            $table->string('address')->nullable();
            $table->string('photo')->nullable();

            // Demographic / context features
            $table->unsignedTinyInteger('age')->default(0);
            $table->string('age_group')->nullable();
            $table->boolean('region_frontline')->default(false);
            $table->boolean('idp_status')->default(false);
            $table->string('household_income_level')->nullable();
            $table->boolean('single_parent')->default(false);
            $table->unsignedTinyInteger('num_siblings')->default(0);
            $table->boolean('parent_unemployed')->default(false);
            $table->boolean('chronic_health_condition')->default(false);
            $table->boolean('active_social_case')->default(false);

            // Event-derived indicator features (priority model inputs)
            $table->boolean('low_attendance_detected')->default(false);
            $table->boolean('academic_performance_drop')->default(false);
            $table->boolean('school_transfer')->default(false);
            $table->boolean('school_behavior_incident')->default(false);
            $table->boolean('missed_scheduled_vaccination')->default(false);
            $table->boolean('injury_report')->default(false);
            $table->boolean('unexplained_injury_concern')->default(false);
            $table->boolean('police_referral')->default(false);
            $table->boolean('domestic_violence_report')->default(false);
            $table->boolean('psychological_support_recommended')->default(false);
            $table->boolean('neglect_concern_observed')->default(false);

            // Aggregates
            $table->unsignedInteger('total_events')->default(0);
            $table->unsignedInteger('school_events')->default(0);
            $table->unsignedInteger('medical_events')->default(0);
            $table->unsignedInteger('police_events')->default(0);
            $table->unsignedInteger('distinct_event_types')->default(0);
            $table->unsignedInteger('risk_sources')->default(0);

            // Training reference (from CSV)
            $table->integer('train_score')->nullable();
            $table->string('train_label')->nullable();

            // Model predictions
            $table->string('predicted_priority')->nullable()->index();
            $table->float('probability_high')->nullable();
            $table->float('probability_medium')->nullable();
            $table->float('probability_low')->nullable();
            $table->timestamp('scored_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
