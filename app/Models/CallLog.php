<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'caller_name',
        'caller_number',
        'call_type',
        'call_duration',
        'call_timestamp',
        'sim_slot_index',
        'sim_name',
        'sim_number',
        'sim_serial_number',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'call_timestamp' => 'datetime',
        'call_duration' => 'integer',
    ];

    /**
     * Get the user that owns the call log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all recordings for this call log.
     */
    public function recordings()
    {
        return $this->hasMany(CallRecording::class);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        // Append time to ensure the entire day is included
        // From: start of day (00:00:00)
        // To: end of day (23:59:59)
        $fromDateTime = $from . ' 00:00:00';
        $toDateTime = $to . ' 23:59:59';

        return $query->whereBetween('call_timestamp', [$fromDateTime, $toDateTime]);
    }

    /**
     * Scope to filter by specific date.
     */
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('call_timestamp', $date);
    }

    /**
     * Scope to filter by time range.
     */
    public function scopeTimeRange($query, $timeFrom, $timeTo)
    {
        return $query->whereTime('call_timestamp', '>=', $timeFrom)
                     ->whereTime('call_timestamp', '<=', $timeTo);
    }

    /**
     * Scope to filter by duration range.
     */
    public function scopeDurationRange($query, $minDuration, $maxDuration)
    {
        return $query->whereBetween('call_duration', [$minDuration, $maxDuration]);
    }

    /**
     * Scope to filter by call type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('call_type', $type);
    }

    /**
     * Scope to filter by user/agent ID.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by branch ID.
     * OPTIMIZED: Uses JOIN instead of whereHas to avoid N+1 subquery problem.
     */
    public function scopeByBranch($query, $branchId)
    {
        // Use JOIN instead of whereHas for better performance
        // This prevents executing a subquery for each row
        return $query->join('users', 'call_logs.user_id', '=', 'users.id')
                     ->where('users.branch_id', $branchId)
                     ->select('call_logs.*'); // Prevent duplicate columns from the JOIN
    }

    /**
     * Scope to filter by caller number.
     */
    public function scopeByNumber($query, $number)
    {
        return $query->where('caller_number', 'LIKE', "%{$number}%");
    }

    /**
     * Scope to search by caller name or number.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('caller_name', 'LIKE', "%{$search}%")
              ->orWhere('caller_number', 'LIKE', "%{$search}%");
        });
    }
}
