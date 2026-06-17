<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attendance_rate' => 'float',
        'expected_anomaly_flag' => 'bool',
        'anomaly_flag' => 'bool',
        'scored_at' => 'datetime',
    ];

    /** Columns sent to the Isolation Forest attendance model. */
    public const ATTENDANCE_FEATURES = [
        'absent_days', 'unexcused_absent_days', 'late_days', 'attendance_rate',
        'max_consecutive_absent_days', 'absence_days_last_14d', 'absence_days_last_30d',
        'monday_friday_absences',
    ];

    public function attendancePayload(): array
    {
        $payload = ['child_id' => $this->code];
        foreach (self::ATTENDANCE_FEATURES as $f) {
            $payload[$f] = $this->{$f} + 0; // numeric
        }

        return $payload;
    }
}
