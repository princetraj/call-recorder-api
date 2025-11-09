<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'phone',
        'email',
        'status',
    ];

    /**
     * Get all users for this branch.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all admins for this branch.
     */
    public function admins()
    {
        return $this->hasMany(Admin::class);
    }
}
