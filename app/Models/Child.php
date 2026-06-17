<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    protected $guarded = [];

    protected $casts = [
        'region_frontline' => 'bool',
        'idp_status' => 'bool',
        'single_parent' => 'bool',
        'parent_unemployed' => 'bool',
        'chronic_health_condition' => 'bool',
        'active_social_case' => 'bool',
        'low_attendance_detected' => 'bool',
        'academic_performance_drop' => 'bool',
        'school_transfer' => 'bool',
        'school_behavior_incident' => 'bool',
        'missed_scheduled_vaccination' => 'bool',
        'injury_report' => 'bool',
        'unexplained_injury_concern' => 'bool',
        'police_referral' => 'bool',
        'domestic_violence_report' => 'bool',
        'psychological_support_recommended' => 'bool',
        'neglect_concern_observed' => 'bool',
        'scored_at' => 'datetime',
    ];

    /**
     * Columns sent to the XGBoost priority model (child_id is a passthrough key).
     * The microservice validates the exact set via its bundle's `features`.
     */
    public const PRIORITY_FEATURES = [
        'single_parent', 'num_siblings', 'parent_unemployed', 'chronic_health_condition',
        'active_social_case', 'low_attendance_detected', 'academic_performance_drop',
        'school_transfer', 'school_behavior_incident', 'missed_scheduled_vaccination',
        'injury_report', 'unexplained_injury_concern', 'police_referral',
        'domestic_violence_report', 'psychological_support_recommended', 'neglect_concern_observed',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** Feature payload for the priority model (ints, not bools). */
    public function priorityPayload(): array
    {
        $payload = ['child_id' => $this->code];
        foreach (self::PRIORITY_FEATURES as $f) {
            $payload[$f] = (int) $this->{$f};
        }

        return $payload;
    }
}
