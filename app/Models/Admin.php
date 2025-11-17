<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_role',
        'status',
        'branch_id',
        'assigned_user_ids',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'assigned_user_ids' => 'array',
    ];

    /**
     * Get the branch that the admin belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if admin is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->admin_role === 'super_admin';
    }

    /**
     * Check if admin is a manager
     */
    public function isManager(): bool
    {
        return $this->admin_role === 'manager';
    }

    /**
     * Check if admin is a trainee
     */
    public function isTrainee(): bool
    {
        return $this->admin_role === 'trainee';
    }

    /**
     * Get all login activities for this admin.
     */
    public function loginActivities()
    {
        return $this->hasMany(UserLoginActivity::class);
    }
}
