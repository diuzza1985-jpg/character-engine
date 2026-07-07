<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'price_monthly', 'credits_included',
        'max_characters', 'max_generations_per_day', 'stripe_price_id',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
