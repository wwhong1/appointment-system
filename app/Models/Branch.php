<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'timezone',
        'opening_time',
        'closing_time',
    ];

    /**
     * Get the staff members (users with role 'staff') assigned to this branch.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'staff');
    }

    /**
     * Get the appointments for this branch.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
