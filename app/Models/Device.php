<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_model',
        'manufacturer',
        'os_version',
        'app_version',
        'perm_read_call_log',
        'perm_read_phone_state',
        'perm_read_contacts',
        'perm_read_external_storage',
        'perm_read_media_audio',
        'perm_post_notifications',
        'connection_type',
        'battery_percentage',
        'signal_strength',
        'is_charging',
        'app_running_status',
        'current_call_status',
        'current_call_number',
        'call_started_at',
        'last_updated_at',
        'should_logout',
    ];

    protected $casts = [
        'battery_percentage' => 'integer',
        'signal_strength' => 'integer',
        'is_charging' => 'boolean',
        'should_logout' => 'boolean',
        'perm_read_call_log' => 'boolean',
        'perm_read_phone_state' => 'boolean',
        'perm_read_contacts' => 'boolean',
        'perm_read_external_storage' => 'boolean',
        'perm_read_media_audio' => 'boolean',
        'perm_post_notifications' => 'boolean',
        'call_started_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the device
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if device is online (updated in last 10 minutes)
     */
    public function isOnline()
    {
        if (!$this->last_updated_at) {
            return false;
        }
        return $this->last_updated_at->diffInMinutes(now()) <= 10;
    }

    /**
     * Get connection status with color indicator
     */
    public function getConnectionStatusAttribute()
    {
        return $this->isOnline() ? 'online' : 'offline';
    }

    /**
     * Get battery status with level
     */
    public function getBatteryStatusAttribute()
    {
        if ($this->battery_percentage === null) {
            return 'unknown';
        }

        if ($this->battery_percentage <= 20) {
            return 'low';
        } elseif ($this->battery_percentage <= 50) {
            return 'medium';
        } else {
            return 'good';
        }
    }

    /**
     * Get signal strength label
     */
    public function getSignalStrengthLabelAttribute()
    {
        switch ($this->signal_strength) {
            case 0:
                return 'none';
            case 1:
                return 'poor';
            case 2:
                return 'moderate';
            case 3:
                return 'good';
            case 4:
                return 'great';
            default:
                return 'unknown';
        }
    }
}
