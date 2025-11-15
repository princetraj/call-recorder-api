<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginActivity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'admin_id',
        'user_type',
        'email',
        'ip_address',
        'user_agent',
        'device_name',
        'device_id',
        'status',
        'failure_reason',
        'login_at',
        'logout_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
    ];

    /**
     * Get the user that owns this login activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin that owns this login activity.
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Scope a query to only include successful logins.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed logins.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include user logins.
     */
    public function scopeUsers($query)
    {
        return $query->where('user_type', 'user');
    }

    /**
     * Scope a query to only include admin logins.
     */
    public function scopeAdmins($query)
    {
        return $query->where('user_type', 'admin');
    }
}
