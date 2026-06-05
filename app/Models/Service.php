<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'duration_minutes',
        'image',
        'price',
        'description',
    ];

    /**
     * Get the appointments for this service.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
