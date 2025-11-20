<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'user_id',
        'admin_id',
        'action_type',
        'device_name',
        'device_model',
        'device_id_value',
        'ip_address',
        'user_agent',
        'notes',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    /**
     * Get the device associated with this activity
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the user whose device was affected
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who performed this action
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Scope for logout actions only
     */
    public function scopeLogouts($query)
    {
        return $query->where('action_type', 'logout');
    }

    /**
     * Scope for removal actions only
     */
    public function scopeRemovals($query)
    {
        return $query->where('action_type', 'removal');
    }

    /**
     * Scope for a specific admin
     */
    public function scopeByAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * Scope for a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
